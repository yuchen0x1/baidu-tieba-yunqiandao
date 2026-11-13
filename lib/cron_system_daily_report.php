<?php

if (!defined('SYSTEM_ROOT')) {
    die('Insufficient Permissions');
}

/**
 * 云签到内部计划任务
 * [每日签到日报]（V7.5 新增，替代每次签到触发的日报）
 *
 * 设计说明：
 * - 原实现把 Reminder::sendDailySummary() 放在每次签到结束时调用，
 *   用户当天第一批签到完成后就收到"每日日报"（大部分贴吧还没签）。
 * - 新任务每小时检查一次，满足以下任一条件才发送：
 *   1) 当前小时 >= daily_report_hour（默认 21 点）
 *   2) 或 daily_report_require_complete=1 且今日待签贴吧数为 0
 * - 发送去重沿用 Reminder 的 sentToday() 标记，保证每天只发一次。
 */

function cron_system_daily_report()
{
    $reportHour = (int) option::get('daily_report_hour');
    if ($reportHour < 0 || $reportHour > 23 || option::get('daily_report_hour') === null || option::get('daily_report_hour') === '') {
        $reportHour = 21;
    }
    $requireComplete = option::get('daily_report_require_complete') === '1';

    //时间窗判定：到了设定小时才发
    $nowHour = (int) date('G');
    $deadlineReached = $nowHour >= $reportHour;

    //完成度判定：待签贴吧数为 0 才发（可选）
    $allSigned = false;
    if ($requireComplete && file_exists(SYSTEM_ROOT . '/lib/class.SignManager.php')) {
        require_once SYSTEM_ROOT . '/lib/class.SignManager.php';
        $stat = SignManager::signSummary();
        $allSigned = ((int) $stat['waiting']) === 0;
    }

    if (!$deadlineReached && !$allSigned) {
        return "未到日报发送时间（当前 " . date('H:i') . "，目标 " . $reportHour . ":00）或签到尚未全部完成，跳过";
    }

    if (!file_exists(SYSTEM_ROOT . '/lib/class.Reminder.php')) {
        return "Reminder 模块不可用，跳过日报发送";
    }
    require_once SYSTEM_ROOT . '/lib/class.Reminder.php';
    $reports = Reminder::sendDailySummary();
    $sent = 0;
    $failed = 0;
    foreach ($reports as $report) {
        if (!empty($report['ok'])) { $sent++; } else { $failed++; }
    }
    return "日报推送完成：渠道已接受 " . $sent . " 位用户，失败 " . $failed . " 位（失败项下轮重试，时间 " . date('H:i') . "）";
}
