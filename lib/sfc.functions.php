<?php

/**
 * StusGame Framework 部分函数库
 * @author Kenvix
 */

if (!defined('SYSTEM_ROOT')) {
    die('Insufficient Permissions');
}
/**
 * 获取用户ip地址
 */
function getIp()
{

    $ip = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '';
    if (!ip2long($ip)) {
        $ip = '';
    }
    return $ip;
}

/**
 * 加密密码
 * @param string $pwd 密码
 * @param boolean $with_legacy 是否包含旧密码
 * @return string|object 新密码和旧版密码
 */
function EncodePwd($pwd, $with_legacy = false)
{
    $p = new P();
    return $p->pwd($pwd, $with_legacy);
}

/**
 * 校验密码
 * @param string $pwd 密码
 * @param string $hash hash 值
 * @return boolean 密码是否合法
 */
function VerifyPwd($pwd, $hash)
{
    $p = new P();
    return $p->pwd_verify($pwd, $hash);
}

/**
 * Maximum lifetime for persistent authentication cookies.
 */
function auth_cookie_lifetime()
{
    return 86400 * 30;
}

/**
 * 签到标记值（tieba 表 `latest` 列）的统一格式：完整日期 Y-m-d。
 *
 * 历史实现用 date('d') 存日号，跨月同号（1 月 15 日签过、2 月 15 日恢复）
 * 会被误判为"今日已签"而漏签。自 V7.5 起一律存完整日期，列宽 varchar(10)
 * 恰好容纳 "2026-11-10"（10 字符），无需改表。
 *
 * 插件若需判断"今日是否已签"，请使用本函数取比较值，不要自行拼日期。
 * @param int|string|null $timestamp 参考时间戳，缺省为当前时间
 * @return string Y-m-d 格式的日期
 */
function sign_marker_today($timestamp = null)
{
    return date('Y-m-d', $timestamp === null ? null : (int) $timestamp);
}

/**
 * Set a cookie with the security attributes supported by the running PHP.
 */
function set_site_cookie($name, $value = '', $expires = 0)
{
    $secure = function_exists('request_is_https') ? request_is_https() : false;
    if (PHP_VERSION_ID >= 70300) {
        setcookie($name, (string) $value, array(
            'expires' => (int) $expires,
            'path' => '/',
            'secure' => $secure,
            'httponly' => true,
            'samesite' => 'Lax'
        ));
        return;
    }
    setcookie($name, (string) $value, (int) $expires, '/', '', $secure, true);
}

function clear_site_cookie($name)
{
    set_site_cookie($name, '', time() - 3600);
}

/**
 * 验证email地址格式
 * @param string @email EMAIL
 * @return bool true表示有效
 */
function checkMail($email)
{

    if (preg_match("/^[\w\.\-]+@\w+([\.\-]\w+)*\.\w+$/", $email) && strlen($email) <= 60) {
        return true;
    } else {
        return false;
    }
}

/**
 * (弃用)生成一个随机的字符串
 *
 * @param int $length
 * @param boolean $special_chars
 * @return string
 */
function getRandStr($length = 12, $special_chars = false)
{

    $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
    if ($special_chars) {
        $chars .= '!@#$%^&*()';
    }
    $randStr = '';
    for ($e = 0; $e < $length; $e++) {
        $randStr .= substr($chars, mt_rand(0, strlen($chars) - 1), 1);
    }
    return $randStr;
}

/**
 * 获取两段文本之间的文本
 *
 * @param string $text 完整的文本
 * @param string $left 左边文本
 * @param string $right 右边文本
 * @return string “左边文本”与“右边文本”之间的文本
 */
function textMiddle($text, $left, $right)
{

    $loc1 = stripos($text, $left);
    if (is_bool($loc1)) {
        return "";
    }
    $loc1 += strlen($left);
    $loc2 = stripos($text, $right, $loc1);
    if (is_bool($loc2)) {
        return "";
    }
    return substr($text, $loc1, $loc2 - $loc1);
}

/**
 * 获取一个bduss对应的百度用户名
 *
 * @param string $bduss BDUSS
 * @return string 百度用户名，失败返回""
 */
function getBaiduId($bduss)
{

    //$c = new wcurl('http://top.baidu.com/user/pass');
    //$c->addCookie(array('BDUSS' => $bduss));
    //$data = $c->get();
    //$c->close();
    $userData = getBaiduUserInfo($bduss);
    return isset($userData["name"]) ? $userData["name"] : "";
}

/**
 * 获取一个bduss对应的百度用户信息
 *
 * @param string $bduss BDUSS
 * @return array|bool 百度用户信息，失败返回FALSE
 */
function getBaiduUserInfo($bduss)
{

    $c = new wcurl('https://c.tieba.baidu.com/c/s/login');
    $c->addCookie(array('BDUSS' => $bduss));
    $temp = array('bdusstoken' => $bduss);
    misc::addTiebaSign($temp);
    $data = $c->post($temp);
    $c->close();
    $data = json_decode($data, true);
    $data = isset($data["user"]) ? $data["user"] : false;
    return $data;
}

/**
 * 获取指定 portrait/baiduid 的用户信息
 *
 * @param string $id portrait/baiduid
 * @param bool $isBaiduId $id是否为百度id
 * @return array 用户信息
 */
function getUserInfo($id, $isBaiduId = true)
{

    $user = new wcurl("https://tieba.baidu.com/home/get/panel?ie=utf-8&" . ($isBaiduId ? "un={$id}" : "id={$id}"));
    $re = $user->get();
    return json_decode($re, true);
}

/**
 * 获取指定邮箱的的Gravatar头像
 * http://en.gravatar.com/site/implement/images/
 * @return bool|string
 */
function gravatar($email, $s = 140, $d = 'mm', $g = 'g', $site = 'secure')
{

    $hash = md5($email);
    if ($site == 'secure') {
        return "https://secure.gravatar.com/avatar/$hash?s=$s&r=$g";
    } else {
        return "//{$site}.gravatar.com/avatar/$hash?s=$s&r=$g";
    }
}

/**
 * 获取当前用户的Gravatar头像或贴吧头像
 * http://en.gravatar.com/site/implement/images/
 * @return bool|string
 */
