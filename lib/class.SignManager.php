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

    /**
     * 为升级脚本和首次运行创建所有管理数据表。
     */
    public static function ensureTables()
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
    }

    /**
     * 写签到日志
     */
    public static function log($pid, $action, $status, $message, $duration_ms = 0)
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
            KEY `pid` (`pid`),
            KEY `action` (`action`),
            KEY `status` (`status`),
            KEY `created_at` (`created_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
        $msg = $m->real_escape_string(substr($message, 0, 500));
        $m->query("INSERT INTO `" . DB_PREFIX . self::LOG_TABLE . "`
            (`pid`, `action`, `status`, `message`, `duration_ms`, `created_at`)
            VALUES ('" . intval($pid) . "', '" . $m->real_escape_string($action) . "',
             '" . $m->real_escape_string($status) . "', '" . $msg . "',
             '" . intval($duration_ms) . "', UNIX_TIMESTAMP());");
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
            $m->query("UPDATE `" . DB_NAME . "`.`" . DB_PREFIX . $table . "` SET `no` = '1' WHERE `pid` = " . intval($pid) . " AND `no` = '0';");
            $affected += $m->affected_rows();
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
    public static function logTiebaResult($pid, $uid, $tieba, $status, $errorCode = 0, $message = '')
    {
        global $m;
        self::ensureHistoryTable();
        $status = (string) $status;
        $message = substr((string) $message, 0, 500);
        $m->query("INSERT INTO `" . DB_PREFIX . self::HISTORY_TABLE . "`
            (`pid`, `uid`, `tieba`, `status`, `error_code`, `message`, `created_at`) VALUES ("
            . (int) $pid . ", " . (int) $uid . ", '" . $m->real_escape_string($tieba) . "', '"
            . $m->real_escape_string($status) . "', " . (int) $errorCode . ", '"
            . $m->real_escape_string($message) . "', UNIX_TIMESTAMP())");
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
     */
    public static function restoreBaiduid($pid, $uid = 0)
    {
        global $m;
        $pid = (int) $pid;
        $uid = (int) $uid;
        $ownerWhere = $uid > 0 ? ' AND `uid` = ' . $uid : '';
        $account = $m->once_fetch_array("SELECT `id`, `uid` FROM `" . DB_PREFIX . "baiduid` WHERE `id` = {$pid}{$ownerWhere} LIMIT 1");
        if (empty($account)) {
            return false;
        }
        $ownerUid = (int) $account['uid'];
        require_once SYSTEM_ROOT . '/lib/security.php';
        $tables = array_values(array_unique(array_merge(array('tieba'), safe_table_names(option::get('fb_tables')))));
        foreach ($tables as $table) {
            $m->query("UPDATE `" . DB_NAME . "`.`" . DB_PREFIX . $table . "` SET `no` = '0' WHERE `pid` = {$pid} AND `uid` = {$ownerUid}");
        }
        $m->query("UPDATE `" . DB_PREFIX . self::HEALTH_TABLE . "` SET `status` = 'unknown', `fail_count` = 0, `error_msg` = NULL, `updated_at` = UNIX_TIMESTAMP() WHERE `pid` = {$pid}");
        return true;
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
        $day = date('d');
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
}

