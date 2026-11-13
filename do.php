<?php

define('SYSTEM_DO_NOT_LOGIN', true);
require dirname(__FILE__) . '/init.php';
global $m,$today,$i;
ignore_user_abort(true);
set_time_limit(0);
$cron_pw = SecretBox::get('cron_pw');
$cmd_pw = function_exists('getopt') ? getopt('', array('pw::', 'loop::')) : false;
$providedCronPassword = request_scalar_value($_REQUEST, 'pw');
$providedCommandPassword = is_array($cmd_pw) && isset($cmd_pw['pw']) ? (string) $cmd_pw['pw'] : '';
$isCli = SYSTEM_ISCONSOLE;
if (!$isCli && empty($cron_pw)) {
    msg('计划任务执行失败：尚未设置计划任务密码。请先在“设置中心”中设置密码，再通过 Web 访问 do.php。', false);
}
if (!empty($cron_pw) && (!$isCli || $providedCommandPassword !== '')) {
    $passwordMatches = hash_equals((string) $cron_pw, $isCli ? $providedCommandPassword : $providedCronPassword);
    if (!$passwordMatches) {
        msg('计划任务执行失败：密码错误<br/><br/>你需要通过访问 <b>do.php?pw=密码</b> 才能执行计划任务<br/>例如：' . SYSTEM_URL . 'do.php?pw=yourpassword<br/><br/>若您要通过命令行执行计划任务，请加上参数 <b>--pw=密码</b><br/>例如：php do.php --pw=yourpassword<br/>命令行模式注意：你需要指明 do.php 的绝对路径，或者将 do.php 加入 PATH', false);
    }
}
if (isset($_GET['in_thread'])) {
    define('SYSTEM_CRON_THREAD', true);
}
doAction('cron_1');
// V7.0 常驻循环模式（仅 CLI，可选）：php do.php --pw=密码 --loop[=间隔秒]
// 复用 runall 的防重入锁/nextrun 调度/失败退避全部机制，与 cron 触发路径互不干扰；
// 不引入新运行时依赖，适合无 crond 的 Windows 部署或不便配置计划任务的场景。
if ($isCli && is_array($cmd_pw) && array_key_exists('loop', $cmd_pw)) {
    $loopInterval = (int) $cmd_pw['loop'];
    $loopInterval = $loopInterval > 0 ? min(600, $loopInterval) : 30;
    // 常驻进程输出须即时落盘（守护场景 stdout 通常重定向到日志），不做控制台缓冲转换
    while (ob_get_level() > 0) {
        @ob_end_clean();
    }
    // V7.3 可选优雅重载（默认关闭）：部署方设置环境变量 LOOP_RELOAD_FILE 指向一个本地
    // 可写标记文件后，升级完成时 Web 侧会写入标记；常驻进程在本轮调度结束后主动退出
    // （退出码 75），由 s6/systemd/任务计划等进程管理器自动拉起以加载新代码。
    // 未设置该环境变量时行为与旧版完全一致，仍需手动重启。
    $loopReloadFile = getenv('LOOP_RELOAD_FILE');
    if ($loopReloadFile === false || trim((string) $loopReloadFile) === '') {
        $loopReloadFile = '';
    } else {
        $loopReloadFile = trim((string) $loopReloadFile);
    }
    $loopStartedAt = time();
    echo '[' . date('H:i:s') . '] 常驻模式已启动，调度间隔 ' . $loopInterval . ' 秒，Ctrl+C 退出' . PHP_EOL;
    while (true) {
        try {
            // 长运行连接可能被服务端回收：断线时重建连接后再调度
            if (!$m->ping()) {
                $m->close();
                $GLOBALS['m'] = new S(DB_HOST, DB_USER, DB_PASSWD, DB_NAME, LONGSQL, DB_SSL);
            }
            cron::runall();
        } catch (Throwable $loopError) {
            error_log('do.php loop 调度异常：' . $loopError->getMessage());
        }
        if ($loopReloadFile !== '' && is_file($loopReloadFile)) {
            // 仅响应进程启动之后写入的标记，避免消费历史遗留文件导致误退出
            $reloadRequestedAt = @filemtime($loopReloadFile);
            if ($reloadRequestedAt !== false && $reloadRequestedAt >= $loopStartedAt) {
                @unlink($loopReloadFile);
                echo '[' . date('H:i:s') . '] 收到升级重载请求，本轮调度已完成，进程优雅退出（退出码 75），等待进程管理器重新拉起…' . PHP_EOL;
                exit(75);
            }
        }
        sleep($loopInterval);
    }
}
if (SYSTEM_PAGE == 'runcron') {
    $cron = isset($_GET['cron']) ? trim(strip_tags($_GET['cron'])) : msg('运行失败：计划任务未指定');
    if (!preg_match('/^[A-Za-z0-9_]+$/', $cron)) {
        msg('运行失败：计划任务名称不合法');
    }
    $cron = sqladds($cron);
    $x    = $m->once_fetch_array("SELECT * FROM `" . DB_PREFIX . "cron` WHERE `name` = '{$cron}';");
    if (empty($x['id'])) {
        msg('运行失败：此计划任务不存在');
    }

    // V5.13：完整执行单个任务（加锁→执行→记录运行历史→解锁→排程），失败自动进入退避重试
    cron::runOnce($x['name']);
} else {
    $sign_multith = option::get('sign_multith');
    if (!isset($_GET['donnot_sign_multith']) && !empty($sign_multith) && function_exists('fsockopen')) {
        for ($ii = 0; $ii < $sign_multith; $ii++) {
            sendRequest(SYSTEM_URL . 'do.php?donnot_sign_multith&in_thread', 'pw=' . urlencode($cron_pw));
        }
    }
        $return = '';
    if (option::get('cron_last_do_time') != $today) {
        option::set('cron_last_do_time', $today);
        option::set('cron_last_do', '0');
    }
        cron::runall();
}
doAction('cron_2');
msg('本次计划任务完成', false, false);
