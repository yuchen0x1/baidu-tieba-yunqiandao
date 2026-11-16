<?php
/**
 * 迁移 007600：为计划任务锁增加租约所有者令牌。
 *
 * 旧实现只按任务名清理 running 标记。任务超过 TTL 被新进程接管后，
 * 旧进程的 finally 可能把新进程的锁清掉，造成并发执行。所有者令牌让
 * 释放和续租都只能修改自己领取的那一份租约。
 */
return array(
    'version' => '007600',
    'desc' => 'V7.7：计划任务锁增加租约所有者令牌，防止过期进程误释放新锁',
    'up' => function ($m, $prefix) {
        $table = $prefix . 'cron';
        $tableEscaped = $m->real_escape_string($table);
        $exists = $m->once_fetch_array("SELECT COUNT(*) AS c FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = '{$tableEscaped}'");
        if (empty($exists['c'])) {
            throw new RuntimeException('计划任务表不存在，无法增加租约所有者列');
        }
        $column = $m->real_escape_string('running_owner');
        $hasColumn = $m->once_fetch_array("SELECT COUNT(*) AS c FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = '{$tableEscaped}' AND column_name = '{$column}'");
        if (empty($hasColumn['c'])) {
            $m->query("ALTER TABLE `{$table}` ADD COLUMN `running_owner` VARCHAR(64) NOT NULL DEFAULT '' AFTER `running_at`");
        }
        return '计划任务租约所有者列已就绪';
    },
    'down' => function ($m, $prefix) {
        $table = $prefix . 'cron';
        $tableEscaped = $m->real_escape_string($table);
        $exists = $m->once_fetch_array("SELECT COUNT(*) AS c FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = '{$tableEscaped}'");
        if (empty($exists['c'])) {
            return;
        }
        $hasColumn = $m->once_fetch_array("SELECT COUNT(*) AS c FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = '{$tableEscaped}' AND column_name = 'running_owner'");
        if (!empty($hasColumn['c'])) {
            $m->query("ALTER TABLE `{$table}` DROP COLUMN `running_owner`");
        }
    },
);
