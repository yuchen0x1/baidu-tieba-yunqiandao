<?php
/**
 * 调度引擎集成测试（真实 MySQL/MariaDB）
 * 在项目副本上完整演练 do.php/cron 调度链路：
 *   正常执行与排程 → 锁冲突跳过 → 过期锁恢复 → 失败退避与耗尽 →
 *   失败通知去重（间隔内抑制 / 间隔 0 关闭）→ 一次性任务 → do.php 只跑到期任务与并发保护
 *
 * 用法与环境变量同 tools/migrate-integration-test.php；共用工具见 tools/lib-integration.php。
 */

if (PHP_SAPI !== 'cli') {
    die('仅支持命令行运行：php tools/scheduler-integration-test.php');
}

require __DIR__ . '/lib-integration.php';

function check($name, $ok)
{
    it_check($name, $ok);
}

list($conn, $port) = it_connect_db();
$repoRoot = dirname(__DIR__);
$workRoot = sys_get_temp_dir() . '/tcs-it-sched-' . getmypid() . '-' . mt_rand(1000, 9999);
$db = 'tcs_it_sched_' . mt_rand(1000, 9999);
$prefix = 'tcs_';
$markers = $workRoot . '/data/markers';

echo "调度集成测试开始：服务器版本 " . $conn->server_version . "，工作目录 {$workRoot}\n";

/**
 * 写入副本内的调度运行器：在完整应用上下文中调用 cron::runOnce / 读取任务状态
 */
function writeSchedulerRunner($workRoot)
{
    $code = <<<'PHP'
<?php
if (PHP_SAPI !== 'cli') { exit(1); }
define('SYSTEM_NO_UI', true);
define('SYSTEM_NO_CHECK_LOGIN', true);
define('SYSTEM_NO_PLUGIN', true);
define('SYSTEM_DO_NOT_LOGIN', true);
require dirname(__DIR__) . '/init.php';
// init.php 在 CLI 下通过 ob_start 注入控制台横幅转换，会污染 JSON 输出；输出前丢弃全部缓冲
while (ob_get_level() > 0) { @ob_end_clean(); }
$action = isset($argv[1]) ? $argv[1] : '';
$name = isset($argv[2]) ? $argv[2] : '';
global $m;
if ($action === 'run' || $action === 'scheduled' || $action === 'retry') {
    $ret = cron::runOnce($name, $action === 'scheduled', $action === 'retry');
    echo json_encode(array('ok' => $ret !== false, 'ret' => is_string($ret) ? $ret : ''));
} elseif ($action === 'encrypt_cron') {
    SecretBox::put('cron_pw', 'itpw');
    echo json_encode(array('encrypted' => strpos(option::get('cron_pw'), 'enc:v1:') === 0));
} elseif ($action === 'history') {
    echo json_encode(cron::runHistory(array('name' => $name, 'status' => 'success', 'since' => time() - 86400), 999999));
} elseif ($action === 'state') {
    $escaped = $m->real_escape_string($name);
    $row = $m->once_fetch_array("SELECT * FROM `" . DB_NAME . "`.`" . DB_PREFIX . "cron` WHERE `name` = '{$escaped}'");
    $runs = array();
    $rs = $m->query("SELECT `status`, `message` FROM `" . DB_PREFIX . "task_runs` WHERE `name` = '{$escaped}' ORDER BY `id` DESC LIMIT 5");
    while ($r = $m->fetch_array($rs)) {
        $runs[] = $r;
    }
    echo json_encode(array('row' => $row, 'runs' => $runs), JSON_UNESCAPED_UNICODE);
} else {
    fwrite(STDERR, "unknown action\n");
    exit(1);
}
PHP;
    return file_put_contents($workRoot . '/tools/it_scheduler.php', $code) !== false;
}

/**
 * 写入探针任务文件：cron::run 要求函数名为 cron_<任务名>
 */
function writeProbeTask($workRoot, $taskName, $markerName, $ret)
{
    $code = "<?php\nfunction cron_{$taskName}() {\n    @mkdir(dirname(__FILE__) . '/../data/markers', 0755, true);\n    file_put_contents(dirname(__FILE__) . '/../data/markers/" . $markerName . ".txt', (string) time());\n    return " . var_export($ret, true) . ";\n}\n";
    return file_put_contents($workRoot . '/lib/cron_' . $taskName . '.php', $code) !== false;
}

