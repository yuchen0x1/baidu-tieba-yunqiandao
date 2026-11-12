<?php
/**
 * 迁移 007500：为贴吧签到行增加短期 claim，防止并发调度重复执行。
 *
 * cron、常驻循环与开放 API 都可能同时触发签到。旧流程先 SELECT 再请求百度，
 * 两个进程会拿到同一行并重复调用接口。claim_owner/claimed_at 让 UPDATE 成为
 * 原子领取动作；进程异常退出后由 TTL 自动回收。
 */
return array(
    'version' => '007500',
    'desc' => 'V7.5：签到行增加短期 claim，防止并发重复签到',
    'up' => function ($m, $prefix) {
        $tables = array('tieba');
        $addTable = function ($name) use (&$tables) {
            $name = strtolower(trim((string) $name));
            if (preg_match('/^[a-z0-9_]{1,64}$/', $name) && !in_array($name, $tables, true)) {
                $tables[] = $name;
            }
        };

        $opt = $m->pquery("SELECT `value` FROM `{$prefix}options` WHERE `name` = ? LIMIT 1", array('fb_tables'));
        $optRow = $opt !== false ? $m->fetch_array($opt) : false;
        if (!empty($optRow['value'])) {
            $decoded = @unserialize($optRow['value']);
            if (is_array($decoded)) {
                foreach ($decoded as $table) {
                    $addTable($table);
                }
            }
        }
        $users = $m->query("SELECT DISTINCT `t` FROM `{$prefix}users` WHERE `t` IS NOT NULL AND `t` != ''");
        if ($users !== false) {
            while ($row = $m->fetch_array($users)) {
                $addTable($row['t']);
            }
        }

        $tableExists = function ($table) use ($m, $prefix) {
            $full = $m->real_escape_string($prefix . $table);
            $row = $m->once_fetch_array("SELECT COUNT(*) AS c FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = '{$full}'");
            return !empty($row['c']);
        };
        $columnExists = function ($table, $column) use ($m, $prefix) {
            $full = $m->real_escape_string($prefix . $table);
            $col = $m->real_escape_string($column);
            $row = $m->once_fetch_array("SELECT COUNT(*) AS c FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = '{$full}' AND column_name = '{$col}'");
            return !empty($row['c']);
        };
        $indexExists = function ($table, $index) use ($m, $prefix) {
            $full = $m->real_escape_string($prefix . $table);
            $name = $m->real_escape_string($index);
            $row = $m->once_fetch_array("SELECT COUNT(*) AS c FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = '{$full}' AND index_name = '{$name}'");
            return !empty($row['c']);
        };

        $changed = 0;
        foreach ($tables as $table) {
            if (!$tableExists($table)) {
                continue;
            }
            $full = $prefix . $table;
            if (!$columnExists($table, 'claim_owner')) {
                $m->query("ALTER TABLE `{$full}` ADD COLUMN `claim_owner` VARCHAR(80) NULL DEFAULT NULL");
                $changed++;
            }
            if (!$columnExists($table, 'claimed_at')) {
                $m->query("ALTER TABLE `{$full}` ADD COLUMN `claimed_at` INT UNSIGNED NULL DEFAULT NULL");
                $changed++;
            }
            if (!$indexExists($table, 'claim_expiry')) {
                $m->query("ALTER TABLE `{$full}` ADD INDEX `claim_expiry` (`claimed_at`)");
                $changed++;
            }
        }
        return '签到 claim 结构完成：处理 ' . count($tables) . ' 个候选表，新增 ' . $changed . ' 项结构';
    },
    'down' => function ($m, $prefix) {
        $tables = array('tieba');
        $opt = $m->pquery("SELECT `value` FROM `{$prefix}options` WHERE `name` = ? LIMIT 1", array('fb_tables'));
        $optRow = $opt !== false ? $m->fetch_array($opt) : false;
        if (!empty($optRow['value'])) {
            $decoded = @unserialize($optRow['value']);
            if (is_array($decoded)) {
                foreach ($decoded as $table) {
                    $table = strtolower(trim((string) $table));
                    if (preg_match('/^[a-z0-9_]{1,64}$/', $table)) {
                        $tables[] = $table;
                    }
                }
            }
        }
        $users = $m->query("SELECT DISTINCT `t` FROM `{$prefix}users` WHERE `t` IS NOT NULL AND `t` != ''");
        if ($users !== false) {
            while ($row = $m->fetch_array($users)) {
                $name = strtolower(trim((string) $row['t']));
                if (preg_match('/^[a-z0-9_]{1,64}$/', $name)) {
                    $tables[] = $name;
                }
            }
        }
        foreach (array_values(array_unique($tables)) as $table) {
            $full = $prefix . $table;
            $exists = $m->once_fetch_array("SELECT COUNT(*) AS c FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = '" . $m->real_escape_string($full) . "'");
            if (empty($exists['c'])) {
                continue;
            }
            $idx = $m->once_fetch_array("SELECT COUNT(*) AS c FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = '" . $m->real_escape_string($full) . "' AND index_name = 'claim_expiry'");
            if (!empty($idx['c'])) {
                $m->query("ALTER TABLE `{$full}` DROP INDEX `claim_expiry`");
            }
            foreach (array('claim_owner', 'claimed_at') as $column) {
                $col = $m->once_fetch_array("SELECT COUNT(*) AS c FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = '" . $m->real_escape_string($full) . "' AND column_name = '{$column}'");
                if (!empty($col['c'])) {
                    $m->query("ALTER TABLE `{$full}` DROP COLUMN `{$column}`");
                }
            }
        }
    },
);
