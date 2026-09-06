<?php
/**
 * 数据库备份恢复演练（真实 MySQL/MariaDB）
 * 使用应用自身的备份逻辑（cron_system_dbbackup → dataBak）执行一次完整演练：
 *   全新安装 → 播种代表性数据 → 备份 → 空库恢复 → 逐表逐行比对校验
 * 同时验证备份保留策略（keep N 轮换）。
 *
 * 用法与环境变量同 tools/migrate-integration-test.php；数据库不可达时自动跳过。
 */

if (PHP_SAPI !== 'cli') {
    die('仅支持命令行运行：php tools/backup-restore-drill.php');
}

require __DIR__ . '/lib-integration.php';

function check($name, $ok)
{
    it_check($name, $ok);
}

list($conn, $port) = it_connect_db();
$repoRoot = dirname(__DIR__);
$workRoot = sys_get_temp_dir() . '/tcs-it-backup-' . getmypid() . '-' . mt_rand(1000, 9999);
$dbSource = 'tcs_it_bk_src_' . mt_rand(1000, 9999);
$dbRestore = 'tcs_it_bk_dst_' . mt_rand(1000, 9999);
$prefix = 'tbk_';

echo "备份恢复演练开始：服务器版本 " . $conn->server_version . "，工作目录 {$workRoot}\n";

/**
 * 读取两张库的全部行（按第一列排序）并序列化，用于逐表比对
 */
function tableRows(mysqli $conn, $db, $table)
{
    $conn->select_db($db);
    $res = $conn->query("SELECT * FROM `{$table}` ORDER BY 1");
    if ($res === false) {
        it_drain($conn);
        return null;
    }
    $rows = array();
    while ($row = $res->fetch_assoc()) {
        $rows[] = $row;
    }
    $res->free();
    it_drain($conn);
    return $rows;
}

function tableFingerprint(mysqli $conn, $db, $table)
{
    $rows = tableRows($conn, $db, $table);
    return $rows === null ? null : md5(serialize($rows));
}