function getGravatar($s = 140, $d = 'mm', $g = 'g', $site = 'secure')
{

    if (option::uget('face_img') == 1) {
        if (option::uget('face_url') != '') {
            return option::uget('face_url');
        } else {
            return 'https://himg.bdimg.com/sys/portrait/item/0';
        }
    } else {
        return gravatar(EMAIL, $s, $d, $g, $site);
    }
}

/**
 * 解压zip
 * @param string $zipfile 要解压的文件
 * @param string $path 解压到该目录
 * @return int
 */
function UnZip($zipfile, $path)
{
    if (!class_exists('ZipArchive', false)) {
        return 3;
    }
    $zip = new ZipArchive();
    if (@$zip->open($zipfile) !== true) {
        return 2;
    }
    $realDest = realpath($path);
    if ($realDest === false) {
        @mkdir($path, 0755, true);
        $realDest = realpath($path);
    }
    if ($realDest === false) {
        $zip->close();
        return 1;
    }
    $realDest = rtrim(str_replace('\\', '/', $realDest), '/');
    for ($i = 0; $i < $zip->numFiles; $i++) {
        $name = $zip->getNameIndex($i);
        if ($name === false) {
            continue;
        }
        $name = str_replace('\\', '/', $name);
        if (strpos($name, '../') !== false || strpos($name, "\0") !== false || (isset($name[0]) && $name[0] === '/')) {
            continue;
        }
        $targetPath = $realDest . '/' . $name;
        if (strpos($targetPath, $realDest . '/') !== 0) {
            continue;
        }
        if (substr($name, -1) === '/') {
            if (!is_dir($targetPath)) {
                @mkdir($targetPath, 0755, true);
            }
        } else {
            $dir = dirname($targetPath);
            if (!is_dir($dir)) {
                @mkdir($dir, 0755, true);
            }
            $content = $zip->getFromIndex($i);
            if ($content !== false) {
                @file_put_contents($targetPath, $content);
            }
        }
    }
    $zip->close();
    return 0;
}
/**
 * 清空缓冲区的内容
 * @note 已修复无法清除缓冲区的bug
 */
function Clean()
{

    ob_clean();
}

/**
 * [已搬走]MySQL 随机取记录
 * 请查看S::rand()
 * @param string $t 表
 * @param string $c ID列，默认为id
 * @param int $n 取多少个
 * @param string $w 条件语句
 * @param bool $f 是否强制以多维数组形式返回，默认false
 * @param string $p 随机数据前缀，如果产生冲突，请修改本项
 * @return array 取1个直接返回结果数组(除非$f为true)，取>1个返回多维数组，用foreach取出
 */
function rand_row($t, $c = 'id', $n = 1, $w = '', $f = false, $p = 'tempval_')
{

    global $m;
    return $m->rand($t, $c, $n, $w, $f, $p);
}

/**
 * 从数组中随机取一个值
 * @param array 数组
 */
function rand_array($a)
{

    $r = array_rand($a, 1);
    return $a[$r];
}

/**
 * 随机生成一个指定长度的正整数
 * @param int $l 长度
 */
function rand_int($l)
{

    $int = null;
    for ($e = 0; $e < $l; $e++) {
        $int .= mt_rand(0, 9);
    }
    return $int;
}

/**
 * 获取空闲的贴吧记录表
 *
 */
function getfreetable()
{

    global $m;
    $x = $m->once_fetch_array("SELECT COUNT(*) AS fffff FROM  `" . DB_NAME . "`.`" . DB_PREFIX . "tieba`");
    $fbs = option::get('fb_tables');
    $fbset = option::get('fb');
    $f = safe_table_names($fbs);
    if (!empty($fbset) && $x['fffff'] >= $fbset && !empty($f)) {
        $c = sizeof($f);
        foreach ($f as $key => $value) {
            $x = $m->once_fetch_array("SELECT COUNT(*) AS fffff FROM  `" . DB_NAME . "`.`" . DB_PREFIX . $value . "`");
            if ($x['fffff'] < $fbset) {
                break;
            }
        }
        return $value;
    } else {
        return 'tieba';
    }
}

/**
 * 清除用户的所有贴吧
 *
 * @param int $id 用户ID
 */
function CleanUser($id)
{

    global $m;
    $id = (int) $id;
    $x = $m->pquery("SELECT `t` FROM `" . DB_PREFIX . "users` WHERE `id` = ? LIMIT 1", array($id));
    $row = $x !== false ? $m->fetch_array($x) : false;
    $userTable = isset($row['t']) ? $row['t'] : '';
    //二阶标识符：users.t 来自数据库，拼入 SQL 前必须过白名单
    $userTable = function_exists('safe_table_name') ? safe_table_name($userTable) : 'tieba';
    $m->pquery('DELETE FROM `' . DB_NAME . '`.`' . DB_PREFIX . $userTable . '` WHERE `uid` = ?', array($id));
}

/**
 * 删除用户
 * 为节省数据库，捆绑清除贴吧数据、百度凭据、签到历史、OAuth 绑定与通知。
 *
 * 隐私合规：百度凭据（BDUSS/STOKEN）必须随之删除，否则注销用户的凭据
 * 永久留在库中；sign_history 等业务数据同样按 uid 级联清理。
 * login_audit 保留但脱敏 username（审计价值大于存储开销）。
 *
 * @param int $id 用户ID
 */
function DeleteUser($id)
{

    global $m;
    $id = (int) $id;
    if ($id <= 0) {
        return;
    }

    //1) 贴吧分表数据
    CleanUser($id);

    //2) 百度账号（含加密 BDUSS/STOKEN）与健康度
    //先取 pid 清理健康度，再删账号
    $pids = array();
    $pidRs = $m->pquery("SELECT `id` FROM `" . DB_PREFIX . "baiduid` WHERE `uid` = ?", array($id));
    if ($pidRs !== false) {
        while ($pr = $m->fetch_array($pidRs)) {
            $pids[] = (int) $pr['id'];
        }
    }
    $m->pquery("DELETE FROM `" . DB_PREFIX . "baiduid` WHERE `uid` = ?", array($id));
    foreach ($pids as $pid) {
        $m->pquery("DELETE FROM `" . DB_PREFIX . "baiduid_health` WHERE `pid` = ?", array($pid));
    }

    //3) 签到历史、通知、成就、OAuth 绑定
    $m->pquery("DELETE FROM `" . DB_PREFIX . "sign_history` WHERE `uid` = ?", array($id));
    $m->pquery("DELETE FROM `" . DB_PREFIX . "notice` WHERE `uid` = ?", array($id));
    $m->pquery("DELETE FROM `" . DB_PREFIX . "achievements` WHERE `uid` = ?", array($id));
    $m->pquery("DELETE FROM `" . DB_PREFIX . "users_oauth` WHERE `uid` = ?", array($id));

    //4) 用户设置
    option::udel($id);

    //5) 登录审计：保留记录但脱敏用户名（保留安全审计价值）
    $m->pquery("UPDATE `" . DB_PREFIX . "login_audit` SET `username` = ? WHERE `username` = (SELECT `name` FROM `" . DB_PREFIX . "users` WHERE `id` = ?)", array('deleted:' . $id, $id));

    //6) 最后删用户行
    $m->pquery('DELETE FROM `' . DB_NAME . '`.`' . DB_PREFIX . 'users` WHERE `id` = ?', array($id));
}

