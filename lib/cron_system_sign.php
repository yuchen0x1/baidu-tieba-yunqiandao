<?php

if (!defined('SYSTEM_ROOT')) {
    die('Insufficient Permissions');
}

/**
 * 云签到内部计划任务
 * [贴吧签到]
 */

function cron_system_sign()
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
        $return = misc::DoSign($value);
    }
    $duration = intval((microtime(true) - $start) * 1000);

    $stat = SignManager::signSummary();
    $msg = "[签到结束 " . date('H:i:s') . "] 已签到 {$stat['signed']} 个贴吧，出错 {$stat['error']} 个，等待 {$stat['waiting']} 个";
    SignManager::log(0, 'sign_all', $stat['error'] > 0 ? 'partial' : 'ok', $msg, $duration);
    if ($stat['error'] > 0) {
        Notify::send(SYSTEM_FN . ' 签到异常提醒', $msg . "\n\n失败贴吧已记录在后台“签到看板”，可通过重试任务下轮复签。", 'sign_fail');
    }

    // 成就自动解锁：扫描今日有签到记录的用户，批量触发 checkAndUnlock
    if (file_exists(SYSTEM_ROOT . '/lib/class.Achievement.php')) {
        require_once SYSTEM_ROOT . '/lib/class.Achievement.php';
        $todayStart = strtotime(date('Y-m-d') . ' 00:00:00');
        $uidResult = $m->query("SELECT DISTINCT `uid` FROM `" . DB_PREFIX . SignManager::HISTORY_TABLE . "` WHERE `uid` > 0 AND `created_at` >= {$todayStart}");
        $checkedUids = array();
        while ($uRow = $m->fetch_array($uidResult)) {
            $checkedUids[] = (int) $uRow['uid'];
        }
        // 同时加载 Notice 类（用于成就解锁通知）
        $noticeReady = file_exists(SYSTEM_ROOT . '/lib/class.Notice.php');
        if ($noticeReady) {
            require_once SYSTEM_ROOT . '/lib/class.Notice.php';
        }
        foreach ($checkedUids as $achUid) {
            $newAch = Achievement::checkAndUnlock($achUid);
            // 为每个新解锁的成就创建站内通知
            if ($noticeReady && !empty($newAch)) {
                $defs = Achievement::definitions();
                foreach ($newAch as $code) {
                    if (isset($defs[$code])) {
                        $d = $defs[$code];
                        Notice::create($achUid, Notice::TYPE_ACHIEVEMENT,
                            '解锁新成就：' . $d['name'],
                            '恭喜！你已达成「' . $d['desc'] . '」，获得 ' . $d['tier'] . ' 级勋章。',
                            'index.php?mod=achievements'
                        );
                    }
                }
            }
        }
        doAction('achievements_checked', $checkedUids);
    }

    // 站内通知：签到失败的用户收到通知
    if (file_exists(SYSTEM_ROOT . '/lib/class.Notice.php')) {
        require_once SYSTEM_ROOT . '/lib/class.Notice.php';
        if ($stat['error'] > 0) {
            // 扫描今日有失败记录的用户
            $failUidResult = $m->query("SELECT DISTINCT `uid` FROM `" . DB_PREFIX . SignManager::HISTORY_TABLE . "` WHERE `uid` > 0 AND `created_at` >= {$todayStart} AND `status` = 'error'");
            while ($fuRow = $m->fetch_array($failUidResult)) {
                $fuId = (int) $fuRow['uid'];
                // 避免同日重复发送（用 option 标记）
                $sentKey = 'notice_sign_fail_' . date('Ymd');
                if (option::uget($sentKey, $fuId) == 1) { continue; }
                Notice::create($fuId, Notice::TYPE_SIGN_FAIL,
                    '今日有贴吧签到失败',
                    '系统检测到你今日有贴吧签到失败，请前往「签到日志」查看详情或手动复签。',
                    'index.php?mod=signlogs'
                );
                option::uset($sentKey, 1, $fuId);
            }
        }
        // 清理超过 90 天的已读通知
        Notice::cleanOld(90);
    }

    // 用户级签到提醒：扫描启用提醒的用户并推送邮件
    // 注意：日报改由独立任务 system_daily_report 在指定时间发送（见 lib/cron_system_daily_report.php），
    // 这里不再调用 Reminder::sendDailySummary()——此前每次签到都发日报，
    // 用户当天第一批签到完成就收到"每日日报"，此时大部分贴吧还没签。
    if (file_exists(SYSTEM_ROOT . '/lib/class.Reminder.php')) {
        require_once SYSTEM_ROOT . '/lib/class.Reminder.php';
        if ($stat['error'] > 0) {
            Reminder::checkSignFailures(1);
        }
        doAction('reminders_sent');
    }

    return $msg;
}