try {
    // ---------- 准备副本与全新安装 ----------
    if (!it_copy_project($repoRoot, $workRoot)) {
        die("FAIL - 无法创建项目副本\n");
    }
    file_put_contents($workRoot . '/setup/install.lock', '1');
    check('写入测试配置', it_write_config($workRoot, $dbSource, $prefix, $port));
    $conn->query("DROP DATABASE IF EXISTS `{$dbSource}`");
    $conn->query("DROP DATABASE IF EXISTS `{$dbRestore}`");
    $conn->query("CREATE DATABASE `{$dbSource}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $template = file_get_contents($workRoot . '/setup/install.template.sql');
    $template = str_replace('{VAR-PREFIX}', $prefix, $template);
    $template = str_replace('{VAR-DB}', $dbSource, $template);
    $template = str_replace('{VAR-COOKIE-SALT}', bin2hex(random_bytes(32)), $template);
    $template = str_replace('{VAR-SYSTEM-URL}', 'http://127.0.0.1', $template);
    check('导入全新安装模板', it_import_sql($conn, $dbSource, $template, $prefix));

    // ---------- 播种代表性数据（含中文、引号、反斜杠、序列化值） ----------
    $conn->select_db($dbSource);
    $expectedGroups = array('g1' => array('name' => '分组"一', 'pid' => array(5)));
    $seeds = array(
        "INSERT INTO `{$prefix}users` (`name`, `pw`, `email`, `role`, `t`) VALUES ('管理员\"A', 'bcrypt~hash\\\\x', 'drill@example.test', 'admin', 'tieba')",
        "INSERT INTO `{$prefix}baiduid` (`uid`, `bduss`, `stoken`, `name`, `portrait`) VALUES (1, 'BDUSS\\\"含引\\\\slash', '', '百度\"吧友', 'p_or trait')",
        "INSERT INTO `{$prefix}tieba` (`uid`, `pid`, `fid`, `tieba`, `no`, `status`, `latest`) VALUES (1, 1, 123, '贴吧''名', 0, 0, '20260906')",
        "INSERT INTO `{$prefix}sign_history` (`pid`, `uid`, `tieba`, `status`, `error_code`, `message`, `created_at`) VALUES (1, 1, '贴吧''名', 'success', 0, '签到''成功\\\\ok', UNIX_TIMESTAMP())",
    );
    $seedOk = true;
    foreach ($seeds as $sql) {
        if ($conn->query($sql) === false) {
            $seedOk = false;
            echo '播种失败：' . $conn->error . "\n";
        }
        it_drain($conn);
    }
    // 序列化配置一律由 serialize() 生成，禁止手工拼接
    $groupsJson = serialize($expectedGroups);
    $stmt = $conn->prepare("INSERT INTO `{$prefix}users_options` (`uid`, `name`, `value`) VALUES (?, ?, ?)");
    $optName = 'ver4_account_groups';
    $optUid = 1;
    $stmt->bind_param('iss', $optUid, $optName, $groupsJson);
    $seedOk = $stmt->execute() && $seedOk;
    $stmt->close();
    it_drain($conn);
    check('播种代表性数据', $seedOk);

    // ---------- 应用内执行备份 ----------
    $runner = <<<'PHP'
<?php
if (PHP_SAPI !== 'cli') { exit(1); }
define('SYSTEM_NO_UI', true);
define('SYSTEM_NO_CHECK_LOGIN', true);
define('SYSTEM_NO_PLUGIN', true);
define('SYSTEM_DO_NOT_LOGIN', true);
require dirname(__DIR__) . '/init.php';
require_once dirname(__DIR__) . '/lib/cron_system_dbbackup.php';
while (ob_get_level() > 0) { @ob_end_clean(); }
option::set('db_backup_enable', '1');
option::set('db_backup_keep', '3');
echo json_encode(array('result' => cron_system_dbbackup()), JSON_UNESCAPED_UNICODE);
PHP;
    check('写入备份运行器', file_put_contents($workRoot . '/tools/it_backup.php', $runner) !== false);
    list($code, $output) = it_run_php($workRoot . '/tools/it_backup.php');
    $result = json_decode($output, true);
    if ($result === null || strpos((string) ($result['result'] ?? ''), '备份完成') === false) {
        echo "诊断[backup] code={$code} output=" . substr($output, 0, 500) . "\n";
    }
    check('应用备份函数执行成功', $code === 0 && $result !== null && strpos((string) $result['result'], '备份完成') !== false);

    $backupFiles = glob($workRoot . '/data/backups/dbbackup_*.sql');
    check('备份文件已生成', is_array($backupFiles) && count($backupFiles) === 1);
    $dump = file_get_contents($backupFiles[0]);
    check('备份包含建表与数据语句', $dump !== false && strpos($dump, 'CREATE TABLE') !== false && strpos($dump, 'INSERT INTO') !== false);

    // ---------- 恢复到全新空库 ----------
    $conn->query("CREATE DATABASE `{$dbRestore}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    check('备份可完整重放恢复', it_import_sql($conn, $dbRestore, $dump, ''));

    // ---------- 逐表指纹比对 ----------
    $conn->select_db($dbSource);
    $tables = array();
    $res = $conn->query("SHOW TABLES");
    while ($row = $res->fetch_row()) {
        $tables[] = $row[0];
    }
    $res->free();
    it_drain($conn);
    check('源库表数量符合预期（>=15）', count($tables) >= 15);

    $mismatch = array();
    foreach ($tables as $table) {
        if (tableFingerprint($conn, $dbSource, $table) !== tableFingerprint($conn, $dbRestore, $table)) {
            $mismatch[] = $table;
        }
    }
    if ($mismatch !== array()) {
        echo '诊断：不一致的数据表：' . implode(', ', $mismatch) . "\n";
        $table = $mismatch[0];
        $srcRows = tableRows($conn, $dbSource, $table);
        $dstRows = tableRows($conn, $dbRestore, $table);
        echo '诊断：源行数=' . count($srcRows) . ' 恢复行数=' . count($dstRows) . "\n";
        for ($i = 0; $i < max(count($srcRows), count($dstRows)); $i++) {
            $s = $srcRows[$i] ?? null;
            $d = $dstRows[$i] ?? null;
            if ($s !== $d) {
                echo '诊断：首个差异行（源）=' . var_export($s, true) . "\n" . '诊断：首个差异行（恢复）=' . var_export($d, true) . "\n";
                break;
            }
        }
    }
    check('全部数据表逐行指纹一致（' . count($tables) . ' 张）', $mismatch === array());

    // ---------- 关键数据语义校验 ----------
    $restored = it_query_value($conn, $dbRestore, "SELECT `value` FROM `{$prefix}users_options` WHERE `name` = 'ver4_account_groups'");
    $unserialized = is_string($restored) ? @unserialize($restored) : false;
    if ($unserialized !== $expectedGroups) {
        echo '诊断：期望=' . var_export($expectedGroups, true) . "\n" . '诊断：恢复=' . var_export($unserialized, true) . "\n";
    }
    check('序列化配置恢复后与原值完全一致', $unserialized === $expectedGroups);
    $portrait = it_query_value($conn, $dbRestore, "SELECT `portrait` FROM `{$prefix}baiduid` WHERE `uid` = 1");
    check('含引号/空格的账号特征原样恢复', $portrait === 'p_or trait');
    $coreVersion = it_query_value($conn, $dbRestore, "SELECT `value` FROM `{$prefix}options` WHERE `name` = 'core_version'");
    check('恢复库 core_version 正确', $coreVersion === '7.0');

    // ---------- 备份保留策略（keep=3 轮换） ----------
    // 触发轮换：3 个过期假备份 + 本次新备份 = 4 > keep，应删除最旧的 2000 份
    touch($workRoot . '/data/backups/dbbackup_2000-01-01_000000.sql');
    touch($workRoot . '/data/backups/dbbackup_2001-01-01_000000.sql');
    touch($workRoot . '/data/backups/dbbackup_2002-01-01_000000.sql');
    list($code, $output) = it_run_php($workRoot . '/tools/it_backup.php');
    $files = glob($workRoot . '/data/backups/dbbackup_*.sql');
    check('备份保留策略轮换到 keep 上限', $code === 0 && count($files) === 3);
    // 两次真实备份可能跨秒（5 个文件裁掉最旧两个）或同秒（裁掉最旧一个），均应删掉 2000 份
    check('最旧的过期备份被清理', !file_exists($workRoot . '/data/backups/dbbackup_2000-01-01_000000.sql'));
} catch (Throwable $e) {
    it_check('无未捕获异常', false);
    echo '异常：' . $e->getMessage() . "\n";
} finally {
    $conn->query("DROP DATABASE IF EXISTS `{$dbSource}`");
    $conn->query("DROP DATABASE IF EXISTS `{$dbRestore}`");
    $conn->close();
    if (strpos($workRoot, 'tcs-it-backup-') !== false && is_dir($workRoot)) {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($workRoot, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($iterator as $item) {
            $item->isDir() ? @rmdir((string) $item) : @unlink((string) $item);
        }
        @rmdir($workRoot);
    }
}

it_finish();