function seedTask($conn, $db, $prefix, $name, $file, $freq, $nextrun = 0, $maxRetry = 2)
{
    $nameEsc = $conn->real_escape_string($name);
    $fileEsc = $conn->real_escape_string($file);
    $freqEsc = $conn->real_escape_string($freq);
    $nextrun = (int) $nextrun;
    $maxRetry = (int) $maxRetry;
    $conn->select_db($db);
    $ok = $conn->query("INSERT INTO `{$prefix}cron` (`name`, `orde`, `file`, `no`, `desc`, `freq`, `nextrun`, `max_retry`) VALUES ('{$nameEsc}', 90, '{$fileEsc}', 0, 'IT probe', '{$freqEsc}', {$nextrun}, {$maxRetry})");
    it_drain($conn);
    return $ok;
}

function fetchRow($workRoot, $name)
{
    list($code, $output) = it_run_php($workRoot . '/tools/it_scheduler.php', array('state', $name));
    $data = json_decode($output, true);
    if ($code !== 0 || !is_array($data)) {
        echo "诊断[state {$name}] code={$code} output=" . substr($output, 0, 300) . "\n";
        return null;
    }
    return $data;
}

function countExhaustLogs($workRoot)
{
    $file = $workRoot . '/data/log/system.log';
    if (!is_file($file)) {
        return 0;
    }
    return substr_count(file_get_contents($file), '连续失败');
}

