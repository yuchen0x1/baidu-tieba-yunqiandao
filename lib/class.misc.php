<?php

if (!defined('SYSTEM_ROOT')) {
    die('Insufficient Permissions');
}

/**
 * 其他功能类
 */

class misc
{
    /**
     * 快捷发送一封邮件
     * @param string $to 收件人
     * @param string $sub 邮件主题
     * @param string $msg 邮件内容(HTML)
     * @param array $att 附件，每个键为文件名称，值为附件内容（可以为二进制文件），例如array('a.txt' => 'abcd' , 'b.png' => file_get_contents('x.png'))
     * @return bool 成功:true 失败：错误消息
     */
    public static function mail($to, $sub = '无主题', $msg = '无内容', $att = array())
    {
        $From = option::get('mail_name');
        if (option::get('mail_mode') == 'SMTP') {
            $Host = option::get('mail_host');
            $Port = intval(option::get('mail_port'));
            $SMTPAuth = option::get('mail_auth');
            $Username = option::get('mail_smtpname');
            $Password = option::get('mail_smtppw');
            $Nickname = option::get('mail_yourname');
            $Secure = option::get('mail_secure');
            $mail = new SMTP($Host, $Port, $SMTPAuth, $Username, $Password, $Secure);
            $mail->att = $att;
            if ($mail->send($to, $From, $sub, $msg, $Nickname)) {
                return true;
            } else {
                return $mail->log;
            }
        } else {
            $header  = "MIME-Version:1.0\r\n";
            $header .= 'Content-type: text/html; charset=utf-8' . "\r\n";
            $header .= "To: " . $to . "\r\n";
            $header .= "From: " . $From . "\r\n";
            $header .= "Subject: " . $sub . "\r\n";
            $header .= 'Reply-To: ' . $From . "\r\n";
            $header .= "Date: " . date("r") . "\r\n";
            $header .= "Content-Transfer-Encoding: base64\r\n";
            return mail($to, $sub, base64_encode($msg), $header);
        }
    }

    /**
     * 通过UID判断某个用户是不是VIP
     * @param string $uid UID
     * @return bool VIP=true
     */
    public static function isvip($uid)
    {
        global $m;
        $x = $m->once_fetch_array("SELECT * FROM `" . DB_PREFIX . "users` WHERE `id` = '{$uid}';");
        if ($x['role'] == 'vip' || $x['role'] == 'admin') {
            return true;
        } else {
            return false;
        }
    }

    /**
     * 通过UID获得指定用户的贴吧数据表
     * @param string $uid UID
     */
    public static function getTable($uid)
    {
        global $m;
        $x = $m->once_fetch_array("SELECT * FROM `" . DB_PREFIX . "users` WHERE `id` = '{$uid}';");
        return $x['t'];
    }

    /**
     * 寻找已缓存的贴吧 FID
     * @param string $kw 贴吧名
     * @return string|boolean FID，如果没有缓存则返回false
     */
    public static function findFid($kw)
    {
        global $i;
        global $m;
        foreach ($i['table'] as $v) {
            $r = $m->once_fetch_array("SELECT * FROM `" . DB_PREFIX . $v . "` WHERE `tieba` = '{$kw}' AND `fid` IS NOT NULL LIMIT 1");
            if (!empty($r['fid'])) {
                return $r['fid'];
                break;
            }
        }
        return false;
    }

    /**
     * 批量设置贴吧 FID
     * @param string $kw 贴吧名
     * @param string $fid FID
     */

    public static function mSetFid($kw, $fid)
    {
        global $m,$i;
        if (empty($fid)) {
            return false;
        }
        foreach ($i['table'] as $v) {
            $r = $m->query("UPDATE  `" . DB_PREFIX . $v . "` SET  `fid` =  '{$fid}' WHERE  `" . DB_PREFIX . $v . "`.`tieba` = '{$kw}';");
        }
    }

    /**
     * 得到贴吧 FID
     * @param string $kw 贴吧名
     * @return string FID
     */

    public static function getFid($kw)
    {
        global $m;
        $f  = misc::findFid($kw);
        if ($f) {
            return $f;
        } else {
            $ch = new wcurl('https://tieba.baidu.com/f/commit/share/fnameShareApi?ie=utf-8&fname=' . urlencode($kw), array('User-Agent: Mozilla/5.0 (iPhone; CPU iPhone OS 14_2 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/14.0.1 Mobile/15E148 Safari/604.1','Referer: https://tieba.baidu.com/'));
            self::applyProxy($ch);
            $r = json_decode($ch->exec(), true);
            return intval($r['no']) === 0 ? $r['data']['fid'] : false;
        }
    }

    /**
     * 得到TBS
     */
    private static $tbs_cache = [];
    public static function getTbs($uid, $bduss, $pid = 0)
    {
        if (isset(self::$tbs_cache[$pid][$bduss])) {
            return self::$tbs_cache[$pid][$bduss];
        }
        $ch = new wcurl('https://tieba.baidu.com/dc/common/tbs');
        self::applyProxy($ch, $pid);
        $ch->addcookie("BDUSS=" . $bduss);
        $x = json_decode($ch->exec(), true);
        self::$tbs_cache[$pid][$bduss] = isset($x['tbs']) && isset($x['is_login']) && $x['is_login']
            ? $x['tbs']
            : '';
        return self::$tbs_cache[$pid][$bduss];
    }

