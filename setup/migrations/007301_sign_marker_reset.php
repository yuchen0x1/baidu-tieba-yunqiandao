<?php
/**
 * 迁移 007301：签到标记 latest 从日号切换为完整日期
 *
 * 背景：V7.5 之前 tieba.latest 存 date('d')（日号 01-31），跨月同号会被
 * 误判为"今日已签"而漏签（1 月 15 日签过、2 月 15 日恢复的账号全天跳过）。
 * V7.5 起改存 date('Y-m-d')，列宽 varchar(10) 已足够容纳 10 字符日期。
 *
 * 处理：把所有贴吧分表的 latest 重置为 '0'，强制升级后重签一次。
 * 不回填"本月已签"（无从考证真实签到日期，宁可重签也不漏签）。
 *
 * 幂等：已为 '0' 的行不重复处理，重复执行无副作用。
 */
return array(
    'version' => '007301',
    'desc' => 'V7.5：签到标记 latest 从日号改为完整日期（强制重签一次）',
    'up' => function ($m, $prefix) {
        $tables = array('tieba');
        $optRow = $m->once_fetch_array("SELECT `value` FROM `{$prefix}options` WHERE `name` = 'fb_tables' LIMIT 1");
        if (!empty($optRow['value'])) {
            $decoded = @unserialize($optRow['value']);
            if (is_array($decoded)) {
                foreach ($decoded as $t) {
                    $t = strtolower(trim((string) $t));
                    if (preg_match('/^[a-z0-9_]{1,64}$/', $t) && !in_array($t, $tables, true)) {
                        $tables[] = $t;
                    }
                }
            }
        }
        $userTables = $m->query("SELECT DISTINCT `t` FROM `{$prefix}users` WHERE `t` IS NOT NULL AND `t` != ''");
        if ($userTables !== false) {
            while ($ur = $m->fetch_array($userTables)) {
                $t = strtolower(trim((string) $ur['t']));
                if (preg_match('/^[a-z0-9_]{1,64}$/', $t) && !in_array($t, $tables, true)) {
                    $tables[] = $t;
                }
            }
        }

        $total = 0;
        foreach ($tables as $tableName) {
            $full = $prefix . $tableName;
            $exists = $m->once_fetch_array("SELECT COUNT(*) AS c FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = '" . $m->real_escape_string($full) . "'");
            if (empty($exists['c'])) {
                continue;
            }
            $result = $m->pquery("UPDATE `{$full}` SET `latest` = '0' WHERE `latest` != '0'", array());
            $total += (int) $m->affected_rows();
        }
        return '签到标记重置完成：' . $total . ' 行 latest 已重置为 0，下一轮签到将重新覆盖所有贴吧';
    },
    'down' => function ($m, $prefix) {
        //原值已丢失，且回滚会重新引入跨月漏签，不提供回滚
    },
);
