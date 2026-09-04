<?php

if (!defined('SYSTEM_ROOT')) {
    die('Insufficient Permissions');
}

require_once SYSTEM_ROOT . '/lib/class.notify.php';

/**
 * 每周签到报告计划任务（用户级，纯用户自主开启，无站点总开关）
 * 用户在「个人设置」开启并选择星期几后，每周到点推送近 7 天签到汇总：
 * 总量/成功/失败/成功率、覆盖吧数、活跃天数、失败原因 Top3。
 * 走用户个人通知渠道（Notify::sendForUser，未配置回退站点渠道），与邮件版每日摘要互补。
 * lastdo 防重：6 天内只推送一次，计划任务同日多次执行不会重复发送。
 */
function cron_system_weekreport()
{
    global $m;
    require_once SYSTEM_ROOT . '/lib/class.SignManager.php';
    $rs = $m->query("SELECT `id` FROM `" . DB_PREFIX . "users` ORDER BY `id` ASC");
    $sent = 0;
    $waiting = 0;
    while ($user = $m->fetch_array($rs)) {
        $uid = (int) $user['id'];
        if (option::uget('weekly_report_enable', $uid) !== '1') {
            continue;
        }
        $dayOpt = option::uget('weekly_report_day', $uid);
        $weekDay = ($dayOpt === null || $dayOpt === '') ? 1 : min(6, max(0, (int) $dayOpt));
        if ((int) date('w') !== $weekDay) {
            $waiting++;
            continue; //未到该用户设定的推送日
        }
        $last = (int) option::uget('weekly_report_lastrun', $uid);
        if ($last > 0 && time() - $last < 6 * 86400) {
            $waiting++;
            continue; //6 天内已推送过
        }
        $overview = SignManager::overview($uid, 7);
        if ((int) $overview['total'] <= 0) {
            option::uset('weekly_report_lastrun', (string) time(), $uid);
            continue; //近 7 天无签到记录，不打扰
        }
        $lines = array();
        $lines[] = '近 7 天共签到 ' . (int) $overview['total'] . ' 次：成功 ' . (int) $overview['success'] . ' 次，失败 ' . (int) $overview['error'] . ' 次，成功率 ' . round((float) $overview['rate'], 1) . '%。';
        $lines[] = '覆盖 ' . (int) $overview['forums'] . ' 个贴吧，活跃 ' . (int) $overview['active_days'] . ' 天。';
        $breakdown = SignManager::failureBreakdown($uid, 3, 7);
        if (!empty($breakdown)) {
            $reasons = array();
            $i = 1;
            foreach ($breakdown as $b) {
                $reasons[] = $i++ . '. ' . mb_substr((string) $b['reason'], 0, 40) . '（' . (int) $b['count'] . ' 次）';
            }
            $lines[] = "失败原因 Top：\n" . implode("\n", $reasons);
        }
        $r = Notify::sendForUser($uid, '签到周报（' . date('Y-m-d') . '）', implode("\n", $lines), 'weekreport');
        option::uset('weekly_report_lastrun', (string) time(), $uid);
        if (!empty($r['ok'])) {
            $sent++;
        }
    }
    return '签到周报完成：推送 ' . $sent . ' 位用户，' . $waiting . ' 位未到推送日或已推送';
}