/**
 * 判断`操作后`剩余的admin数量，如为0则报错
 * `操作`通常指 删除 或 调整用户组
 * 也就是判断`操作` $id_list 后剩余的admin数量
 * @param array $id_list
 */
function MustAdminWarning($id_list)
{
    global $m;
    //调用方可能直接传入 $_POST 数组，边界处强制转 int，杜绝 NOT IN 拼接注入
    $id_list = array_filter(array_map('intval', (array) $id_list), static function ($v) {
        return $v > 0;
    });
    if (empty($id_list)) {
        return;
    }
    $id_list = implode(',', $id_list);
// 收集欲处理的用户id
    $q = "SELECT COUNT(*) as num FROM `" . DB_NAME . "`.`" . DB_PREFIX . "users` WHERE `role` = 'admin' AND `id` NOT IN ({$id_list})";
    $q = $m->once_fetch_array($q);
    if ($q['num'] == 0) {
        msg('必须保留至少一位管理员！');
    }
}


/**
 * zip压缩
 * @param string $orig_fname 将在zip的文件路径
 * @param string $content 文件内容
 * @param string $tempzip zip存储路径
 * @return bool
 */
function CreateZip($orig_fname, $content, $tempzip)
{

    if (!class_exists('ZipArchive', false)) {
        return false;
    }
    $zip = new ZipArchive();
    $res = $zip->open($tempzip, ZipArchive::CREATE);
    if ($res === true) {
        $zip->addFromString($orig_fname, $content);
        $zip->close();
        return true;
    } else {
        return false;
    }
}

/**
 * 删除文件或目录
 */
function DeleteFile($file)
{

    if (!file_exists($file)) {
        return false;
    }
    if (empty($file)) {
        return false;
    }
    if (@is_file($file)) {
        return @unlink($file);
    }
    $ret = true;
    if ($handle = @opendir($file)) {
        while ($filename = @readdir($handle)) {
            if ($filename == '.' || $filename == '..') {
                continue;
            }
            if (!DeleteFile($file . '/' . $filename)) {
                $ret = false;
            }
        }
    } else {
        $ret = false;
    }
    @closedir($handle);
    if (file_exists($file) && !rmdir($file)) {
        $ret = false;
    }
    return $ret;
}

/**
 * 批量复制
 * @param string $source 源目录名
 * @param string $destination 目的目录名
 * @return bool 成功返回TRUE，失败返回原因
 */
function CopyAll($source, $destination)
{

    if (!is_dir($source)) {
        return '错误：' . $source . '并不是一个目录';
    }

    if (!is_dir($destination)) {
        if (!mkdir($destination, 0777, true) && !is_dir($destination)) {
            return false;
        }
    }

    $handle = dir($source);
    while ($entry = $handle->read()) {
        if (($entry !== '.') && ($entry !== '..')) {
            if (is_dir($source . '/' . $entry)) {
                if (CopyAll($source . '/' . $entry, $destination . '/' . $entry) !== true) {
                    $handle->close();
                    return false;
                }
            } else {
                if (!copy($source . '/' . $entry, $destination . '/' . $entry)) {
                    $handle->close();
                    return false;
                }
            }
        }
    }
    $handle->close();
    return true;
}

/**
 * 列出目录里的文件和目录，不扫描子目录
 * @param string $dir 文件夹路径
 * @param int $order 排序，1=倒序
 * @return array|bool 成功返回列表，失败返回false
 */
function listDir($dirpath, $order = 0)
{

    if (!file_exists($dirpath)) {
        return false;
    }
    if (function_exists('scandir')) {
        $dir = scandir($dirpath, $order);
        if (!$dir) {
            return false;
        }
        if ($order == 0) {
            array_shift($dir);
            array_shift($dir);
        } else {
            array_pop($dir);
            array_pop($dir);
        }
    } else {
        $h = opendir($dirpath);
        if (!$h) {
            return false;
        }
        $dir = array();
        while (($f = readdir($h)) !== false) {
            if ($f != '.' && $f != '..') {
                $dir[] = $f;
            }
        }
    }
    return $dir;
}

/**
 * 备份指定表的数据结构和所有数据
 *
 * @param string $table 数据库表名
 * @return string
 */
function dataBak($table)
{

    //表名拼进 SHOW CREATE TABLE / SELECT，必须白名单，反引号等一律拒绝
    if (!is_string($table) || !preg_match('/^[A-Za-z0-9_]{1,64}$/', $table)) {
        return false;
    }
    global $m;
    // 转储由 utf8mb4 应用连接生成。显式声明编码，避免导入端默认 latin1 导致
    // 中文/emoji 变形甚至因字符数膨胀而恢复失败；手动、定时与更新前备份均复用此处。
    $sql = "SET NAMES utf8mb4;\nDROP TABLE IF EXISTS `$table`;\n";
    $createtable = $m->query("SHOW CREATE TABLE $table");
    $create = $m->fetch_row($createtable);
    $sql .= $create[1] . ";\n\n";
    $rows = $m->query("SELECT * FROM $table");
    $numfields = $m->num_fields($rows);
    $numrows = $m->num_rows($rows);
    while ($row = $m->fetch_row($rows)) {
        $comma = '';
        $sql .= "INSERT INTO `$table` VALUES(";
        for ($e = 0; $e < $numfields; $e++) {
            //V7.0：NULL 列导出为 SQL NULL；此前导出为空字符串，恢复后 NULL→'' 静默失真
            $sql .= $comma . ($row[$e] === null ? 'NULL' : "'" . $m->escape_string($row[$e]) . "'");
            $comma = ',';
        }
        $sql .= ");\n";
    }
    $sql .= "\n";
    return $sql;
}

