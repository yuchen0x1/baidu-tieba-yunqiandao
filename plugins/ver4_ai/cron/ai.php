<?php

if (!defined('SYSTEM_ROOT')) {
    die('Insufficient Permissions');
}
/**
 * AI 大模型定时发帖/回帖计划任务（按用户配置执行）
 * 每个百度账号归属站点用户（baiduid.uid），仅当该用户在个人面板启用 AI 且配置可用时才执行，
 * 用户可用自己的 API Key 或（管理员开放时）站点共享配置。
 * 回帖 c/c/post/add、发主题帖 c/c/thread/add、帖子列表 c/f/frs/page、楼层列表 c/f/pb/page，
 * 接口参数集与 ver4_reply 保持一致（tiebaclient 签名），内容由大模型按人设生成。
 * 调度：计划任务建议每 5 分钟触发；回帖按「轮间隔」节流（用户级），发帖按「每日小时点 / 每 N 小时」节流。
 */

require_once SYSTEM_ROOT . '/plugins/ver4_ai/ver4_ai_client.php';

function ver4_ai_cron_log($msg)
{
    cron::aset('ver4_ai_do', array('log' => '[' . date('Y-m-d H:i:s') . ']' . $msg));
}

/** 站长配置的敏感词列表（全站生效） */
function ver4_ai_banned_words()
{
    return array_values(array_filter(array_map('trim', explode("\n", (string) option::get('ver4_ai_banned_words'))), 'strlen'));
}

/** 内容是否命中敏感词 */
function ver4_ai_content_banned($text)
{
    foreach (ver4_ai_banned_words() as $word) {
        if ($word !== '' && mb_stripos((string) $text, $word) !== false) {
            return true;
        }
    }
    return false;
}

/** 近 7 天该账号是否已发送过相同内容（内容去重） */
function ver4_ai_content_duplicated($pid, $content)
{
    global $m;
    $md5 = md5((string) $content);
    $since = time() - 7 * 86400;
    $result = $m->pquery(
        "SELECT COUNT(*) AS c FROM `" . DB_PREFIX . "ver4_ai_log` WHERE `pid` = ? AND `content_md5` = ? AND `status` = 1 AND `created_at` > ?",
        array((int) $pid, $md5, $since)
    );
    if ($result === false) {
        return false;
    }
    $row = $result->fetch_array();
    return !empty($row['c']);
}

function cron_ver4_ai_do()
{
    if (function_exists('set_time_limit')) {
        @set_time_limit(600);
    }
    global $m;
    ver4_ai_ensure_table();
    ver4_ai_ensure_draft_table();
    ver4_ai_draft_prune_stale();
    $day = date('Ymd');

    //按归属用户分组账号，逐用户解析配置执行
    $rs = $m->query("SELECT `id`, `uid`, `bduss` FROM `" . DB_PREFIX . "baiduid` ORDER BY `id` ASC");
    $byUid = array();
    while ($acc = $m->fetch_array($rs)) {
        $byUid[(int) $acc['uid']][] = $acc;
    }
    foreach ($byUid as $uid => $accounts) {
        $cfg = ver4_ai_user_cfg($uid);
        if ($cfg === false) {
            continue; //用户未启用或无可用配置
        }
        //===== AI 智能回帖：用户级轮间隔节流 =====
        if ($cfg['reply_enable'] && !empty($cfg['reply_kws'])) {
            $interval = $cfg['reply_interval'] * 60;
            $lastRun = (int) option::uget('uai_reply_lastrun', $uid);
            if (time() - $lastRun >= $interval) {
                ver4_ai_uopt_set($uid, 'uai_reply_lastrun', (string) time());
                foreach ($accounts as $acc) {
                    ver4_ai_do_replies($cfg, $acc, $day);
                }
            }
        }
        //===== AI 定时发主题帖 =====
        if ($cfg['thread_enable'] && !empty($cfg['thread_kws'])) {
            foreach ($accounts as $acc) {
                ver4_ai_do_threads($cfg, $acc, $day);
            }
        }
    }
}

/**
 * AI 回帖阶段（单账号，配置来自账号归属用户）
 */
