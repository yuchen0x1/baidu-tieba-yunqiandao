<?php

if (!defined('SYSTEM_ROOT')) {
    die('Insufficient Permissions');
}

/**
 * 云签到内部计划任务
 * [重新尝试签到出错的贴吧]
 */

function cron_system_sign_retry()
{
    global $i, $m;
    require_once SYSTEM_ROOT . '/lib/class.SignManager.php';
    require_once SYSTEM_ROOT . '/lib/class.notify.php';

    $today = date('Y-m-d');
    require_once SYSTEM_ROOT . '/lib/security.php';
    $sign_again = safe_retry_state(option::get('cron_sign_again'));
    if ($sign_again['lastdo'] != $today) {
        option::set('cron_sign_again', serialize(array('num' => 0, 'lastdo' => $today)));
    }

    $start = microtime(true);
    foreach ($i['table'] as $value) {
        misc::DoSign_retry($value);
    }
    $duration = intval((microtime(true) - $start) * 1000);

    //复签轮数自增：供智能重试退避（shouldRetrySmart）按 2 的幂间隔判定
    $sign_again = safe_retry_state(option::get('cron_sign_again'));
    $sign_again['num'] = (isset($sign_again['num']) ? (int) $sign_again['num'] : 0) + 1;
    $sign_again['lastdo'] = $today;
    option::set('cron_sign_again', serialize($sign_again));

    $stat = SignManager::signSummary();
    $msg = "[复签结束 " . date('H:i:s') . "] 已签到 {$stat['signed']} 个贴吧，出错 {$stat['error']} 个，等待 {$stat['waiting']} 个";
    SignManager::log(0, 'sign_retry', $stat['error'] > 0 ? 'partial' : 'ok', $msg, $duration);
    if ($stat['error'] > 0) {
        Notify::send(SYSTEM_FN . ' 复签仍有失败', $msg, 'sign_fail');
    }
    return $msg;
}
