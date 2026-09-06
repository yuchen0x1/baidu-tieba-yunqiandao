<?php
/**
 * 迁移 007000：V7.0 升级基线
 * 前置要求：数据库已升级到 6.6（由 Migrator::precheck 强制）。
 * 本迁移不删除任何数据：
 *   1) 校验 6.6 基线核心表齐全，缺失即失败中止（不猜、不跳过）；
 *   2) 幂等补齐调度结构（cron 调度列、task_runs、api_rate_limits）与既有系统选项/任务，
 *      兼容经旧脚本链升级、schema_migrations 为空的 6.6 存量库。
 */
return array(
    'version' => '007000',
    'desc' => 'V7.0：升级基线校验与调度结构修复（幂等，无破坏性变更）',
    'up' => function ($m, $prefix) {
        $tableExists = function ($table) use ($m, $prefix) {
            $t = $m->real_escape_string($prefix . $table);
            $row = $m->once_fetch_array("SELECT COUNT(*) AS c FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = '{$t}'");
            return !empty($row['c']);
        };
        $columnExists = function ($table, $column) use ($m, $prefix) {
            $t = $m->real_escape_string($prefix . $table);
            $c = $m->real_escape_string($column);
            $row = $m->once_fetch_array("SELECT COUNT(*) AS c FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = '{$t}' AND column_name = '{$c}'");
            return !empty($row['c']);
        };

        // 1) 基线校验：6.6 必备核心表
        $requiredTables = array('baiduid', 'cron', 'options', 'plugins', 'tieba', 'users', 'users_options', 'baiduid_health', 'cron_log_batch', 'sign_history');
        $missing = array();
        foreach ($requiredTables as $table) {
            if (!$tableExists($table)) {
                $missing[] = $prefix . $table;
            }
        }
        if (!empty($missing)) {
            throw new RuntimeException('数据库缺少 6.6 基线核心表：' . implode(', ', $missing) . '。请先恢复完整备份，不要在结构不完整时强行升级');
        }
        if (!$columnExists('users', 'api_token_hash')) {
            throw new RuntimeException('users.api_token_hash 列缺失，数据库结构与 6.6 基线不符，请先完成 6.6 升级链');
        }

        // 2) 幂等补齐调度增强列（等价 001001，兼容空 schema_migrations 的存量 6.6 库）
        $cronColumns = array(
            'enable'          => 'TINYINT(1) UNSIGNED NOT NULL DEFAULT 1',
            'nextrun'         => 'INT UNSIGNED NOT NULL DEFAULT 0',
            'retry'           => 'TINYINT(3) UNSIGNED NOT NULL DEFAULT 0',
            'max_retry'       => 'TINYINT(3) UNSIGNED NOT NULL DEFAULT 2',
            'last_status'     => "VARCHAR(20) NOT NULL DEFAULT ''",
            'last_duration'   => 'INT UNSIGNED NOT NULL DEFAULT 0',
            'running'         => 'TINYINT(1) UNSIGNED NOT NULL DEFAULT 0',
            'running_at'      => 'INT UNSIGNED NOT NULL DEFAULT 0',
        );
        foreach ($cronColumns as $col => $def) {
            if (!$columnExists('cron', $col)) {
                $m->query("ALTER TABLE `{$prefix}cron` ADD COLUMN `{$col}` {$def} AFTER `log`");
            }
        }
        $m->query("UPDATE `{$prefix}cron` SET `enable` = IF(`no` = 1, 0, 1) WHERE `enable` = 1 AND `no` = 1");
        $m->query("UPDATE `{$prefix}cron` SET `nextrun` = IF(`freq` > 0 AND `lastdo` > 0, `lastdo` + `freq`, 0) WHERE `nextrun` = 0");

        // 3) 调度/限流表
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
        $m->query("CREATE TABLE IF NOT EXISTS `{$prefix}api_rate_limits` (
            `token_hash` VARCHAR(64) NOT NULL DEFAULT '',
            `window_start` INT UNSIGNED NOT NULL DEFAULT 0,
            `count` INT UNSIGNED NOT NULL DEFAULT 0,
            PRIMARY KEY (`token_hash`, `window_start`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

        // 4) 系统选项与清理/周报任务（已存在则跳过，等价旧 update 脚本）
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
            'cleanup_enable'             => '1',
            'cleanup_sign_history_days'  => '180',
            'cleanup_task_runs_days'     => '90',
            'cleanup_cron_log_days'      => '30',
            'cleanup_plugin_log_days'    => '90',
            'cleanup_api_rate_days'      => '7',
        );
        foreach ($newOptions as $name => $value) {
            $k = $m->real_escape_string($name);
            $row = $m->once_fetch_array("SELECT COUNT(*) AS c FROM `{$prefix}options` WHERE `name` = '{$k}'");
            if (empty($row['c'])) {
                $m->pquery("INSERT IGNORE INTO `{$prefix}options` (`name`, `value`) VALUES (?, ?)", array($name, $value));
            }
        }
        $m->query("INSERT IGNORE INTO `{$prefix}cron` (`name`, `orde`, `file`, `no`, `desc`, `freq`, `lastdo`, `log`) VALUES
            ('system_cleanup', '10', 'lib/cron_system_cleanup.php', '0', '每日按保留天数清理签到历史/任务运行历史/插件日志等过期数据', '86400', '0', ''),
            ('system_weekreport', '11', 'lib/cron_system_weekreport.php', '0', '每周按用户配置推送签到周报（用户个人通知渠道）', '86400', '0', '')");
    },
    'down' => function ($m, $prefix) {
        // 基线迁移无破坏性回滚：仅回退 core_version 由运行方决定，这里保持空实现
    },
);