function ver4_ai_do_replies($cfg, $acc, $day)
{
    global $m;
    $pid = (int) $acc['id'];
    if (!empty($cfg['reply_pids']) && !in_array($pid, $cfg['reply_pids'], true)) {
        return;
    }
    $bduss = P::decrypt($acc['bduss']);
    if (empty($bduss)) {
        return;
    }
    $tbs = misc::getTbs(0, $bduss, $pid);
    if (empty($tbs)) {
        ver4_ai_insert_log($pid, 0, '', 0, 2, 'TBS 获取失败（账号可能已失效）', $day);
        return;
    }
    $perKw = $cfg['reply_num'];
    $daily = $cfg['reply_daily'];
    $floorRate = $cfg['reply_floor_rate'];
    $useContext = $cfg['reply_context'];
    $fallbacks = $cfg['reply_fallback'];
    $todayCount = ver4_ai_day_count($pid, 0, $day);
    if ($todayCount >= $daily) {
        return;
    }
    $floorsCache = array();
    foreach ($cfg['reply_kws'] as $kw) {
        if ($todayCount >= $daily) {
            break;
        }
        $data = ver4_ai_get_threads($bduss, $kw, $pid);
        if ($data === false || empty($data['threads'])) {
            continue;
        }
        $fid = $data['fid'];
        if ($fid <= 0) {
            $fid = (int) misc::getFid($kw);
        }
        if ($fid <= 0) {
            continue;
        }
        $done = 0;
        foreach ($data['threads'] as $t) {
            if ($done >= $perKw || $todayCount >= $daily) {
                break;
            }
            $tid = (int) $t['tid'];
            if (ver4_ai_replied_today($pid, $tid, $day)) {
                continue;
            }
            //生成 AI 回复（可带首楼上下文），失败走兜底内容库
            $context = $useContext ? ver4_ai_get_first_post($bduss, $kw, $tid, $pid) : '';
            $aiErr = null;
            $content = ver4_ai_gen_reply($cfg, $kw, $t['title'], $context, $aiErr);
            if ($content === false) {
                ver4_ai_insert_log($pid, $tid, $kw, 0, 4, 'AI 生成失败 @' . $t['title'] . '：' . $aiErr, $day, md5(''));
                if (!empty($fallbacks)) {
                    $content = $fallbacks[mt_rand(0, count($fallbacks) - 1)];
                } else {
                    continue;
                }
            }
            //敏感词预检：命中则重新生成（最多 2 次），仍命中则跳过；内容去重：近 7 天同账号发过相同内容则跳过
            $contentChecks = 0;
            while ((ver4_ai_content_banned($content) || ver4_ai_content_duplicated($pid, $content)) && $contentChecks < 2) {
                $contentChecks++;
                $content = ver4_ai_gen_reply($cfg, $kw, $t['title'], $context, $aiErr);
                if ($content === false) {
                    break;
                }
            }
            if ($content === false || ver4_ai_content_banned($content) || ver4_ai_content_duplicated($pid, $content)) {
                $reason = ver4_ai_content_banned($content ?? '') ? '命中敏感词' : '内容重复';
                ver4_ai_insert_log($pid, $tid, $kw, 0, 5, $reason . '，已跳过 @' . $t['title'], $day, md5($content ?? ''));
                continue;
            }
            //按比例随机楼中楼
            $quoteId = 0;
            if ($floorRate > 0 && mt_rand(1, 100) <= $floorRate) {
                $cacheKey = $kw . ':' . $tid;
                if (!isset($floorsCache[$cacheKey])) {
                    $floorsCache[$cacheKey] = ver4_ai_get_floors($bduss, $kw, $tid, $pid);
                }
                $floors = $floorsCache[$cacheKey];
                if (!empty($floors)) {
                    $quoteId = (int) $floors[mt_rand(0, count($floors) - 1)];
                }
            }
            list($rCode, $rMsg) = ver4_ai_post($bduss, $tbs, $kw, $fid, $tid, $content, $pid, $quoteId);
            $status = $rCode === 0 ? 1 : ($rCode === -2 ? 3 : 2);
            ver4_ai_insert_log($pid, $tid, $kw, 0, $status, ($quoteId > 0 ? '[楼中楼] ' : '') . $rMsg . ' @' . $t['title'], $day, md5($content));
            if ($rCode === 0) {
                $todayCount++;
            } elseif ($rCode === -2) {
                ver4_ai_cron_log("账号 {$pid} AI 回帖触发频率限制：{$rMsg}，本轮终止");
                return;
            }
            $done++;
            usleep((2000 + mt_rand(0, 1500)) * 1000);
        }
    }
    ver4_ai_cron_log("AI 回帖完成：账号 {$pid} 今日累计 " . ver4_ai_day_count($pid, 0, $day) . ' 条');
}

