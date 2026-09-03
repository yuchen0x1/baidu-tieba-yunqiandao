<?php

if (!defined('SYSTEM_ROOT')) { die('Insufficient Permissions'); }

require_once SYSTEM_ROOT . '/lib/class.SignManager.php';

/**
 * 用户级签到提醒
 *
 * 与全局 Notify 不同，Reminder 面向单个用户，按用户在「个人设置」中勾选的
 *  remind_sign_fail / remind_daily_summary 选项决定是否推送。
 * 当前实现走用户自己的邮箱（users.email）；如需扩展为用户级推送渠道，
 *  只需在 sendToUser() 中追加分支即可。
 *
 * 调用时机：
 *  - lib/cron_system_sign.php 签到结束后调用 Reminder::checkSignFailures()
 *  - 任意位置通过 Reminder::sendDailySummary($uid) 触发每日摘要
 */
class Reminder
{
    /**
     * 签到失败提醒：扫描今日有失败记录的用户，按其设置推送邮件。
     * @param int $failThreshold 全局失败阈值（仅当用户失败数 >= 此值才提醒；默认 1）
     * @return array [{uid, ok, detail}]
     */
    public static function checkSignFailures($failThreshold = 1)
    {
        global $m;
        SignManager::ensureHistoryTable();
        $failThreshold = max(1, (int) $failThreshold);
        $todayStart = strtotime(date('Y-m-d') . ' 00:00:00');

        // 聚合今日每个用户的失败次数与最后一次失败原因
        $sql = "SELECT `uid`,
            SUM(`status` = 'error') AS fail_count,
            SUM(`status` = 'success') AS ok_count,
            SUBSTRING_INDEX(GROUP_CONCAT(`message` ORDER BY `id` DESC SEPARATOR ' | '), ' | ', 1) AS last_msg
            FROM `" . DB_PREFIX . SignManager::HISTORY_TABLE . "`
            WHERE `created_at` >= {$todayStart} AND `uid` > 0
            GROUP BY `uid`";
        $result = $m->query($sql);
        $reports = array();
        while ($row = $m->fetch_array($result)) {
            $uid = (int) $row['uid'];
            $fail = (int) $row['fail_count'];
            $ok = (int) $row['ok_count'];
            // 用户未启用失败提醒，跳过
            if (option::uget('remind_sign_fail', $uid) != 1) {
                continue;
            }
            // 用户自定义阈值（默认 1）
            $userThreshold = (int) option::uget('remind_sign_fail_threshold', $uid);
            if ($userThreshold <= 0) {
                $userThreshold = $failThreshold;
            }
            if ($userThreshold > 20) {
                $userThreshold = 20;
            }
            if ($fail < $userThreshold) {
                continue;
            }
            // 当日已发送过提醒，避免重复
            if (self::sentToday($uid, 'sign_fail')) {
                continue;
            }
            $lastMsg = (string) $row['last_msg'];
            $title = SYSTEM_FN . ' 签到失败提醒';
            $content = sprintf(
                "今日签到情况：成功 %d 次，失败 %d 次。\n最近一次失败原因：%s\n\n请前往「签到日志」查看详情，或到「贴吧与签到」页手动复签。",
                $ok,
                $fail,
                $lastMsg
            );
            $detail = self::sendToUser($uid, $title, $content);
            self::markSent($uid, 'sign_fail');
            $reports[] = array('uid' => $uid, 'ok' => $detail['ok'], 'detail' => $detail['detail']);
        }
        return $reports;
    }

    /**
     * 每日签到摘要：对启用的用户发送当日结果汇总。
     * @return array [{uid, ok, detail}]
     */
    public static function sendDailySummary()
    {
        global $m;
        SignManager::ensureHistoryTable();
        $todayStart = strtotime(date('Y-m-d') . ' 00:00:00');

        // 找出今日有任意签到记录且启用摘要的用户
        $sql = "SELECT `uid`,
            SUM(`status` = 'success') AS ok_count,
            SUM(`status` = 'error') AS fail_count,
            COUNT(*) AS total
            FROM `" . DB_PREFIX . SignManager::HISTORY_TABLE . "`
            WHERE `created_at` >= {$todayStart} AND `uid` > 0
            GROUP BY `uid`";
        $result = $m->query($sql);
        $reports = array();
        while ($row = $m->fetch_array($result)) {
            $uid = (int) $row['uid'];
            if (option::uget('remind_daily_summary', $uid) != 1) {
                continue;
            }
            if (self::sentToday($uid, 'daily_summary')) {
                continue;
            }
            $ok = (int) $row['ok_count'];
            $fail = (int) $row['fail_count'];
            $total = (int) $row['total'];
            $rate = $total > 0 ? round($ok / $total * 100, 1) : 0;
            $title = SYSTEM_FN . ' 每日签到摘要';
            $content = sprintf(
                "今日签到汇总：成功 %d 次，失败 %d 次，共 %d 次，成功率 %.1f%%。",
                $ok,
                $fail,
                $total,
                $rate
            );
            $detail = self::sendToUser($uid, $title, $content);
            self::markSent($uid, 'daily_summary');
            $reports[] = array('uid' => $uid, 'ok' => $detail['ok'], 'detail' => $detail['detail']);
        }
        return $reports;
    }

    /**
     * 给某用户发送邮件。仅当用户表里 email 不为空且 mail_mode 已配置时发送。
     * 这里不依赖全局 Notify，是因为 Notify 走管理员配置的渠道；用户级提醒
     * 应发往用户自己的邮箱。如需扩展为用户自选渠道，可在此处分支。
     */
    protected static function sendToUser($uid, $title, $content)
    {
        global $m;
        $uid = (int) $uid;
        $row = $m->once_fetch_array("SELECT `name`, `email` FROM `" . DB_PREFIX . "users` WHERE `id` = {$uid} LIMIT 1");
        if (empty($row) || empty($row['email'])) {
            return array('ok' => false, 'detail' => '用户未设置邮箱');
        }
        $to = $row['email'];
        $name = $row['name'] ? $row['name'] : ('UID:' . $uid);
        try {
            $html = nl2br(htmlspecialchars($content, ENT_QUOTES, 'UTF-8'));
            $result = misc::mail($to, $title, $html);
            if ($result === true) {
                return array('ok' => true, 'detail' => '邮件已发送至 ' . $to);
            }
            return array('ok' => false, 'detail' => '邮件发送失败: ' . (string) $result);
        } catch (Throwable $e) {
            return array('ok' => false, 'detail' => '邮件异常: ' . $e->getMessage());
        }
    }

    /**
     * 记录今日已发送某事件提醒，避免一天发多次。
     * 用 option::uset 存一个时间戳即可。
     */
    protected static function markSent($uid, $event)
    {
        $key = 'remind_last_sent_' . $event;
        option::uset($key, date('Ymd'), (int) $uid);
    }

    protected static function sentToday($uid, $event)
    {
        $key = 'remind_last_sent_' . $event;
        $val = option::uget($key, (int) $uid);
        return $val === date('Ymd');
    }
}
