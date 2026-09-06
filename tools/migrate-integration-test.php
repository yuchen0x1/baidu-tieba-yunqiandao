<?php
/**
 * 数据库迁移集成测试（真实 MySQL/MariaDB）
 * 在临时目录复制项目、生成 6.6 存量库夹具与全新安装夹具，完整演练迁移器生命周期：
 *   失败恢复 → 升级成功 → 幂等重跑 → checksum 漂移阻断 → 迁移锁 → down 回滚 → 全新安装基线
 *
 * 用法：
 *   php tools/migrate-integration-test.php
 * 环境变量：
 *   TCS_IT_DB_HOST (默认 127.0.0.1)   TCS_IT_DB_PORT (默认 3306)
 *   TCS_IT_DB_USER (默认 root)        TCS_IT_DB_PASS (默认空)
 * 数据库不可达时输出 SKIP 并以 0 退出（本地无库不阻塞）；CI 中服务容器保证可达。
 * 共用工具见 tools/lib-integration.php：测试只操作自建的 tcs_it_* 库与系统临时目录。
 */

if (PHP_SAPI !== 'cli') {
    die('仅支持命令行运行：php tools/migrate-integration-test.php');
}

require __DIR__ . '/lib-integration.php';

function check($name, $ok)
{
    it_check($name, $ok);
}

list($conn, $port) = it_connect_db();
$repoRoot = dirname(__DIR__);
$workRoot = sys_get_temp_dir() . '/tcs-it-run-' . getmypid() . '-' . mt_rand(1000, 9999);
$dbUp = 'tcs_it_up_' . mt_rand(1000, 9999);
$dbFresh = 'tcs_it_fresh_' . mt_rand(1000, 9999);

echo "集成测试开始：服务器版本 " . $conn->server_version . "，工作目录 {$workRoot}\n";

function migrationStatus($conn, $db, $prefix, $version)
{
    $v = it_query_value($conn, $db, "SELECT `status` FROM `{$prefix}schema_migrations` WHERE `version` = '" . $conn->real_escape_string($version) . "'");
    return $v === null ? 'absent' : (string) $v;
}