    /**
     * 当前模拟的贴吧客户端版本号（可在后台"签到设置"中修改）
     * @return string
     */
    public static function clientVersion()
    {
        $ver = option::get('client_version');
        return empty($ver) ? '12.22.1.0' : $ver;
    }

    /**
     * 对输入的数组添加客户端验证代码（tiebaclient!!!）
     * @param array $data 数组
     */
    public static function addTiebaSign(&$data, $withClientType = true)
    {
        $data["_client_version"] = self::clientVersion();
        if (option::get('client_rotate') !== '0') {
            //客户端指纹轮换：随机切换客户端类型，并追加随机设备标识，防止请求特征固化
            if ($withClientType) {
                $data["_client_type"] = (string) mt_rand(1, 4);
            }
            if (!isset($data["_client_id"])) {
                $data["_client_id"] = 'wappc_' . mt_rand(100000000, 999999999) . '_' . mt_rand(100, 999);
            }
            if (!isset($data["_phone_imei"])) {
                $data["_phone_imei"] = md5(uniqid('', true));
            }
        } elseif ($withClientType) {
            $data["_client_type"] = "4";
        }
        ksort($data);
        $x = '';
        foreach ($data as $k => $v) {
            $x .= $k . '=' . $v;
        }
        $data['sign'] = strtoupper(md5($x . 'tiebaclient!!!'));
    }

    /**
     * 解析代理字符串，格式：type://[user:pass@]host:port
     * @param string $str 代理字符串
     * @return array|false 解析失败返回 false
     */
    public static function parseProxyUrl($str)
    {
        $str = trim((string) $str);
        if ($str === '' || strcasecmp($str, 'off') === 0) {
            return false;
        }
        $type = 'http';
        if (preg_match('/^(https?|socks5):\/\//i', $str, $m)) {
            $type = strtolower($m[1]) === 'socks5' ? 'socks5' : 'http';
            $str = substr($str, strlen($m[0]));
        }
        $auth = array('user' => '', 'pass' => '');
        $authAt = strrpos($str, '@');
        if ($authAt !== false) {
            $authPart = substr($str, 0, $authAt);
            $str = substr($str, $authAt + 1);
            $kv = explode(':', $authPart, 2);
            $auth = array(
                'user' => rawurldecode(isset($kv[0]) ? $kv[0] : ''),
                'pass' => rawurldecode(isset($kv[1]) ? $kv[1] : '')
            );
        }
        $port = '';
        if (preg_match('/^\[([0-9a-f:]+)\]:(\d{1,5})$/i', $str, $hp)) {
            $host = $hp[1];
            $port = $hp[2];
        } elseif (preg_match('/^([a-z0-9.-]+):(\d{1,5})$/i', $str, $hp)) {
            $host = $hp[1];
            $port = $hp[2];
        } else {
            return false;
        }
        if ((int) $port < 1 || (int) $port > 65535) {
            return false;
        }
        return array_merge(array('type' => $type, 'host' => $host, 'port' => $port), $auth);
    }

    /**
     * 取某个百度账号的代理配置（账号级覆盖优先于全局设置）
     * @param int $pid 百度账号 PID，0 表示只取全局设置
     * @return array|false 无代理时返回 false
     */
    public static function getProxy($pid = 0)
    {
        global $m;
        if ($pid > 0) {
            static $columnExists = null;
            if ($columnExists === null) {
                $col = $m->once_fetch_array("SELECT COUNT(*) AS c FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = '" . DB_PREFIX . "baiduid' AND column_name = 'proxy'");
                $columnExists = !empty($col['c']);
            }
            if ($columnExists) {
                $row = $m->once_fetch_array("SELECT `proxy` FROM `" . DB_PREFIX . "baiduid` WHERE `id` = " . (int) $pid);
                $parsed = self::parseProxyUrl(isset($row['proxy']) ? $row['proxy'] : '');
                if ($parsed !== false) {
                    return $parsed;
                }
                // 账号级显式关闭(off)时不再回退全局
                if (strcasecmp(trim((string) (isset($row['proxy']) ? $row['proxy'] : '')), 'off') === 0) {
                    return false;
                }
            }
        }
        $proxyHost = trim((string) option::get('proxy_host'));
        if ((string) option::get('proxy_type') === '0' || $proxyHost === '') {
            return false;
        }
        // 支持 host:port 与 [IPv6]:port 两种写法
        $host = '';
        $port = '';
        if (preg_match('/^\[([0-9a-f:]+)\]:(\d{1,5})$/i', $proxyHost, $mm)) {
            $host = $mm[1];
            $port = $mm[2];
        } elseif (preg_match('/^([a-z0-9.-]+):(\d{1,5})$/i', $proxyHost, $mm)) {
            $host = $mm[1];
            $port = $mm[2];
        }
        if ($host === '' || (int) $port < 1 || (int) $port > 65535) {
            return false;
        }
        return array(
            'type' => (string) option::get('proxy_type') === '2' ? 'socks5' : 'http',
            'host' => $host,
            'port' => $port,
            'user' => (string) option::get('proxy_user'),
            'pass' => (string) option::get('proxy_pass'),
        );
    }

    /**
     * 为 wcurl 实例应用代理配置
     * @param wcurl $ch cURL 实例
     * @param int   $pid 百度账号 PID，0 表示全局设置
     * @return wcurl
     */
    public static function applyProxy($ch, $pid = 0)
    {
        if ($ch instanceof wcurl) {
            $ch->setProxy((array) self::getProxy($pid));
        }
        return $ch;
    }

