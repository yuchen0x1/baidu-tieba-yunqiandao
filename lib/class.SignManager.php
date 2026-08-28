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

        $tbs = misc::getTbs(0, $bduss);
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
            return false;
        }
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
    public static function signSummary()
    {
        global $m;
        $table = DB_PREFIX . 'tieba';
        $day = date('d');
        $signed = $m->once_fetch_array("SELECT COUNT(*) AS c FROM `{$table}` WHERE `no` = 0 AND `latest` = '{$day}' AND `status` = 0;");
        $error = $m->once_fetch_array("SELECT COUNT(*) AS c FROM `{$table}` WHERE `no` = 0 AND `latest` = '{$day}' AND `status` != 0;");
        $waiting = $m->once_fetch_array("SELECT COUNT(*) AS c FROM `{$table}` WHERE `no` = 0 AND `latest` != '{$day}';");
        return array(
            'signed' => isset($signed['c']) ? (int) $signed['c'] : 0,
            'error' => isset($error['c']) ? (int) $error['c'] : 0,
            'waiting' => isset($waiting['c']) ? (int) $waiting['c'] : 0,
        );
    }
}