try {
    // ---------- 准备副本与夹具 ----------
    if (!it_copy_project($repoRoot, $workRoot)) {
        die("FAIL - 无法创建项目副本\n");
    }
    // 副本视为已安装站点：否则 init.php 的 install.lock 守卫会以退出码 0 中止迁移器
    file_put_contents($workRoot . '/setup/install.lock', '1');
    $template = file_get_contents($workRoot . '/setup/install.template.sql');
    $marker = 'DROP TABLE IF EXISTS `{VAR-PREFIX}schema_migrations`';
    $baselinePos = strpos($template, $marker);
    check('安装模板包含 schema_migrations 基线段', $baselinePos !== false);

    // 6.6 存量夹具：完整结构但无迁移记录、core_version=6.6（等价经旧脚本链升级的 6.6 库）
    $fixture66 = substr($template, 0, $baselinePos);
    $fixture66 = str_replace("('core_version', '7.0')", "('core_version', '6.6')", $fixture66);
    $fixture66 = str_replace('{VAR-PREFIX}', 'tca_', $fixture66);
    $fixture66 = str_replace('{VAR-DB}', $dbUp, $fixture66);
    $fixture66 = str_replace('{VAR-COOKIE-SALT}', bin2hex(random_bytes(32)), $fixture66);
    $fixture66 = str_replace('{VAR-SYSTEM-URL}', 'http://127.0.0.1', $fixture66);
    check('6.6 夹具不含 schema_migrations', strpos($fixture66, 'schema_migrations') === false);

    check('写入测试配置（升级库）', it_write_config($workRoot, $dbUp, 'tca_', $port));

    $conn->query("DROP DATABASE IF EXISTS `{$dbUp}`");
    $conn->query("CREATE DATABASE `{$dbUp}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    check('导入 6.6 夹具', it_import_sql($conn, $dbUp, $fixture66, 'tca_'));
    check('夹具 core_version=6.6', it_query_value($conn, $dbUp, "SELECT `value` FROM `tca_options` WHERE `name` = 'core_version'") === '6.6');

    // ---------- 场景 1：迁移失败必须留下可恢复状态且不更新 core_version ----------
    file_put_contents($workRoot . '/setup/migrations/009000_it_fail.php', "<?php\nreturn array(\n    'version' => '009000',\n    'desc' => '集成测试：故意失败',\n    'up' => function (\$m, \$prefix) { throw new RuntimeException('intentional failure'); },\n    'down' => function (\$m, \$prefix) { },\n);\n");
    list($code, $output) = it_run_php($workRoot . '/tools/migrate.php', array('up'));
    check('失败迁移使 CLI 以非零码退出', $code !== 0);
    check('失败输出包含原因', strpos($output, 'intentional failure') !== false);
    check('失败后 core_version 保持 6.6', it_query_value($conn, $dbUp, "SELECT `value` FROM `tca_options` WHERE `name` = 'core_version'") === '6.6');
    check('成功迁移 001001 已记录', migrationStatus($conn, $dbUp, 'tca_', '001001') === 'success');
    check('成功迁移 007000 已记录', migrationStatus($conn, $dbUp, 'tca_', '007000') === 'success');
    check('失败迁移 009000 记录为 failed', migrationStatus($conn, $dbUp, 'tca_', '009000') === 'failed');

    // ---------- 场景 2：修复后重跑成功完成升级 ----------
    unlink($workRoot . '/setup/migrations/009000_it_fail.php');
    list($code, $output) = it_run_php($workRoot . '/tools/migrate.php', array('up'));
    check('修复后重跑成功退出', $code === 0);
    check('升级后 core_version=7.0', it_query_value($conn, $dbUp, "SELECT `value` FROM `tca_options` WHERE `name` = 'core_version'") === '7.0');
    check('task_runs 表已创建', it_table_exists($conn, $dbUp, 'tca_task_runs'));
    check('api_rate_limits 表已创建', it_table_exists($conn, $dbUp, 'tca_api_rate_limits'));
    check('cron 调度列已补齐', it_column_exists($conn, $dbUp, 'tca_cron', 'nextrun') && it_column_exists($conn, $dbUp, 'tca_cron', 'enable'));
    check('调度选项已落库', it_query_value($conn, $dbUp, "SELECT COUNT(*) FROM `tca_options` WHERE `name` = 'cron_max_retry'") === '1');
    check('失败记录 009000 保留为审计痕迹', migrationStatus($conn, $dbUp, 'tca_', '009000') === 'failed');

    // ---------- 场景 3：幂等重跑 ----------
    list($code, $output) = it_run_php($workRoot . '/tools/migrate.php', array('up'));
    check('重复 up 幂等退出', $code === 0 && strpos($output, '无待应用迁移') !== false);
    check('幂等后 core_version 仍为 7.0', it_query_value($conn, $dbUp, "SELECT `value` FROM `tca_options` WHERE `name` = 'core_version'") === '7.0');

    // ---------- 场景 4：checksum 漂移阻断 ----------
    $foundationFile = $workRoot . '/setup/migrations/007000_v70_foundation.php';
    $foundationOriginal = file_get_contents($foundationFile);
    file_put_contents($foundationFile, $foundationOriginal . "\n// drift\n");
    list($code, $output) = it_run_php($workRoot . '/tools/migrate.php', array('up'));
    check('checksum 漂移被阻断', $code !== 0 && strpos($output, 'checksum') !== false);
    file_put_contents($foundationFile, $foundationOriginal);
    list($code, $output) = it_run_php($workRoot . '/tools/migrate.php', array('up'));
    check('恢复文件后恢复正常', $code === 0);

    // ---------- 场景 5：迁移锁互斥 ----------
    $lockName = 'tc_migrate_' . $dbUp . '_tca_';
    $conn->select_db($dbUp);
    $res = $conn->query("SELECT GET_LOCK('{$lockName}', 0) AS l");
    $lockRow = $res->fetch_assoc();
    $res->free();
    it_drain($conn);
    check('测试连接已取得迁移锁', (int) $lockRow['l'] === 1);
    list($code, $output) = it_run_php($workRoot . '/tools/migrate.php', array('up'));
    check('持锁时迁移器拒绝执行', $code !== 0 && strpos($output, '迁移锁') !== false);
    $res = $conn->query("SELECT RELEASE_LOCK('{$lockName}') AS r");
    $res->fetch_assoc();
    $res->free();
    it_drain($conn);
    list($code, $output) = it_run_php($workRoot . '/tools/migrate.php', array('up'));
    check('释放锁后迁移器恢复正常', $code === 0);

    // ---------- 场景 6：down 回滚指定版本且不动 core_version ----------
    list($code, $output) = it_run_php($workRoot . '/tools/migrate.php', array('down', '007000'));
    check('down 007000 成功退出', $code === 0);
    check('007000 记录已回滚', migrationStatus($conn, $dbUp, 'tca_', '007000') === 'absent');
    check('001001 记录保留', migrationStatus($conn, $dbUp, 'tca_', '001001') === 'success');
    check('down 不回退 core_version', it_query_value($conn, $dbUp, "SELECT `value` FROM `tca_options` WHERE `name` = 'core_version'") === '7.0');

    // ---------- 场景 7：全新安装基线（自定义前缀） ----------
    check('写入测试配置（全新库）', it_write_config($workRoot, $dbFresh, 'tcz_', $port));
    $conn->query("DROP DATABASE IF EXISTS `{$dbFresh}`");
    $conn->query("CREATE DATABASE `{$dbFresh}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $freshSql = str_replace('{VAR-PREFIX}', 'tcz_', $template);
    $freshSql = str_replace('{VAR-DB}', $dbFresh, $freshSql);
    $freshSql = str_replace('{VAR-COOKIE-SALT}', bin2hex(random_bytes(32)), $freshSql);
    $freshSql = str_replace('{VAR-SYSTEM-URL}', 'http://127.0.0.1', $freshSql);
    check('导入全新安装模板', it_import_sql($conn, $dbFresh, $freshSql, 'tcz_'));
    list($code, $output) = it_run_php($workRoot . '/tools/migrate.php', array('status'));
    check('全新安装无待应用迁移', $code === 0 && strpos($output, '待应用') === false);
    list($code, $output) = it_run_php($workRoot . '/tools/migrate.php', array('up'));
    check('全新安装 up 幂等成功', $code === 0 && strpos($output, '无待应用迁移') !== false);
    check('全新安装 core_version 保持 7.0', it_query_value($conn, $dbFresh, "SELECT `value` FROM `tcz_options` WHERE `name` = 'core_version'") === '7.0');
    check('全新安装基线记录为 success', migrationStatus($conn, $dbFresh, 'tcz_', '007000') === 'success');
    // 防漂移：基线迁移播种的选项必须与模板预置一致（缺失会导致新装与升级装行为不同）
    foreach (array('api_rate_limit', 'metrics_token', 'webhook_enable', 'cleanup_enable', 'cleanup_sign_history_days', 'cron_notify_interval') as $option) {
        check('全新安装选项已预置：' . $option, it_query_value($conn, $dbFresh, "SELECT COUNT(*) FROM `tcz_options` WHERE `name` = '" . $conn->real_escape_string($option) . "'") === '1');
    }
} catch (Throwable $e) {
    it_check('无未捕获异常', false);
    echo '异常：' . $e->getMessage() . "\n";
} finally {
    $conn->query("DROP DATABASE IF EXISTS `{$dbUp}`");
    $conn->query("DROP DATABASE IF EXISTS `{$dbFresh}`");
    $conn->close();
    // 清理临时副本（只删除本测试创建的目录）
    if (strpos($workRoot, 'tcs-it-run-') !== false && is_dir($workRoot)) {
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
