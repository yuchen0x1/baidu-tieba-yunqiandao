<?php

if (!defined('SYSTEM_ROOT')) {
    die('Insufficient Permissions');
}

/**
 * 云端自动回帖/发主题帖计划任务
 * 回帖接口 c/c/post/add（楼中楼通过 quote_id 指定楼层，参数集参考 96dl/Tieba-Cloud-Sign-Plugins 的 ver4_post 与 FirefoxBar Tieba_Client），
 * 发主题帖接口 c/c/thread/add（参数集参考 FirefoxBar Tieba_Client 的 threadPost），
 * 帖子列表接口 c/f/frs/page、楼层列表接口 c/f/pb/page 参考 aiotieba 等开源实现，均走 tiebaclient 签名。
 */

function ver4_reply_log($msg)
{
    cron::aset('ver4_reply_do', array('log' => '[' . date('Y-m-d H:i:s') . ']' . $msg));
}

function ver4_reply_ensure_table()
{
    static $ready = false;
    if ($ready) {
        return;
    }
    global $m;
    //回帖/发帖去重与每日限额记录：type 0=回帖 1=主题帖；同一账号对同一主题帖每天只回一次
    $m->query("CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "ver4_reply_log` (
        `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        `pid` INT UNSIGNED NOT NULL DEFAULT 0,
        `tid` BIGINT UNSIGNED NOT NULL DEFAULT 0,
        `kw` VARCHAR(60) NOT NULL DEFAULT '',
        `type` TINYINT NOT NULL DEFAULT 0,
        `status` TINYINT NOT NULL DEFAULT 0,
        `msg` VARCHAR(255) NOT NULL DEFAULT '',
        `day` CHAR(8) NOT NULL DEFAULT '',
        `created_at` INT UNSIGNED NOT NULL,
        PRIMARY KEY (`id`),
        UNIQUE KEY `pid_tid_day` (`pid`, `tid`, `day`),
        KEY `pid_day` (`pid`, `day`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
    //旧版本表结构补齐 type 列
    $m->addColumn('ver4_reply_log', 'type', 'tinyint', 'NOT NULL DEFAULT 0');
    $ready = true;
}

/**
 * 拉取贴吧第一页最新主题帖
 * @return array|false array('fid' => int, 'threads' => array(array('tid'=>, 'title'=>)), 失败返回 false
 */
function ver4_reply_get_threads($bduss, $kw, $pid)
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

/**
 * 拉取主题帖第一页楼层列表（用于楼中楼回复）
 * @return array 楼层 pid 列表，失败返回空数组
 */
function ver4_reply_get_floors($bduss, $kw, $tid, $pid)
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
 * 对一个主题帖回帖（$quoteId > 0 时为楼中楼回复）
 * @return array code: 0=成功 1=已回过/跳过 -1=失败；msg 提示
 */
function ver4_reply_post($bduss, $tbs, $kw, $fid, $tid, $content, $pid, $quoteId = 0)
{
    $url = 'https://c.tieba.baidu.com/c/c/post/add';
    if (!wcurl::isAllowedPublicUrl($url)) {
        return array(-1, '接口地址校验失败');
    }
    $data = array(
        'BDUSS' => $bduss,
        '_client_id' => 'wappc_' . mt_rand(100000000, 999999999) . '_' . mt_rand(100, 999),
        '_phone_imei' => md5(uniqid('', true)),
        'anonymous' => '0',
        'content' => $content,
        'fid' => (string) $fid,
        'kw' => $kw,
        'net_type' => '3',
        'new_vcode' => '1',
        'tbs' => $tbs,
        'tid' => (string) $tid,
        'title' => '',
    );
    if ($quoteId > 0) {
        $data['quote_id'] = (string) $quoteId;
    }
    misc::addTiebaSign($data);
    $ch = new wcurl($url);
    misc::applyProxy($ch, $pid);
    $ch->addCookie(array('BDUSS' => $bduss));
    $resp = json_decode($ch->post($data), true);
    if (!is_array($resp)) {
        return array(-1, '接口返回无法解析');
    }
    $code = isset($resp['error_code']) ? (int) $resp['error_code'] : -1;
    if ($code === 0) {
        return array(0, $quoteId > 0 ? '楼中楼回复成功' : '回帖成功');
    }
    $map = array(
        4 => '发帖过于频繁，本轮停止',
        5 => '需要验证码或未关注该吧',
        7 => '操作过于频繁',
        8 => '账号已被本吧封禁',
    );
    if (isset($map[$code])) {
        return array($code === 4 || $code === 7 ? -2 : -1, $map[$code]);
    }
    $errmsg = isset($resp['error_msg']) ? (string) $resp['error_msg'] : '';
    return array(-1, '错误码 ' . $code . ($errmsg !== '' ? '：' . $errmsg : ''));
}

/**
 * 在目标贴吧发布一个新主题帖
 * @return array array(0=成功 -1=失败 -2=频繁, msg, 新主题tid)
 */
function ver4_reply_post_thread($bduss, $tbs, $kw, $fid, $title, $content, $pid)
{
    $url = 'https://c.tieba.baidu.com/c/c/thread/add';
    if (!wcurl::isAllowedPublicUrl($url)) {
        return array(-1, '接口地址校验失败', 0);
    }
    $data = array(
        'BDUSS' => $bduss,
        '_client_id' => 'wappc_' . mt_rand(100000000, 999999999) . '_' . mt_rand(100, 999),
        '_phone_imei' => md5(uniqid('', true)),
        'anonymous' => '0',
        'content' => $content,
        'fid' => (string) $fid,
        'kw' => $kw,
        'net_type' => '3',
        'new_vcode' => '1',
        'tbs' => $tbs,
        'title' => $title,
        'vcode_tag' => '11',
    );
    misc::addTiebaSign($data);
    $ch = new wcurl($url);
    misc::applyProxy($ch, $pid);
    $ch->addCookie(array('BDUSS' => $bduss));
    $resp = json_decode($ch->post($data), true);
    if (!is_array($resp)) {
        return array(-1, '接口返回无法解析', 0);
    }
    $code = isset($resp['error_code']) ? (int) $resp['error_code'] : -1;
    //tid 在响应顶层（实测），旧文档的 data.tid 作兜底
    $newTid = isset($resp['tid']) ? (int) $resp['tid'] : (isset($resp['data']['tid']) ? (int) $resp['data']['tid'] : 0);
    if ($code === 0) {
        return array(0, '发主题帖成功', $newTid);
    }
    $map = array(
        4 => '发帖过于频繁，本轮停止',
        5 => '需要验证码或未关注该吧',
        7 => '操作过于频繁',
        8 => '账号已被本吧封禁',
    );
    if (isset($map[$code])) {
        return array($code === 4 || $code === 7 ? -2 : -1, $map[$code], 0);
    }
    $errmsg = isset($resp['error_msg']) ? (string) $resp['error_msg'] : '';
    return array(-1, '错误码 ' . $code . ($errmsg !== '' ? '：' . $errmsg : ''), 0);
}

function cron_ver4_reply_do()
{
    global $m;
    ver4_reply_ensure_table();

    $day = date('Ymd');
    $okCount = 0;
    $failCount = 0;
    $skipCount = 0;
    $threadOk = 0;
    $threadFail = 0;

    $rs = $m->query("SELECT `id`, `bduss` FROM `" . DB_NAME . "`.`" . DB_PREFIX . "baiduid` ORDER BY `id` ASC");
    while ($acc = $m->fetch_array($rs)) {
        $pid = (int) $acc['id'];
        $bduss = P::decrypt($acc['bduss']);
        if (empty($bduss)) {
            continue;
        }
        $tbs = misc::getTbs(0, $bduss, $pid);
        if (empty($tbs)) {
            $m->query("INSERT IGNORE INTO `" . DB_PREFIX . "ver4_reply_log` (`pid`, `tid`, `kw`, `type`, `status`, `msg`, `day`, `created_at`) VALUES ({$pid}, 0, '', 0, 2, 'TBS 获取失败（账号可能已失效）', '{$day}', UNIX_TIMESTAMP())");
            continue;
        }
        ver4_reply_do_replies($bduss, $tbs, $pid, $day, $okCount, $failCount, $skipCount);
        ver4_reply_do_threads($bduss, $tbs, $pid, $day, $threadOk, $threadFail);
    }
    ver4_reply_log("自动回帖完成：成功 {$okCount}，失败 {$failCount}，跳过 {$skipCount}；发主题帖完成：成功 {$threadOk}，失败 {$threadFail}");
}

/**
 * 回帖阶段：对目标贴吧的最新主题帖回复，按比例随机走楼中楼
 */
function ver4_reply_do_replies($bduss, $tbs, $pid, $day, &$okCount, &$failCount, &$skipCount)
{
    global $m;
    $kws = array_values(array_filter(array_map('trim', explode("\n", (string) option::get('ver4_reply_kws'))), 'strlen'));
    $contents = array_values(array_filter(array_map('trim', explode("\n", (string) option::get('ver4_reply_contents'))), 'strlen'));
    if (empty($kws) || empty($contents)) {
        return;
    }
    $perKw = max(1, (int) option::get('ver4_reply_num'));
    $daily = max(1, (int) option::get('ver4_reply_daily'));
    $sleep = max(0, (int) option::get('ver4_reply_sleep'));
    $floorRate = min(100, max(0, (int) option::get('ver4_reply_floor_rate')));
    $used = $m->once_fetch_array("SELECT COUNT(*) AS c FROM `" . DB_PREFIX . "ver4_reply_log` WHERE `pid` = {$pid} AND `type` = 0 AND `day` = '{$day}'");
    $todayCount = (int) $used['c'];
    if ($todayCount >= $daily) {
        $skipCount++;
        return;
    }
    $floorsCache = array();
    foreach ($kws as $kw) {
        if ($todayCount >= $daily) {
            break;
        }
        $kwSafe = $m->real_escape_string($kw);
        $data = ver4_reply_get_threads($bduss, $kw, $pid);
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
            $dup = $m->once_fetch_array("SELECT COUNT(*) AS c FROM `" . DB_PREFIX . "ver4_reply_log` WHERE `pid` = {$pid} AND `tid` = {$tid} AND `type` = 0 AND `day` = '{$day}'");
            if (!empty($dup['c'])) {
                $skipCount++;
                continue;
            }
            //按比例随机楼中楼：拉取楼层列表后随机选一栋楼层回复
            $quoteId = 0;
            if ($floorRate > 0 && mt_rand(1, 100) <= $floorRate) {
                if (!isset($floorsCache[$kw])) {
                    $floorsCache[$kw] = array();
                }
                if (!isset($floorsCache[$kw][$tid])) {
                    $floorsCache[$kw][$tid] = ver4_reply_get_floors($bduss, $kw, $tid, $pid);
                }
                $floors = $floorsCache[$kw][$tid];
                if (!empty($floors)) {
                    $quoteId = (int) $floors[mt_rand(0, count($floors) - 1)];
                }
            }
            $content = $contents[mt_rand(0, count($contents) - 1)];
            list($rCode, $rMsg) = ver4_reply_post($bduss, $tbs, $kw, $fid, $tid, $content, $pid, $quoteId);
            $status = $rCode === 0 ? 1 : ($rCode === -2 ? 3 : 2);
            $msgSafe = $m->real_escape_string(($quoteId > 0 ? '[楼中楼] ' : '') . $rMsg . ' @' . $t['title']);
            $m->query("INSERT IGNORE INTO `" . DB_PREFIX . "ver4_reply_log` (`pid`, `tid`, `kw`, `type`, `status`, `msg`, `day`, `created_at`) VALUES ({$pid}, {$tid}, '{$kwSafe}', 0, {$status}, '{$msgSafe}', '{$day}', UNIX_TIMESTAMP())");
            if ($rCode === 0) {
                $okCount++;
            } elseif ($rCode === -2) {
                //频繁类错误直接终止本轮，避免加剧风控
                $failCount++;
                ver4_reply_log("账号 {$pid} 触发频率限制：{$rMsg}，本轮终止");
                return;
            } else {
                $failCount++;
            }
            $done++;
            $todayCount++;
            if ($sleep > 0) {
                usleep(($sleep + mt_rand(0, 1500)) * 1000);
            }
        }
    }
}

/**
 * 发主题帖阶段：向目标贴吧发布新主题
 */
function ver4_reply_do_threads($bduss, $tbs, $pid, $day, &$threadOk, &$threadFail)
{
    global $m;
    $tKws = array_values(array_filter(array_map('trim', explode("\n", (string) option::get('ver4_reply_thread_kws'))), 'strlen'));
    $tTitles = array_values(array_filter(array_map('trim', explode("\n", (string) option::get('ver4_reply_thread_titles'))), 'strlen'));
    $tContents = array_values(array_filter(array_map('trim', explode("\n", (string) option::get('ver4_reply_thread_contents'))), 'strlen'));
    if (empty($tKws) || empty($tTitles) || empty($tContents)) {
        return;
    }
    $tNum = max(1, (int) option::get('ver4_reply_thread_num'));
    $tDaily = max(1, (int) option::get('ver4_reply_thread_daily'));
    $sleep = max(0, (int) option::get('ver4_reply_sleep'));
    $used = $m->once_fetch_array("SELECT COUNT(*) AS c FROM `" . DB_PREFIX . "ver4_reply_log` WHERE `pid` = {$pid} AND `type` = 1 AND `day` = '{$day}'");
    $todayCount = (int) $used['c'];
    if ($todayCount >= $tDaily) {
        return;
    }
    foreach ($tKws as $kw) {
        if ($todayCount >= $tDaily) {
            break;
        }
        $fid = (int) misc::getFid($kw);
        if ($fid <= 0) {
            continue;
        }
        $kwSafe = $m->real_escape_string($kw);
        $done = 0;
        while ($done < $tNum && $todayCount < $tDaily) {
            $title = $tTitles[mt_rand(0, count($tTitles) - 1)];
            $content = $tContents[mt_rand(0, count($tContents) - 1)];
            list($rCode, $rMsg, $newTid) = ver4_reply_post_thread($bduss, $tbs, $kw, $fid, $title, $content, $pid);
            $status = $rCode === 0 ? 1 : ($rCode === -2 ? 3 : 2);
            $msgSafe = $m->real_escape_string($rMsg . ' @' . $title);
            $m->query("INSERT IGNORE INTO `" . DB_PREFIX . "ver4_reply_log` (`pid`, `tid`, `kw`, `type`, `status`, `msg`, `day`, `created_at`) VALUES ({$pid}, {$newTid}, '{$kwSafe}', 1, {$status}, '{$msgSafe}', '{$day}', UNIX_TIMESTAMP())");
            if ($rCode === 0) {
                $threadOk++;
            } elseif ($rCode === -2) {
                $threadFail++;
                ver4_reply_log("账号 {$pid} 发主题帖触发频率限制：{$rMsg}，本轮终止");
                return;
            } else {
                $threadFail++;
            }
            $done++;
            $todayCount++;
            if ($sleep > 0) {
                usleep(($sleep + mt_rand(0, 1500)) * 1000);
            }
        }
    }
}