    /**
     * 得到BDUSS
     * @param int|string $pid 用户PID
     * @param bool $withStoken 是否带有stoken
     */
    public static function getCookie($pid, $withStoken = false)
    {
        global $m;
        if (empty($pid)) {
            return false;
        }
        $temp = $m->fetch_array($m->query("SELECT * FROM `" . DB_NAME . "`.`" . DB_PREFIX . "baiduid` WHERE `id` = {$pid} LIMIT 1"));
        return $withStoken ? ["bduss" => P::decrypt($temp['bduss']), "stoken" => P::decrypt($temp['stoken'])] : P::decrypt($temp['bduss']);
    }

    /**
     * 50贴吧客户端一键签到
     */
    public static function DoSign_Onekey($uid, $kw, $id, $pid, $fid, $ck)
    {
        $ch = new wcurl('https://c.tieba.baidu.com/c/c/forum/msign', array(
            'User-Agent: bdtb for Android 6.5.8'
        ));
        self::applyProxy($ch, $pid);
        $ch->addcookie(array('BDUSS' => $ck));
        $temp = array(
            'fid' => $fid,
            'kw' => $kw,
            'tbs' => misc::getTbs($uid, $ck, $pid)
        );
        self::addTiebaSign($temp);
        return $ch->post($temp);
    }

    /**
     * 手机网页签到
     */
    public static function DoSign_Mobile($uid, $kw, $id, $pid, $fid, $ck)
    {
        //没问题了
        $ch = new wcurl('https://tieba.baidu.com/mo/q/sign?tbs=' . misc::getTbs($uid, $ck, $pid) . '&kw=' . urlencode($kw) . '&is_like=1&fid=' . $fid, array('User-Agent: Mozilla/5.0 (iPhone; CPU iPhone OS 14_2 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/14.0.1 Mobile/15E148 Safari/604.1','Referer: https://tieba.baidu.com/f?kw=' . $kw , 'Host: tieba.baidu.com','X-Forwarded-For: ' . mt_rand(0, 255) . '.' . mt_rand(0, 255) . '.' . mt_rand(0, 255) . '.' . mt_rand(0, 255), 'Origin: https://tieba.baidu.com', 'Connection: Keep-Alive'));
        self::applyProxy($ch, $pid);
        $ch->addcookie(array('BDUSS' => $ck,'BAIDUID' => strtoupper(md5(time()))));
        return $ch->exec();
    }

    /**
     * 网页签到
     */
    public static function DoSign_Default($uid, $kw, $id, $pid, $fid, $ck)
    {
        global $m,$today;
        $cookie = array('BDUSS' => $ck,'BAIDUID' => strtoupper(md5(time())));
        $ch = new wcurl('https://tieba.baidu.com/mo/m?kw=' . urlencode($kw) . '&fid=' . $fid, array('User-Agent: Mozilla/5.0 (iPhone; CPU iPhone OS 14_2 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/14.0.1 Mobile/15E148 Safari/604.1','Referer: https://wapp.baidu.com/','Content-Type: application/x-www-form-urlencoded'));
        self::applyProxy($ch, $pid);
        $ch->addcookie($cookie);
        $s  = $ch->exec();
        $ch->close();
        preg_match('/\<td style=\"text-align:right;\"\>\<a href=\"(.*)\"\>签到\<\/a\>\<\/td\>\<\/tr\>/', $s, $s);
        if (isset($s[1])) {
            $ch = new wcurl(
                'https://tieba.baidu.com' . $s[1],
                array(
                    'Accept: text/html, application/xhtml+xml, */*',
                    'Accept-Language: zh-Hans-CN,zh-Hans;q=0.8,en-US;q=0.5,en;q=0.3',
                    'User-Agent: Mozilla/5.0 (iPhone; CPU iPhone OS 14_2 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/14.0.1 Mobile/15E148 Safari/604.1'
                )
            );
            self::applyProxy($ch, $pid);
            $ch->addcookie($cookie);
            $ch->exec();
            $ch->close();
//临时判断解决方案
            $ch = new wcurl('https://tieba.baidu.com/mo/m?kw=' . urlencode($kw) . '&fid=' . $fid, array('User-Agent: Mozilla/5.0 (iPhone; CPU iPhone OS 14_2 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/14.0.1 Mobile/15E148 Safari/604.1','Referer: https://wapp.baidu.com/','Content-Type: application/x-www-form-urlencoded'));
            self::applyProxy($ch, $pid);
            $ch->addcookie($cookie);
            $s = $ch->exec();
            $ch->close();
//如果找不到这段html则表示没有签到则stripos()返回false，同时is_bool()返回true，最终返回false
            return !is_bool(stripos($s, '<td style="text-align:right;"><span >已签到</span></td>'));
        } else {
            return true;
        }
    }

    /**
     * 客户端签到
     */
    public static function DoSign_Client($uid, $kw, $id, $pid, $fid, $ck)
    {
        $ch = new wcurl('https://c.tieba.baidu.com/c/c/forum/sign');
        self::applyProxy($ch, $pid);
        $ch->addcookie("BDUSS=" . $ck);
        $temp = array(
            'BDUSS' => misc::getCookie($pid),
            'fid' => $fid,
            'kw' => $kw,
            'tbs' => misc::getTbs($uid, $ck, $pid)
        );
        self::addTiebaSign($temp);
        return $ch->post($temp);
    }