/**
 * AI 发主题帖阶段（单账号）：daily 模式在设定小时点后发一帖；hourly 模式每 N 小时发一帖
 */
function ver4_ai_do_threads($cfg, $acc, $day)
{
    global $m;
    $pid = (int) $acc['id'];
    if (!empty($cfg['thread_pids']) && !in_array($pid, $cfg['thread_pids'], true)) {
        return;
    }
    $bduss = P::decrypt($acc['bduss']);
    if (empty($bduss)) {
        return;
    }
    $tbs = misc::getTbs(0, $bduss, $pid);
    if (empty($tbs)) {
        ver4_ai_insert_log($pid, 0, '', 1, 2, 'TBS 获取失败（账号可能已失效）', $day);
        return;
    }
    $mode = $cfg['thread_mode'];
    $hourPoint = $cfg['thread_hour'];
    $everyHours = $cfg['thread_every'] * 3600;
    $dailyMax = $cfg['thread_daily'];
    //半自动模式：到点只生成候选草稿，由用户在个人页挑选发布
    $semi = !empty($cfg['thread_semi']);
    $uid = (int) (isset($acc['uid']) ? $acc['uid'] : 0);

    if ($mode === 'daily' && (int) date('G') < $hourPoint) {
        return; //未到当日设定小时点
    }

    foreach ($cfg['thread_kws'] as $kw) {
        if ($semi) {
            //半自动：按生成节奏节流（发布是手动动作，不计入全自动每日上限）
            if ($mode === 'daily') {
                if (ver4_ai_draft_made_today($pid, $kw)) {
                    continue; //该吧今日已生成过候选
                }
            } else {
                $lastDraft = ver4_ai_draft_last_time($pid, $kw);
                if ($lastDraft > 0 && time() - $lastDraft < $everyHours) {
                    continue; //未到 N 小时间隔
                }
            }
        } else {
            if (ver4_ai_day_count($pid, 1, $day) >= $dailyMax) {
                break;
            }
            if ($mode === 'daily') {
                if (ver4_ai_posted_today($pid, $kw, $day)) {
                    continue; //该吧今日已发
                }
            } else {
                $last = ver4_ai_last_post_time($pid, $kw);
                if ($last > 0 && time() - $last < $everyHours) {
                    continue; //未到 N 小时间隔
                }
            }
        }
        $fid = (int) misc::getFid($kw);
        if ($fid <= 0) {
            ver4_ai_insert_log($pid, 0, $kw, 1, 2, '获取吧 ID 失败（贴吧名可能不存在）', $day);
            continue;
        }
        if ($semi) {
            //===== 半自动：一次生成 3 条候选，逐条过敏感词/去重检查后存草稿箱 =====
            $made = 0;
            $seenMd5 = array();
            for ($ci = 0; $ci < 3; $ci++) {
                $aiErr = null;
                $thread = ver4_ai_gen_thread($cfg, $kw, $aiErr);
                if ($thread === false) {
                    continue;
                }
                $contentChecks = 0;
                while ((ver4_ai_content_banned($thread['title']) || ver4_ai_content_banned($thread['content']) || ver4_ai_content_duplicated($pid, $thread['title'] . $thread['content'])) && $contentChecks < 2) {
                    $contentChecks++;
                    $thread = ver4_ai_gen_thread($cfg, $kw, $aiErr);
                    if ($thread === false) {
                        break;
                    }
                }
                if ($thread === false || ver4_ai_content_banned($thread['title']) || ver4_ai_content_banned($thread['content'])) {
                    continue;
                }
                $md5 = md5($thread['title'] . $thread['content']);
                if (isset($seenMd5[$md5]) || ver4_ai_content_duplicated($pid, $thread['title'] . $thread['content'])) {
                    continue; //与已发布内容或本批候选重复
                }
                $seenMd5[$md5] = true;
                ver4_ai_draft_add($uid, $pid, $kw, $thread['title'], $thread['content']);
                $made++;
                usleep(800 * 1000);
            }
            ver4_ai_insert_log($pid, 0, $kw, 1, 6, "半自动模式：已生成 {$made} 条候选，请到个人页「发帖草稿箱」挑选发布", $day, md5(''));
            ver4_ai_cron_log("半自动发帖：账号 {$pid} 在 {$kw}吧 生成 {$made} 条候选");
            continue;
        }
        $aiErr = null;
        $thread = ver4_ai_gen_thread($cfg, $kw, $aiErr);
        if ($thread === false) {
            ver4_ai_insert_log($pid, 0, $kw, 1, 4, 'AI 生成失败：' . $aiErr, $day, md5(''));
            continue;
        }
        //敏感词预检：标题或正文命中则重新生成（最多 2 次），仍命中则跳过；近 7 天同内容去重
        $contentChecks = 0;
        while ((ver4_ai_content_banned($thread['title']) || ver4_ai_content_banned($thread['content']) || ver4_ai_content_duplicated($pid, $thread['title'] . $thread['content'])) && $contentChecks < 2) {
            $contentChecks++;
            $thread = ver4_ai_gen_thread($cfg, $kw, $aiErr);
            if ($thread === false) {
                break;
            }
        }
        if ($thread === false || ver4_ai_content_banned($thread['title']) || ver4_ai_content_banned($thread['content']) || ver4_ai_content_duplicated($pid, $thread['title'] . $thread['content'])) {
            ver4_ai_insert_log($pid, 0, $kw, 1, 5, '内容命中敏感词或重复，已跳过', $day, md5(''));
            continue;
        }
        list($rCode, $rMsg, $newTid) = ver4_ai_post_thread($bduss, $tbs, $kw, $fid, $thread['title'], $thread['content'], $pid);
        $status = $rCode === 0 ? 1 : ($rCode === -2 ? 3 : 2);
        ver4_ai_insert_log($pid, $newTid, $kw, 1, $status, $rMsg . ' @' . $thread['title'], $day, md5($thread['title'] . $thread['content']));
        if ($rCode === 0) {
            ver4_ai_cron_log("AI 发帖成功：账号 {$pid} 在 {$kw}吧 发布《" . $thread['title'] . "》");
        } elseif ($rCode === -2) {
            ver4_ai_cron_log("账号 {$pid} AI 发帖触发频率限制：{$rMsg}，本轮终止");
            return;
        }
        usleep((2000 + mt_rand(0, 1500)) * 1000);
    }
}