/**
 * 执行一个网络请求而不等待返回结果
 * @param string $url URL
 * @param string $post post数据包，留空为get
 * @param string $cookie cookies
 * @return bool fsockopen是否成功
 */
function sendRequest($url, $post = '', $cookie = '')
{

    if (function_exists('fsockopen')) {
        $matches = parse_url($url);
        if (!$matches || empty($matches['host']) || empty($matches['scheme']) || !in_array(strtolower($matches['scheme']), array('http', 'https'))) {
            error_log("sendRequest 失败: URL 不合法");
            return false;
        }
        $host = $matches['host'];
        $useSSL = strtolower($matches['scheme']) === 'https';
        $connectHost = ($useSSL ? 'tls://' : '') . $host;
        $path = (isset($matches['path']) && $matches['path'] !== '') ? $matches['path'] . (isset($matches['query']) && $matches['query'] !== '' ? '?' . $matches['query'] : '') : '/';
        $port = !empty($matches['port']) ? (int) $matches['port'] : ($useSSL ? 443 : 80);
        if (!empty($post)) {
            $out = "POST $path HTTP/1.1\r\n";
            $out .= "Content-Type: application/x-www-form-urlencoded\r\n";
            $out .= 'Content-Length: ' . strlen($post) . "\r\n";
        } else {
            $out = "GET $path HTTP/1.1\r\n";
        }
        $out .= "Host: $host\r\n";
        if (!empty($cookie)) {
            $out .= "Cookie: $cookie\r\n";
        }
        $out .= "Connection: Close\r\n\r\n";
        if (!empty($post)) {
            $out .= $post;
        }
        $socketErrno = 0;
        $socketErrorStr = "";
        $fp = fsockopen($connectHost, $port, $socketErrno, $socketErrorStr, 1);
        if (!$fp) {
            error_log("sendRequest 失败: 未能调用 fsockopen: #$socketErrno - $socketErrorStr");
            return false;
        } else {
            stream_set_blocking($fp, false);
            stream_set_timeout($fp, 1);
            fwrite($fp, $out);
            fclose($fp);
            return true;
        }
    } else {
        if (!preg_match('~^https?://~i', $url)) {
            return false;
        }
        $x = new wcurl($url);
        $x->set(CURLOPT_CONNECTTIMEOUT, 1);
        $x->set(CURLOPT_TIMEOUT, 1);
        if (!empty($cookie)) {
            $x->addcookie($cookie);
        }
        if (!empty($post)) {
            $x->post($post);
        } else {
            $x->exec();
        }
        return true;
    }
}

/**
 * fosckopen 改进版
 */

function XFSockOpen($url, $limit = 0, $post = '', $cookie = '', $bysocket = false, $ip = '', $timeout = 15, $block = false)
{

    if (function_exists('fsockopen')) {
        $return = '';
        $matches = parse_url($url);
        $host = $matches['host'];
        $path = $matches['path'] ? $matches['path'] . ($matches['query'] ? '?' . $matches['query'] : '') : '/';
        $port = !empty($matches['port']) ? $matches['port'] : 80;
        if ($post) {
            $out = "POST $path HTTP/1.0\r\n";
            $out .= "Accept: */*\r\n";
        //$out .= "Referer: $boardurl\r\n";
                $out .= "Accept-Language: zh-cn\r\n";
            $out .= "Content-Type: application/x-www-form-urlencoded\r\n";
            $out .= "User-Agent: $_SERVER[HTTP_USER_AGENT]\r\n";
            $out .= "Host: $host\r\n";
            $out .= 'Content-Length: ' . strlen($post) . "\r\n";
            $out .= "Connection: Close\r\n";
            $out .= "Cache-Control: no-cache\r\n";
            $out .= "Cookie: $cookie\r\n\r\n";
            $out .= $post;
        } else {
            $out = "GET $path HTTP/1.0\r\n";
            $out .= "Accept: */*\r\n";
        //$out .= "Referer: $boardurl\r\n";
                $out .= "Accept-Language: zh-cn\r\n";
            $out .= "User-Agent: $_SERVER[HTTP_USER_AGENT]\r\n";
            $out .= "Host: $host\r\n";
            $out .= "Connection: Close\r\n";
            $out .= "Cookie: $cookie\r\n\r\n";
        }
        $fp = fsockopen(($ip ? $ip : $host), $port, $errno, $errstr, $timeout);
        if (!$fp) {
            return false;
        //note $errstr : $errno \r\n
        } else {
            stream_set_blocking($fp, $block);
            stream_set_timeout($fp, $timeout);
            fwrite($fp, $out);
            while (!feof($fp)) {
                $status = stream_get_meta_data($fp);
                if (!empty($status['timed_out'])) {
                    return false;
                }
                if (($header = fgets($fp)) && ($header == "\r\n" ||  $header == "\n")) {
                    break;
                }
            }
                $stop = false;
            while (!feof($fp) && !$stop) {
                $status = stream_get_meta_data($fp);
                if (!empty($status['timed_out'])) {
                    return false;
                }
                $data = fread($fp, ($limit == 0 || $limit > 8192 ? 8192 : $limit));
                $return .= $data;
                if ($limit) {
                    $limit -= strlen($data);
                    $stop = $limit <= 0;
                }
            }
                fclose($fp);
            return $return;
        }
    }
}


/**
 * 该函数在插件中调用,挂载插件函数到预留的钩子上
 * WARNING:所有addAction应该放到doAction前面，否则将不能执行！
 *
 * @param string $hook
 * @param string $actionFunc
 * @return bool
 */
function addAction($hook, $actionFunc)
{

    global $i;
    $i['plugins']['hook'][$hook][] = $actionFunc;
    return true;
}



/**
 * 执行挂在钩子上的函数,支持多参数 eg:doAction('post_comment', $author, $email, $url, $comment);
 * 执行成功返回函数返回的值，如该钩子下没有函数则返回false
 *
 * @param string $hook
 * @param mixed ... 更多参数
 * @return string or bool
 */