    /**
     * 对一个贴吧执行完整的签到任务
     */
    public static function DoSign_All($uid, $kw, $id, $table, $sign_mode, $pid, $fid)
    {
        global $m;
        $again_error_id     = 160002;
//重复签到错误代码
        $again_error_id_2   = 1101;
//特殊的重复签到错误代码！！！签到过快=已签到
        $again_error_id_3   = 1102;
//特殊的重复签到错误代码！！！签到过快=已签到
        $status_succ    = false;
        $ck = misc::getCookie($pid);
        $kw = addslashes($kw);
        $today = date('d');
        if (empty($fid)) {
            $fid = misc::getFid($kw);
            $m->pquery("UPDATE `" . DB_PREFIX . $table . "` SET `fid` = ? WHERE `" . DB_PREFIX . $table . "`.`id` = ?", array((string) $fid, (int) $id));
        }

        //dump(json_decode(self::DoSign_Client($uid,$kw,$id,$pid,$fid,$ck),true),true);die;

        $expGained = 0;
        $signRank = 0;
        $forumLevel = 0;

        if (!empty($sign_mode) && in_array('1', $sign_mode) && $status_succ === false) {
            $r = self::DoSign_Client($uid, $kw, $id, $pid, $fid, $ck);
            $v = json_decode($r, true);
            if ($v != $r && $v != null) {
            //decode失败时会直接返回原文或NULL
                if (empty($v['error_code']) || $v['error_code'] == $again_error_id) {
                    $status_succ = true;
                    // 解析客户端签到返回中的成长经验、排名、等级
                    if (!empty($v['user_info'])) {
                        $uInfo = $v['user_info'];
                        $expGained = isset($uInfo['sign_bonus']) ? (int) $uInfo['sign_bonus'] : (isset($uInfo['c_sign_bonus']) ? (int) $uInfo['c_sign_bonus'] : 0);
                        $signRank = isset($uInfo['user_sign_rank']) ? (int) $uInfo['user_sign_rank'] : 0;
                        $forumLevel = isset($uInfo['level_id']) ? (int) $uInfo['level_id'] : 0;
                    }
                } else {
                    $error_code = $v['error_code'];
                    $error_msg  = $v['error_msg'];
                }
            }
        }

        if (!empty($sign_mode) && in_array('3', $sign_mode) && $status_succ === false) {
            $r = self::DoSign_Mobile($uid, $kw, $id, $pid, $fid, $ck);
            $v = json_decode($r, true);
            if ($v != $r && $v != null) {
            //decode失败时会直接返回原文或NULL
                if (empty($v['no']) || $v['no'] == $again_error_id_2 || $v['no'] == $again_error_id_3) {
                    $status_succ = true;
                    if (!empty($v['data']['sign_version'])) {
                        $expGained = isset($v['data']['sign_bonus']) ? (int) $v['data']['sign_bonus'] : 0;
                    }
                } else {
                    $error_code  = $v['no'];
                    $error_msg   = $v['error'];
                }
            }
        }

        if (!empty($sign_mode) && in_array('2', $sign_mode) && $status_succ === false) {
            if (self::DoSign_Default($uid, $kw, $id, $pid, $fid, $ck) === true) {
                $status_succ = true;
            }
        }

        if ($status_succ === true) {
            $m->pquery("UPDATE `" . DB_NAME . "`.`" . DB_PREFIX . $table . "` SET `latest` = ?, `status` = 0, `last_error` = NULL WHERE `" . DB_PREFIX . $table . "`.`id` = ?", array($today, (int) $id));
            if (class_exists('SignManager')) {
                SignManager::logTiebaResult($pid, $uid, $kw, 'success', 0, '', $expGained, $signRank, $forumLevel);
            }
        } elseif (!empty($error_code)) {
            //error_msg 来自百度接口返回，可能含引号等字符，必须参数化否则落库失败且无法复签
            $m->pquery("UPDATE `" . DB_NAME . "`.`" . DB_PREFIX . $table . "` SET `latest` = ?, `status` = ?, `last_error` = ? WHERE `" . DB_PREFIX . $table . "`.`id` = ?", array($today, (string) $error_code, (string) $error_msg, (int) $id));
            if (class_exists('SignManager')) {
                SignManager::logTiebaResult($pid, $uid, $kw, 'error', (int) $error_code, $error_msg);
            }
        } else {
            if (class_exists('SignManager')) {
                SignManager::logTiebaResult($pid, $uid, $kw, 'error', 0, '签到接口未返回可识别的结果');
            }
        }

        usleep(option::get('sign_sleep') * 1000);
        $jitter = (int) option::get('sign_jitter');
        if ($jitter > 0) {
            usleep(mt_rand(0, $jitter) * 1000);
        }
    }

    /**
     * 判断是否允许复签（供冒烟测试覆盖）
     * @param string $retryMax  retry_max 选项：'0'=不限次数，'-1'=禁止复签，其他=次数上限
     * @param mixed  $signAgain unserialize 后的 cron_sign_again 选项
     * @param string $today     Y-m-d 格式的当天日期
     * @return bool
     */
    public static function shouldRetry($retryMax, $signAgain, $today)
    {
        if (!is_array($signAgain)) {
            $signAgain = array();
        }
        if ($retryMax == '0') {
            return true;
        }
        if ($retryMax == '-1') {
            return false;
        }
        return isset($signAgain['lastdo'])
            && $signAgain['lastdo'] == $today
            && isset($signAgain['num'])
            && $signAgain['num'] <= $retryMax;
    }