try {
    // ---------- 准备副本与环境 ----------
    if (!it_copy_project($repoRoot, $workRoot)) {
        die("FAIL - 无法创建项目副本\n");
    }
    file_put_contents($workRoot . '/setup/install.lock', '1');
    check('写入调度运行器', writeSchedulerRunner($workRoot));
    check('写入探针任务 it_probe', writeProbeTask($workRoot, 'it_probe', 'probe', 'probe-ok'));
    check('写入探针任务 it_fail', writeProbeTask($workRoot, 'it_fail', 'fail-marker', false));
    check('写入探针任务 it_once', writeProbeTask($workRoot, 'it_once', 'once', 'once-ok'));
    check('写入探针任务 it_due', writeProbeTask($workRoot, 'it_due', 'due', 'due-ok'));
    check('写入探针任务 it_future', writeProbeTask($workRoot, 'it_future', 'future', 'future-ok'));
    check('写入探针任务 it_loop', writeProbeTask($workRoot, 'it_loop', 'loop', 'loop-ok'));

    check('写入测试配置', it_write_config($workRoot, $db, $prefix, $port));
    $conn->query("DROP DATABASE IF EXISTS `{$db}`");
    $conn->query("CREATE DATABASE `{$db}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $template = file_get_contents($workRoot . '/setup/install.template.sql');
    $template = str_replace('{VAR-DB}', $db, $template);
    $template = str_replace('{VAR-COOKIE-SALT}', bin2hex(random_bytes(32)), $template);
    $template = str_replace('{VAR-SYSTEM-URL}', 'http://127.0.0.1', $template);
    check('导入全新安装模板', it_import_sql($conn, $db, $template, $prefix));

    // 环境收口：设置 cron 密码；关闭多线程分支；禁用系统自带任务避免干扰断言
    $conn->select_db($db);
    foreach (array(
        "UPDATE `{$prefix}options` SET `value` = 'itpw' WHERE `name` = 'cron_pw'",
        "UPDATE `{$prefix}options` SET `value` = '0' WHERE `name` = 'sign_multith'",
        "UPDATE `{$prefix}cron` SET `no` = 1 WHERE `name` LIKE 'system\\_%'",
    ) as $sql) {
        $conn->query($sql);
        it_drain($conn);
    }

    // ---------- 场景 A：到期任务正常执行与排程 ----------
    check('种子任务 it_probe', seedTask($conn, $db, $prefix, 'it_probe', 'lib/cron_it_probe.php', '3600'));
    list($code, $output) = it_run_php($workRoot . '/tools/it_scheduler.php', array('run', 'it_probe'));
    $result = json_decode($output, true);
    if ($result === null) {
        echo "诊断[run it_probe] code={$code} output=" . substr($output, 0, 300) . "\n";
    }
    check('runOnce 执行成功', $code === 0 && $result !== null && $result['ok'] === true && $result['ret'] === 'probe-ok');
    $state = fetchRow($workRoot, 'it_probe');
    $row = $state['row'];
    check('任务标记 last_status=success', $row !== null && $row['last_status'] === 'success');
    check('执行后锁已释放', $row !== null && (int) $row['running'] === 0);
    check('重试计数保持 0', $row !== null && (int) $row['retry'] === 0);
    check('nextrun 推进一个周期', $row !== null && abs((int) $row['nextrun'] - (time() + 3600)) <= 15);
    check('task_runs 记录 success', $state !== null && ($state['runs'][0]['status'] ?? '') === 'success');
    check('探针副作用已产生', is_file($markers . '/probe.txt'));

    // 新调度器不能凭过时快照再次运行已被另一个调度器推进的任务；手动运行仍可绕过时间。
    @unlink($markers . '/probe.txt');
    list($code, $output) = it_run_php($workRoot . '/tools/it_scheduler.php', array('scheduled', 'it_probe'));
    check('原子领取拒绝已被推进到未来的任务', json_decode($output, true)['ok'] === false && !is_file($markers . '/probe.txt'));
    list($code, $output) = it_run_php($workRoot . '/tools/it_scheduler.php', array('run', 'it_probe'));
    check('手动运行仍可执行未到期任务', json_decode($output, true)['ok'] === true && is_file($markers . '/probe.txt'));
    $conn->query("UPDATE `{$prefix}cron` SET `enable` = 0, `nextrun` = 0 WHERE `name` = 'it_probe'");
    list($code, $output) = it_run_php($workRoot . '/tools/it_scheduler.php', array('scheduled', 'it_probe'));
    check('原子领取拒绝已停用任务', json_decode($output, true)['ok'] === false);
    $conn->query("UPDATE `{$prefix}cron` SET `enable` = 1 WHERE `name` = 'it_probe'");
    list($code, $output) = it_run_php($workRoot . '/tools/it_scheduler.php', array('history', 'it_probe'));
    $history = json_decode($output, true);
    check('真实数据库历史筛选与越界页码收敛', $history['total'] === 2 && count($history['rows']) === 2 && $history['page'] === 1);
    check('结束记录保存真实开始时间', (int) $history['rows'][0]['started_at'] > 0);
    $conn->query("INSERT INTO `{$prefix}task_runs` (`name`,`status`,`message`,`started_at`,`finished_at`) VALUES ('it_legacy','success','legacy event',0," . time() . ")");
    list($code, $output) = it_run_php($workRoot . '/tools/it_scheduler.php', array('history', 'it_legacy'));
    check('旧版无开始时间的结束事件仍可按时间检索', json_decode($output, true)['total'] === 1);

    // ---------- 场景 B：锁冲突 → skipped，不执行 ----------
    $conn->query("UPDATE `{$prefix}cron` SET `running` = 1, `running_at` = " . time() . " WHERE `name` = 'it_probe'");
    it_drain($conn);
    list($code, $output) = it_run_php($workRoot . '/tools/it_scheduler.php', array('run', 'it_probe'));
    $result = json_decode($output, true);
    check('持锁时 runOnce 返回失败', $code === 0 && $result !== null && $result['ok'] === false);
    $state = fetchRow($workRoot, 'it_probe');
    check('锁冲突记录 task_runs skipped', ($state['runs'][0]['status'] ?? '') === 'skipped');
    check('持锁期间任务未被执行', (int) it_query_value($conn, $db, "SELECT COUNT(*) FROM `{$prefix}task_runs` WHERE `name` = 'it_probe' AND `status` = 'success'") === 2);

    // ---------- 场景 C：过期锁恢复 ----------
    $conn->query("UPDATE `{$prefix}cron` SET `running` = 1, `running_at` = " . (time() - 700) . " WHERE `name` = 'it_probe'");
    it_drain($conn);
    list($code, $output) = it_run_php($workRoot . '/tools/it_scheduler.php', array('run', 'it_probe'));
    $result = json_decode($output, true);
    check('过期锁被接管并执行成功', $code === 0 && $result !== null && $result['ok'] === true);
    $state = fetchRow($workRoot, 'it_probe');
    check('接管后锁正常释放', $state !== null && (int) $state['row']['running'] === 0);

    // ---------- 场景 D：失败退避 → 耗尽 → 通知去重 ----------
    check('种子任务 it_fail', seedTask($conn, $db, $prefix, 'it_fail', 'lib/cron_it_fail.php', '60'));
    $runFail = function () use ($workRoot) {
        it_run_php($workRoot . '/tools/it_scheduler.php', array('run', 'it_fail'));
    };
    $runFail();
    $row = fetchRow($workRoot, 'it_fail')['row'];
    check('第 1 次失败进入退避 retry=1', (int) $row['retry'] === 1 && abs((int) $row['nextrun'] - (time() + 300)) <= 15);
    $runFail();
    $row = fetchRow($workRoot, 'it_fail')['row'];
    check('第 2 次失败退避加倍 retry=2', (int) $row['retry'] === 2 && abs((int) $row['nextrun'] - (time() + 600)) <= 15);
    check('重试阶段未触发告警标记', it_query_value($conn, $db, "SELECT COUNT(*) FROM `{$prefix}options` WHERE `name` = 'cron_notify_last_it_fail'") === '0');
    $runFail();
    $row = fetchRow($workRoot, 'it_fail')['row'];
    check('第 3 次失败耗尽重试并复位', (int) $row['retry'] === 0 && $row['last_status'] === 'error' && abs((int) $row['nextrun'] - (time() + 60)) <= 15);
    check('耗尽写入告警时间标记', (int) it_query_value($conn, $db, "SELECT COUNT(*) FROM `{$prefix}options` WHERE `name` = 'cron_notify_last_it_fail'") === 1);
    $firstMarker = (int) it_query_value($conn, $db, "SELECT `value` FROM `{$prefix}options` WHERE `name` = 'cron_notify_last_it_fail'");
    check('耗尽仅产生一条结构化错误日志', countExhaustLogs($workRoot) === 1);
    // 第二轮耗尽（默认去重窗口 3600 秒内）：渠道告警被抑制，标记不更新
    $runFail();
    $runFail();
    $runFail();
    check('去重窗口内再次耗尽仍不更新告警标记', (int) it_query_value($conn, $db, "SELECT `value` FROM `{$prefix}options` WHERE `name` = 'cron_notify_last_it_fail'") === $firstMarker);
    check('结构化日志不随告警节流（累计 2 条）', countExhaustLogs($workRoot) === 2);
    // 关闭去重（interval=0）：每次耗尽都告警
    $conn->query("UPDATE `{$prefix}options` SET `value` = '0' WHERE `name` = 'cron_notify_interval'");
    it_drain($conn);
    sleep(1);
    $runFail();
    $runFail();
    $runFail();
    $thirdMarker = (int) it_query_value($conn, $db, "SELECT `value` FROM `{$prefix}options` WHERE `name` = 'cron_notify_last_it_fail'");
    check('interval=0 时耗尽立即更新告警标记', $thirdMarker > $firstMarker);
    check('结构化日志累计 3 条', countExhaustLogs($workRoot) === 3);

    check('种子零重试任务', seedTask($conn, $db, $prefix, 'it_zero', 'lib/cron_it_zero.php', '3600', 0, 0));
    check('写入零重试探针', writeProbeTask($workRoot, 'it_zero', 'zero', false));
    it_run_php($workRoot . '/tools/it_scheduler.php', array('run', 'it_zero'));
    $zero = fetchRow($workRoot, 'it_zero')['row'];
    check('单任务 max_retry=0 生效', (int) $zero['retry'] === 0 && abs((int) $zero['nextrun'] - (time() + 3600)) <= 15);
    list($code, $output) = it_run_php($workRoot . '/tools/it_scheduler.php', array('encrypt_cron'));
    check('计划任务密码已加密落库', json_decode($output, true)['encrypted'] === true);
    list($code, $output) = it_run_php($workRoot . '/do.php', array('--pw=wrong-password'));
    check('错误明文密码被拒绝', strpos($output, '密码错误') !== false);
    list($code, $output) = it_run_php($workRoot . '/do.php', array('--pw=itpw'));
    check('正确明文密码可读取加密配置并执行', strpos($output, '本次计划任务完成') !== false);

    // ---------- 场景 E：一次性任务执行后删除 ----------
    check('种子一次性任务 it_once', seedTask($conn, $db, $prefix, 'it_once', 'lib/cron_it_once.php', '-1'));
    list($code, $output) = it_run_php($workRoot . '/tools/it_scheduler.php', array('run', 'it_once'));
    $result = json_decode($output, true);
    check('一次性任务执行成功', $code === 0 && $result !== null && $result['ok'] === true);
    check('一次性任务已从 cron 表删除', fetchRow($workRoot, 'it_once')['row'] === null);
    check('一次性任务运行历史保留', (int) it_query_value($conn, $db, "SELECT COUNT(*) FROM `{$prefix}task_runs` WHERE `name` = 'it_once' AND `status` = 'success'") === 1);

    // ---------- 场景 F：do.php 只跑到期任务 + 并发保护 ----------
    check('种子到期任务 it_due', seedTask($conn, $db, $prefix, 'it_due', 'lib/cron_it_due.php', '3600', 0));
    check('种子未到期任务 it_future', seedTask($conn, $db, $prefix, 'it_future', 'lib/cron_it_future.php', '3600', time() + 9999));
    list($code, $output) = it_run_php($workRoot . '/do.php', array('--pw=itpw'));
    check('do.php 正常完成', $code === 0 && strpos($output, '本次计划任务完成') !== false);
    check('到期任务被执行', is_file($markers . '/due.txt'));
    check('未到期任务被跳过', !is_file($markers . '/future.txt'));
    // 并发保护：外部持有 it_due 锁时，do.php 不得重复执行。
    // 首次成功后 nextrun 已推进到未来，需重置回到期状态，才会再次尝试领取。
    $conn->query("UPDATE `{$prefix}cron` SET `running` = 1, `running_at` = " . time() . ", `nextrun` = 0 WHERE `name` = 'it_due'");
    it_drain($conn);
    @unlink($markers . '/due.txt');
    list($code, $output) = it_run_php($workRoot . '/do.php', array('--pw=itpw'));
    check('持锁时 do.php 完成但不重复执行到期任务', $code === 0 && !is_file($markers . '/due.txt'));
    check('锁冲突记录 skipped 历史', (int) it_query_value($conn, $db, "SELECT COUNT(*) FROM `{$prefix}task_runs` WHERE `name` = 'it_due' AND `status` = 'skipped'") >= 1);
    $conn->query("UPDATE `{$prefix}cron` SET `running` = 0, `running_at` = 0 WHERE `name` = 'it_due'");
    it_drain($conn);

    // ---------- 场景 G：do.php 常驻循环模式（--loop，仅 CLI 可选） ----------
    check('种子循环模式任务 it_loop', seedTask($conn, $db, $prefix, 'it_loop', 'lib/cron_it_loop.php', '3600', 0));
    @unlink($markers . '/loop.txt');
    $nullDevice = DIRECTORY_SEPARATOR === '\\' ? 'NUL' : '/dev/null';
    $loopLog = sys_get_temp_dir() . '/tcs-it-loop.log';
    $loopProc = proc_open(
        escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($workRoot . '/do.php') . ' --pw=itpw --loop=2',
        array(
            0 => array('file', $nullDevice, 'r'),
            1 => array('file', $loopLog, 'w'),
            2 => array('file', $loopLog, 'w'),
        ),
        $loopPipes,
        null,
        null,
        array('bypass_shell' => true)
    );
    check('常驻循环进程已启动', is_resource($loopProc));
    // 2 秒间隔：6 秒内应至少完成两轮调度并执行到期任务
    sleep(6);
    check('常驻模式执行了到期任务', is_file($markers . '/loop.txt'));
    check('常驻模式日志包含启动标记', strpos((string) @file_get_contents($loopLog), '常驻模式已启动') !== false);
    // 按 PID 强杀（proc_get_status 的 PID 即服务器/工作进程本体，见 api-integration-test 同款处理）
    $loopStatus = proc_get_status($loopProc);
    if (DIRECTORY_SEPARATOR === '\\') {
        exec('taskkill /PID ' . (int) $loopStatus['pid'] . ' /F 2>NUL');
    } else {
        exec('kill -9 ' . (int) $loopStatus['pid'] . ' 2>/dev/null');
    }
    proc_close($loopProc);
    check('常驻循环进程可正常终止', !is_resource($loopProc));
} catch (Throwable $e) {
    it_check('无未捕获异常', false);
    echo '异常：' . $e->getMessage() . "\n";
} finally {
    $conn->query("DROP DATABASE IF EXISTS `{$db}`");
    $conn->close();
    if (strpos($workRoot, 'tcs-it-sched-') !== false && is_dir($workRoot)) {
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
