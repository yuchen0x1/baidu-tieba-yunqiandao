<?php

if (!defined('SYSTEM_ROOT')) { die('Insufficient Permissions'); }

/**
 * 站内通知中心
 *
 * 与 Notify（全局推送）和 Reminder（用户级邮件提醒）不同，Notice 是站内消息：
 *  - 存数据库 user_notices 表
 *  - 在导航栏显示未读计数
 *  - 用户可在「站内通知」页查看/标记已读/删除
 *
 * 调用时机：
 *  - SignManager 签到失败时 Notice::create($uid, 'sign_fail', ...)
 *  - Achievement::checkAndUnlock 解锁新成就时 Notice::create($uid, 'achievement', ...)
 *  - BDUSS 健康检查失效时 Notice::create($uid, 'baiduid_fail', ...)
 *  - 任意业务点通过 doAction('notice_create', $data) 触发
 */
class Notice
{
    const TABLE = 'user_notices';

    // 通知类型
    const TYPE_SIGN_FAIL    = 'sign_fail';
    const TYPE_ACHIEVEMENT  = 'achievement';
    const TYPE_BAIDUID_FAIL = 'baiduid_fail';
    const TYPE_SYSTEM       = 'system';
    const TYPE_DAILY        = 'daily_summary';

    /**
     * 建表（幂等）。
     */
    public static function ensureTable()
    {
        static $ready = false;
        if ($ready) { return; }
        global $m;
        $m->query("CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . self::TABLE . "` (
            `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            `uid` INT UNSIGNED NOT NULL DEFAULT 0,
            `type` VARCHAR(32) NOT NULL DEFAULT 'system',
            `title` VARCHAR(200) NOT NULL DEFAULT '',
            `content` TEXT,
            `link` VARCHAR(500) NOT NULL DEFAULT '',
            `is_read` TINYINT UNSIGNED NOT NULL DEFAULT 0,
            `created_at` INT UNSIGNED NOT NULL DEFAULT 0,
            PRIMARY KEY (`id`),
            KEY `uid` (`uid`),
            KEY `uid_read` (`uid`, `is_read`),
            KEY `type` (`type`),
            KEY `created_at` (`created_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
        $ready = true;
    }

    /**
     * 创建一条通知。
     * @param int $uid
     * @param string $type  通知类型（见 TYPE_* 常量）
     * @param string $title 标题（<=200 字符）
     * @param string $content 详细内容
     * @param string $link 点击跳转链接（相对路径如 index.php?mod=xxx）
     * @return int|false 插入 id 或 false
     */
    public static function create($uid, $type, $title, $content = '', $link = '')
    {
        global $m;
        self::ensureTable();
        $uid = (int) $uid;
        if ($uid <= 0) { return false; }
        $type = substr((string) $type, 0, 32);
        $title = mb_substr((string) $title, 0, 200, 'UTF-8');
        $content = (string) $content;
        $link = substr((string) $link, 0, 500);
        $now = time();
        $sql = "INSERT INTO `" . DB_PREFIX . self::TABLE . "` (`uid`, `type`, `title`, `content`, `link`, `is_read`, `created_at`) VALUES (?, ?, ?, ?, ?, 0, ?)";
        $m->pquery($sql, array($uid, $type, $title, $content, $link, $now));
        $newId = $m->insert_id();
        return $newId ? (int) $newId : false;
    }

    /**
     * 查询通知列表。
     * @param int $uid
     * @param bool $unreadOnly true 只返回未读
     * @param int $page
     * @param int $pageSize
     * @return array ['rows'=>[], 'total'=>int, 'page'=>int, 'pages'=>int]
     */
    public static function items($uid, $unreadOnly = false, $page = 1, $pageSize = 20)
    {
        global $m;
        self::ensureTable();
        $uid = (int) $uid;
        $page = max(1, (int) $page);
        $pageSize = max(5, min(100, (int) $pageSize));
        $where = "WHERE `uid` = ?";
        $params = array($uid);
        if ($unreadOnly) {
            $where .= " AND `is_read` = 0";
        }
        $countSql = "SELECT COUNT(*) AS c FROM `" . DB_PREFIX . self::TABLE . "` $where";
        $countResult = $m->pquery($countSql, $params);
        $countRow = $m->fetch_array($countResult);
        $total = (int) ($countRow['c'] ?? 0);
        $pages = $pageSize > 0 ? (int) ceil($total / $pageSize) : 1;
        $offset = ($page - 1) * $pageSize;
        $sql = "SELECT `id`, `type`, `title`, `content`, `link`, `is_read`, `created_at`
            FROM `" . DB_PREFIX . self::TABLE . "` $where
            ORDER BY `created_at` DESC, `id` DESC
            LIMIT $offset, $pageSize";
        $result = $m->pquery($sql, $params);
        $rows = array();
        while ($row = $m->fetch_array($result)) {
            $rows[] = array(
                'id'         => (int) $row['id'],
                'type'       => (string) $row['type'],
                'title'      => (string) $row['title'],
                'content'    => (string) $row['content'],
                'link'       => (string) $row['link'],
                'is_read'    => (int) $row['is_read'],
                'created_at' => (int) $row['created_at'],
            );
        }
        return array(
            'rows'      => $rows,
            'total'     => $total,
            'page'      => $page,
            'pages'     => $pages,
            'page_size' => $pageSize,
        );
    }

    /**
     * 未读计数（供导航栏徽章使用）。
     */
    public static function unreadCount($uid)
    {
        global $m;
        self::ensureTable();
        $uid = (int) $uid;
        if ($uid <= 0) { return 0; }
        $row = $m->once_fetch_array("SELECT COUNT(*) AS c FROM `" . DB_PREFIX . self::TABLE . "` WHERE `uid` = {$uid} AND `is_read` = 0");
        return (int) ($row['c'] ?? 0);
    }

    /**
     * 标记单条已读。
     */
    public static function markRead($id, $uid)
    {
        global $m;
        self::ensureTable();
        $id = (int) $id;
        $uid = (int) $uid;
        if ($id <= 0 || $uid <= 0) { return false; }
        $m->pquery("UPDATE `" . DB_PREFIX . self::TABLE . "` SET `is_read` = 1 WHERE `id` = ? AND `uid` = ?", array($id, $uid));
        return $m->affected_rows() > 0;
    }

    /**
     * 全部已读。
     */
    public static function markAllRead($uid)
    {
        global $m;
        self::ensureTable();
        $uid = (int) $uid;
        if ($uid <= 0) { return false; }
        $m->pquery("UPDATE `" . DB_PREFIX . self::TABLE . "` SET `is_read` = 1 WHERE `uid` = ? AND `is_read` = 0", array($uid));
        return $m->affected_rows() > 0;
    }

    /**
     * 删除单条通知。
     */
    public static function delete($id, $uid)
    {
        global $m;
        self::ensureTable();
        $id = (int) $id;
        $uid = (int) $uid;
        if ($id <= 0 || $uid <= 0) { return false; }
        $m->pquery("DELETE FROM `" . DB_PREFIX . self::TABLE . "` WHERE `id` = ? AND `uid` = ?", array($id, $uid));
        return $m->affected_rows() > 0;
    }

    /**
     * 清理超过 N 天的已读通知（供 cron 调用）。
     */
    public static function cleanOld($days = 90)
    {
        global $m;
        self::ensureTable();
        $days = max(7, (int) $days);
        $cutoff = time() - 86400 * $days;
        $m->pquery("DELETE FROM `" . DB_PREFIX . self::TABLE . "` WHERE `is_read` = 1 AND `created_at` < ?", array($cutoff));
        return $m->affected_rows();
    }

    /**
     * 类型 → 标签 + 图标 映射（供模板使用）。
     */
    public static function typeMeta($type)
    {
        $map = array(
            self::TYPE_SIGN_FAIL    => array('label' => '签到失败', 'icon' => 'i-alert', 'color' => 'danger'),
            self::TYPE_ACHIEVEMENT  => array('label' => '成就解锁', 'icon' => 'i-award', 'color' => 'brand'),
            self::TYPE_BAIDUID_FAIL => array('label' => '账号失效', 'icon' => 'i-alert', 'color' => 'warning'),
            self::TYPE_SYSTEM       => array('label' => '系统', 'icon' => 'i-info', 'color' => 'info'),
            self::TYPE_DAILY        => array('label' => '每日摘要', 'icon' => 'i-calendar', 'color' => 'success'),
        );
        return isset($map[$type]) ? $map[$type] : array('label' => '通知', 'icon' => 'i-bell', 'color' => 'info');
    }
}
