<?php

if (!defined('SYSTEM_ROOT')) {
    die('Insufficient Permissions');
}

require_once SYSTEM_ROOT . '/lib/class.notify.php';

/**
 * 帖子监控计划任务（按用户配置执行）
 * 每个百度账号归属站点用户（baiduid.uid），扫描该用户配置的目标贴吧最新主题帖，
 * 标题命中关键词（留空=全部新帖）且未记录过时：写入命中表 + 推送提醒
 * （优先用户个人通知渠道 ver4_notify_*，未配置时回退站点渠道）。
 * 帖子列表接口 c/f/frs/page（tiebaclient 签名，实测可用）。
 */

function ver4_monitor_log($msg)
{
    cron::aset('ver4_monitor_do', array('log' => '[' . date('Y-m-d H:i:s') . ']' . $msg));
}

function ver4_monitor_ensure_table()
{
    static $ready = false;
    if ($ready) {
        return;
    }
    global $m;
    $m->query("CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "ver4_monitor_hit` (
        `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        `uid` INT UNSIGNED NOT NULL DEFAULT 0,
        `pid` INT UNSIGNED NOT NULL DEFAULT 0,
        `forum` VARCHAR(60) NOT NULL DEFAULT '',
        `kw` VARCHAR(60) NOT NULL DEFAULT '',
        `tid` BIGINT UNSIGNED NOT NULL DEFAULT 0,
        `title` VARCHAR(255) NOT NULL DEFAULT '',
        `notified` TINYINT NOT NULL DEFAULT 0,
        `created_at` INT UNSIGNED NOT NULL,
        PRIMARY KEY (`id`),
        UNIQUE KEY `uid_tid` (`uid`, `tid`),
        KEY `uid_created` (`uid`, `created_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
    //旧版本表结构补齐 notified 列（实时推送已发送的记 1，汇总模式下未推送的记 0）
    $m->addColumn('ver4_monitor_hit', 'notified', 'tinyint', 'NOT NULL DEFAULT 1');
    $ready = true;
}

/**
 * 拉取贴吧第一页最新主题帖
 * @return array|false
 */
function ver4_monitor_get_threads($bduss, $kw, $pid)
{
    $url = 'https://c.tieba.baidu.com/c/f/frs/page';
    if (!wcurl::isAllowedPublicUrl($url)) {
        return false;
    }
    $data = array(
        'BDUSS' => $bduss,
        'kw' => $kw,
        'pn' => 1,
        'rn' => 30,
    );
    misc::addTiebaSign($data, false);
    $ch = new wcurl($url);
    misc::applyProxy($ch, $pid);
    $ch->addCookie(array('BDUSS' => $bduss));
    $resp = json_decode($ch->post($data), true);
    if (!is_array($resp) || empty($resp['thread_list'])) {
        return false;
    }
    $out = array();
    foreach ($resp['thread_list'] as $t) {
        if (!empty($t['is_ad'])) {
            continue;
        }
        $tid = isset($t['id']) ? (int) $t['id'] : 0;
        if ($tid > 0) {
            $out[] = array(
                'tid' => $tid,
                'title' => isset($t['title']) ? (string) $t['title'] : '',
            );
        }
    }
    return $out;
}

/** 某用户是否已记录过某帖 */
function ver4_monitor_hit_exists($uid, $tid)
{
    global $m;
    $result = $m->pquery("SELECT COUNT(*) AS c FROM `" . DB_PREFIX . "ver4_monitor_hit` WHERE `uid` = ? AND `tid` = ?", array($uid, $tid));
    if ($result === false) {
        return true; //查询失败时宁可跳过，避免重复推送
    }
    $row = $result->fetch_array();
    return !empty($row['c']);
}

/** 记录一次命中（$notified：实时推送模式记 1；每日汇总模式记 0，待汇总后置 1） */
function ver4_monitor_insert_hit($uid, $pid, $forum, $kw, $tid, $title, $notified)
{
    global $m;
    $m->pquery(
        "INSERT IGNORE INTO `" . DB_PREFIX . "ver4_monitor_hit` (`uid`, `pid`, `forum`, `kw`, `tid`, `title`, `notified`, `created_at`) VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
        array((int) $uid, (int) $pid, (string) $forum, (string) $kw, (int) $tid, mb_substr((string) $title, 0, 200), (int) $notified, time())
    );
}

function cron_ver4_monitor_do()
{
    if (function_exists('set_time_limit')) {
        @set_time_limit(600);
    }
    global $m;
    ver4_monitor_ensure_table();

    //按归属用户分组，逐用户处理
    $rs = $m->query("SELECT `id`, `uid`, `bduss` FROM `" . DB_PREFIX . "baiduid` ORDER BY `id` ASC");
    $byUid = array();
    while ($acc = $m->fetch_array($rs)) {
        $byUid[(int) $acc['uid']][] = $acc;
    }
    foreach ($byUid as $uid => $accounts) {
        if (option::uget('ver4_monitor_enable', $uid) !== '1') {
            continue;
        }
        $forumsRaw = (string) option::uget('ver4_monitor_kws', $uid);
        $forums = array_values(array_filter(array_map('trim', explode("\n", $forumsRaw)), 'strlen'));
        if (empty($forums)) {
            continue;
        }
        $wordsRaw = (string) option::uget('ver4_monitor_words', $uid);
        $words = array_values(array_filter(array_map('trim', explode("\n", $wordsRaw)), 'strlen'));
        $doNotify = option::uget('ver4_monitor_notify', $uid) !== '0';
        $pushMode = option::uget('ver4_monitor_mode', $uid) === 'digest' ? 'digest' : 'realtime';
        $digestHour = min(23, max(0, (int) ((option::uget('ver4_monitor_digest_hour', $uid) !== null && option::uget('ver4_monitor_digest_hour', $uid) !== '') ? option::uget('ver4_monitor_digest_hour', $uid) : 21)));

        //每日汇总模式：到汇总小时点，把当天未推送的命中合并成一条摘要推送
        if ($pushMode === 'digest' && $doNotify && (int) date('G') === $digestHour) {
            $dayStart = strtotime(date('Y-m-d') . ' 00:00:00');
            $pending = $m->pquery("SELECT `forum`, `kw`, `tid`, `title` FROM `" . DB_PREFIX . "ver4_monitor_hit` WHERE `uid` = ? AND `notified` = 0 AND `created_at` >= ? ORDER BY `id` ASC LIMIT 30", array($uid, $dayStart));
            $digestLines = array();
            if ($pending !== false) {
                while ($pRow = $pending->fetch_array()) {
                    $digestLines[] = '【' . $pRow['forum'] . '吧】' . mb_substr((string) $pRow['title'], 0, 50) . "\nhttps://tieba.baidu.com/p/" . (int) $pRow['tid'];
                }
            }
            if (!empty($digestLines)) {
                Notify::sendForUser($uid, '帖子监控 · 今日汇总（' . count($digestLines) . ' 条命中）', implode("\n\n", $digestLines), 'monitor');
                $m->pquery("UPDATE `" . DB_PREFIX . "ver4_monitor_hit` SET `notified` = 1 WHERE `uid` = ? AND `notified` = 0 AND `created_at` >= ?", array($uid, $dayStart));
                ver4_monitor_log("用户 {$uid} 今日汇总推送 " . count($digestLines) . ' 条');
            }
        }

        //用该用户的第一个可用账号身份扫描
        $acc = $accounts[0];
        $pid = (int) $acc['id'];
        $bduss = P::decrypt($acc['bduss']);
        if (empty($bduss)) {
            continue;
        }
        $totalHits = 0;
        $lines = array();
        foreach ($forums as $forum) {
            if ($totalHits >= 10) {
                break; //单轮单用户最多记录/推送 10 条，防止刷屏
            }
            $threads = ver4_monitor_get_threads($bduss, $forum, $pid);
            if ($threads === false) {
                continue;
            }
            foreach ($threads as $t) {
                if ($totalHits >= 10) {
                    break;
                }
                $title = $t['title'];
                $excluded = false;
                foreach ($excludeWords as $ew) {
                    if ($ew !== '' && mb_stripos($title, $ew) !== false) {
                        $excluded = true;
                        break;
                    }
                }
                if ($excluded) {
                    continue;
                }
                $hitWord = '';
                if (empty($words)) {
                    $hitWord = '';
                } else {
                    foreach ($words as $w) {
                        if ($w !== '' && mb_stripos($title, $w) !== false) {
                            $hitWord = $w;
                            break;
                        }
                    }
                    if ($hitWord === '') {
                        continue; //未命中任何关键词
                    }
                }
                $tid = (int) $t['tid'];
                if (ver4_monitor_hit_exists($uid, $tid)) {
                    continue;
                }
                //实时模式立即推送（notified=1）；汇总模式先记录（notified=0），到汇总点合并推送
                ver4_monitor_insert_hit($uid, $pid, $forum, $hitWord, $tid, $title, $pushMode === 'realtime' ? 1 : 0);
                $totalHits++;
                if ($pushMode === 'realtime') {
                    $lines[] = '【' . $forum . '吧】' . $title . "\nhttps://tieba.baidu.com/p/" . $tid . ($hitWord !== '' ? "\n命中关键词：{$hitWord}" : '');
                }
                usleep(500 * 1000);
            }
            usleep(800 * 1000);
        }
        if ($totalHits > 0) {
            ver4_monitor_log("用户 {$uid} 命中 {$totalHits} 条新帖");
            if ($doNotify && $pushMode === 'realtime') {
                //优先用户个人通知渠道；未配置时回退站点渠道
                Notify::sendForUser($uid, '帖子监控提醒（' . $totalHits . ' 条新命中）', implode("\n\n", array_slice($lines, 0, 10)), 'monitor');
            }
        }
    }
}