    /**
     * 智能重试退避判定：第 1、2 轮立即重试，之后按 2 的幂轮次重试（4、8、16…），
     * 避免复签任务每分钟轰炸百度接口。$num 为当天已执行的复签轮数（从 0 计）。
     * @param mixed $signAgain unserialize 后的 cron_sign_again 选项
     * @param string $today    Y-m-d 格式的当天日期
     * @return bool
     */
    public static function shouldRetrySmart($signAgain, $today)
    {
        if (!is_array($signAgain) || !isset($signAgain['lastdo']) || $signAgain['lastdo'] != $today) {
            return false;
        }
        $num = isset($signAgain['num']) ? (int) $signAgain['num'] : 0;
        if ($num >= 63) {
            return false; //一天最多退避到约 64 分钟间隔
        }
        $round = $num + 1; //本轮序号（1 起）
        return ($round & ($round - 1)) === 0; //轮序号为 2 的幂时允许重试
    }

    /**
     * 签到时段判定：配置了签到时段结束时仅在该时段内签到（支持跨零点），
     * 未配置时保持旧行为（sign_hour 点（含）之前忽略；sign_hour 为 -1/空表示不限制）
     * @return false|string false=允许签到，字符串=被阻止的原因
     */
    public static function signWindowBlocked()
    {
        $startOpt = option::get('sign_hour');
        $startH = ($startOpt === '' || $startOpt === null) ? -1 : (int) $startOpt;
        $nowH = (int) date('H');
        $endOpt = option::get('sign_window_end');
        if ($endOpt === '' || $endOpt === null) {
            return ($startH >= 0 && $nowH <= $startH) ? $startH . '点时忽略签到' : false;
        }
        $endH = (int) $endOpt;
        if ($startH === $endH) {
            return false;
        }
        if ($startH < $endH) {
            $in = ($nowH > $startH && $nowH <= $endH);
        } else {
            $in = ($nowH > $startH || $nowH <= $endH); //跨零点时段
        }
        return $in ? false : '不在签到时段内';
    }

    /**
     * 执行一个表的签到任务
     * @param string $table 表
     */
    public static function DoSign($table, $onlyUid = 0)
    {
        global $m;
        require_once SYSTEM_ROOT . '/lib/security.php';
        $sign_mode = safe_sign_modes(option::get('sign_mode'));
        $today = date('d');
        $uidWhere = (int) $onlyUid > 0 ? ' AND `uid` = ' . (int) $onlyUid : '';
        $windowBlocked = self::signWindowBlocked();
        if ($windowBlocked !== false) {
            return $windowBlocked;
        }

        $limit = option::get('cron_limit');
//处理所有未签到的贴吧
        if ($limit == 0) {
            $q  = array();
            $qs = $m->query("SELECT * FROM  `" . DB_NAME . "`.`" . DB_PREFIX . $table . "` WHERE `no` = 0 AND `latest` != '" . $today . "'{$uidWhere}");
            while ($qss = $m->fetch_array($qs)) {
                $q[] = array(
                    'id'     => $qss['id'],
                    'uid'    => $qss['uid'],
                    'pid'    => $qss['pid'],
                    'fid'    => $qss['fid'],
                    'tieba'  => $qss['tieba'],
                    'no'     => $qss['no'],
                    'status' => $qss['status'],
                    'latest' => $qss['latest'],
                    'last_error'    => $qss['last_error']
                );
            }
            if (option::get('sign_shuffle') != '0') {
                shuffle($q);
            }
        } else {
            $q = rand_row(DB_PREFIX . $table, 'id', $limit, "`no` = 0 AND `latest` != '{$today}'{$uidWhere}", true);
        }

        foreach ($q as $x) {
            self::DoSign_All($x['uid'], $x['tieba'], $x['id'], $table, $sign_mode, $x['pid'], $x['fid']);
        }
    }