/**
 * 拉取贴吧第一页最新主题帖
 * @return array|false
 */
function ver4_ai_get_threads($bduss, $kw, $pid)
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
    $out = array(
        'fid' => isset($resp['forum']['id']) ? (int) $resp['forum']['id'] : 0,
        'threads' => array(),
    );
    foreach ($resp['thread_list'] as $t) {
        if (!empty($t['is_ad'])) {
            continue;
        }
        $tid = isset($t['id']) ? (int) $t['id'] : 0;
        if ($tid > 0) {
            $out['threads'][] = array(
                'tid' => $tid,
                'title' => isset($t['title']) ? (string) $t['title'] : '',
            );
        }
    }
    return $out;
}

/** 从帖子内容片段数组提取纯文本 */
function ver4_ai_fragments_text($fragments)
{
    $text = '';
    if (is_array($fragments)) {
        foreach ($fragments as $frag) {
            if (isset($frag['text']) && is_string($frag['text'])) {
                $text .= $frag['text'];
            }
        }
    }
    return trim($text);
}

/**
 * 拉取主题帖首楼内容（作为回帖上下文）
 * @return string
 */
function ver4_ai_get_first_post($bduss, $kw, $tid, $pid)
{
    $url = 'https://c.tieba.baidu.com/c/f/pb/page';
    if (!wcurl::isAllowedPublicUrl($url)) {
        return '';
    }
    $data = array(
        'BDUSS' => $bduss,
        'kw' => $kw,
        'pn' => 1,
        'rn' => 10,
        'tid' => (string) $tid,
    );
    misc::addTiebaSign($data, false);
    $ch = new wcurl($url);
    misc::applyProxy($ch, $pid);
    $ch->addCookie(array('BDUSS' => $bduss));
    $resp = json_decode($ch->post($data), true);
    if (!is_array($resp) || empty($resp['post_list']) || !is_array($resp['post_list'][0])) {
        return '';
    }
    return ver4_ai_fragments_text(isset($resp['post_list'][0]['content']) ? $resp['post_list'][0]['content'] : array());
}

