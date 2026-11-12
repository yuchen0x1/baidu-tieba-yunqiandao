<?php

if (!defined('SYSTEM_ROOT')) {
    die('Insufficient Permissions');
}

/**
 * 登录防护 - 失败限流 + 审计日志
 * 同一账号在 15 分钟内失败超过 MAX_FAIL 次, 锁定 LOCK 秒。
 */
class LoginGuard
{
    const TABLE = 'login_audit';
    const MAX_FAIL = 5;
    const WINDOW = 900;
    const LOCK = 900;
    /** 同一 IP 在窗口内的失败上限（防多账号喷洒） */
    const IP_MAX_FAIL = 20;
    /** IP 级锁定时长（秒） */
    const IP_LOCK = 900;
    /** 2FA 失败上限（同一 UID 在窗口内） */
    const TOTP_MAX_FAIL = 5;
    /** 2FA 失败窗口（秒） */
    const TOTP_WINDOW = 600;
    /** 2FA 锁定时长（秒） */
    const TOTP_LOCK = 900;
    /** 2FA 失败计数的选项键前缀 */
    const TOTP_OPTION_PREFIX = 'totp_fail_';

    /**
     * 确保审计表存在并返回表名
     */
    public static function table()
    {
        global $m;
        $table = DB_PREFIX . self::TABLE;
        $m->query("CREATE TABLE IF NOT EXISTS `{$table}` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `username` VARCHAR(100) NOT NULL DEFAULT '',
            `ip` VARCHAR(45) NOT NULL DEFAULT '',
            `ua` VARCHAR(255) NOT NULL DEFAULT '',
            `success` TINYINT(1) NOT NULL DEFAULT 0,
            `ts` INT UNSIGNED NOT NULL,
            PRIMARY KEY (`id`),
            KEY `username` (`username`),
            KEY `ip` (`ip`),
            KEY `ts` (`ts`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
        return $table;
    }

    public static function clientIp()
    {
        return isset($_SERVER['REMOTE_ADDR']) ? substr((string) $_SERVER['REMOTE_ADDR'], 0, 45) : '';
    }

    /**
     * 记录一次登录尝试
     * @param bool $success 是否登录成功
     * @param string $username 账号标识（调用方传入，函数内不再读取请求）
     */
    public static function auditLogin($success, $username = '')
    {
        global $m;
        $table = self::table();
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $table)) {
            return;
        }
        //规范化（白名单字符集 + 截断）后经预处理语句写入，请求输入不参与 SQL 拼接
        $username = self::normalizeUsername($username);
        $ip = substr(self::clientIp(), 0, 45);
        $ua = substr((string) (isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : ''), 0, 255);
        $successValue = $success ? 1 : 0;
        $m->pquery("INSERT INTO `{$table}` (`username`, `ip`, `ua`, `success`, `ts`) VALUES (?, ?, ?, ?, UNIX_TIMESTAMP());", array($username, $ip, $ua, $successValue));
    }

    public static function normalizeUsername($username)
    {
        if (!is_scalar($username)) {
            return '';
        }
        return substr(trim(preg_replace('/[^a-zA-Z0-9@._-]/', '', (string) $username)), 0, 100);
    }

    /**
     * 返回剩余锁定秒数; 0 表示未锁定
     */
    public static function isLocked($username, $ip = null)
    {
        global $m;
        $table = self::table();
        if ($ip === null) {
            $ip = self::clientIp();
        }
        $username = $m->real_escape_string(self::normalizeUsername($username));
        $ip = $m->real_escape_string(substr((string) $ip, 0, 45));
        $since = time() - self::WINDOW;
        $row = $m->once_fetch_array("SELECT COUNT(*) AS c, MAX(ts) AS last FROM `{$table}` WHERE `success` = 0 AND `username` = '{$username}' AND `ip` = '{$ip}' AND `ts` > {$since};");
        if (empty($row) || (int) $row['c'] < self::MAX_FAIL) {
            return 0;
        }
        $unlockAt = (int) $row['last'] + self::LOCK;
        return max(0, $unlockAt - time());
    }

    /**
     * IP 级限流：同一 IP 在窗口内失败超过 IP_MAX_FAIL 次即锁定 IP_LOCK 秒。
     * 防止攻击者换用户名绕过账号级限流（喷洒多账号）。
     * @return int 剩余锁定秒数; 0 表示未锁定
     */
    public static function isIpLocked($ip = null)
    {
        global $m;
        $table = self::table();
        if ($ip === null) {
            $ip = self::clientIp();
        }
        $ip = $m->real_escape_string(substr((string) $ip, 0, 45));
        $since = time() - self::WINDOW;
        $row = $m->once_fetch_array("SELECT COUNT(*) AS c, MAX(ts) AS last FROM `{$table}` WHERE `success` = 0 AND `ip` = '{$ip}' AND `ts` > {$since};");
        if (empty($row) || (int) $row['c'] < self::IP_MAX_FAIL) {
            return 0;
        }
        $unlockAt = (int) $row['last'] + self::IP_LOCK;
        return max(0, $unlockAt - time());
    }

    /**
     * 记录一次 2FA 失败（状态存 options 表，键 totap_fail_<uid>）。
     * 10 分钟窗口内同一 UID 失败 ≥ 5 次即锁定 15 分钟。
     */
    public static function record2faFailure($uid)
    {
        $uid = (int) $uid;
        if ($uid <= 0) {
            return;
        }
        $key = self::TOTP_OPTION_PREFIX . $uid;
        $stored = (string) option::get($key);
        $data = $stored !== '' ? json_decode($stored, true) : null;
        if (!is_array($data) || !isset($data['window_start']) || (time() - (int) $data['window_start']) > self::TOTP_WINDOW) {
            $data = array('count' => 0, 'window_start' => time());
        }
        $data['count'] = (int) $data['count'] + 1;
        option::set($key, json_encode($data));
    }

    /**
     * 判断 2FA 是否被锁定。
     * @return int 剩余锁定秒数; 0 表示未锁定
     */
    public static function is2faLocked($uid)
    {
        $uid = (int) $uid;
        if ($uid <= 0) {
            return 0;
        }
        $key = self::TOTP_OPTION_PREFIX . $uid;
        $stored = (string) option::get($key);
        $data = $stored !== '' ? json_decode($stored, true) : null;
        if (!is_array($data) || empty($data['count']) || empty($data['window_start'])) {
            return 0;
        }
        $windowAge = time() - (int) $data['window_start'];
        if ($windowAge > self::TOTP_WINDOW) {
            //窗口已过期，重置计数
            option::set($key, '');
            return 0;
        }
        if ((int) $data['count'] < self::TOTP_MAX_FAIL) {
            return 0;
        }
        //锁定：从最后一次失败时刻起算
        $unlockAt = (int) $data['window_start'] + self::TOTP_WINDOW + self::TOTP_LOCK;
        return max(0, $unlockAt - time());
    }

    /**
     * 2FA 校验成功后调用：清零失败计数。
     */
    public static function clear2faFailures($uid)
    {
        $uid = (int) $uid;
        if ($uid <= 0) {
            return;
        }
        option::set(self::TOTP_OPTION_PREFIX . $uid, '');
    }

    /**
     * 最近登录审计记录
     */
    public static function recent($limit = 50)
    {
        global $m;
        $table = self::table();
        $limit = min(200, max(1, (int) $limit));
        $rows = array();
        $rs = $m->query("SELECT * FROM `{$table}` ORDER BY `id` DESC LIMIT {$limit};");
        while ($r = $m->fetch_array($rs)) {
            $rows[] = $r;
        }
        return $rows;
    }
}