    /**
     * 执行一个表的签到重試任务
     * @param string $table 表
     */
    public static function DoSign_retry($table, $onlyUid = 0)
    {
        global $m;
        $uidWhere = (int) $onlyUid > 0 ? ' AND `uid` = ' . (int) $onlyUid : '';
        $today = date('Y-m-d');
        $todayDay = date('d');
        $windowBlocked = self::signWindowBlocked();
        if ($windowBlocked !== false) {
            return $windowBlocked;
        }

        $x = $m->fetch_array($m->query("SELECT count(id) AS `c` FROM `" . DB_NAME . "`.`" . DB_PREFIX . $table . "` WHERE `no` = 0 AND `latest` != '{$todayDay}'{$uidWhere} "));
        if ($x['c'] == 0) {
            $limit = option::get('cron_limit');
            require_once SYSTEM_ROOT . '/lib/security.php';
            $sign_mode = safe_sign_modes(option::get('sign_mode'));
            $sign_again = safe_retry_state(option::get('cron_sign_again'));
            $retry_max  = option::get('retry_max');
            $smartRetry = option::get('retry_smart') != '0';
            $q = array();
            $x = array();
        //重新尝试签到出错的贴吧
            if ($limit == 0) {
                if ($smartRetry) {
                    if (self::shouldRetrySmart($sign_again, $today)) {
                        $qs = $m->query("SELECT * FROM  `" . DB_NAME . "`.`" . DB_PREFIX . $table . "` WHERE `no` = 0 AND `status` IN (340011,2280007,110001,1989004,255){$uidWhere}");
                        while ($qss = $m->fetch_array($qs)) {
                            $q[] = array(
                                'id'     => $qss['id'],
                                'uid'    => $qss['uid'],
                                'pid'    => $qss['pid'],
                                'fid'    => $qss['fid'],
                                'tieba'  => $qss['tieba'],
                                'no'     => $qss['no'],
                                'status' => $qss['status'],
                                'latest' => $qss['latest'],
                                'last_error'    => $qss['last_error']
                            );
                        }
                        if (option::get('sign_shuffle') != '0') {
                            shuffle($q);
                        }
                    }
                } elseif (self::shouldRetry($retry_max, $sign_again, $today)) {
                    $qs = $m->query("SELECT * FROM  `" . DB_NAME . "`.`" . DB_PREFIX . $table . "` WHERE `no` = 0 AND `status` IN (340011,2280007,110001,1989004,255){$uidWhere}");
                    while ($qss = $m->fetch_array($qs)) {
                        $q[] = array(
                            'id'     => $qss['id'],
                            'uid'    => $qss['uid'],
                            'pid'    => $qss['pid'],
                            'fid'    => $qss['fid'],
                            'tieba'  => $qss['tieba'],
                            'no'     => $qss['no'],
                            'status' => $qss['status'],
                            'latest' => $qss['latest'],
                            'last_error'    => $qss['last_error']
                        );
                    }
                    if (option::get('sign_shuffle') != '0') {
                        shuffle($q);
                    }
                }
            } else {
                if ($smartRetry) {
                    if (self::shouldRetrySmart($sign_again, $today)) {
                        $q = rand_row(DB_PREFIX . $table, 'id', $limit, "`no` = 0 AND `status` IN (340011,2280007,110001,1989004,255) AND `latest` = '{$todayDay}'{$uidWhere}", true);
                    }
                } elseif (self::shouldRetry($retry_max, $sign_again, $today)) {
                    $q = rand_row(DB_PREFIX . $table, 'id', $limit, "`no` = 0 AND `status` IN (340011,2280007,110001,1989004,255) AND `latest` = '{$todayDay}'{$uidWhere}", true);
                }
            }

            foreach ($q as $x) {
                self::DoSign_All($x['uid'], $x['tieba'], $x['id'], $table, $sign_mode, $x['pid'], $x['fid']);
            }
        }
    }

    /**
     * 登录百度
     * @param string $bd_name 百度用户名
     * @param string $bd_pw百度密码
     * @param string $verifycode 验证码
     * @param string $vcodestr 验证字符
     * @return array [0成功|-1网络请求失败|-2json解析失败|-3表示需要验证码或验证码错误|2表示登陆失败|其他为百度提供的错误代码, 成功为BDUSS|需要验证码则返回vcodestr|其他错误返回百度提供的错误信息, 如果登陆成功，返回百度用户名|如果需要验证码，则此处返回验证图片地址 ]
     */
    public static function loginBaidu($bd_name, $bd_pw, $verifycode = '', $vcodestr = '')
    {
        $x = new wcurl('https://c.tieba.baidu.com/c/s/login');
        self::applyProxy($x);
        $p = array(
                'passwd'      => base64_encode($bd_pw),
                'timestamp'   => time() . '156',
                'un'          => $bd_name,
        );
        if (!empty($verifycode) && !empty($vcodestr)) {
            $p['vcode'] = $verifycode;
            $p['vcode_md5'] = $vcodestr;
        }
        self::addTiebaSign($p);
        if (!$data = $x->post($p)) {
            return array(-1, '网络请求失败');
        }
        if (!$v = json_decode($data, true)) {
            return array(-2, 'json解析失败');
        }
        if (!empty($v['user'])) {
            $md5pos = strpos($v['user']['BDUSS'], '|');
            if (!empty($md5pos)) {
                $bduss = substr($v['user']['BDUSS'], 0, $md5pos);
            } else {
                $bduss = $v['user']['BDUSS'];
            }
        }
        if ($v['error_code'] == '0') {
            return array(0, $bduss, $v['user']['name']);
        } else {
            switch ($v['error_code']) {
                case '5': //需要验证码或验证码输入错误
                case '6':
                    if (!isset($v['anti']['vcode_md5']) || !isset($v['anti']['vcode_pic_url'])) {
                        return array(-3, '', '');
                    }
                    // 首次遇到验证码时，若已配置打码平台则自动识别后重试一次
                    if (empty($verifycode) && class_exists('dama') && dama::enabled()) {
                        $img = self::fetchCaptchaImage($v['anti']['vcode_pic_url']);
                        $code = dama::solve($img);
                        if ($code !== false && !empty($code)) {
                            return self::loginBaidu($bd_name, $bd_pw, $code, $v['anti']['vcode_md5']);
                        }
                    }
                    return array(-3, $v['anti']['vcode_md5'], $v['anti']['vcode_pic_url']);
                break;
                default: //其他错误
                    return array((int)$v['error_code'], $v['error_msg']);
                break;
            }
        }
    }