function doAction($hook)
{

    global $i;
    $args = array_slice(func_get_args(), 1);
    if (isset($i['plugins']['hook'][$hook])) {
        foreach ($i['plugins']['hook'][$hook] as $function) {
            $string = call_user_func_array($function, $args);
        }
        return $string;
    } else {
        return false;
    }
}

/**
 * 判断钩子上面是否挂有函数
 *
 * @param string $hook
 * @return bool
 */
function hasAction($hook)
{

    global $i;
    return isset($i['plugins']['hook'][$hook]);
}

/**
 * 打印可读的变量信息
 * @param mixed $var
 * @param bool $mode false = var_dump() | true = print_r()
 */
function dump($var, $mode = false)
{

    echo '<pre>';
    if (!$mode) {
        var_dump($var);
    } else {
        print_r($var);
    }
    echo '</pre>';
}

/**
 * 将ROLE名称转换为中文名称
 *
 * @param ROLE
 */
function getrole($role)
{

    $role = strtolower($role);
    if ($role == 'admin') {
        return '管理员';
    } elseif ($role == 'user') {
        return '用户';
    } elseif ($role == 'vip') {
        return 'VIP';
    } elseif ($role == 'visitor') {
        return '访客';
    } elseif ($role == 'banned') {
        return '禁止访问';
    } else {
        return '未定义';
    }
}

/**
 * 页面重定向
 *
 * @param string $url 地址
 */

function redirect($url)
{

    Clean();
    $url = (string) $url;
    if (SYSTEM_ISCONSOLE) {
        msg('控制台模式下，请手动打开此地址：' . PHP_EOL . $url, false);
    }
    $url = safe_redirect_url($url);
    $headerName = 'Location';
    header($headerName . ': ' . $url, true, 302);
    $safeUrl = htmlspecialchars($url, ENT_QUOTES, 'UTF-8');
    msg('<meta http-equiv="refresh" content="0; url=' . $safeUrl . '" />请稍候......<br/><br/>如果您的浏览器没有自动跳转，请点击下面的链接', $url);
}

function safe_redirect_url($url)
{
    $url = str_replace(array("\r", "\n"), '', trim((string) $url));
    if ($url === '' || preg_match('/^[a-z][a-z0-9+.-]*:/i', $url) && stripos($url, 'http://') !== 0 && stripos($url, 'https://') !== 0) {
        return 'index.php';
    }
    if (strpos($url, '//') === 0) {
        return 'index.php';
    }
    $parse = parse_url($url);
    if ($parse === false) {
        return 'index.php';
    }
    if (empty($parse['scheme'])) {
        return preg_match('/^[A-Za-z0-9_\.\/\?&=#%:+-]*$/', $url) ? $url : 'index.php';
    }
    $expected = parse_url(SYSTEM_URL);
    if (empty($expected['host']) || empty($parse['host'])) {
        return 'index.php';
    }
    $scheme = strtolower($parse['scheme']);
    if (!in_array($scheme, array('http', 'https'), true) || strcasecmp($parse['host'], $expected['host']) !== 0) {
        return 'index.php';
    }
    if (isset($parse['port']) && isset($expected['port']) && (int) $parse['port'] !== (int) $expected['port']) {
        return 'index.php';
    }
    return $url;
}

/**
 * 执行一个计划任务
 *
 * @param 计划任务文件
 * @param 计划任务名称
 * @return bool 执行成功true，否则false
 */

function RunCron($file, $name)
{

    return cron::run($file, $name);
}

/**
 * 使用反斜线引用字符串或数组
 * @param string|array $s 需要转义的字符串或数组
 * @return string|array 转义结果
 */

function adds($s)
{

    if (is_array($s)) {
        return array_map('addslashes', $s);
    } else {
        return addslashes($s);
    }
}

/**
 * 使用反斜线引用字符串或数组以便于SQL查询
 * 只引用'和\
 * @param string|array $s 需要转义的
 * @return string|array 转义结果
 */
function sqladds($s)
{

    if (is_array($s)) {
        $r = array();
        foreach ($s as $key => $value) {
            $k = str_replace('\'', '\\\'', str_replace('\\', '\\\\', $value));
            if (!is_array($value)) {
                $r[$k] = str_replace('\'', '\\\'', str_replace('\\', '\\\\', $value));
            } else {
                $r[$k] = sqladds($value);
            }
        }
        return $r;
    } else {
        return str_replace('\'', '\\\'', str_replace('\\', '\\\\', $s));
    }
}

/**
 * 去除英文字母、数字、下划线以外所有字符
 * @param string $s 需要处理的
 * @return string 处理结果
 */
function onlyalnum($s)
{

    if (is_array($s)) {
        $r = array();
        foreach ($s as $key => $value) {
            $k = preg_replace('/[^a-zA-Z0-9._]*/', '', $key);
            if (!is_array($value)) {
                $r[$k] = preg_replace('/[^a-zA-Z0-9._]*/', '', $value);
            } else {
                $r[$k] = onlyalnum($value);
            }
        }
        return $r;
    } else {
        return preg_replace('/[^a-zA-Z0-9._]*/', '', $s);
    }
}

/**
 * 转换并得到绝对值
 * @param int|string $s 需要转换的
 * @return int 转换结果
 */

function topos($s)
{

    return abs(intval($s));
}

/**
 * 执行一个通配符表达式匹配
 * [可当preg_match()的简化版本去理解]
 * @param string $exp 匹配表达式
 * @param string $str 在这个字符串内运行匹配
 * @param int $pat 规定匹配模式，0表示尽可能多匹配，1表示尽可能少匹配
 * @return array 匹配结果，$matches[0]将包含完整模式匹配到的文本， $matches[1] 将包含第一个捕获子组匹配到的文本，以此类推。
 */
function easy_match($exp, $str, $pat = 0)
{

    $exp = str_ireplace('\\', '\\\\', $exp);
    $exp = str_ireplace('/', '\/', $exp);
    $exp = str_ireplace('?', '\?', $exp);
    $exp = str_ireplace('<', '\<', $exp);
    $exp = str_ireplace('>', '\>', $exp);
    $exp = str_ireplace('^', '\^', $exp);
    $exp = str_ireplace('$', '\$', $exp);
    $exp = str_ireplace('+', '\+', $exp);
    $exp = str_ireplace('(', '\(', $exp);
    $exp = str_ireplace(')', '\)', $exp);
    $exp = str_ireplace('[', '\[', $exp);
    $exp = str_ireplace(']', '\]', $exp);
    $exp = str_ireplace('|', '\|', $exp);
    $exp = str_ireplace('}', '\}', $exp);
    $exp = str_ireplace('{', '\{', $exp);
    if ($pat == 0) {
        $z = '(.*)';
    } else {
        $z = '(.*?)';
    }
    $exp = str_ireplace('*', $z, $exp);
    $exp = '/' . $exp . '/';
    preg_match($exp, $str, $r);
    return $r;
}