/**
 * 拉取楼层列表（用于楼中楼回复）
 * @return array
 */
function ver4_ai_get_floors($bduss, $kw, $tid, $pid)
{
    $url = 'https://c.tieba.baidu.com/c/f/pb/page';
    if (!wcurl::isAllowedPublicUrl($url)) {
        return array();
    }
    $data = array(
        'BDUSS' => $bduss,
        'kw' => $kw,
        'pn' => 1,
        'rn' => 30,
        'tid' => (string) $tid,
    );
    misc::addTiebaSign($data, false);
    $ch = new wcurl($url);
    misc::applyProxy($ch, $pid);
    $ch->addCookie(array('BDUSS' => $bduss));
    $resp = json_decode($ch->post($data), true);
    if (!is_array($resp) || empty($resp['post_list'])) {
        return array();
    }
    $out = array();
    foreach ($resp['post_list'] as $floor) {
        $floorPid = isset($floor['id']) ? (int) $floor['id'] : 0;
        if ($floorPid > 0) {
            $out[] = $floorPid;
        }
    }
    return $out;
}

/**
 * 回帖（$quoteId > 0 时为楼中楼）：见 ver4_ai_client.php（与用户页半自动发布共用）
 */

/**
 * 发布新主题帖：见 ver4_ai_client.php（与用户页半自动发布共用）
 */

/** 某账号某类型今日已成功执行条数 */
function ver4_ai_day_count($pid, $type, $day)
{
    global $m;
    $result = $m->pquery("SELECT COUNT(*) AS c FROM `" . DB_PREFIX . "ver4_ai_log` WHERE `pid` = ? AND `type` = ? AND `day` = ? AND `status` = 1", array($pid, $type, $day));
    if ($result === false) {
        return 0;
    }
    $row = $result->fetch_array();
    return isset($row['c']) ? (int) $row['c'] : 0;
}

/** 某账号今日是否已回复过某主题帖 */
function ver4_ai_replied_today($pid, $tid, $day)
{
    global $m;
    $result = $m->pquery("SELECT COUNT(*) AS c FROM `" . DB_PREFIX . "ver4_ai_log` WHERE `pid` = ? AND `tid` = ? AND `type` = 0 AND `day` = ?", array($pid, $tid, $day));
    if ($result === false) {
        return false;
    }
    $row = $result->fetch_array();
    return !empty($row['c']);
}

/** 某账号今日是否已在某吧发过主题帖 */
function ver4_ai_posted_today($pid, $kw, $day)
{
    global $m;
    $result = $m->pquery("SELECT COUNT(*) AS c FROM `" . DB_PREFIX . "ver4_ai_log` WHERE `pid` = ? AND `kw` = ? AND `type` = 1 AND `status` = 1 AND `day` = ?", array($pid, $kw, $day));
    if ($result === false) {
        return false;
    }
    $row = $result->fetch_array();
    return !empty($row['c']);
}

/** 某账号在某吧最近一次成功发帖时间 */
function ver4_ai_last_post_time($pid, $kw)
{
    global $m;
    $result = $m->pquery("SELECT MAX(`created_at`) AS t FROM `" . DB_PREFIX . "ver4_ai_log` WHERE `pid` = ? AND `kw` = ? AND `type` = 1 AND `status` = 1", array($pid, $kw));
    if ($result === false) {
        return 0;
    }
    $row = $result->fetch_array();
    return empty($row['t']) ? 0 : (int) $row['t'];
}
