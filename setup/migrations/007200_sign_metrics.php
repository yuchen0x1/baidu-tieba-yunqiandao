<?php
/**
 * 迁移 007200：签到成长数据与日报支持
 * 1. 为 sign_history 表增加 exp_gained (本次获得经验)、sign_rank (今日签到排名)、forum_level (贴吧等级)
 * 2. 幂等更新，已有列则自动跳过。
 */
return array(
    'version' => '007200',
    'desc' => 'V7.3.0：签到历史表增加成长经验/签到排名/贴吧等级支持',
    'up' => function ($m, $prefix) {
        $columnExists = function ($table, $column) use ($m, $prefix) {
            $t = $m->real_escape_string($prefix . $table);
            $c = $m->real_escape_string($column);
            $row = $m->once_fetch_array("SELECT COUNT(*) AS c FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = '{$t}' AND column_name = '{$c}'");
            return !empty($row['c']);
        };

        if (!$columnExists('sign_history', 'exp_gained')) {
            $m->query("ALTER TABLE `{$prefix}sign_history` ADD COLUMN `exp_gained` INT UNSIGNED NOT NULL DEFAULT 0 AFTER `message`");
        }
        if (!$columnExists('sign_history', 'sign_rank')) {
            $m->query("ALTER TABLE `{$prefix}sign_history` ADD COLUMN `sign_rank` INT UNSIGNED NOT NULL DEFAULT 0 AFTER `exp_gained`");
        }
        if (!$columnExists('sign_history', 'forum_level')) {
            $m->query("ALTER TABLE `{$prefix}sign_history` ADD COLUMN `forum_level` TINYINT UNSIGNED NOT NULL DEFAULT 0 AFTER `sign_rank`");
        }
    },
    'down' => function ($m, $prefix) {
        $columnExists = function ($table, $column) use ($m, $prefix) {
            $t = $m->real_escape_string($prefix . $table);
            $c = $m->real_escape_string($column);
            $row = $m->once_fetch_array("SELECT COUNT(*) AS c FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = '{$t}' AND column_name = '{$c}'");
            return !empty($row['c']);
        };
        if ($columnExists('sign_history', 'exp_gained')) {
            $m->query("ALTER TABLE `{$prefix}sign_history` DROP COLUMN `exp_gained`");
        }
        if ($columnExists('sign_history', 'sign_rank')) {
            $m->query("ALTER TABLE `{$prefix}sign_history` DROP COLUMN `sign_rank`");
        }
        if ($columnExists('sign_history', 'forum_level')) {
            $m->query("ALTER TABLE `{$prefix}sign_history` DROP COLUMN `forum_level`");
        }
    },
);
