<?php
/**
 * 集成测试共用工具（仅测试使用）
 * 被 tools/migrate-integration-test.php 与 tools/scheduler-integration-test.php 引用：
 * 项目临时副本、测试配置写入、真实数据库断言与 CLI 子进程封装。
 * 约束：只操作自建的 tcs_it_* 临时数据库与系统临时目录，不触碰仓库与既有数据。
 */

if (PHP_SAPI !== 'cli') {
    die('仅支持命令行运行');
}

$GLOBALS['IT_PASSED'] = 0;
$GLOBALS['IT_FAILED'] = 0;

/**
 * 断言并输出一行结果
 */
function it_check($name, $ok)
{
    if ($ok) {
        $GLOBALS['IT_PASSED']++;
        echo "ok   - {$name}\n";
    } else {
        $GLOBALS['IT_FAILED']++;
        echo "FAIL - {$name}\n";
    }
}

/**
 * 连接测试数据库；不可达时输出 SKIP 并以 0 退出（本地无库不阻塞，CI 中服务容器保证可达）
 */
function it_connect_db()
{
    if (!class_exists('mysqli')) {
        echo "SKIP: 缺少 mysqli 扩展\n";
        exit(0);
    }
    $host = getenv('TCS_IT_DB_HOST') ?: '127.0.0.1';
    $port = (int) (getenv('TCS_IT_DB_PORT') ?: '3306');
    $user = getenv('TCS_IT_DB_USER') ?: 'root';
    $pass = getenv('TCS_IT_DB_PASS') ?: '';
    try {
        $conn = new mysqli($host, $user, $pass, '', $port);
    } catch (mysqli_sql_exception $e) {
        echo "SKIP: 数据库不可达（{$host}:{$port}）：{$e->getMessage()}\n";
        exit(0);
    }
    return array($conn, $port);
}

/**
 * 递归复制项目到临时目录（跳过 vendor 与 data）
 */
function it_copy_project($src, $dst)
{
    if (!is_dir($dst) && !mkdir($dst, 0777, true)) {
        return false;
    }
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($src, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );
    foreach ($iterator as $item) {
        $path = (string) $item;
        $relative = substr($path, strlen($src) + 1);
        $top = strtok($relative, '/\\');
        if ($top === 'vendor' || $top === 'data') {
            if ($item->isDir()) {
                $iterator->next();
            }
            continue;
        }
        $target = $dst . '/' . $relative;
        if ($item->isDir()) {
            if (!is_dir($target) && !mkdir($target, 0777, true)) {
                return false;
            }
        } elseif (!copy($path, $target)) {
            return false;
        }
    }
    return true;
}

/**
 * 在副本中写入测试用 config.php（连接信息经环境变量透传，便于指向临时实例端口）
 */
function it_write_config($workRoot, $db, $prefix, $port)
{
    $config = "<?php\n"
        . "define('DB_HOST', '127.0.0.1:{$port}');\n"
        . "define('DB_USER', " . var_export(getenv('TCS_IT_DB_USER') ?: 'root', true) . ");\n"
        . "define('DB_PASSWD', " . var_export(getenv('TCS_IT_DB_PASS') ?: '', true) . ");\n"
        . "define('DB_NAME', '{$db}');\n"
        . "define('DB_SSL', 0);\n"
        . "define('DB_PREFIX', '{$prefix}');\n"
        . "define('ANTI_CSRF', true);\n"
        . "define('SYSTEM_SALT', 'it-test-salt-0123456789abcdef');\n";
    return file_put_contents($workRoot . '/config.php', $config) !== false;
}

/**
 * 在副本中执行 PHP CLI 脚本，返回 [exitCode, output]
 */
function it_run_php($script, $args = array())
{
    $cmd = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($script);
    foreach ($args as $arg) {
        $cmd .= ' ' . escapeshellarg($arg);
    }
    $output = array();
    $code = 0;
    exec($cmd . ' 2>&1', $output, $code);
    return array($code, implode("\n", $output));
}

function it_drain(mysqli $conn)
{
    while ($conn->more_results()) {
        $conn->next_result();
        if ($res = $conn->store_result()) {
            $res->free();
        }
    }
}

/**
 * 导入多语句 SQL（自动替换 {VAR-PREFIX}），任一语句失败即返回 false
 */
function it_import_sql(mysqli $conn, $db, $sql, $prefix)
{
    $sql = str_replace('{VAR-PREFIX}', $prefix, $sql);
    $conn->select_db($db);
    $ok = $conn->multi_query($sql);
    it_drain($conn);
    foreach ($conn->error_list as $error) {
        echo "导入错误：#{$error['errno']} {$error['error']}\n";
        $ok = false;
    }
    return $ok;
}

function it_query_value(mysqli $conn, $db, $sql)
{
    $conn->select_db($db);
    $res = $conn->query($sql);
    if (!$res) {
        it_drain($conn);
        return null;
    }
    $row = $res->fetch_row();
    $res->free();
    it_drain($conn);
    return $row ? $row[0] : null;
}

function it_table_exists(mysqli $conn, $db, $table)
{
    $v = it_query_value($conn, $db, "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = '" . $conn->real_escape_string($db) . "' AND table_name = '" . $conn->real_escape_string($table) . "'");
    return (int) $v > 0;
}

function it_column_exists(mysqli $conn, $db, $table, $column)
{
    $v = it_query_value($conn, $db, "SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = '" . $conn->real_escape_string($db) . "' AND table_name = '" . $conn->real_escape_string($table) . "' AND column_name = '" . $conn->real_escape_string($column) . "'");
    return (int) $v > 0;
}

/**
 * 汇总结果并以约定退出码退出
 */
function it_finish()
{
    echo "\n集成测试完成：{$GLOBALS['IT_PASSED']} 通过，{$GLOBALS['IT_FAILED']} 失败\n";
    exit($GLOBALS['IT_FAILED'] === 0 ? 0 : 1);
}
