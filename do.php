<?php

define('SYSTEM_DO_NOT_LOGIN', true);
require dirname(__FILE__) . '/init.php';
global $m,$today,$i;
ignore_user_abort(true);
set_time_limit(0);
$cron_pw = option::get('cron_pw');
$cmd_pw = function_exists('getopt') ? getopt('', array('pw::')) : false;
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
if (SYSTEM_PAGE == 'runcron') {
    $cron = isset($_GET['cron']) ? trim(strip_tags($_GET['cron'])) : msg('运行失败：计划任务未指定');
    if (!preg_match('/^[A-Za-z0-9_]+$/', $cron)) {
        msg('运行失败：计划任务名称不合法');
    }
    $cron = sqladds($cron);
    $cpw  = option::get('cron_pw');

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