    /**
     * 抓取验证码图片内容（供打码平台识别）
     * @param string $url 验证码图片地址
     * @return string 图片二进制内容，失败返回空字符串
     */
    public static function fetchCaptchaImage($url)
    {
        if (empty($url) || !wcurl::isAllowedPublicUrl($url)) {
            return '';
        }
        try {
            $ch = new wcurl($url, array('Referer: https://tieba.baidu.com/'));
            self::applyProxy($ch);
            $ch->setTimeOut(15000);
            return (string) $ch->exec();
        } catch (Exception $e) {
            return '';
        }
    }

    /*
     * 获取指定pid用户userid
     */
    // out to date
    public static function getUserid($pid)
    {

        global $m;
        $ub  = $m->once_fetch_array("SELECT portrait FROM `" . DB_PREFIX . "baiduid` WHERE `id` = '{$pid}';");
        return self::getUseridByPortrait($ub['portrait']);
    }
    /*
     * 获取指定portrait的userid
     */
    // out to date
    public static function getUseridByPortrait($portrait)
    {

        $ur = getUserInfo($portrait, false);
        $userid = (isset($ur["no"]) && $ur["no"] === 0) ? $ur['data']['id'] : 0;
        return $userid;
    }
    /*
    * uid转旧版portrait
     */
    public static function uid2LegacyPortrait($uid)
    {

        //貌似对uid较大的新账号没啥效果?
        $stric = str_pad(dechex(trim($uid)), 8, 0, STR_PAD_LEFT);
        $sc = '';
        for ($i = 6; $i >= 0; $i -= 2) {
            $sc .= substr($stric, $i, 2);
        }
        return $sc;
    }
    /*
     * 获取指定pid
     */
    public static function getTieba($userid, $bduss, $pn)
    {

        $head = array();
        $head[] = 'Content-Type: application/x-www-form-urlencoded';
        $head[] = 'User-Agent: Mozilla/5.0 (SymbianOS/9.3; Series60/3.2 NokiaE72-1/021.021; Profile/MIDP-2.1 Configuration/CLDC-1.1 ) AppleWebKit/525 (KHTML, like Gecko) Version/3.0 BrowserNG/7.1.16352';
        $tl = new wcurl('https://c.tieba.baidu.com/c/f/forum/like', $head);
        self::applyProxy($tl);
        $data = array(
            'BDUSS' => $bduss,
            'friend_uid' => $userid,
            'page_no' => $pn,
            'page_size' => 200,
        );
        self::addTiebaSign($data, false);
        $tl->addCookie(array('BDUSS' => $bduss));
        $tl->set(CURLOPT_RETURNTRANSFER, true);
        $rt = $tl->post($data);
        return $rt;
    }

    public static function getTieba2($bduss, $stoken, $pn = 1)
    {

        $tl = new wcurl("https://tieba.baidu.com/mg/o/getForumHome?st=0&pn={$pn}&rn=200", ['User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/85.0.4183.102 Safari/537.36']);
        self::applyProxy($tl);
        $tl->addCookie(array('BDUSS' => $bduss, 'STOKEN' => $stoken));
        return $tl->get();
    }

    public static function getTieba3($bduss, $stoken, $pn = 1, $pid = 0)
    {
        $head = [
            'Subapp-Type: hybrid',
            'Content-Type: application/x-www-form-urlencoded',
            'User-Agent: tieba/' . self::clientVersion(),
        ];
        $tl = new wcurl('https://tieba.baidu.com/c/f/forum/forumGuide', $head);
        self::applyProxy($tl, $pid);
        $data = array(
            'BDUSS' => $bduss,
            'stoken' => $stoken,
            'page_no' => $pn,
            'res_num' => '200',
            'sort_type' => '3',
            'call_from' => '3',
            'tbs' => misc::getTbs(0, $bduss),
        );
        self::addTiebaSign($data, false);
        $tl->addCookie(array('BDUSS' => $bduss));
        $tl->set(CURLOPT_RETURNTRANSFER, true);
        $rt = $tl->post($data);
        return $rt;
    }

    public static function scanTiebaByValidatedPid($pid)
    {
        $pid = (int) $pid;
        if ($pid <= 0) {
            return;
        }
        self::scanTiebaByPid($pid);
    }

    /**
     * 扫描指定PID的所有贴吧
     * @param string $pid PID
     */
    public static function scanTiebaByPid($pid)
    {

        global $m;
        $pid = (int) $pid;
        if ($pid <= 0) {
            return;
        }
        $cma    = $m->once_fetch_array("SELECT * FROM `" . DB_PREFIX . "baiduid` WHERE `id` = {$pid};");
        if (empty($cma)) {
            return;
        }
        $uid    = (int) $cma['uid'];
        $table  = self::getTable($uid);
        $tb     = $m->fetch_array($m->query("SELECT count(id) AS `c` FROM `" . DB_NAME . "`.`" . DB_PREFIX . $table . "` WHERE `uid` = {$uid}"));
        $bduss  = P::decrypt($cma['bduss']);
        $stoken = P::decrypt($cma['stoken']);
        $isvip  = self::isvip($uid);
        $pid    = $cma['id'];
        //$bid    = self::getUserid($pid);
        $o      = option::get('tb_max');
        $pn     = 1;
        $a      = 0;
        while (true) {
            //if (empty($bid)) break;
            $rc = self::getTieba3($bduss, $stoken, $pn, $pid);//fetch forum list //default 200 per page
            $rc = json_decode($rc, true);
            if (!$rc) {
                break;
            }
            $ngf = isset($rc["like_forum"]) ? $rc["like_forum"] : [];
            foreach ($ngf as $v) {
                if ($tb['c'] + $a >= $o && !empty($o) && !$isvip) {
                    break;
                }

                // 被封贴吧 => is_forbidden
                // 被合并的暂时没有办法直接判断，但签到时会有：340006#贴吧目录出问题啦，请到贴吧签到吧反馈
                if (!$v['forum_id'] || $v['is_forbidden']) {
                    continue;
                }

                $vn  = addslashes(htmlspecialchars($v['forum_name']));
                $ist = $m->once_fetch_array("SELECT COUNT(id) AS `c` FROM `" . DB_NAME . "`.`" . DB_PREFIX . $table . "` WHERE `pid` = {$pid} AND `tieba` = '{$vn}';");
                if ($ist['c'] == 0) {
                    $a++;
                    if (!$v['is_sign']) {
                        $latest = 0;
                    } else {
                        $latest = date('d');
                    }
                    $m->query("INSERT INTO `" . DB_NAME . "`.`" . DB_PREFIX . $table . "` (`pid`,`fid`, `uid`, `tieba`, `latest`) VALUES ({$pid},'{$v['forum_id']}', {$uid}, '{$vn}', {$latest});");
                }
            }
            $pn++;
            // 30 * 200 -> 6000
			// avoid loop
            if (!$rc["like_forum_has_more"] || $pn > 20) {
                break;
            }
        }
    }

