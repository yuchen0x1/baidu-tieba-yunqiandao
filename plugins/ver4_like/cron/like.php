<?php

if (!defined('SYSTEM_ROOT')) {
    die('Insufficient Permissions');
}

/**
 * 云点赞计划任务
 * 参考 aiotieba 等开源实现的贴吧客户端接口：
 *  - 帖子列表：c/f/frs/page（kw/pn/rn，tiebaclient 签名）
 *  - 帖子点赞：c/c/post/like（kz/z/tbs，tiebaclient 签名）
 */

function ver4_like_log($msg)
{
    cron::aset('ver4_like_do', array('log' => '[' . date('Y-m-d H:i:s') . ']' . $msg));
}

/**
 * 拉取贴吧第一页最新帖子，返回 array(array(tid, pid), ...)
 */
function ver4_like_get_threads($bduss, $kw, $pid = 0)
{
    $data = array(
        'BDUSS' => $bduss,
        'kw' => $kw,
        'pn' => 1,
        'rn' => 30,
    );
    misc::addTiebaSign($data, false);
    $ch = new wcurl('https://c.tieba.baidu.com/c/f/frs/page');
    misc::applyProxy($ch, $pid);
    $ch->addCookie(array('BDUSS' => $bduss));
    $resp = json_decode($ch->post($data), true);
    if (!is_array($resp) || empty($resp['thread_list'])) {
        return array();
    }
    $threads = array();
    foreach ($resp['thread_list'] as $t) {
        $tid = isset($t['id']) ? $t['id'] : 0;
        $zid = 0;
        foreach (array('first_post_id', 'fp_id', 'first_post_id_str') as $key) {
            if (!empty($t[$key])) {
                $zid = $t[$key];
                break;
            }
        }
        if ($tid > 0 && $zid > 0) {
            $threads[] = array('tid' => $tid, 'zid' => $zid);
        }
    }
    return $threads;
}

/**
 * 对一个帖子点赞
 * @return int 0=成功 1=已赞过 -1=失败
 */
function ver4_like_one($bduss, $tbs, $tid, $zid, $pid = 0)
{
    $data = array(
        'BDUSS' => $bduss,
        'kz' => $tid,
        'z' => $zid,
        'tbs' => $tbs,
    );
    misc::addTiebaSign($data);
    $ch = new wcurl('https://c.tieba.baidu.com/c/c/post/like');
    misc::applyProxy($ch, $pid);
    $ch->addCookie(array('BDUSS' => $bduss));
    $resp = json_decode($ch->post($data), true);
    if (is_array($resp)) {
        $code = isset($resp['error_code']) ? (int) $resp['error_code'] : -1;
        if ($code === 0) {
            return 0;
        }
        //已赞过 / 已点过赞 视为成功
        if (strpos((string) (isset($resp['error_msg']) ? $resp['error_msg'] : ''), '已') !== false) {
            return 1;
        }
    }
    return -1;
}

function cron_ver4_like_do()
{
    global $m;
    $kwsRaw = (string) option::get('ver4_like_kws');
    if (trim($kwsRaw) === '') {
        ver4_like_log('未配置目标贴吧，跳过执行');
        return;
    }
    $kws = array_values(array_filter(array_map('trim', explode("\n", $kwsRaw)), 'strlen'));
    $perKw = max(1, (int) option::get('ver4_like_num'));
    $sleep = max(0, (int) option::get('ver4_like_sleep'));
    $okCount = 0;
    $skipCount = 0;
    $failCount = 0;
    $rs = $m->query("SELECT `id`, `bduss` FROM `" . DB_NAME . "`.`" . DB_PREFIX . "baiduid` ORDER BY `id` ASC");
    while ($acc = $m->fetch_array($rs)) {
        $bduss = P::decrypt($acc['bduss']);
        if (empty($bduss)) {
            continue;
        }
        foreach ($kws as $kw) {
            $threads = ver4_like_get_threads($bduss, $kw, (int) $acc['id']);
            if (empty($threads)) {
                continue;
            }
            $tbs = misc::getTbs(0, $bduss, $acc['id']);
            $done = 0;
            foreach ($threads as $t) {
                if ($done >= $perKw) {
                    break;
                }
                $r = ver4_like_one($bduss, $tbs, $t['tid'], $t['zid'], (int) $acc['id']);
                if ($r === 0) {
                    $okCount++;
                    $done++;
                } elseif ($r === 1) {
                    $skipCount++;
                    $done++;
                } else {
                    $failCount++;
                    $done++;
                }
                if ($sleep > 0) {
                    usleep($sleep * 1000);
                }
            }
        }
    }
    ver4_like_log("云点赞完成：成功 {$okCount}，已赞过 {$skipCount}，失败 {$failCount}");
}
