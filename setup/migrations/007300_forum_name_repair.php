<?php
/**
 * 迁移 007300：修复历史贴吧名的双重转义/HTML 实体污染
 *
 * 背景：V7.5 之前 scanTiebaByPid() 用 addslashes(htmlspecialchars($name)) 入库，
 * 含 '&' 的吧名会被存成 &amp;，含引号/反斜杠的吧名会带反斜杠。落库值与百度侧
 * 不一致，导致签到失败、健康度统计对不上。
 *
 * 处理：对 sign_history.tieba 与所有贴吧分表的 tieba 列做 html_entity_decode +
 * stripslashes，仅当修复后值通过 normalize_forum_keyword 校验、且不与同表
 * 其他行产生冲突时更新。幂等：已修复/不可修复的行不会被重复处理。
 *
 * down：不回滚（原值已丢失，且回滚会重新引入损坏数据）。
 */
return array(
    'version' => '007300',
    'desc' => 'V7.5：修复历史贴吧名的 HTML 实体与反斜杠双重转义污染',
    'up' => function ($m, $prefix) {
        //贴吧名规范化器：lib/security.php 未必已加载，按需引入
        if (!function_exists('normalize_forum_keyword')) {
            $sec = __DIR__ . '/../../lib/security.php';
            if (is_file($sec)) {
                require_once $sec;
            }
        }
        $normalize = function_exists('normalize_forum_keyword')
            ? 'normalize_forum_keyword'
            : null;

        //收集贴吧分表白名单：base 表 + fb_tables 选项 + users.t
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

        $repaired = 0;
        $skipped = 0;

        // --- 1) 修复贴吧分表的 tieba 列 ---
        foreach ($tables as $tableName) {
            $full = $prefix . $tableName;
            //表存在性检查
            $exists = $m->once_fetch_array("SELECT COUNT(*) AS c FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = '" . $m->real_escape_string($full) . "'");
            if (empty($exists['c'])) {
                continue;
            }
            $rows = $m->query("SELECT `id`, `tieba` FROM `{$full}` WHERE `tieba` LIKE '%&%' OR `tieba` LIKE '%\\\\%' OR `tieba` LIKE '%&amp;%' OR `tieba` LIKE '%&lt;%' OR `tieba` LIKE '%&gt;%' OR `tieba` LIKE '%&#%' LIMIT 5000");
            if ($rows === false) {
                continue;
            }
            while ($r = $m->fetch_array($rows)) {
                $raw = (string) $r['tieba'];
                $fixed = stripslashes($raw);
                $fixed = html_entity_decode($fixed, ENT_QUOTES | ENT_HTML5, 'UTF-8');
                $fixed = trim($fixed);
                //二次去除残留反斜杠（html_entity_decode 不处理反斜杠）
                if (strpos($fixed, '\\') !== false) {
                    $fixed = stripslashes($fixed);
                }
                if ($fixed === $raw) {
                    $skipped++;
                    continue;
                }
                //修复后值必须通过贴吧名白名单，否则保持原样（宁可不修也不写坏数据）
                if ($normalize !== null && call_user_func($normalize, $fixed) === '') {
                    $skipped++;
                    continue;
                }
                //同 pid+uid 下不得产生重复
                $dup = $m->pquery(
                    "SELECT COUNT(*) AS c FROM `{$full}` WHERE `pid` = (SELECT `pid` FROM `{$full}` WHERE `id` = ? LIMIT 1) AND `uid` = (SELECT `uid` FROM `{$full}` WHERE `id` = ? LIMIT 1) AND `tieba` = ? AND `id` != ? LIMIT 1",
                    array((int) $r['id'], (int) $r['id'], $fixed, (int) $r['id'])
                );
                $dupRow = $dup !== false ? $m->fetch_array($dup) : array('c' => 0);
                if (!empty($dupRow['c'])) {
                    $skipped++;
                    continue;
                }
                $m->pquery("UPDATE `{$full}` SET `tieba` = ? WHERE `id` = ?", array($fixed, (int) $r['id']));
                $repaired++;
            }
        }

        // --- 2) 修复 sign_history.tieba ---
        $histFull = $prefix . 'sign_history';
        $histExists = $m->once_fetch_array("SELECT COUNT(*) AS c FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = '" . $m->real_escape_string($histFull) . "'");
        if (!empty($histExists['c'])) {
            $rows = $m->query("SELECT `id`, `tieba` FROM `{$histFull}` WHERE `tieba` LIKE '%&%' OR `tieba` LIKE '%\\\\%' OR `tieba` LIKE '%&amp;%' OR `tieba` LIKE '%&#%' LIMIT 5000");
            if ($rows !== false) {
                while ($r = $m->fetch_array($rows)) {
                    $raw = (string) $r['tieba'];
                    $fixed = stripslashes($raw);
                    $fixed = html_entity_decode($fixed, ENT_QUOTES | ENT_HTML5, 'UTF-8');
                    $fixed = trim($fixed);
                    if (strpos($fixed, '\\') !== false) {
                        $fixed = stripslashes($fixed);
                    }
                    if ($fixed === $raw) {
                        $skipped++;
                        continue;
                    }
                    if ($normalize !== null && call_user_func($normalize, $fixed) === '') {
                        $skipped++;
                        continue;
                    }
                    $m->pquery("UPDATE `{$histFull}` SET `tieba` = ? WHERE `id` = ?", array($fixed, (int) $r['id']));
                    $repaired++;
                }
            }
        }

        return '贴吧名修复完成：更新 ' . $repaired . ' 行，跳过 ' . $skipped . ' 行（无需修复/修复后不合法/会重复）';
    },
    'down' => function ($m, $prefix) {
        //原值已丢失，且回滚会重新引入损坏数据，不提供回滚
    },
);