    /**
     * 扫描指定用户的所有贴吧并储存
     * @param UID，如果留空，表示当前用户的UID
     */
    public static function scanTiebaByUser($uid = '')
    {

        global $i;
        global $m;
        set_time_limit(0);
        if (empty($uid)) {
            $bduss = $i['user']['bduss'];
        } else {
            $bx = $m->query("SELECT * FROM `" . DB_PREFIX . "baiduid` WHERE `uid` = '{$uid}';");
            while ($by = $m->fetch_array($bx)) {
                $upid         = $by['id'];
                $bduss[$upid] = $by['bduss'];
            }
        }
        $n      = 0;
        foreach ($bduss as $pid => $ubduss) {
            $t = self::scanTiebaByPid($pid);
        }
    }

    /**
     * 获得二维码及sign
     */
    public static function get_login_qrcode()
    {

        $resp = ["sign" => null, "imgurl" => null];
        $qrcodeUrl = "https://passport.baidu.com/v2/api/getqrcode?lp=pc";
        if (!wcurl::isAllowedPublicUrl($qrcodeUrl)) {
            return $resp;
        }
        $qrcode_ch = new wcurl($qrcodeUrl);
        self::applyProxy($qrcode_ch);
        $get_qrcode = json_decode($qrcode_ch->get(), true);
        if (isset($get_qrcode["imgurl"]) && isset($get_qrcode["sign"])) {
            // 百度返回的是无协议前缀的 host 路径，归一化为 https
            $imgurl = (string) $get_qrcode["imgurl"];
            if ($imgurl !== '' && stripos($imgurl, 'http') !== 0 && stripos($imgurl, '//') !== 0) {
                $imgurl = 'https://' . ltrim($imgurl, '/');
            }
            if (wcurl::isAllowedPublicUrl($imgurl)) {
                $resp = ["sign" => $get_qrcode["sign"], "imgurl" => $imgurl];
            }
        }
        return $resp;
    }
    public static function get_real_bduss($sign)
    {

        //status code
        //errno不等于0或1时需要要求更换二维码及sign
        //-1 更换二维码
        //0 进入下一步
        //1 无需操作
        //2 已确认
        $r = ["error" => 1, "bduss" => "", "stoken" => "", "msg" => ""];
        $unicast_ch = new wcurl("https://passport.baidu.com/channel/unicast?channel_id={$sign}&callback=");
        self::applyProxy($unicast_ch);
        $response = $unicast_ch->get();
        if ($response) {
            $responseParse = json_decode(str_replace(array("(",")"), '', $response), true);
            if (!$responseParse["errno"]) {
                $channel_v = json_decode($responseParse["channel_v"], true);
                if ($channel_v["status"]) {
                    $r["error"] = -1;
                    $r["msg"] = "Continue";
                } else {
                    $qrlogin_ch = new wcurl('https://passport.baidu.com/v3/login/main/qrbdusslogin?bduss=' . $channel_v["v"]);
                    self::applyProxy($qrlogin_ch);
                    $s_bduss = json_decode(preg_replace("/'([^'']+)'/", '"$1"', str_replace("\\&", "&", $qrlogin_ch->get())), true);
                    if ($s_bduss && $s_bduss["code"] === "110000") {
                        $r["error"] = 0;
                        $r["msg"] = "Success";
                        $r["bduss"] = $s_bduss["data"]["session"]["bduss"];
                        $r["stoken"] = self::qrlogin_parse_stoken($s_bduss["data"]["session"]["stokenList"])["tb"];
                    }
                }
            } else {
                $r["error"] = $responseParse["errno"];
            }
        } else {
            $r["error"] = -2;
            $r["msg"] = "Invalid QR Code";
        }
        return $r;
    }

    public static function qrlogin_parse_stoken(string $stokenList)
    {

        $tmpStokenList = [];
        foreach (json_decode(str_replace("&quot;", '"', $stokenList), true) as $stoken) {
            preg_match("/([\w]+)#(.*)/", $stoken, $tmpStoken);
            $tmpStokenList[$tmpStoken[1]] = $tmpStoken[2];
        }
        return $tmpStokenList;
    }
}
