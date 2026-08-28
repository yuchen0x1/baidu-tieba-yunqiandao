<?php

if (!defined('SYSTEM_ROOT')) {
    die('Insufficient Permissions');
}

/**
 * BDUSS 健康度检查计划任务
 */
function cron_system_health_check()
{
    global $m;
    require_once SYSTEM_ROOT . '/lib/class.SignManager.php';
    require_once SYSTEM_ROOT . '/lib/class.notify.php';

    $baiduids = $m->query("SELECT `id`, `bduss` FROM `" . DB_PREFIX . "baiduid` WHERE 1;");
    $checked = 0;
    $invalid = 0;
    $start = microtime(true);
    while ($row = $m->fetch_array($baiduids)) {
        $ok = SignManager::checkBaiduidHealth($row['id'], P::decrypt($row['bduss']));
        if (!$ok) {
            $invalid++;
        }
        $checked++;
        usleep(200000); // 200ms 间隔避免触发风控
    }
    $duration = intval((microtime(true) - $start) * 1000);
    $now = date('Y-m-d H:i:s');
    $msg = "[$now] 健康度检查完成：共检查 $checked 个账号，其中 $invalid 个 BDUSS 已失效";
    if ($invalid > 0) {
        option::set('cron_health_invalid', $invalid);
        $msg .= "。请及时更新已失效的 BDUSS！";
        SignManager::log(0, 'health_check', 'invalid', $msg, $duration);
        Notify::send(SYSTEM_FN . ' 账号健康异常', "检测到 $invalid 个百度账号 BDUSS 已失效，请及时更新。", 'health_invalid');
    } else {
        option::set('cron_health_invalid', 0);
        SignManager::log(0, 'health_check', 'ok', $msg, $duration);
    }
    return $msg;
}
