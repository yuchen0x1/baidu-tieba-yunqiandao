<?php
/**
 * 迁移 007303：补缺失的复合索引与唯一约束
 *
 * 背景：
 * - tieba 表只有 uid、latest 单列索引，但按账号签到/列表查询大量使用 WHERE pid=?
 * - users_options 只有 uid、name 单列索引，实际查询是 WHERE uid=? AND name=?
 * - sign_history 有 (uid, created_at)，但 tiebaHealth() 的相关子查询是
 *   WHERE uid=? AND tieba=? AND created_at>=?，缺 (uid, tieba, created_at)
 * - login_audit 查询是 username + ip + ts，缺复合索引
 *
 * 幂等：已存在的索引跳过。
 */
return array(
    'version' => '007303',
    'desc' => 'V7.5：补 tieba/users_options/sign_history/login_audit 缺失索引',
    'up' => function ($m, $prefix) {
        $indexExists = function ($table, $indexName) use ($m, $prefix) {
            $t = $m->real_escape_string($prefix . $table);
            $i = $m->real_escape_string($indexName);
            $row = $m->once_fetch_array("SELECT COUNT(*) AS c FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = '{$t}' AND index_name = '{$i}'");
            return !empty($row['c']);
        };

        if (!$indexExists('tieba', 'pid_no_latest')) {
            $m->query("ALTER TABLE `{$prefix}tieba` ADD INDEX `pid_no_latest` (`pid`, `no`, `latest`)");
        }
        if (!$indexExists('sign_history', 'uid_tieba_created')) {
            $m->query("ALTER TABLE `{$prefix}sign_history` ADD INDEX `uid_tieba_created` (`uid`, `tieba`, `created_at`)");
        }
        if (!$indexExists('login_audit', 'username_ip_ts')) {
            $m->query("ALTER TABLE `{$prefix}login_audit` ADD INDEX `username_ip_ts` (`username`, `ip`, `ts`)");
        }
        //users_options(uid, name) 唯一：先去重，再加唯一索引
        if (!$indexExists('users_options', 'uid_name')) {
            //去重：保留 id 最大的行
            $m->query("DELETE t1 FROM `{$prefix}users_options` t1 INNER JOIN `{$prefix}users_options` t2
                ON t1.`uid` = t2.`uid` AND t1.`name` = t2.`name` AND t1.`id` < t2.`id`");
            $m->query("ALTER TABLE `{$prefix}users_options` ADD UNIQUE INDEX `uid_name` (`uid`, `name`)");
        }
    },
    'down' => function ($m, $prefix) {
        $m->query("ALTER TABLE `{$prefix}tieba` DROP INDEX `pid_no_latest`");
        $m->query("ALTER TABLE `{$prefix}sign_history` DROP INDEX `uid_tieba_created`");
        $m->query("ALTER TABLE `{$prefix}login_audit` DROP INDEX `username_ip_ts`");
        $m->query("ALTER TABLE `{$prefix}users_options` DROP INDEX `uid_name`");
    },
);