/**
 * 执行一个全局通配符表达式匹配
 * [可当preg_match_all()的简化版本去理解]
 * @param string $exp 匹配表达式
 * @param string $str 在这个字符串内运行匹配
 * @param int $pat 规定匹配模式，0表示尽可能多匹配，1表示尽可能少匹配
 * @param int $flags 可以使用 PREG_PATTERN_ORDER 或 PREG_SET_ORDER 或 PREG_OFFSET_CAPTURE
 * @return array 匹配结果，数组排序通过flags指定。
 */
function easy_match_all($exp, $str, $pat = 0, $flags = PREG_PATTERN_ORDER)
{

    $exp = str_ireplace('\\', '\\\\', $exp);
    $exp = str_ireplace('/', '\/', $exp);
    $exp = str_ireplace('?', '\?', $exp);
    $exp = str_ireplace('<', '\<', $exp);
    $exp = str_ireplace('>', '\>', $exp);
    $exp = str_ireplace('^', '\^', $exp);
    $exp = str_ireplace('$', '\$', $exp);
    $exp = str_ireplace('+', '\+', $exp);
    $exp = str_ireplace('(', '\(', $exp);
    $exp = str_ireplace(')', '\)', $exp);
    $exp = str_ireplace('[', '\[', $exp);
    $exp = str_ireplace(']', '\]', $exp);
    $exp = str_ireplace('|', '\|', $exp);
    $exp = str_ireplace('}', '\}', $exp);
    $exp = str_ireplace('{', '\{', $exp);
    if ($pat == 0) {
        $z = '(.*)';
    } else {
        $z = '(.*?)';
    }
    $exp = str_ireplace('*', $z, $exp);
    $exp = '/' . $exp . '/';
    preg_match($exp, $str, $r, $flags);
    return $r;
}

/**
 * 根据文件名称获取扩展名
 * @param string $name 文件名
 * @return string 扩展名（不带.）
 */
function get_extname($name)
{

    return pathinfo($name, PATHINFO_EXTENSION);
}

/**
 * 根据扩展名获取文件的MIME
 * @param string $ext 扩展名
 * @return string MIME
 */
