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
     */
    public static function auditLogin($success)
    {
        global $m;
        $table = self::table();
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $table)) {
            return;
        }
        $username = '';
        $ip = self::escapeAuditValue(self::clientIp(), 45);
        $ua = self::escapeAuditValue(isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '', 255);
        $successValue = $success ? 1 : 0;
        $m->query("INSERT INTO `{$table}` (`username`, `ip`, `ua`, `success`, `ts`) VALUES ('{$username}', '{$ip}', '{$ua}', {$successValue}, UNIX_TIMESTAMP());");
    }

    private static function escapeAuditValue($value, $limit)
    {
        global $m;
        return $m->real_escape_string(substr((string) $value, 0, (int) $limit));
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
        $username = $m->real_escape_string(substr((string) $username, 0, 100));
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
