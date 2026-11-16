<?php

if (!defined('SYSTEM_ROOT')) {
    die('Insufficient Permissions');
}

/**
 * 原子消费一个 API 限流窗口额度。
 *
 * 先尝试带上限的 UPDATE，再处理首次出现的 token 行。两条语句都在数据库
 * 行锁下完成，避免旧的 SELECT→UPDATE 流程在并发请求下超发额度。
 *
 * @return bool|null true=本次获得额度，false=已达到上限，null=存储失败
 */
function api_rate_limit_consume($db, $tokenHash, $windowStart, $limit)
{
    if (!is_object($db) || $limit <= 0) {
        return true;
    }
    $table = '`' . str_replace('`', '``', DB_PREFIX . 'api_rate_limits') . '`';
    $tokenHash = (string) $tokenHash;
    $windowStart = (int) $windowStart;
    $limit = max(1, (int) $limit);

    $updated = $db->pquery(
        "UPDATE {$table} SET `count` = `count` + 1 WHERE `token_hash` = ? AND `window_start` = ? AND `count` < ?",
        array($tokenHash, $windowStart, $limit)
    );
    if ($updated === false) {
        return null;
    }
    if ($db->affected_rows() === 1) {
        return true;
    }

    // 行不存在时插入；并发插入由唯一键收敛到同一个原子 UPDATE。
    $inserted = $db->pquery(
        "INSERT INTO {$table} (`token_hash`, `window_start`, `count`) VALUES (?, ?, 1)
         ON DUPLICATE KEY UPDATE `count` = IF(`count` < ?, `count` + 1, `count`)",
        array($tokenHash, $windowStart, $limit)
    );
    if ($inserted === false) {
        return null;
    }
    // INSERT 影响 1 行，重复键且递增通常影响 2 行；达到上限时为 0。
    return $db->affected_rows() > 0;
}
