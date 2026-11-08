<?php

if (!defined('SYSTEM_ROOT')) {
    die('Insufficient Permissions');
}

/**
 * 贴吧消息中心（V6.1 Phase 1）
 * 通过贴吧客户端接口拉取「回复我的 / @我的」消息，去重入库，供站内展示与后续推送。
 */
class MessageCenter
{
    const TABLE = 'messages';
    const FEED_RN = 20;

    /**
     * 惰性建表
     */
    public static function ensureTable()
    {
        global $m;
        $m->query("CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . self::TABLE . "` (
            `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            `uid` INT UNSIGNED NOT NULL,
            `pid` INT UNSIGNED NOT NULL DEFAULT 0,
            `type` VARCHAR(10) NOT NULL DEFAULT 'reply',
            `msg_key` VARCHAR(64) NOT NULL,
            `title` VARCHAR(255) NOT NULL DEFAULT '',
            `content` TEXT NULL,
            `sender_name` VARCHAR(64) NOT NULL DEFAULT '',
            `sender_portrait` VARCHAR(128) NOT NULL DEFAULT '',
            `forum` VARCHAR(64) NOT NULL DEFAULT '',
            `url` VARCHAR(255) NOT NULL DEFAULT '',
            `is_read` TINYINT UNSIGNED NOT NULL DEFAULT 0,
            `created_at` INT UNSIGNED NOT NULL DEFAULT 0,
            `pulled_at` INT UNSIGNED NOT NULL DEFAULT 0,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uk_uid_key` (`uid`, `msg_key`),
            KEY `uid_read` (`uid`, `is_read`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
        // 自愈：确保拉取计划任务已注册，但默认 enable=0。
        // 百度的 replyme/atme 接口当前对纯 BDUSS 返回 needlogin=1（见 docs/feature-roadmap.md Phase 1），
        // 保持启用只会让每个账号每小时白打一次被拦截的请求并污染任务日志。
        // 管理员确认数据源可用后，可到「计划任务」页手动启用 system_message_pull。
        $m->query("INSERT IGNORE INTO `" . DB_PREFIX . "cron` (`name`, `orde`, `file`, `no`, `desc`, `freq`, `lastdo`, `nextrun`, `enable`)
            VALUES ('system_message_pull', 0, 'lib/cron_system_message_pull.php', 0, '贴吧消息中心拉取（回复/@我聚合）——数据源恢复前默认停用', 3600, 0, 1, 0);");
    }

    /**
     * 拉取单个百度账号的「回复我的 / @我的」消息
     * @param int $pid 百度账号 PID
     * @param int $uid 站内用户 ID
     * @param string $bduss 已解密的 BDUSS
     * @return array{reply:int, at:int, new:int, error:string}
     */
    public static function pullForBaiduid($pid, $uid, $bduss)
    {
        global $m;
        self::ensureTable();
        $pid = (int) $pid;
        $uid = (int) $uid;
        $stats = array('reply' => 0, 'at' => 0, 'new' => 0, 'error' => '');
        foreach (array('reply' => 'c/u/feed/replyme', 'at' => 'c/u/feed/atme') as $type => $apiPath) {
            $data = array('rn' => (string) self::FEED_RN);
            misc::addTiebaSign($data, false);
            $ch = new wcurl('https://c.tieba.baidu.com/' . $apiPath, array(
                'User-Agent: bdtb for Android ' . misc::clientVersion()
            ));
            misc::applyProxy($ch, $pid);
            $ch->addcookie(array('BDUSS' => $bduss));
            $resp = json_decode((string) $ch->post($data), true);
            $ch->close();
            if (!is_array($resp) || !isset($resp['error_code']) || (string) $resp['error_code'] !== '0') {
                $stats['error'] = '接口返回异常（' . (isset($resp['error']) ? (string) $resp['error'] : '未知') . '）';
                continue;
            }
            $feed = isset($resp['feed']) && is_array($resp['feed']) ? $resp['feed'] : array();
            foreach ($feed as $item) {
                if (!is_array($item) || !isset($item['id'])) {
                    continue;
                }
                $msgKey = md5($type . '|' . (string) $item['id']);
                $threadId = isset($item['thread_id']) ? (string) $item['thread_id'] : '';
                $title = '';
                foreach (array('thread_name', 'title') as $tk) {
                    if (isset($item[$tk]) && (string) $item[$tk] !== '') {
                        $title = (string) $item[$tk];
                        break;
                    }
                }
                $content = '';
                foreach (array('reply', 'content') as $ck) {
                    if (isset($item[$ck]) && (string) $item[$ck] !== '') {
                        $content = (string) $item[$ck];
                        break;
                    }
                }
                $senderName = '';
                $senderPortrait = '';
                if (isset($item['author']) && is_array($item['author'])) {
                    $senderName = isset($item['author']['name']) ? (string) $item['author']['name'] : '';
                    $senderPortrait = isset($item['author']['portrait']) ? (string) $item['author']['portrait'] : '';
                }
                $forum = isset($item['fname']) ? (string) $item['fname'] : '';
                $url = $threadId !== '' ? 'https://tieba.baidu.com/p/' . $threadId : '';
                if ($url !== '' && isset($item['reply_id']) && (string) $item['reply_id'] !== '') {
                    $url .= '?pid=' . (string) $item['reply_id'];
                }
                $createdAt = isset($item['time']) && (int) $item['time'] > 0 ? (int) $item['time'] : time();
                $st = $m->pquery("INSERT IGNORE INTO `" . DB_PREFIX . self::TABLE . "`
                    (`uid`, `pid`, `type`, `msg_key`, `title`, `content`, `sender_name`, `sender_portrait`, `forum`, `url`, `is_read`, `created_at`, `pulled_at`)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, ?, ?)", array(
                    $uid,
                    $pid,
                    $type,
                    $msgKey,
                    mb_substr($title, 0, 250, 'UTF-8'),
                    $content,
                    mb_substr($senderName, 0, 60, 'UTF-8'),
                    mb_substr($senderPortrait, 0, 120, 'UTF-8'),
                    mb_substr($forum, 0, 60, 'UTF-8'),
                    mb_substr($url, 0, 250, 'UTF-8'),
                    $createdAt,
                    time(),
                ));
                if ($st && $m->affected_rows > 0) {
                    $stats[$type]++;
                    $stats['new']++;
                }
            }
            usleep(300000);
        }
        return $stats;
    }

    /**
     * 拉取全部（或指定用户）绑定账号的消息
     * @return string 摘要文本（供计划任务日志使用）
     */
    public static function pullAll($uid = 0)
    {
        global $m;
        self::ensureTable();
        $uid = (int) $uid;
        $sql = "SELECT `id`, `uid`, `bduss` FROM `" . DB_PREFIX . "baiduid`";
        if ($uid > 0) {
            $sql .= " WHERE `uid` = " . $uid;
        }
        $ex = $m->query($sql);
        $checked = 0;
        $failed = 0;
        $totalNew = 0;
        while ($row = $m->fetch_array($ex)) {
            $result = self::pullForBaiduid((int) $row['id'], (int) $row['uid'], P::decrypt($row['bduss']));
            $checked++;
            $totalNew += $result['new'];
            if ($result['error'] !== '') {
                $failed++;
            }
            usleep(200000);
        }
        $now = date('Y-m-d H:i:s');
        return "[{$now}] 消息拉取完成：共检查 {$checked} 个账号，新增 {$totalNew} 条消息" . ($failed > 0 ? "，{$failed} 个账号拉取异常" : '');
    }

    /**
     * 消息列表（分页）
     * @return array{rows: array, total: int, pages: int, page: int}
     */
    public static function items($uid, $type = '', $unreadOnly = false, $page = 1, $perPage = 20)
    {
        global $m;
        self::ensureTable();
        $uid = (int) $uid;
        $page = max(1, (int) $page);
        $perPage = max(1, (int) $perPage);
        $where = "WHERE `uid` = ?";
        $whereParams = array($uid);
        if ($type === 'reply' || $type === 'at') {
            $where .= " AND `type` = ?";
            $whereParams[] = $type === 'reply' ? 'reply' : 'at';
        }
        if ($unreadOnly) {
            $where .= " AND `is_read` = 0";
        }
        $totalRow = $m->fetch_array($m->pquery("SELECT COUNT(*) AS total FROM `" . DB_PREFIX . self::TABLE . "` {$where}", $whereParams));
        $total = (int) $totalRow['total'];
        $pages = max(1, (int) ceil($total / $perPage));
        $page = min($page, $pages);
        $offset = ($page - 1) * $perPage;
        $ex = $m->pquery("SELECT * FROM `" . DB_PREFIX . self::TABLE . "` {$where} ORDER BY `created_at` DESC, `id` DESC LIMIT ? OFFSET ?", array_merge($whereParams, array($perPage, $offset)));
        $rows = array();
        while ($row = $m->fetch_array($ex)) {
            $rows[] = $row;
        }
        return array('rows' => $rows, 'total' => $total, 'pages' => $pages, 'page' => $page);
    }

    /**
     * 未读消息数
     */
    public static function unreadCount($uid)
    {
        global $m;
        self::ensureTable();
        $row = $m->once_fetch_array("SELECT COUNT(*) AS total FROM `" . DB_PREFIX . self::TABLE . "` WHERE `uid` = " . intval($uid) . " AND `is_read` = 0");
        return (int) $row['total'];
    }

    /**
     * 标记单条已读
     */
    public static function markRead($id, $uid)
    {
        global $m;
        self::ensureTable();
        $id = (int) $id;
        $uid = (int) $uid;
        if ($id <= 0 || $uid <= 0) {
            return false;
        }
        $m->pquery("UPDATE `" . DB_PREFIX . self::TABLE . "` SET `is_read` = 1 WHERE `id` = ? AND `uid` = ?", array($id, $uid));
        return $m->affected_rows() > 0;
    }

    /**
     * 全部标为已读
     */
    public static function markAllRead($uid)
    {
        global $m;
        self::ensureTable();
        $uid = (int) $uid;
        if ($uid <= 0) {
            return false;
        }
        $m->pquery("UPDATE `" . DB_PREFIX . self::TABLE . "` SET `is_read` = 1 WHERE `uid` = ? AND `is_read` = 0", array($uid));
        return $m->affected_rows() > 0;
    }
}
