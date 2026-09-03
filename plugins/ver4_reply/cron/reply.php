<?php

if (!defined('SYSTEM_ROOT')) {
    die('Insufficient Permissions');
}

/**
 * 云端自动回帖计划任务
 * 发帖接口 c/c/post/add 与参数集参考 kookxiang/Tieba_Sign 的 xxx_post 插件，
 * 帖子列表接口 c/f/frs/page 参考 aiotieba 等开源实现，均走 tiebaclient 签名。
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
    //回帖去重与每日限额记录：同一账号对同一主题帖每天只回一次
    $m->query("CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "ver4_reply_log` (
        `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        `pid` INT UNSIGNED NOT NULL DEFAULT 0,
        `tid` BIGINT UNSIGNED NOT NULL DEFAULT 0,
        `kw` VARCHAR(60) NOT NULL DEFAULT '',
        `status` TINYINT NOT NULL DEFAULT 0,
        `msg` VARCHAR(255) NOT NULL DEFAULT '',
        `day` CHAR(8) NOT NULL DEFAULT '',
        `created_at` INT UNSIGNED NOT NULL,
        PRIMARY KEY (`id`),
        UNIQUE KEY `pid_tid_day` (`pid`, `tid`, `day`),
        KEY `pid_day` (`pid`, `day`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
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
 * 对一个主题帖回帖
 * @return array code: 0=成功 1=已回过/跳过 -1=失败；msg 提示
 */
function ver4_reply_post($bduss, $tbs, $kw, $fid, $tid, $content, $pid)
{
    $url = 'https://c.tieba.baidu.com/c/c/post/add';
    if (!wcurl::isAllowedPublicUrl($url)) {
        return array(-1, '接口地址校验失败');
    }
    $data = array(
        'BDUSS' => $bduss,
        '_client_id' => 'wappc_' . mt_rand(100000000, 999999999) . '_' . mt_rand(100, 999),
        '_client_type' => '2',
        '_client_version' => misc::clientVersion(),
        '_phone_imei' => md5(uniqid('', true)),
        'anonymous' => '0',
        'content' => $content,
        'fid' => (string) $fid,
        'kw' => $kw,
        'net_type' => '3',
        'tbs' => $tbs,
        'tid' => (string) $tid,
        'title' => '',
    );
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
        return array(0, '回帖成功');
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

function cron_ver4_reply_do()
{
    global $m;
    ver4_reply_ensure_table();

    $kws = array_values(array_filter(array_map('trim', explode("\n", (string) option::get('ver4_reply_kws'))), 'strlen'));
    $contents = array_values(array_filter(array_map('trim', explode("\n", (string) option::get('ver4_reply_contents'))), 'strlen'));
    if (empty($kws) || empty($contents)) {
        ver4_reply_log('未配置目标贴吧或回帖内容库，跳过执行');
        return;
    }
    $perKw = max(1, (int) option::get('ver4_reply_num'));
    $daily = max(1, (int) option::get('ver4_reply_daily'));
    $sleep = max(0, (int) option::get('ver4_reply_sleep'));
    $day = date('Ymd');
    $okCount = 0;
    $failCount = 0;
    $skipCount = 0;

    $rs = $m->query("SELECT `id`, `bduss` FROM `" . DB_NAME . "`.`" . DB_PREFIX . "baiduid` ORDER BY `id` ASC");
    while ($acc = $m->fetch_array($rs)) {
        $pid = (int) $acc['id'];
        $bduss = P::decrypt($acc['bduss']);
        if (empty($bduss)) {
            continue;
        }
        //每账号每日上限
        $used = $m->once_fetch_array("SELECT COUNT(*) AS c FROM `" . DB_PREFIX . "ver4_reply_log` WHERE `pid` = {$pid} AND `day` = '{$day}'");
        $todayCount = (int) $used['c'];
        if ($todayCount >= $daily) {
            $skipCount++;
            continue;
        }
        $tbs = misc::getTbs(0, $bduss, $pid);
        if (empty($tbs)) {
            ver4_reply_ensure_table();
            $m->query("INSERT IGNORE INTO `" . DB_PREFIX . "ver4_reply_log` (`pid`, `tid`, `kw`, `status`, `msg`, `day`, `created_at`) VALUES ({$pid}, 0, '', 2, 'TBS 获取失败（账号可能已失效）', '{$day}', UNIX_TIMESTAMP())");
            continue;
        }
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
                $dup = $m->once_fetch_array("SELECT COUNT(*) AS c FROM `" . DB_PREFIX . "ver4_reply_log` WHERE `pid` = {$pid} AND `tid` = {$tid} AND `day` = '{$day}'");
                if (!empty($dup['c'])) {
                    $skipCount++;
                    continue;
                }
                $content = $contents[mt_rand(0, count($contents) - 1)];
                list($rCode, $rMsg) = ver4_reply_post($bduss, $tbs, $kw, $fid, $tid, $content, $pid);
                $status = $rCode === 0 ? 1 : ($rCode === -2 ? 3 : 2);
                $msgSafe = $m->real_escape_string($rMsg . ' @' . $t['title']);
                $m->query("INSERT IGNORE INTO `" . DB_PREFIX . "ver4_reply_log` (`pid`, `tid`, `kw`, `status`, `msg`, `day`, `created_at`) VALUES ({$pid}, {$tid}, '{$kwSafe}', {$status}, '{$msgSafe}', '{$day}', UNIX_TIMESTAMP())");
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
    ver4_reply_log("自动回帖完成：成功 {$okCount}，失败 {$failCount}，跳过 {$skipCount}");
}
