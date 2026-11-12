<?php

if (!defined('SYSTEM_ROOT')) { die('Insufficient Permissions'); }

/**
 * 签到管理器 - 增强版
 * 提供签到日志、健康度检查、韧性增强功能
 */
class SignManager
{
    const LOG_TABLE = 'cron_log_batch';
    const HEALTH_TABLE = 'baiduid_health';
    const HISTORY_TABLE = 'sign_history';
    const HEALTH_SNAPSHOT_TABLE = 'baiduid_health_snapshot';

    /**
     * 为升级脚本和首次运行创建所有管理数据表。
     */
    public static function ensureTables()
    {
        static $ready = false;
        if ($ready) {
            return;
        }
        global $m;
        self::createLogTable();
        $m->query("CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . self::HEALTH_TABLE . "` (
            `pid` INT UNSIGNED NOT NULL PRIMARY KEY,
            `name` VARCHAR(40) NOT NULL DEFAULT '',
            `status` VARCHAR(20) NOT NULL DEFAULT 'unknown',
            `last_ok` INT UNSIGNED NOT NULL DEFAULT 0,
            `last_fail` INT UNSIGNED NOT NULL DEFAULT 0,
            `fail_count` INT UNSIGNED NOT NULL DEFAULT 0,
            `error_msg` TEXT,
            `updated_at` INT UNSIGNED NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
        self::ensureHistoryTable();
        $ready = true;
    }

    private static function createLogTable()
    {
        global $m;
        $m->query("CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . self::LOG_TABLE . "` (
            `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            `pid` INT UNSIGNED NOT NULL DEFAULT 0,
            `action` VARCHAR(50) NOT NULL DEFAULT '',
            `status` VARCHAR(20) NOT NULL DEFAULT '',
            `message` TEXT,
            `duration_ms` INT UNSIGNED NOT NULL DEFAULT 0,
            `created_at` INT UNSIGNED NOT NULL,
            PRIMARY KEY (`id`),
            KEY `pid` (`pid`), KEY `action` (`action`), KEY `status` (`status`), KEY `created_at` (`created_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
    }

    /**
     * 写签到日志
     */
    public static function log($pid, $action, $status, $message, $duration_ms = 0)
    {
        global $m;
        static $ddlDone = false;
        if (!$ddlDone) {
            self::createLogTable();
            $ddlDone = true;
        }
        $msg = $m->real_escape_string(substr($message, 0, 500));
        try {
            $m->query("INSERT INTO `" . DB_PREFIX . self::LOG_TABLE . "`
                (`pid`, `action`, `status`, `message`, `duration_ms`, `created_at`)
                VALUES ('" . intval($pid) . "', '" . $m->real_escape_string($action) . "',
                 '" . $m->real_escape_string($status) . "', '" . $msg . "',
                 '" . intval($duration_ms) . "', UNIX_TIMESTAMP());");
        } catch (Throwable $e) {
            //表可能在同进程内被删（如备份恢复），重建一次再重试；仍失败则按原样抛出
            self::createLogTable();
            $m->query("INSERT INTO `" . DB_PREFIX . self::LOG_TABLE . "`
                (`pid`, `action`, `status`, `message`, `duration_ms`, `created_at`)
                VALUES ('" . intval($pid) . "', '" . $m->real_escape_string($action) . "',
                 '" . $m->real_escape_string($status) . "', '" . $msg . "',
                 '" . intval($duration_ms) . "', UNIX_TIMESTAMP());");
        }
    }

    /**
     * 检查 BDUSS 健康度并更新
     */
    public static function checkBaiduidHealth($pid, $bduss)
    {
        global $m;
        self::ensureTables();

        $tbs = misc::getTbs(0, $bduss, (int) $pid);
        $now = time();
        if ($tbs) {
            $m->query("INSERT INTO `" . DB_PREFIX . self::HEALTH_TABLE . "`
                (`pid`, `status`, `last_ok`, `fail_count`, `updated_at`)
                VALUES ('" . intval($pid) . "', 'ok', '$now', 0, '$now')
                ON DUPLICATE KEY UPDATE
                `status` = 'ok', `last_ok` = '$now', `fail_count` = 0, `updated_at` = '$now', `error_msg` = NULL;");
            self::snapshotBaiduidHealth($pid, true, 0);
            return true;
        } else {
            $row = $m->once_fetch_array("SELECT `name` FROM `" . DB_PREFIX . "baiduid` WHERE `id` = '" . intval($pid) . "';");
            $name = $row ? $row['name'] : '未知';
            $m->query("INSERT INTO `" . DB_PREFIX . self::HEALTH_TABLE . "`
                (`pid`, `name`, `status`, `last_fail`, `fail_count`, `error_msg`, `updated_at`)
                VALUES ('" . intval($pid) . "', '" . $m->real_escape_string($name) . "', 'invalid', '$now', 1, 'BDUSS 已失效', '$now')
                ON DUPLICATE KEY UPDATE
                `status` = 'invalid', `last_fail` = '$now',
                `fail_count` = `fail_count` + 1,
                `error_msg` = 'BDUSS 已失效', `updated_at` = '$now';");
            $failRow = $m->once_fetch_array("SELECT `fail_count` FROM `" . DB_PREFIX . self::HEALTH_TABLE . "` WHERE `pid` = " . intval($pid) . ";");
            self::snapshotBaiduidHealth($pid, false, is_array($failRow) ? (int) $failRow['fail_count'] : 0);
            self::maybeAutoDisable($pid, $name);
            return false;
        }
    }

    /**
     * 失效账号自动处置：连续失败达到阈值时，停止该账号所有贴吧的自动签到并推送提醒
     * （对应选项 health_auto_disable / health_fail_max，默认关闭）
     */
    public static function maybeAutoDisable($pid, $name)
    {
        global $m;
        if (option::get('health_auto_disable') != '1') {
            return;
        }
        $max = max(1, (int) option::get('health_fail_max'));
        if ($max > 20) {
            $max = 20;
        }
        $row = $m->once_fetch_array("SELECT `fail_count` FROM `" . DB_PREFIX . self::HEALTH_TABLE . "` WHERE `pid` = " . intval($pid) . ";");
        if (empty($row) || (int) $row['fail_count'] !== $max) {
            //只在恰好达到阈值的当次触发一次，避免重复停用与重复通知
            return;
        }
        require_once SYSTEM_ROOT . '/lib/security.php';
        $tables = array_values(array_unique(array_merge(array('tieba'), safe_table_names(option::get('fb_tables')))));
        $affected = 0;
        foreach ($tables as $table) {
            $m->pquery("UPDATE `" . DB_NAME . "`.`" . DB_PREFIX . $table . "` SET `no` = '1' WHERE `pid` = ? AND `no` = '0'", array((int) $pid));
            $affected += (int) $m->affected_rows();
        }
        if ($affected > 0 && class_exists('Notify')) {
            require_once SYSTEM_ROOT . '/lib/class.notify.php';
            Notify::send('百度账号已自动停用', "账号 {$name} (PID " . intval($pid) . ") 已连续 {$max} 次健康检查失败（BDUSS 失效），其 {$affected} 个贴吧已自动停止签到。请重新绑定账号后再到「贴吧与签到」页手动恢复。", 'health_invalid');
        }
    }

    /**
     * 初始化历史记录表。
     */
    public static function ensureHistoryTable()
    {
        static $ready = false;
        if ($ready) {
            return;
        }
        global $m;
        $m->query("CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . self::HISTORY_TABLE . "` (
            `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            `pid` INT UNSIGNED NOT NULL DEFAULT 0,
            `uid` INT UNSIGNED NOT NULL DEFAULT 0,
            `tieba` VARCHAR(200) NOT NULL DEFAULT '',
            `status` VARCHAR(20) NOT NULL DEFAULT '',
            `error_code` INT NOT NULL DEFAULT 0,
            `message` VARCHAR(500) NOT NULL DEFAULT '',
            `exp_gained` INT UNSIGNED NOT NULL DEFAULT 0,
            `sign_rank` INT UNSIGNED NOT NULL DEFAULT 0,
            `forum_level` TINYINT UNSIGNED NOT NULL DEFAULT 0,
            `created_at` INT UNSIGNED NOT NULL,
            PRIMARY KEY (`id`),
            KEY `pid_created_at` (`pid`, `created_at`),
            KEY `uid_created_at` (`uid`, `created_at`),
            KEY `created_at` (`created_at`),
            KEY `status` (`status`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
        $ready = true;
    }

    /**
     * 写入单个贴吧的最终签到结果。
     */
    public static function logTiebaResult($pid, $uid, $tieba, $status, $errorCode = 0, $message = '', $expGained = 0, $signRank = 0, $forumLevel = 0)
    {
        global $m;
        self::ensureHistoryTable();
        $status = (string) $status;
        $message = substr((string) $message, 0, 500);
        $expGained = max(0, (int) $expGained);
        $signRank = max(0, (int) $signRank);
        $forumLevel = max(0, min(100, (int) $forumLevel));
        $m->query("INSERT INTO `" . DB_PREFIX . self::HISTORY_TABLE . "`
            (`pid`, `uid`, `tieba`, `status`, `error_code`, `message`, `exp_gained`, `sign_rank`, `forum_level`, `created_at`) VALUES ("
            . (int) $pid . ", " . (int) $uid . ", '" . $m->real_escape_string($tieba) . "', '"
            . $m->real_escape_string($status) . "', " . (int) $errorCode . ", '"
            . $m->real_escape_string($message) . "', " . $expGained . ", " . $signRank . ", " . $forumLevel . ", UNIX_TIMESTAMP())");
    }

    /**
     * 返回最近 N 天的签到日历数据。
     */
    public static function calendar($days = 90, $uid = 0)
    {
        global $m;
        self::ensureHistoryTable();
        $days = min(366, max(1, (int) $days));
        $since = time() - ($days * 86400);
        $sql = "SELECT DATE(FROM_UNIXTIME(`created_at`)) AS `day`,
            SUM(`status` = 'success') AS `success`, SUM(`status` = 'error') AS `error`,
            COUNT(*) AS `total` FROM `" . DB_PREFIX . self::HISTORY_TABLE . "`
            WHERE `created_at` >= ?";
        $params = array($since);
        if ($uid > 0) {
            $sql .= ' AND `uid` = ?';
            $params[] = (int) $uid;
        }
        $sql .= ' GROUP BY `day` ORDER BY `day` ASC';
        $rows = array();
        $result = $m->pquery($sql, $params);
        while ($row = $m->fetch_array($result)) {
            $rows[] = array(
                'day' => $row['day'],
                'success' => (int) $row['success'],
                'error' => (int) $row['error'],
                'total' => (int) $row['total'],
            );
        }
        return $rows;
    }

    /**
     * 恢复指定账号的贴吧签到（仅账号所有者或管理员可调用）。
     *
     * 恢复前会先实测 BDUSS 是否仍有效：已失效的凭据即使恢复，下一轮签到也必然再失败，
     * 用户只会看到"恢复了又停用"的假闭环。凭据无效时直接拒绝并要求重新绑定。
     *
     * @return array{ok:bool,msg:string} ok=false 时 msg 为可直接展示的原因
     */
    public static function restoreBaiduid($pid, $uid = 0)
    {
        global $m;
        $pid = (int) $pid;
        $uid = (int) $uid;
        $ownerWhere = $uid > 0 ? ' AND `uid` = ' . $uid : '';
        $account = $m->once_fetch_array("SELECT `id`, `uid`, `bduss` FROM `" . DB_PREFIX . "baiduid` WHERE `id` = {$pid}{$ownerWhere} LIMIT 1");
        if (empty($account)) {
            return array('ok' => false, 'msg' => '账号不存在或无权恢复');
        }
        $bduss = (string) P::decrypt($account['bduss']);
        if ($bduss === '') {
            return array('ok' => false, 'msg' => '凭据无法解密，请重新绑定该账号后再恢复签到');
        }
        //先按健康检查的同一口径实测凭据：通过则顺带把健康状态重置为正常，
        //失败则维持失效状态并返回原因（账号已停用，重复停用不会产生额外通知）
        if (!self::checkBaiduidHealth($pid, $bduss)) {
            return array('ok' => false, 'msg' => '该账号 BDUSS 仍处于失效状态，已阻止恢复。请先重新绑定百度账号，再恢复签到。');
        }
        $ownerUid = (int) $account['uid'];
        require_once SYSTEM_ROOT . '/lib/security.php';
        $tables = array_values(array_unique(array_merge(array('tieba'), safe_table_names(option::get('fb_tables')))));
        foreach ($tables as $table) {
            $m->query("UPDATE `" . DB_NAME . "`.`" . DB_PREFIX . $table . "` SET `no` = '0' WHERE `pid` = {$pid} AND `uid` = {$ownerUid}");
        }
        return array('ok' => true, 'msg' => '凭据验证通过，已恢复该账号的贴吧自动签到');
    }
    /**
     * 获取随机 UA
     */
    public static function getUserAgent()
    {
        $uas = [
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/125.0.0.0 Safari/537.36',
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/124.0.0.0 Safari/537.36',
            'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 Safari/605.1.15',
            'Mozilla/5.0 (iPhone; CPU iPhone OS 17_5 like Mac OS X) AppleWebKit/605.1.15 Mobile/15E148 Safari/604.1',
            'Mozilla/5.0 (Linux; Android 14; Pixel 8 Pro) AppleWebKit/537.36 Chrome/125.0.6422.165 Mobile Safari/537.36',
        ];
        return $uas[array_rand($uas)];
    }

    /**
     * 带超时的 curl
     */
    public static function curlExecTimeout($ch, $timeout_ms = 15000)
    {
        curl_setopt($ch, CURLOPT_TIMEOUT_MS, $timeout_ms);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT_MS, 5000);
        $start = microtime(true);
        $result = curl_exec($ch);
        $duration = intval((microtime(true) - $start) * 1000);
        return ['result' => $result, 'duration_ms' => $duration, 'error' => curl_error($ch)];
    }

    /**
     * 清理 30 天前日志
     */
    public static function cleanOldLogs()
    {
        global $m;
        $cutoff = time() - 86400 * 30;
        $m->query("DELETE FROM `" . DB_PREFIX . self::LOG_TABLE . "` WHERE `created_at` < '$cutoff';");
    }

    /**
     * 当日签到统计 (基于默认贴吧表)
     * @return array [signed, error, waiting]
     */
    public static function signSummary($uid = 0)
    {
        global $m;
        $tables = array('tieba');
        $whereUid = '';
        if ((int) $uid > 0) {
            $user = $m->once_fetch_array("SELECT `t` FROM `" . DB_PREFIX . "users` WHERE `id` = " . (int) $uid . " LIMIT 1");
            $candidate = !empty($user['t']) ? strtolower((string) $user['t']) : 'tieba';
            $tables = preg_match('/^[a-z0-9_]{1,64}$/', $candidate) ? array($candidate) : array('tieba');
            $whereUid = ' AND `uid` = ' . (int) $uid;
        }
        require_once SYSTEM_ROOT . '/lib/security.php';
        $tables = array_values(array_unique(array_merge($tables, $uid > 0 ? array() : safe_table_names(option::get('fb_tables')))));
        $day = date('Y-m-d');
        $signed = $error = $waiting = 0;
        foreach ($tables as $tableName) {
            $table = DB_PREFIX . $tableName;
            $row = $m->once_fetch_array("SELECT
                SUM(`no` = 0 AND `latest` = '" . $m->real_escape_string($day) . "' AND `status` = 0) AS signed_count,
                SUM(`no` = 0 AND `latest` = '" . $m->real_escape_string($day) . "' AND `status` != 0) AS error_count,
                SUM(`no` = 0 AND `latest` != '" . $m->real_escape_string($day) . "') AS waiting_count
                FROM `{$table}` WHERE 1=1{$whereUid}");
            $signed += isset($row['signed_count']) ? (int) $row['signed_count'] : 0;
            $error += isset($row['error_count']) ? (int) $row['error_count'] : 0;
            $waiting += isset($row['waiting_count']) ? (int) $row['waiting_count'] : 0;
        }
        return array('signed' => $signed, 'error' => $error, 'waiting' => $waiting);
    }

    /**
     * 连签天数统计（基于 sign_history 表）
     * @param int $uid 0 表示全局
     * @return array {current_streak, max_streak, last_sign_day}
     */
    public static function streak($uid = 0)
    {
        global $m;
        self::ensureHistoryTable();
        $since = time() - 86400 * 366;
        $sql = "SELECT DATE(FROM_UNIXTIME(`created_at`)) AS `day`,
            SUM(`status` = 'success') AS `success_count`
            FROM `" . DB_PREFIX . self::HISTORY_TABLE . "`
            WHERE `created_at` >= ?";
        $params = array($since);
        if ($uid > 0) {
            $sql .= ' AND `uid` = ?';
            $params[] = (int) $uid;
        }
        $sql .= ' GROUP BY `day` HAVING `success_count` > 0 ORDER BY `day` DESC';
        $result = $m->pquery($sql, $params);
        $days = array();
        while ($row = $m->fetch_array($result)) {
            $days[] = $row['day'];
        }
        if (empty($days)) {
            return array('current_streak' => 0, 'max_streak' => 0, 'last_sign_day' => null);
        }
        // 计算当前连签（从最近一天往前找连续）
        $current_streak = 0;
        $today = date('Y-m-d');
        $yesterday = date('Y-m-d', strtotime('-1 day'));
        // 如果今天或昨天有签到记录，开始计数
        if ($days[0] === $today || $days[0] === $yesterday) {
            $current_streak = 1;
            $expected = date('Y-m-d', strtotime($days[0] . ' -1 day'));
            for ($i = 1; $i < count($days); $i++) {
                if ($days[$i] === $expected) {
                    $current_streak++;
                    $expected = date('Y-m-d', strtotime($days[$i] . ' -1 day'));
                } else {
                    break;
                }
            }
        }
        // 计算最长连签
        $max_streak = 1;
        $run = 1;
        for ($i = 1; $i < count($days); $i++) {
            $expected = date('Y-m-d', strtotime($days[$i - 1] . ' -1 day'));
            if ($days[$i] === $expected) {
                $run++;
                $max_streak = max($max_streak, $run);
            } else {
                $run = 1;
            }
        }
        $max_streak = max($max_streak, $current_streak, count($days) > 0 ? 1 : 0);
        return array(
            'current_streak' => $current_streak,
            'max_streak' => $max_streak,
            'last_sign_day' => $days[0],
        );
    }

    /**
     * 签到排行榜（基于 sign_history 表按用户聚合）
     * @param int $limit
     * @param int $uid 0 表示全局（仅本人）
     * @return array [{uid, name, success_count, total_count}]
     */
    public static function ranking($limit = 20, $uid = 0)
    {
        global $m;
        self::ensureHistoryTable();
        $limit = max(1, min(100, (int) $limit));
        $since = time() - 86400 * 30; // 最近 30 天
        $sql = "SELECT h.`uid`, u.`name`,
            SUM(h.`status` = 'success') AS `success_count`,
            COUNT(*) AS `total_count`
            FROM `" . DB_PREFIX . self::HISTORY_TABLE . "` h
            LEFT JOIN `" . DB_PREFIX . "users` u ON h.`uid` = u.`id`
            WHERE h.`created_at` >= ?";
        $params = array($since);
        if ($uid > 0) {
            $sql .= ' AND h.`uid` = ?';
            $params[] = (int) $uid;
        }
        $sql .= ' GROUP BY h.`uid`, u.`name` ORDER BY `success_count` DESC, `total_count` DESC LIMIT ?';
        $params[] = $limit;
        $rows = array();
        $result = $m->pquery($sql, $params);
        while ($row = $m->fetch_array($result)) {
            $rows[] = array(
                'uid' => (int) $row['uid'],
                'name' => $row['name'] ? $row['name'] : 'UID:' . (int) $row['uid'],
                'success_count' => (int) $row['success_count'],
                'total_count' => (int) $row['total_count'],
            );
        }
        return $rows;
    }

    /**
     * 失败原因分布（基于 sign_history.message 聚合 Top N）
     * @param int $uid 0 表示全局
     * @param int $limit
     * @param int $days 统计区间天数，默认 30
     * @return array [{reason, count}]
     */
    public static function failureBreakdown($uid = 0, $limit = 10, $days = 30)
    {
        global $m;
        self::ensureHistoryTable();
        $limit = max(1, min(50, (int) $limit));
        $days = max(1, min(365, (int) $days));
        $since = strtotime('-' . ($days - 1) . ' days midnight');
        $sql = "SELECT COALESCE(NULLIF(`message`, ''), '未知错误') AS `reason`, COUNT(*) AS `count`
            FROM `" . DB_PREFIX . self::HISTORY_TABLE . "`
            WHERE `status` != 'success' AND `created_at` >= ?";
        $params = array($since);
        if ($uid > 0) {
            $sql .= ' AND `uid` = ?';
            $params[] = (int) $uid;
        }
        $sql .= ' GROUP BY `reason` ORDER BY `count` DESC LIMIT ?';
        $params[] = $limit;
        $rows = array();
        $result = $m->pquery($sql, $params);
        while ($row = $m->fetch_array($result)) {
            $rows[] = array(
                'reason' => (string) $row['reason'],
                'count' => (int) $row['count'],
            );
        }
        return $rows;
    }

    /**
     * 指定时间内的签到概览（账号分析页指标卡）
     * @param int $uid
     * @param int $days 统计区间天数
     * @return array {total, success, error, skipped, rate, forums, active_days, avg_per_day}
     */
    public static function overview($uid, $days = 30)
    {
        global $m;
        self::ensureHistoryTable();
        $uid = (int) $uid;
        $days = max(1, min(365, (int) $days));
        $empty = array('total' => 0, 'success' => 0, 'error' => 0, 'skipped' => 0,
            'rate' => 0.0, 'forums' => 0, 'active_days' => 0, 'avg_per_day' => 0.0);
        if ($uid <= 0) {
            return $empty;
        }
        $since = strtotime('-' . ($days - 1) . ' days midnight');
        $row = $m->once_fetch_array("SELECT COUNT(*) AS `total`,
            SUM(`status` = 'success') AS `success`,
            SUM(`status` = 'error') AS `error`,
            COUNT(DISTINCT `tieba`) AS `forums`,
            COUNT(DISTINCT DATE(FROM_UNIXTIME(`created_at`))) AS `active_days`
            FROM `" . DB_PREFIX . self::HISTORY_TABLE . "`
            WHERE `uid` = {$uid} AND `created_at` >= {$since}");
        $total = (int) ($row['total'] ?? 0);
        $success = (int) ($row['success'] ?? 0);
        $error = (int) ($row['error'] ?? 0);
        $result = $empty;
        $result['total'] = $total;
        $result['success'] = $success;
        $result['error'] = $error;
        $result['skipped'] = max(0, $total - $success - $error);
        $result['rate'] = $total > 0 ? round($success / $total * 100, 1) : 0.0;
        $result['forums'] = (int) ($row['forums'] ?? 0);
        $result['active_days'] = (int) ($row['active_days'] ?? 0);
        $result['avg_per_day'] = $days > 0 ? round($total / $days, 1) : 0.0;
        return $result;
    }

    /**
     * 签到时段分布：近 N 天按小时聚合的成功/失败次数（账号分析页）
     * @param int $uid
     * @param int $days 统计区间天数
     * @return array [{hour, success, error, total}] hour 为 0-23，无记录的小时也返回 0
     */
    public static function hourDistribution($uid, $days = 30)
    {
        global $m;
        self::ensureHistoryTable();
        $uid = (int) $uid;
        $days = max(1, min(365, (int) $days));
        $buckets = array();
        for ($h = 0; $h < 24; $h++) {
            $buckets[$h] = array('hour' => $h, 'success' => 0, 'error' => 0, 'total' => 0);
        }
        if ($uid <= 0) {
            return array_values($buckets);
        }
        $since = strtotime('-' . ($days - 1) . ' days midnight');
        $result = $m->pquery("SELECT HOUR(FROM_UNIXTIME(`created_at`)) AS `hour`,
            SUM(`status` = 'success') AS `success`,
            SUM(`status` = 'error') AS `error`,
            COUNT(*) AS `total`
            FROM `" . DB_PREFIX . self::HISTORY_TABLE . "`
            WHERE `uid` = ? AND `created_at` >= ?
            GROUP BY `hour`", array($uid, $since));
        while ($row = $m->fetch_array($result)) {
            $h = (int) $row['hour'];
            if (isset($buckets[$h])) {
                $buckets[$h]['success'] = (int) $row['success'];
                $buckets[$h]['error'] = (int) $row['error'];
                $buckets[$h]['total'] = (int) $row['total'];
            }
        }
        return array_values($buckets);
    }

    /**
     * 账号维度对比：近 N 天每个百度账号（pid）的签到聚合
     * @param int $uid
     * @param int $days 统计区间天数
     * @return array [{pid, name, portrait, success, error, total, rate}]
     */
    public static function accountCompare($uid, $days = 30)
    {
        global $m;
        self::ensureHistoryTable();
        $uid = (int) $uid;
        $days = max(1, min(365, (int) $days));
        if ($uid <= 0) { return array(); }
        $since = strtotime('-' . ($days - 1) . ' days midnight');
        $result = $m->pquery("SELECT h.`pid`, b.`name`, b.`portrait`,
            SUM(h.`status` = 'success') AS `success`,
            SUM(h.`status` = 'error') AS `error`,
            COUNT(*) AS `total`
            FROM `" . DB_PREFIX . self::HISTORY_TABLE . "` h
            LEFT JOIN `" . DB_PREFIX . "baiduid` b ON h.`pid` = b.`id`
            WHERE h.`uid` = ? AND h.`created_at` >= ?
            GROUP BY h.`pid`, b.`name`, b.`portrait`
            ORDER BY `total` DESC", array($uid, $since));
        $rows = array();
        while ($row = $m->fetch_array($result)) {
            $success = (int) $row['success'];
            $total = (int) $row['total'];
            $rows[] = array(
                'pid' => (int) $row['pid'],
                'name' => (string) ($row['name'] ?? ''),
                'portrait' => (string) ($row['portrait'] ?? ''),
                'success' => $success,
                'error' => (int) $row['error'],
                'total' => $total,
                'rate' => $total > 0 ? round($success / $total * 100, 1) : 0.0,
            );
        }
        return $rows;
    }

    /**
     * 当前用户所有百度账号的 BDUSS 健康状态（baiduid_health）
     * @param int $uid
     * @return array 以 pid 为键：{pid, name, status, last_ok, last_fail, fail_count, error_msg, updated_at}
     */
    public static function accountHealth($uid)
    {
        global $m;
        $uid = (int) $uid;
        if ($uid <= 0) { return array(); }
        $result = $m->pquery("SELECT h.`pid`, h.`status`, h.`last_ok`, h.`last_fail`,
            h.`fail_count`, h.`error_msg`, h.`updated_at`
            FROM `" . DB_PREFIX . self::HEALTH_TABLE . "` h
            INNER JOIN `" . DB_PREFIX . "baiduid` b ON h.`pid` = b.`id`
            WHERE b.`uid` = ?", array($uid));
        $rows = array();
        while ($row = $m->fetch_array($result)) {
            $rows[(int) $row['pid']] = array(
                'pid' => (int) $row['pid'],
                'status' => (string) $row['status'],
                'last_ok' => (int) $row['last_ok'],
                'last_fail' => (int) $row['last_fail'],
                'fail_count' => (int) $row['fail_count'],
                'error_msg' => (string) ($row['error_msg'] ?? ''),
                'updated_at' => (int) $row['updated_at'],
            );
        }
        return $rows;
    }

    /**
     * 确保 BDUSS 健康度每日快照表存在（每账号每天一条，取当天最后一次检查结果）
     */
    public static function ensureHealthSnapshotTable()
    {
        static $ready = false;
        if ($ready) {
            return;
        }
        global $m;
        $m->query("CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . self::HEALTH_SNAPSHOT_TABLE . "` (
            `pid` INT UNSIGNED NOT NULL DEFAULT 0,
            `day` CHAR(8) NOT NULL DEFAULT '',
            `ok` TINYINT NOT NULL DEFAULT 0,
            `fail_count` INT UNSIGNED NOT NULL DEFAULT 0,
            `checked_at` INT UNSIGNED NOT NULL,
            PRIMARY KEY (`pid`, `day`),
            KEY `day_checked` (`day`, `checked_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
        $ready = true;
    }

    /**
     * 记录某账号当天的健康快照（健康检查每跑一次覆盖当天记录）
     */
    public static function snapshotBaiduidHealth($pid, $ok, $failCount = 0)
    {
        global $m;
        self::ensureHealthSnapshotTable();
        $m->pquery(
            "REPLACE INTO `" . DB_PREFIX . self::HEALTH_SNAPSHOT_TABLE . "` (`pid`, `day`, `ok`, `fail_count`, `checked_at`) VALUES (?, ?, ?, ?, ?)",
            array((int) $pid, date('Ymd'), $ok ? 1 : 0, max(0, (int) $failCount), time())
        );
    }

    /**
     * 账号健康度每日趋势（正常账号数 / 参与检查账号数）
     * @return array [{date, ok, total, rate}]（空白天已填充，保证趋势线连续）
     */
    public static function healthTrend($uid, $days = 30)
    {
        global $m;
        self::ensureHealthSnapshotTable();
        $uid = (int) $uid;
        $days = max(1, min(365, (int) $days));
        if ($uid <= 0) { return array(); }
        $since = strtotime('-' . ($days - 1) . ' days midnight');
        $result = $m->pquery(
            "SELECT s.`day`,
                SUM(s.`ok` = 1) AS `ok_cnt`,
                COUNT(*) AS `total`
            FROM `" . DB_PREFIX . self::HEALTH_SNAPSHOT_TABLE . "` s
            INNER JOIN `" . DB_PREFIX . "baiduid` b ON s.`pid` = b.`id`
            WHERE b.`uid` = ? AND s.`day` >= ? AND s.`day` <= ?
            GROUP BY s.`day` ORDER BY s.`day` ASC",
            array($uid, date('Ymd', $since), date('Ymd'))
        );
        $map = array();
        while ($row = $m->fetch_array($result)) {
            $map[(string) $row['day']] = $row;
        }
        $rows = array();
        $stamp = $since;
        $end = strtotime('today midnight');
        while ($stamp <= $end) {
            $day = date('Ymd', $stamp);
            if (isset($map[$day])) {
                $ok = (int) $map[$day]['ok_cnt'];
                $total = (int) $map[$day]['total'];
            } else {
                $ok = 0;
                $total = 0;
            }
            $rows[] = array(
                'date'  => date('Y-m-d', $stamp),
                'ok'    => $ok,
                'total' => $total,
                'rate'  => $total > 0 ? round($ok / $total * 100, 1) : 0,
            );
            $stamp = strtotime('+1 day', $stamp); // 用日历日推进，避免 DST 时区重复/跳日
        }
        return $rows;
    }

    /**
     * 帖子监控命中统计（每吧命中数 Top 与每日命中趋势）。
     * 监控表由 ver4_monitor 插件维护；插件未安装/表不存在时 available=false。
     * @return array ['available'=>bool, 'total'=>int, 'top'=>[{forum,hits,replied}], 'trend'=>[{date,hits}]]
     */
    public static function monitorHitStats($uid, $days = 30)
    {
        global $m;
        $uid = (int) $uid;
        $days = max(1, min(365, (int) $days));
        $empty = array('available' => false, 'total' => 0, 'top' => array(), 'trend' => array());
        if ($uid <= 0) {
            return $empty;
        }
        $t = $m->once_fetch_array(
            "SELECT COUNT(*) AS c FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = '" . $m->real_escape_string(DB_PREFIX . 'ver4_monitor_hit') . "'"
        );
        if (empty($t['c'])) {
            return $empty;
        }
        $since = strtotime('-' . ($days - 1) . ' days midnight');
        $totalRow = $m->once_fetch_array(
            "SELECT COUNT(*) AS c FROM `" . DB_PREFIX . "ver4_monitor_hit` WHERE `uid` = " . $uid . " AND `created_at` >= " . (int) $since
        );
        $total = !empty($totalRow['c']) ? (int) $totalRow['c'] : 0;
        $topResult = $m->pquery(
            "SELECT `forum`, COUNT(*) AS `hits`, SUM(`replied` = 1) AS `replied`
            FROM `" . DB_PREFIX . "ver4_monitor_hit`
            WHERE `uid` = ? AND `created_at` >= ?
            GROUP BY `forum` ORDER BY `hits` DESC, `forum` ASC LIMIT 10",
            array($uid, $since)
        );
        $top = array();
        while ($row = $m->fetch_array($topResult)) {
            $top[] = array(
                'forum'   => (string) $row['forum'],
                'hits'    => (int) $row['hits'],
                'replied' => (int) $row['replied'],
            );
        }
        $trendResult = $m->pquery(
            "SELECT DATE(FROM_UNIXTIME(`created_at`)) AS `day`, COUNT(*) AS `hits`
            FROM `" . DB_PREFIX . "ver4_monitor_hit`
            WHERE `uid` = ? AND `created_at` >= ?
            GROUP BY `day` ORDER BY `day` ASC",
            array($uid, $since)
        );
        $trendMap = array();
        while ($row = $m->fetch_array($trendResult)) {
            $trendMap[(string) $row['day']] = (int) $row['hits'];
        }
        $trend = array();
        $stamp = $since;
        $end = strtotime('today midnight');
        while ($stamp <= $end) {
            $date = date('Y-m-d', $stamp);
            $trend[] = array('date' => $date, 'hits' => isset($trendMap[$date]) ? $trendMap[$date] : 0);
            $stamp = strtotime('+1 day', $stamp); // 用日历日推进，避免 DST 时区重复/跳日
        }
        return array('available' => true, 'total' => $total, 'top' => $top, 'trend' => $trend);
    }

    /**
     * 分页查询用户签到日志（带筛选条件）。
     *
     * @param array $filters ['uid'=>, 'pid'=>, 'date_from'=>, 'date_to'=>, 'tieba'=>, 'status'=>]
     * @param int $page     1-based
     * @param int $pageSize 默认 30
     * @return array ['rows'=>[{id,pid,uid,tieba,status,error_code,message,created_at}], 'total'=>, 'page'=>, 'pages'=>, 'page_size'=>]
     */
    public static function logs($filters = array(), $page = 1, $pageSize = 30)
    {
        global $m;
        self::ensureHistoryTable();
        $filters = is_array($filters) ? $filters : array();
        $page = max(1, (int) $page);
        //上限 5000：满足 CSV 导出（api.php / control.php 传 5000），分页浏览不受影响
        $pageSize = max(5, min(5000, (int) $pageSize));

        $where = array('1=1');
        $params = array();

        if (!empty($filters['uid'])) {
            $where[] = '`uid` = ?';
            $params[] = (int) $filters['uid'];
        }
        if (!empty($filters['pid'])) {
            $where[] = '`pid` = ?';
            $params[] = (int) $filters['pid'];
        }
        if (!empty($filters['tieba'])) {
            //先转义 LIKE 通配符再交给参数绑定，用户输入的 % _ 按字面匹配
            $where[] = '`tieba` LIKE ?';
            $params[] = '%' . $m->real_escape_string(addcslashes((string) $filters['tieba'], '%_')) . '%';
        }
        if (!empty($filters['status']) && in_array($filters['status'], array('success', 'error', 'skipped'), true)) {
            $where[] = '`status` = ?';
            $params[] = $filters['status'];
        }
        if (!empty($filters['date_from'])) {
            $ts = strtotime($filters['date_from'] . ' 00:00:00');
            if ($ts > 0) {
                $where[] = '`created_at` >= ?';
                $params[] = $ts;
            }
        }
        if (!empty($filters['date_to'])) {
            $ts = strtotime($filters['date_to'] . ' 23:59:59');
            if ($ts > 0) {
                $where[] = '`created_at` <= ?';
                $params[] = $ts;
            }
        }

        $whereSql = implode(' AND ', $where);

        // 总数
        $countSql = "SELECT COUNT(*) AS c FROM `" . DB_PREFIX . self::HISTORY_TABLE . "` WHERE " . $whereSql;
        $countResult = $m->pquery($countSql, $params);
        $countRow = $m->fetch_array($countResult);
        $total = (int) ($countRow['c'] ?? 0);
        $pages = max(1, (int) ceil($total / $pageSize));
        if ($page > $pages) { $page = $pages; }
        $offset = ($page - 1) * $pageSize;

        $sql = "SELECT `id`, `pid`, `uid`, `tieba`, `status`, `error_code`, `message`, `exp_gained`, `sign_rank`, `forum_level`, `created_at`
            FROM `" . DB_PREFIX . self::HISTORY_TABLE . "`
            WHERE " . $whereSql . "
            ORDER BY `created_at` DESC, `id` DESC
            LIMIT " . (int) $offset . ", " . (int) $pageSize;
        $result = $m->pquery($sql, $params);
        $rows = array();
        while ($row = $m->fetch_array($result)) {
            $rows[] = array(
                'id' => (int) $row['id'],
                'pid' => (int) $row['pid'],
                'uid' => (int) $row['uid'],
                'tieba' => (string) $row['tieba'],
                'status' => (string) $row['status'],
                'error_code' => (int) $row['error_code'],
                'message' => (string) $row['message'],
                'exp_gained' => (int) ($row['exp_gained'] ?? 0),
                'sign_rank' => (int) ($row['sign_rank'] ?? 0),
                'forum_level' => (int) ($row['forum_level'] ?? 0),
                'created_at' => (int) $row['created_at'],
            );
        }
        return array(
            'rows' => $rows,
            'total' => $total,
            'page' => $page,
            'pages' => $pages,
            'page_size' => $pageSize,
        );
    }

    /**
     * 签到趋势：近 N 天每天的成功/失败/总数
     * @param int $days 默认 30，上限 365
     * @param int $uid 0 表示全局
     * @return array [{date, success, error, total, rate}]
     */
    public static function trend($days = 30, $uid = 0)
    {
        global $m;
        self::ensureHistoryTable();
        $days = max(1, min(365, (int) $days));
        $since = strtotime('-' . ($days - 1) . ' days midnight');
        $sql = "SELECT DATE(FROM_UNIXTIME(`created_at`)) AS `day`,
            SUM(`status` = 'success') AS `success`,
            SUM(`status` = 'error')   AS `error`,
            COUNT(*) AS `total`
            FROM `" . DB_PREFIX . self::HISTORY_TABLE . "`
            WHERE `created_at` >= ?";
        $params = array($since);
        if ($uid > 0) {
            $sql .= ' AND `uid` = ?';
            $params[] = (int) $uid;
        }
        $sql .= ' GROUP BY `day` ORDER BY `day` ASC';
        $result = $m->pquery($sql, $params);
        $map = array();
        while ($row = $m->fetch_array($result)) {
            $map[$row['day']] = $row;
        }
        // 填充空白天，保证趋势线连续
        $rows = array();
        $stamp = $since;
        $end = strtotime('today midnight');
        while ($stamp <= $end) {
            $day = date('Y-m-d', $stamp);
            if (isset($map[$day])) {
                $success = (int) $map[$day]['success'];
                $error   = (int) $map[$day]['error'];
                $total   = (int) $map[$day]['total'];
            } else {
                $success = 0;
                $error   = 0;
                $total   = 0;
            }
            $rate = $total > 0 ? round($success / $total * 100, 1) : 0;
            $rows[] = array(
                'date'    => $day,
                'success' => $success,
                'error'   => $error,
                'total'   => $total,
                'rate'    => $rate,
            );
            $stamp = strtotime('+1 day', $stamp); // 用日历日推进，避免 DST 时区重复/跳日
        }
        return $rows;
    }

    /**
     * 每个贴吧的历史成功率统计（用于贴吧健康度看板）
     * @param int $uid
     * @param int $days 默认 30
     * @return array [{tieba, success, error, total, rate, last_status, last_time}]
     */
    public static function tiebaHealth($uid, $days = 30)
    {
        global $m;
        self::ensureHistoryTable();
        $uid = (int) $uid;
        $days = max(1, min(365, (int) $days));
        if ($uid <= 0) { return array(); }
        $since = strtotime('-' . ($days - 1) . ' days midnight');
        // 子查询：每个贴吧的聚合 + 最近一条记录（last_msg 用相关子查询，消息含逗号时 GROUP_CONCAT 会截断）
        $sql = "SELECT t.`tieba`,
            SUM(t.`status` = 'success') AS `success`,
            SUM(t.`status` = 'error')   AS `error`,
            COUNT(*) AS `total`,
            MAX(t.`created_at`) AS `last_time`,
            SUBSTRING_INDEX(GROUP_CONCAT(t.`status` ORDER BY t.`id` DESC), ',', 1) AS `last_status`,
            (SELECT t2.`message` FROM `" . DB_PREFIX . self::HISTORY_TABLE . "` t2
              WHERE t2.`uid` = t.`uid` AND t2.`tieba` = t.`tieba` AND t2.`created_at` >= ?
              ORDER BY t2.`id` DESC LIMIT 1) AS `last_msg`,
            (SELECT t3.`exp_gained` FROM `" . DB_PREFIX . self::HISTORY_TABLE . "` t3
              WHERE t3.`uid` = t.`uid` AND t3.`tieba` = t.`tieba` AND t3.`status` = 'success' AND t3.`created_at` >= ?
              ORDER BY t3.`id` DESC LIMIT 1) AS `last_exp`,
            (SELECT t4.`sign_rank` FROM `" . DB_PREFIX . self::HISTORY_TABLE . "` t4
              WHERE t4.`uid` = t.`uid` AND t4.`tieba` = t.`tieba` AND t4.`status` = 'success' AND t4.`created_at` >= ?
              ORDER BY t4.`id` DESC LIMIT 1) AS `last_rank`,
            (SELECT t5.`forum_level` FROM `" . DB_PREFIX . self::HISTORY_TABLE . "` t5
              WHERE t5.`uid` = t.`uid` AND t5.`tieba` = t.`tieba` AND t5.`status` = 'success' AND t5.`created_at` >= ?
              ORDER BY t5.`id` DESC LIMIT 1) AS `last_level`
            FROM `" . DB_PREFIX . self::HISTORY_TABLE . "` t
            WHERE t.`uid` = ? AND t.`created_at` >= ?
            GROUP BY t.`uid`, t.`tieba`
            ORDER BY `total` DESC";
        $sinceTs = $since;
        $result = $m->pquery($sql, array($sinceTs, $sinceTs, $sinceTs, $sinceTs, $uid, $since));
        $rows = array();
        while ($row = $m->fetch_array($result)) {
            $success = (int) $row['success'];
            $error   = (int) $row['error'];
            $total   = (int) $row['total'];
            $rows[] = array(
                'tieba'       => (string) $row['tieba'],
                'success'     => $success,
                'error'       => $error,
                'total'       => $total,
                'rate'        => $total > 0 ? round($success / $total * 100, 1) : 0,
                'last_status' => (string) $row['last_status'],
                'last_time'   => (int) $row['last_time'],
                'last_msg'    => (string) $row['last_msg'],
                'last_exp'    => (int) ($row['last_exp'] ?? 0),
                'last_rank'   => (int) ($row['last_rank'] ?? 0),
                'last_level'  => (int) ($row['last_level'] ?? 0),
            );
        }
        return $rows;
    }
}

