<?php
/**
 * 迁移 001001：V5.13 调度引擎 + 可观测性 + API 增强
 * 说明：本迁移幂等，与 setup/update5.12to5.13.php 效果一致，可重复执行。
 */
return array(
    'version' => '001001',
    'desc' => 'V5.13：cron 表调度增强列 + task_runs + api_rate_limits + 新增选项',
    'up' => function ($m, $prefix) {
        $columnExists = function ($table, $column) use ($m, $prefix) {
            $t = $m->real_escape_string($prefix . $table);
            $c = $m->real_escape_string($column);
            $row = $m->once_fetch_array("SELECT COUNT(*) AS c FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = '{$t}' AND column_name = '{$c}'");
            return !empty($row['c']);
        };
        // 1) cron 表新增调度增强字段
        $cronColumns = array(
            'enable'          => "TINYINT(1) UNSIGNED NOT NULL DEFAULT 1",
            'nextrun'         => "INT UNSIGNED NOT NULL DEFAULT 0",
            'retry'           => "TINYINT(3) UNSIGNED NOT NULL DEFAULT 0",
            'max_retry'       => "TINYINT(3) UNSIGNED NOT NULL DEFAULT 2",
            'last_status'     => "VARCHAR(20) NOT NULL DEFAULT ''",
            'last_duration'   => "INT UNSIGNED NOT NULL DEFAULT 0",
            'running'         => "TINYINT(1) UNSIGNED NOT NULL DEFAULT 0",
            'running_at'      => "INT UNSIGNED NOT NULL DEFAULT 0",
        );
        foreach ($cronColumns as $col => $def) {
            if (!$columnExists('cron', $col)) {
                $m->query("ALTER TABLE `{$prefix}cron` ADD COLUMN `{$col}` {$def} AFTER `log`");
            }
        }
        // 2) 存量回填
        $m->query("UPDATE `{$prefix}cron` SET `enable` = IF(`no` = 1, 0, 1) WHERE `enable` = 1 AND `no` = 1");
        $m->query("UPDATE `{$prefix}cron` SET `nextrun` = IF(`freq` > 0 AND `lastdo` > 0, `lastdo` + `freq`, 0) WHERE `nextrun` = 0");

        // 3) 任务运行历史表
        $m->query("CREATE TABLE IF NOT EXISTS `{$prefix}task_runs` (
            `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            `name` VARCHAR(40) NOT NULL DEFAULT '',
            `status` VARCHAR(20) NOT NULL DEFAULT '',
            `message` TEXT,
            `duration_ms` INT UNSIGNED NOT NULL DEFAULT 0,
            `started_at` INT UNSIGNED NOT NULL DEFAULT 0,
            `finished_at` INT UNSIGNED NOT NULL DEFAULT 0,
            PRIMARY KEY (`id`),
            KEY `name` (`name`),
            KEY `status` (`status`),
            KEY `started_at` (`started_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

        // 4) API 限流表
        $m->query("CREATE TABLE IF NOT EXISTS `{$prefix}api_rate_limits` (
            `token_hash` VARCHAR(64) NOT NULL DEFAULT '',
            `window_start` INT UNSIGNED NOT NULL DEFAULT 0,
            `count` INT UNSIGNED NOT NULL DEFAULT 0,
            PRIMARY KEY (`token_hash`, `window_start`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

        // 5) 新增选项（存在则跳过）
        $newOptions = array(
            'cron_max_retry'      => '2',
            'cron_retry_backoff'  => '300',
            'cron_lock_timeout'   => '600',
            'cron_notify_on_fail' => '1',
            'metrics_token'       => '',
            'api_rate_limit'      => '60',
            'webhook_enable'      => '0',
            'webhook_url'         => '',
            'webhook_secret'      => '',
            'webhook_events'      => '',
        );
        foreach ($newOptions as $name => $value) {
            $k = $m->real_escape_string($name);
            $row = $m->once_fetch_array("SELECT COUNT(*) AS c FROM `{$prefix}options` WHERE `name` = '{$k}'");
            if (empty($row['c'])) {
                option::add($name, $value);
            }
        }
    },
    'down' => function ($m, $prefix) {
        // 保守回滚：仅删除新增表，不删 cron 新列（避免误删运行数据）
        $m->query("DROP TABLE IF EXISTS `{$prefix}task_runs`");
        $m->query("DROP TABLE IF EXISTS `{$prefix}api_rate_limits`");
    },
);