function get_mime($ext)
{

    static $mime_types = array(
        'apk'     => 'application/vnd.android.package-archive',
        '3gp'     => 'video/3gpp',
        'ai'      => 'application/postscript',
        'aif'     => 'audio/x-aiff',
        'aifc'    => 'audio/x-aiff',
        'aiff'    => 'audio/x-aiff',
        'asc'     => 'text/plain',
        'atom'    => 'application/atom+xml',
        'au'      => 'audio/basic',
        'avi'     => 'video/x-msvideo',
        'bcpio'   => 'application/x-bcpio',
        'bin'     => 'application/octet-stream',
        'bmp'     => 'image/bmp',
        'cdf'     => 'application/x-netcdf',
        'cgm'     => 'image/cgm',
        'class'   => 'application/octet-stream',
        'cpio'    => 'application/x-cpio',
        'cpt'     => 'application/mac-compactpro',
        'csh'     => 'application/x-csh',
        'css'     => 'text/css',
        'dcr'     => 'application/x-director',
        'dif'     => 'video/x-dv',
        'dir'     => 'application/x-director',
        'djv'     => 'image/vnd.djvu',
        'djvu'    => 'image/vnd.djvu',
        'dll'     => 'application/octet-stream',
        'dmg'     => 'application/octet-stream',
        'dms'     => 'application/octet-stream',
        'doc'     => 'application/msword',
        'dtd'     => 'application/xml-dtd',
        'dv'      => 'video/x-dv',
        'dvi'     => 'application/x-dvi',
        'dxr'     => 'application/x-director',
        'eps'     => 'application/postscript',
        'etx'     => 'text/x-setext',
        'exe'     => 'application/octet-stream',
        'ez'      => 'application/andrew-inset',
        'flv'     => 'video/x-flv',
        'gif'     => 'image/gif',
        'gram'    => 'application/srgs',
        'grxml'   => 'application/srgs+xml',
        'gtar'    => 'application/x-gtar',
        'gz'      => 'application/x-gzip',
        'hdf'     => 'application/x-hdf',
        'hqx'     => 'application/mac-binhex40',
        'htm'     => 'text/html',
        'html'    => 'text/html',
        'ice'     => 'x-conference/x-cooltalk',
        'ico'     => 'image/x-icon',
        'ics'     => 'text/calendar',
        'ief'     => 'image/ief',
        'ifb'     => 'text/calendar',
        'iges'    => 'model/iges',
        'igs'     => 'model/iges',
        'jnlp'    => 'application/x-java-jnlp-file',
        'jp2'     => 'image/jp2',
        'jpe'     => 'image/jpeg',
        'jpeg'    => 'image/jpeg',
        'jpg'     => 'image/jpeg',
        'js'      => 'application/x-javascript',
        'kar'     => 'audio/midi',
        'latex'   => 'application/x-latex',
        'lha'     => 'application/octet-stream',
        'lzh'     => 'application/octet-stream',
        'm3u'     => 'audio/x-mpegurl',
        'm4a'     => 'audio/mp4a-latm',
        'm4p'     => 'audio/mp4a-latm',
        'm4u'     => 'video/vnd.mpegurl',
        'm4v'     => 'video/x-m4v',
        'mac'     => 'image/x-macpaint',
        'man'     => 'application/x-troff-man',
        'mathml'  => 'application/mathml+xml',
        'me'      => 'application/x-troff-me',
        'mesh'    => 'model/mesh',
        'mid'     => 'audio/midi',
        'midi'    => 'audio/midi',
        'mif'     => 'application/vnd.mif',
        'mov'     => 'video/quicktime',
        'movie'   => 'video/x-sgi-movie',
        'mp2'     => 'audio/mpeg',
        'mp3'     => 'audio/mpeg',
        'mp4'     => 'video/mp4',
        'mpe'     => 'video/mpeg',
        'mpeg'    => 'video/mpeg',
        'mpg'     => 'video/mpeg',
        'mpga'    => 'audio/mpeg',
        'ms'      => 'application/x-troff-ms',
        'msh'     => 'model/mesh',
        'mxu'     => 'video/vnd.mpegurl',
        'nc'      => 'application/x-netcdf',
        'oda'     => 'application/oda',
        'ogg'     => 'application/ogg',
        'ogv'     => 'video/ogv',
        'pbm'     => 'image/x-portable-bitmap',
        'pct'     => 'image/pict',
        'pdb'     => 'chemical/x-pdb',
        'pdf'     => 'application/pdf',
        'pgm'     => 'image/x-portable-graymap',
        'pgn'     => 'application/x-chess-pgn',
        'pic'     => 'image/pict',
        'pict'    => 'image/pict',
        'png'     => 'image/png',
        'pnm'     => 'image/x-portable-anymap',
        'pnt'     => 'image/x-macpaint',
        'pntg'    => 'image/x-macpaint',
        'ppm'     => 'image/x-portable-pixmap',
        'ppt'     => 'application/vnd.ms-powerpoint',
        'ps'      => 'application/postscript',
        'qt'      => 'video/quicktime',
        'qti'     => 'image/x-quicktime',
        'qtif'    => 'image/x-quicktime',
        'ra'      => 'audio/x-pn-realaudio',
        'ram'     => 'audio/x-pn-realaudio',
        'ras'     => 'image/x-cmu-raster',
        'rdf'     => 'application/rdf+xml',
        'rgb'     => 'image/x-rgb',
        'rm'      => 'application/vnd.rn-realmedia',
        'roff'    => 'application/x-troff',
        'rtf'     => 'text/rtf',
        'rtx'     => 'text/richtext',
        'sgm'     => 'text/sgml',
        'sgml'    => 'text/sgml',
        'sh'      => 'application/x-sh',
        'shar'    => 'application/x-shar',
        'silo'    => 'model/mesh',
        'sit'     => 'application/x-stuffit',
        'skd'     => 'application/x-koan',
        'skm'     => 'application/x-koan',
        'skp'     => 'application/x-koan',
        'skt'     => 'application/x-koan',
        'smi'     => 'application/smil',
        'smil'    => 'application/smil',
        'snd'     => 'audio/basic',
        'so'      => 'application/octet-stream',
        'spl'     => 'application/x-futuresplash',
        'src'     => 'application/x-wais-source',
        'sv4cpio' => 'application/x-sv4cpio',
        'sv4crc'  => 'application/x-sv4crc',
        'svg'     => 'image/svg+xml',
        'swf'     => 'application/x-shockwave-flash',
        't'       => 'application/x-troff',
        'tar'     => 'application/x-tar',
        'tcl'     => 'application/x-tcl',
        'tex'     => 'application/x-tex',
        'texi'    => 'application/x-texinfo',
        'texinfo' => 'application/x-texinfo',
        'tif'     => 'image/tiff',
        'tiff'    => 'image/tiff',
        'tr'      => 'application/x-troff',
        'tsv'     => 'text/tab-separated-values',
        'txt'     => 'text/plain',
        'ustar'   => 'application/x-ustar',
        'vcd'     => 'application/x-cdlink',
        'vrml'    => 'model/vrml',
        'vxml'    => 'application/voicexml+xml',
        'wav'     => 'audio/x-wav',
        'wbmp'    => 'image/vnd.wap.wbmp',
        'wbxml'   => 'application/vnd.wap.wbxml',
        'webm'    => 'video/webm',
        'wml'     => 'text/vnd.wap.wml',
        'wmlc'    => 'application/vnd.wap.wmlc',
        'wmls'    => 'text/vnd.wap.wmlscript',
        'wmlsc'   => 'application/vnd.wap.wmlscriptc',
        'wmv'     => 'video/x-ms-wmv',
        'wrl'     => 'model/vrml',
        'xbm'     => 'image/x-xbitmap',
        'xht'     => 'application/xhtml+xml',
        'xhtml'   => 'application/xhtml+xml',
        'xls'     => 'application/vnd.ms-excel',
        'xml'     => 'application/xml',
        'xpm'     => 'image/x-xpixmap',
        'xsl'     => 'application/xml',
        'xslt'    => 'application/xslt+xml',
        'xul'     => 'application/vnd.mozilla.xul+xml',
        'xwd'     => 'image/x-xwindowdump',
        'xyz'     => 'chemical/x-xyz',
        'zip'     => 'application/zip'
    );
    return isset($mime_types[$ext]) ? $mime_types[$ext] : 'application/octet-stream';
}


/**
 * 防CSRF验证
 * @param bool $strict 严格模式。拒绝空referer
 */
function csrf_token()
{
    $secret = defined('SYSTEM_SALT') && SYSTEM_SALT !== '' ? (string) SYSTEM_SALT : '';
    if ($secret === '' && class_exists('option', false)) {
        //与 P::cryptoKey 相同的按需供应：首次使用即生成随机密钥落库。
        //禁止硬编码回退盐——那会让任何拿到源码的人算出合法 CSRF 令牌。
        $secret = (string) option::get('crypto_key');
        if ($secret === '') {
            $secret = bin2hex(random_bytes(32));
            option::add('crypto_key', $secret);
        }
    }
    if ($secret === '') {
        //无数据库上下文（Web 请求不应到达此处）：逐请求随机，宁可令牌失效降级到 Referer 门控，也不落入稳定弱密钥
        $secret = bin2hex(random_bytes(32));
    }
    $nonce = isset($_COOKIE['tc_csrf']) ? (string) $_COOKIE['tc_csrf'] : '';
    $uid = defined('UID') ? (string) UID : 'visitor';
    return hash_hmac('sha256', 'csrf:' . $uid . ':' . $nonce, $secret);
}

/**
 * 防CSRF验证
 * @param bool $strict 严格模式。拒绝空referer（兼容无 token 的旧请求）
 */
