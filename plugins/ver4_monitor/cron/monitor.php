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

/** 记录命中的自动回帖状态 */
function ver4_monitor_set_replied($uid, $tid, $replied)
{
    global $m;
    $m->pquery("UPDATE `" . DB_PREFIX . "ver4_monitor_hit` SET `replied` = ? WHERE `uid` = ? AND `tid` = ?", array((int) $replied, (int) $uid, (int) $tid));
}

/**
 * 命中联动 AI 自动回帖（复用 ver4_ai 的用户配置、生成、发布与安全预检：
 * 站长敏感词、近 7 天内容去重、同账号同帖同日唯一）
 * @return int 1=成功 2=失败 3=跳过（达上限/敏感词/重复） -2=触发频率限制
 */
function ver4_monitor_auto_reply($cfg, $bduss, $pid, $forum, $tid, $title, $dailyCap)
{
    global $m;
    $day = date('Ymd');
    //与常规 AI 回帖共用同帖同日去重，防止两个功能对同一帖重复回复
    if (ver4_ai_replied_today($pid, $tid, $day)) {
        return 3;
    }
    //今日监控自动回帖上限（按执行记录中带 [监控] 前缀的成功条数统计）
    $result = $m->pquery(
        "SELECT COUNT(*) AS c FROM `" . DB_PREFIX . "ver4_ai_log` WHERE `pid` = ? AND `type` = 0 AND `day` = ? AND `status` = 1 AND `msg` LIKE '[监控]%'",
        array((int) $pid, $day)
    );
    $today = ($result !== false && ($row = $result->fetch_array())) ? (int) $row['c'] : 0;
    if ($today >= $dailyCap) {
        return 3;
    }
    $tbs = misc::getTbs(0, $bduss, $pid);
    if (empty($tbs)) {
        ver4_ai_insert_log($pid, $tid, $forum, 0, 2, '[监控] TBS 获取失败（账号可能已失效）', $day);
        return 2;
    }
    $fid = (int) misc::getFid($forum);
    if ($fid <= 0) {
        ver4_ai_insert_log($pid, $tid, $forum, 0, 2, '[监控] 获取吧 ID 失败', $day);
        return 2;
    }
    //首楼内容作为生成上下文，让回复贴合主题
    $context = ver4_ai_get_first_post($bduss, $forum, $tid, $pid);
    $aiErr = null;
    $content = ver4_ai_gen_reply($cfg, $forum, $title, $context, $aiErr);
    if ($content === false) {
        ver4_ai_insert_log($pid, $tid, $forum, 0, 4, '[监控] AI 生成失败 @' . $title . '：' . $aiErr, $day, md5(''));
        return 2;
    }
    //敏感词/内容去重预检：重新生成最多 2 次，仍命中则跳过
    $checks = 0;
    while ((ver4_ai_content_banned($content) || ver4_ai_content_duplicated($pid, $content)) && $checks < 2) {
        $checks++;
        $content = ver4_ai_gen_reply($cfg, $forum, $title, $context, $aiErr);
        if ($content === false) {
            break;
        }
    }
    if ($content === false || ver4_ai_content_banned($content) || ver4_ai_content_duplicated($pid, $content)) {
        ver4_ai_insert_log($pid, $tid, $forum, 0, 5, '[监控] 内容命中敏感词或重复，已跳过 @' . $title, $day, md5((string) $content));
        return 3;
    }
    list($rCode, $rMsg) = ver4_ai_post($bduss, $tbs, $forum, $fid, $tid, $content, $pid);
    $status = $rCode === 0 ? 1 : ($rCode === -2 ? 3 : 2);
    ver4_ai_insert_log($pid, $tid, $forum, 0, $status, '[监控] ' . $rMsg . ' @' . $title, $day, md5($content));
    usleep((2000 + mt_rand(0, 1500)) * 1000);
    return $rCode === 0 ? 1 : ($rCode === -2 ? -2 : 2);
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
        $excludeRaw = (string) option::uget('ver4_monitor_exclude', $uid);
        $excludeWords = array_values(array_filter(array_map('trim', explode("\n", $excludeRaw)), 'strlen'));
        $doNotify = option::uget('ver4_monitor_notify', $uid) !== '0';
        $pushMode = option::uget('ver4_monitor_mode', $uid) === 'digest' ? 'digest' : 'realtime';
        $digestHour = min(23, max(0, (int) ((option::uget('ver4_monitor_digest_hour', $uid) !== null && option::uget('ver4_monitor_digest_hour', $uid) !== '') ? option::uget('ver4_monitor_digest_hour', $uid) : 21)));
        //命中联动 AI 自动回帖：需 ver4_ai 插件在位且用户 AI 配置可用
        $autoDaily = min(20, max(1, (int) ((option::uget('ver4_monitor_autoreply_daily', $uid) !== null && option::uget('ver4_monitor_autoreply_daily', $uid) !== '') ? option::uget('ver4_monitor_autoreply_daily', $uid) : 5)));
        $autoCfg = false;
        if (option::uget('ver4_monitor_autoreply', $uid) === '1' && file_exists(SYSTEM_ROOT . '/plugins/ver4_ai/ver4_ai.php')) {
            require_once SYSTEM_ROOT . '/plugins/ver4_ai/cron/ai.php';
            $autoCfg = ver4_ai_user_cfg($uid);
        }

        //每日汇总模式：到汇总小时点，把当天未推送的命中合并成一条摘要推送（附各吧命中统计）
        if ($pushMode === 'digest' && $doNotify && (int) date('G') === $digestHour) {
            $dayStart = strtotime(date('Y-m-d') . ' 00:00:00');
            $pending = $m->pquery("SELECT `forum`, `kw`, `tid`, `title` FROM `" . DB_PREFIX . "ver4_monitor_hit` WHERE `uid` = ? AND `notified` = 0 AND `created_at` >= ? ORDER BY `id` ASC LIMIT 30", array($uid, $dayStart));
            $digestLines = array();
            $forumStats = array();
            if ($pending !== false) {
                while ($pRow = $pending->fetch_array()) {
                    $digestLines[] = '【' . $pRow['forum'] . '吧】' . mb_substr((string) $pRow['title'], 0, 50) . "\nhttps://tieba.baidu.com/p/" . (int) $pRow['tid'];
                    $fsKey = (string) $pRow['forum'];
                    $forumStats[$fsKey] = ($forumStats[$fsKey] ?? 0) + 1;
                }
            }
            if (!empty($digestLines)) {
                $statParts = array();
                foreach ($forumStats as $fsForum => $fsCount) {
                    $statParts[] = $fsForum . '吧 ' . $fsCount . ' 条';
                }
                $digestLines[] = '—— 各吧命中：' . implode('、', $statParts);
                Notify::sendForUser($uid, '帖子监控 · 今日汇总（' . count($digestLines) - 1 . ' 条命中）', implode("\n\n", $digestLines), 'monitor');
                $m->pquery("UPDATE `" . DB_PREFIX . "ver4_monitor_hit` SET `notified` = 1 WHERE `uid` = ? AND `notified` = 0 AND `created_at` >= ?", array($uid, $dayStart));
                ver4_monitor_log("用户 {$uid} 今日汇总推送 " . (count($digestLines) - 1) . ' 条');
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
                //命中联动 AI 自动回帖
                if (is_array($autoCfg)) {
                    $replied = ver4_monitor_auto_reply($autoCfg, $bduss, $pid, $forum, $tid, $title, $autoDaily);
                    ver4_monitor_set_replied($uid, $tid, $replied === -2 ? 2 : $replied);
                    if ($replied === 1) {
                        ver4_monitor_log("用户 {$uid} 监控命中自动回帖成功：{$forum}吧 {$title}");
                    }
                    if ($replied === -2) {
                        //触发频率限制：本轮剩余命中只记录不再回帖
                        $autoCfg = false;
                        ver4_monitor_log("用户 {$uid} 监控自动回帖触发频率限制，本轮停止回帖");
                    }
                }
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