function csrf($strict = true, $requireToken = false)
{

    if (defined('ANTI_CSRF') && !ANTI_CSRF) {
        return;
    }
    if (SYSTEM_ISCONSOLE) {
        return;
    }
    $token = isset($_REQUEST['csrf_token']) ? trim((string) $_REQUEST['csrf_token']) : '';
    if ($token === '' && isset($_SERVER['HTTP_X_CSRF_TOKEN'])) {
        $token = trim((string) $_SERVER['HTTP_X_CSRF_TOKEN']);
    }
    if ($token !== '' && hash_equals(csrf_token(), $token)) {
        return;
    }
    global $i;
    // Keep the legacy Referer fallback for existing admin/plugin links. New core
    // AJAX write requests opt into the signed-token requirement explicitly.
    // 请求带了 token 但校验不过：不是过期就是伪造，直接拒绝，不再静默降级到 Referer。
    if ($token !== '') {
        msg('CSRF 防御：安全令牌校验失败。请刷新页面后重试。');
    }
    $referer = isset($_SERVER['HTTP_REFERER']) ? trim((string) $_SERVER['HTTP_REFERER']) : '';
    $method = isset($_SERVER['REQUEST_METHOD']) ? strtoupper((string) $_SERVER['REQUEST_METHOD']) : 'GET';
    $isWriteMethod = in_array($method, array('POST', 'PUT', 'PATCH', 'DELETE'), true);
    if (($isWriteMethod || $requireToken) && $token === '') {
        msg('CSRF 防御：无效请求（缺少安全令牌）。请刷新页面后重试。');
    }
    if ($referer === '') {
        if ($strict) {
            msg('CSRF防御：无效请求（缺少来源或安全令牌）。<a href="' . SUPPORT_URL . '/%E5%85%B3%E4%BA%8E%E4%BA%91%E7%AD%BE%E5%88%B0CSRF%E9%98%B2%E5%BE%A1" target="_blank">了解更多关于CSRF防御...</a>');
        }
        return;
    }
    $p = parse_url($referer);
    $parse_system_url = parse_url(isset($i['opt']['system_url']) ? $i['opt']['system_url'] : '');
    if (!$p || empty($p['host'])) {
        msg('CSRF防御：无效请求。<a href="' . SUPPORT_URL . '/%E5%85%B3%E4%BA%8E%E4%BA%91%E7%AD%BE%E5%88%B0CSRF%E9%98%B2%E5%BE%A1" target="_blank">了解更多关于CSRF防御...</a>');
    }
    $expected_host = !empty($parse_system_url['host']) ? $parse_system_url['host'] : '';
    if ($expected_host === '' && !empty($_SERVER['SERVER_NAME'])) {
        $expected_host = $_SERVER['SERVER_NAME'];
    }
    if ($expected_host === '' && !empty($_SERVER['HTTP_HOST'])) {
        $host_url = parse_url('//' . $_SERVER['HTTP_HOST']);
        $expected_host = isset($host_url['host']) ? $host_url['host'] : '';
    }
    if ($expected_host === '' || strcasecmp($p['host'], $expected_host) !== 0) {
        msg('CSRF防御：错误的请求来源<a href="' . SUPPORT_URL . '/%E5%85%B3%E4%BA%8E%E4%BA%91%E7%AD%BE%E5%88%B0CSRF%E9%98%B2%E5%BE%A1" target="_blank">了解更多关于CSRF防御...</a>');
    }
}

/**
 * 检查URL，防XSS
 * @param string $url 待处理的url
 * @param string $linktext 链接文字
 * @return string 纯文本或者链接dom
 */
function sanitize_html_link($url = "", $linktext = "")
{

    if (!$url) {
        return htmlspecialchars($linktext ?: "");
    }
    $parseUrl = parse_url($url);
    if (isset($parseUrl["scheme"]) && in_array($parseUrl["scheme"], ["http", "https"]) && !empty($parseUrl["host"])) {
        return '<a href="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '" target="_blank">' . htmlspecialchars($linktext ?: "", ENT_QUOTES, 'UTF-8') . '</a>';
    } else {
        return htmlspecialchars($linktext ?: "");
    }
}

/**
 * 安全解压 ZIP，防止路径穿越（zip slip）
 * @param ZipArchive $zip 已打开的压缩包
 * @param string $dest 目标目录（必须是已存在的绝对/相对路径）
 * @return bool
 */
function safe_zip_extract($zip, $dest)
{
    $base = realpath($dest);
    if ($base === false || !is_dir($base)) {
        return false;
    }
    $base = rtrim(str_replace('\\', '/', $base), '/') . '/';
    for ($i = 0; $i < $zip->numFiles; $i++) {
        $name = $zip->getNameIndex($i);
        if ($name === false) {
            return false;
        }
        $name = str_replace('\\', '/', $name);
        if ($name === '' || strpos($name, "\0") !== false || strpos($name, '../') !== false || $name[0] === '/' || preg_match('/^[A-Za-z]:/', $name)) {
            return false;
        }
        $target = $base . $name;
        if (substr($target, 0, strlen($base)) !== $base) {
            return false;
        }
        if (substr($name, -1) === '/') {
            if (!is_dir($target) && !mkdir($target, 0777, true) && !is_dir($target)) {
                return false;
            }
            continue;
        }
        $dir = dirname($target);
        if (!is_dir($dir) && !mkdir($dir, 0777, true) && !is_dir($dir)) {
            return false;
        }
        $stream = $zip->getStream($name);
        if ($stream === false) {
            return false;
        }
        $ok = file_put_contents($target, stream_get_contents($stream));
        fclose($stream);
        if ($ok === false) {
            return false;
        }
    }
    return true;
}

/**
 * 编码一行 CSV 字段（RFC 4180：含逗号、引号或换行的字段加引号包裹，引号翻倍）
 * @param array $fields 字段列表
 * @return string 以换行结尾的 CSV 行
 */
function csv_line(array $fields)
{
    $fields = array_map('strval', $fields);
    foreach ($fields as &$v) {
        //防公式注入：以 = + - @ 或制表符开头的单元格会被 Excel/WPS 当公式执行
        if ($v !== '' && preg_match('/^[=+\-@\t]/', $v)) {
            $v = "'" . $v;
        }
        if (preg_match('/[",\r\n]/', $v)) {
            $v = '"' . str_replace('"', '""', $v) . '"';
        }
    }
    unset($v);
    return implode(',', $fields) . "\n";
}
