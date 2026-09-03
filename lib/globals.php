<?php

if (!defined('SYSTEM_ROOT')) {
    die('Insufficient Permissions');
}
/**
 * 全局用户控制
 */

/**
 * 校验找回密码令牌，返回 array(uid, expire) 或 false
 */
if (!function_exists('totp_enabled')) {
    function totp_enabled()
    {
        return (string) option::get('admin_totp_secret') !== '';
    }
}
if (!function_exists('totp_get_secret')) {
    function totp_get_secret()
    {
        $s = (string) option::get('admin_totp_secret');
        if ($s === '') {
            return '';
        }
        return P::decrypt($s);
    }
}
if (!function_exists('totp_issue_cookie')) {
    function totp_issue_cookie()
    {
        if (!defined('UID')) {
            return;
        }
        $val = hash_hmac('sha256', 'totp:' . UID, totp_get_secret()) . ':' . UID;
        set_site_cookie('tc_2fa', $val, time() + 2592000);
    }
}
if (!function_exists('totp_check_cookie')) {
    function totp_check_cookie()
    {
        if (!defined('UID') || empty($_COOKIE['tc_2fa'])) {
            return false;
        }
        $parts = explode(':', (string) $_COOKIE['tc_2fa'], 2);
        if (count($parts) !== 2) {
            return false;
        }
        list($sig, $uid) = $parts;
        if ((int) $uid !== (int) UID) {
            return false;
        }
        $expect = hash_hmac('sha256', 'totp:' . UID, totp_get_secret());
        return hash_equals($expect, $sig);
    }
}
if (!function_exists('lostpw_token_info')) {
    function lostpw_token_info($token)
    {
        global $m;
        $token = trim((string) $token);
        if ($token === '' || strlen($token) > 64 || !ctype_xdigit($token)) {
            return false;
        }
        $row = $m->once_fetch_array("SELECT `value` FROM `" . DB_NAME . "`.`" . DB_PREFIX . "options` WHERE `name` = 'lostpw:token:{$token}' LIMIT 1");
        if (empty($row['value'])) {
            return false;
        }
        $info = json_decode((string) $row['value'], true);
        if (!is_array($info) || empty($info['uid']) || empty($info['expire']) || $info['expire'] < time()) {
            return false;
        }
        return $info;
    }
}

/**
 * 确保邀请码表存在（多邀请码功能）
 */
if (!function_exists('invite_ensure_table')) {
    function invite_ensure_table()
    {
        global $m;
        $m->query("CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "invites` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `code` VARCHAR(32) NOT NULL DEFAULT '',
            `max_uses` INT UNSIGNED NOT NULL DEFAULT 1,
            `used_count` INT UNSIGNED NOT NULL DEFAULT 0,
            `expire` INT UNSIGNED NOT NULL DEFAULT 0,
            `status` TINYINT NOT NULL DEFAULT 1,
            `note` VARCHAR(255) NOT NULL DEFAULT '',
            `created_at` INT UNSIGNED NOT NULL DEFAULT 0,
            PRIMARY KEY (`id`),
            UNIQUE KEY `code` (`code`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
    }
}

/**
 * 校验邀请码是否可用
 * @return array|false 可用返回邀请码行，否则 false
 */
if (!function_exists('invite_check')) {
    function invite_check($code)
    {
        global $m;
        $code = trim((string) $code);
        if ($code === '' || strlen($code) > 32) {
            return false;
        }
        invite_ensure_table();
        $rs = $m->pquery("SELECT * FROM `" . DB_PREFIX . "invites` WHERE `code` = ? AND `status` = 1 AND (`expire` = 0 OR `expire` > " . time() . ") LIMIT 1", array($code));
        $row = $rs ? $m->fetch_array($rs) : array();
        if (empty($row['id']) || (int) $row['used_count'] >= (int) $row['max_uses']) {
            return false;
        }
        return $row;
    }
}

/**
 * 核销一次邀请码（原子递增，超上限不再计数）
 */
if (!function_exists('invite_consume')) {
    function invite_consume($inviteId)
    {
        global $m;
        $m->pquery("UPDATE `" . DB_PREFIX . "invites` SET `used_count` = `used_count` + 1 WHERE `id` = ? AND `used_count` < `max_uses`", array((int) $inviteId));
    }
}

/**
 * 生成注册验证令牌并发送验证邮件
 * @return bool 邮件是否发送成功
 */
if (!function_exists('send_regverify_mail')) {
    function send_regverify_mail($uid, $mail)
    {
        $uid = (int) $uid;
        $token = bin2hex(random_bytes(20));
        option::set('regverify:token:' . $token, json_encode(array('uid' => $uid, 'expire' => time() + 86400)));
        option::set('regverify:uid:' . $uid, $token);
        $link = SYSTEM_URL . 'index.php?mod=verify&token=' . $token;
        $site = htmlspecialchars(SYSTEM_NAME, ENT_QUOTES, 'UTF-8');
        $body = '<p>您好！</p><p>您正在注册 <strong>' . $site . '</strong>，请点击以下链接完成邮箱验证（24小时内有效）：</p><p><a href="' . $link . '">' . $link . '</a></p><p>若非本人操作，请忽略本邮件。</p>';
        $ok = misc::mail($mail, $site . ' - 邮箱验证', $body);
        option::set('regverify:last:' . $uid, (string) time());
        return $ok === true;
    }
}

doAction('globals');
if (isset($_COOKIE['uid']) && isset($_COOKIE['pwd'])) {
    if (!empty($_COOKIE['con_pwd']) && !empty($_COOKIE['con_uid'])) {
        $con_uid = (int) $_COOKIE['con_uid'];
        $con_pw  = (string) $_COOKIE['con_pwd'];
        $con_p   = $m->once_fetch_array("SELECT * FROM  `" . DB_NAME . "`.`" . DB_PREFIX . "users` WHERE `id` = '{$con_uid}' LIMIT 1");
        if (empty($con_p['id']) || hash_hmac('sha256', $con_p['pw'], $con_p['id'] . $con_p['pw']) !== $con_pw) {
            clear_site_cookie('con_uid');
            clear_site_cookie('con_pwd');
        } else {
            if (SYSTEM_PAGE == 'usercontrolback') {
                set_site_cookie('uid', $con_uid, time() + auth_cookie_lifetime());
                set_site_cookie('pwd', $con_pw, time() + auth_cookie_lifetime());
                clear_site_cookie('con_uid');
                clear_site_cookie('con_pwd');
                redirect('index.php');
            } else {
                define('CON_UID', $con_p['id']);
                define('CON_NAME', $con_p['name']);
                define('CON_EMAIL', $con_p['email']);
                define('CON_TABLE', $con_p['t']);
            }
        }
    }
    $uid = isset($_COOKIE['uid']) ? (int) $_COOKIE['uid'] : 0;
    $pw = isset($_COOKIE['pwd']) ? (string) $_COOKIE['pwd'] : '';
    $osq = $m->pquery("SELECT * FROM  `" . DB_NAME . "`.`" . DB_PREFIX . "users` WHERE `id` = ? LIMIT 1", array($uid));
    if ($m->num_rows($osq) == 0) {
        clear_site_cookie('uid');
        clear_site_cookie('pwd');
        ReDirect("index.php?mod=login&error_msg=" . urlencode('Cookies 所记录的账号信息不正确，请重新登录(#1)') . "");
        die;
    }
    doAction('globals_1');
    $p = $m->fetch_array($osq);
    if (hash_hmac('sha256', $p['pw'], $p['id'] . $p['pw']) !== $pw) {
        clear_site_cookie('uid');
        clear_site_cookie('pwd');
        ReDirect("index.php?mod=login&error_msg=" . urlencode('Cookies 所记录的账号信息不正确，请重新登录(#2)') . "");
        die;
    } else {
        define('LOGIN', true);
        define('ROLE', $p['role']);
        define('UID', $p['id']);
        define('NAME', $p['name']);
        define('EMAIL', $p['email']);
        define('TABLE', $p['t']);
        if (!defined('SYSTEM_ONLY_CHECK_LOGIN')) {
            $i['user']['login'] = true;
            $i['user']['role'] = $p['role'];
            $i['user']['uid'] = $p['id'];
            $i['user']['name'] = $p['name'];
            $i['user']['email'] = $p['email'];
            $i['user']['pw'] = $p['pw'];
            $i['user']['bduss'] = array();
            $i['user']['table'] = $p['t'];
            $i['user']['tbnum'] = $m->fetch_row($m->query('SELECT COUNT(*) FROM  `' . DB_NAME . '`.`' . DB_PREFIX . TABLE . '` WHERE `uid` = ' . UID));
            $i['user']['tbnum'] = $i['user']['tbnum'][0];
            $bds = $m->query("SELECT * FROM  `" . DB_NAME . "`.`" . DB_PREFIX . "baiduid` WHERE uid = " . UID);
            while ($bd = $m->fetch_array($bds)) {
                $bdspid = $bd['id'];
                $i['user']['bduss'][$bdspid] = P::decrypt($bd['bduss']);
                $i['user']['baidu'][$bdspid] = $bd['name'];
                $i['user']['baidu_portrait'][$bdspid] = $bd['portrait'];
                $i['user']['stoken'][$bdspid] = P::decrypt($bd['stoken']);
                $i['user']['bduss_proxy'][$bdspid] = isset($bd['proxy']) ? $bd['proxy'] : '';
            }
            $optss = $m->query("SELECT * FROM  `" . DB_NAME . "`.`" . DB_PREFIX . "users_options` WHERE uid = " . UID);
    //$GLOBALS = $i['user'];
            while ($opts = $m->fetch_array($optss)) {
                $name = $opts['name'];
                $i['user']['opt'][$name] = $opts['value'];
            }
        }
        //是否为VIP，管理员和VIP组的用户都为VIP
        if (ROLE == 'admin') {
            define('ISADMIN', true);
        } else {
            define('ISADMIN', false);
        }
        if (ROLE == 'admin' || ROLE == 'vip') {
            $i['user']['isvip'] = true;
            define('ISVIP', true);
        } else {
            $i['user']['isvip'] = false;
            define('ISVIP', false);
        }
        if (ROLE == 'banned') {
            msg('你已被禁止访问，请联系管理员解封');
        }
    }
    // V5.13 B5：管理员双因素认证（TOTP）强制校验
    if (ROLE == 'admin' && totp_enabled() && !in_array(SYSTEM_PAGE, array('admin:2fa', 'admin:totp', 'admin:logout'), true) && !totp_check_cookie()) {
        ReDirect('index.php?mod=admin:2fa');
        die;
    }
    doAction('globals_2');
}
doAction('globals_3');
csrf(false);
// 首次使用初始化：系统中不存在管理员账号时，强制先完成管理员账号初始化再使用
// （计划任务与命令行工具入口不受此拦截影响）
if (!defined('SYSTEM_DO_NOT_LOGIN') && !SYSTEM_ISCONSOLE && SYSTEM_PAGE != 'init' && SYSTEM_PAGE != 'init:do') {
    $initAdminRow = $m->once_fetch_array('SELECT COUNT(*) AS total FROM `' . DB_NAME . '`.`' . DB_PREFIX . 'users` WHERE `role` = \'admin\'');
    if ((int) $initAdminRow['total'] <= 0) {
        ReDirect('index.php?mod=init');
        die;
    }
}
if (SYSTEM_PAGE == 'admin:login') {
    if (defined('ROLE')) {
        ReDirect('index.php');
    }
    define('ROLE', 'visitor');
    $i['user']['role'] = 'visitor';
    doAction('admin_login_1');
// 检查验证码
    if (option::get('captcha')) {
        session_start();
        $captcha = request_scalar_value($_POST, 'captcha');
        if ($captcha === '') {
            redirect('index.php?mod=login&error_msg=' . urlencode('请填写验证码'));
                die;
        } else {
            if (strtolower($captcha) != strtolower((string) $_SESSION['captcha'])) {
                redirect('index.php?mod=login&error_msg=' . urlencode('验证码错误'));
                die;
            } else {
                session_destroy();
            }
        }
    }
    // 正常登录流程
    $rawLoginName = request_scalar_value($_POST, 'user');
    $name = sqladds($rawLoginName);
    $pw = request_scalar_value($_POST, 'pw');
    $loginRawName = LoginGuard::normalizeUsername($rawLoginName);
    if (empty($name) || empty($pw)) {
        ReDirect("index.php?mod=login&error_msg=" . urlencode('请填写账户或密码'));
            die;
    }
    // 登录限流: 同一账号短时间多次失败后锁定
    $loginLockRemain = LoginGuard::isLocked($loginRawName);
    if ($loginLockRemain > 0) {
        ReDirect("index.php?mod=login&error_msg=" . urlencode('登录失败次数过多，请 ' . (int) ceil($loginLockRemain / 60) . ' 分钟后再试'));
        die;
    }
    $osq = $m->pquery("SELECT * FROM  `" . DB_NAME . "`.`" . DB_PREFIX . "users` WHERE `name` = ? OR `email` = ? LIMIT 1", array($rawLoginName, $rawLoginName));
    if ($m->num_rows($osq) == 0) {
        LoginGuard::auditLogin(false, $loginRawName);
        ReDirect("index.php?mod=login&error_msg=" . urlencode('账户不存在 [ 提示：账户不是昵称，账户可为用户名或者邮箱地址 ]'));
            die;
    }
    $p = $m->fetch_array($osq);
    if (!VerifyPwd($pw, $p['pw'])) {
        LoginGuard::auditLogin(false, $loginRawName);
        ReDirect("index.php?mod=login&error_msg=" . urlencode('密码错误'));
        die;
    } else {
        //邮箱验证注册：存在未验证标记的账户拦截登录（无标记的老账户不受影响）
        $verRow = $m->once_fetch_array("SELECT `value` FROM `" . DB_NAME . "`.`" . DB_PREFIX . "users_options` WHERE `uid` = " . (int) $p['id'] . " AND `name` = 'email_verified' LIMIT 1");
        if (!empty($verRow) && $verRow['value'] != '1') {
            ReDirect('index.php?mod=verify&resend=' . (int) $p['id']);
            die;
        }
        LoginGuard::auditLogin(true, $loginRawName);
        if (P::needsRehash($p['pw'])) {
            $rehash = EncodePwd($pw);
            $m->query("UPDATE `" . DB_PREFIX . "users` SET `pw` = '" . $m->real_escape_string($rehash) . "' WHERE `id` = " . (int) $p['id'] . ";");
            // Keep the in-memory hash aligned with the persisted hash so the
            // login cookie remains valid after an automatic password upgrade.
            $p['pw'] = $rehash;
        }
        doAction('admin_login_3');
        if (isset($_POST['ispersis']) && $_POST['ispersis'] == 1) {
            $cktime = (int) option::get('cktime');
            if ($cktime <= 0) {
                $cktime = auth_cookie_lifetime();
            }
            $cktime = min(max($cktime, 1), auth_cookie_lifetime());
            set_site_cookie('uid', (int) $p['id'], time() + $cktime);
            set_site_cookie('pwd', hash_hmac('sha256', $p['pw'], $p['id'] . $p['pw']), time() + $cktime);
            ReDirect('index.php');
        } else {
            set_site_cookie('uid', (int) $p['id']);
            set_site_cookie('pwd', hash_hmac('sha256', $p['pw'], $p['id'] . $p['pw']));
            ReDirect('index.php');
        }
    }
    doAction('admin_login_2');
} elseif (SYSTEM_PAGE == 'admin:reg') {
    if (defined('ROLE')) {
        ReDirect('index.php');
    }
    define('ROLE', 'visitor');
    doAction('admin_reg_1');
    if (option::get('enable_reg') != '1') {
        msg('注册失败：该站点已关闭注册');
    }
    // 检查验证码
    if (option::get('captcha')) {
        session_start();
        $captcha = request_scalar_value($_POST, 'captcha');
        if ($captcha === '') {
            redirect('index.php?mod=reg&error_msg=' . urlencode('请填写验证码'));
            die;
        } else {
            if (strtolower($captcha) != strtolower((string) $_SESSION['captcha'])) {
                redirect('index.php?mod=reg&error_msg=' . urlencode('验证码错误'));
                die;
            } else {
                session_destroy();
            }
        }
    }
    // 正常注册流程
    $name = trim((string) request_scalar_value($_POST, 'user'));
// 用户名不得含有@符号，避免登录时与邮箱混淆
    if (stripos($name, '@') !== false) {
        msg('注册失败：用户名不得含有@符号');
    }
    if (!preg_match('/^[A-Za-z0-9_\x{4e00}-\x{9fa5}]{1,20}$/u', $name)) {
        msg('注册失败：用户名只允许字母、数字、下划线、中文，长度不超过 20');
    }
    $mail = trim((string) request_scalar_value($_POST, 'mail'));
    $pw = request_scalar_value($_POST, 'pw');
    $repeatPw = request_scalar_value($_POST, 'rpw');
    $yr = sqladds(request_scalar_value($_POST, 'yr'));
    if (empty($name) || empty($mail) || empty($pw)) {
        msg('注册失败：请正确填写用户名、密码或邮箱');
    }
    if ($pw !== $repeatPw) {
        msg('注册失败：两次输入的密码不一致，请重新输入');
    }
    $x = $m->once_fetch_array("SELECT COUNT(*) AS total FROM `" . DB_NAME . "`.`" . DB_PREFIX . "users` WHERE `name` = '" . sqladds($name) . "' OR `email` = '" . sqladds($mail) . "' LIMIT 1");
    $y = $m->once_fetch_array("SELECT COUNT(*) AS total FROM `" . DB_NAME . "`.`" . DB_PREFIX . "users`");
    if ($x['total'] > 0) {
        msg('注册失败：用户名或邮箱已经被注册');
    }
    if (!checkMail($mail)) {
        msg('注册失败：邮箱格式不正确');
    }
    $yr_reg = option::get('yr_reg');
    $inviteRow = false;
    if (option::get('yr_reg_multi') == '1') {
        //多邀请码模式：校验邀请码表，单码设置失效
        if (empty($yr)) {
            msg('注册失败：请输入邀请码');
        } else {
            $inviteRow = invite_check($yr);
            if ($inviteRow === false) {
                msg('注册失败：邀请码无效、已过期或已用尽');
            }
        }
    } elseif (!empty($yr_reg)) {
        if (empty($yr)) {
            msg('注册失败：请输入邀请码');
        } else {
            if ($yr_reg != $yr) {
                msg('注册失败：邀请码错误');
            }
        }
    }
    if ($y['total'] <= 0) {
        $role = 'admin';
    } else {
        $role = 'user';
    }
    doAction('admin_reg_2');
    $m->pquery('INSERT INTO `' . DB_NAME . '`.`' . DB_PREFIX . 'users` (`name`, `pw`, `email`, `role`, `t`) VALUES (?, ?, ?, ?, ?)', array($name, EncodePwd($pw), $mail, $role, getfreetable()));
    $newUid = (int) $m->insert_id();
    if ($inviteRow !== false) {
        invite_consume($inviteRow['id']);
    }
    $regMsg = '成功注册，请输入账号信息登录本站 [ 账号为用户名或邮箱地址 ]';
    //邮箱验证注册：标记未验证并发送验证邮件
    if (option::get('reg_mail_verify') == '1') {
        $m->query("INSERT INTO `" . DB_NAME . "`.`" . DB_PREFIX . "users_options` (`id`, `uid`, `name`, `value`) VALUES (NULL, {$newUid}, 'email_verified', '0')");
        if (send_regverify_mail($newUid, $mail)) {
            $regMsg = '注册成功！验证邮件已发送至您的邮箱，请完成邮箱验证后再登录';
        } else {
            $regMsg = '注册成功，但验证邮件发送失败；请在登录页按提示重新发送验证邮件';
        }
    }
    doAction('admin_reg_3');
    ReDirect('index.php?mod=login&msg=' . urlencode($regMsg));
} elseif (SYSTEM_PAGE == 'lostpw') {
//访客可访问的找回密码页面
    if (defined('ROLE')) {
        ReDirect('index.php');
    }
    define('ROLE', 'visitor');
    $i['user']['role'] = 'visitor';
    doAction('lostpw_page_1');
    $token = isset($_GET['token']) ? trim((string) $_GET['token']) : '';
    if ($token !== '' && lostpw_token_info($token) === false) {
        msg('重置链接无效或已过期，请重新申请');
    }
    template('lostpw');
    doAction('lostpw_page_2');
    die;
} elseif (SYSTEM_PAGE == 'lostpw:do') {
//找回密码的提交处理（申请重置 / 执行重置）
    if (defined('ROLE')) {
        ReDirect('index.php');
    }
    define('ROLE', 'visitor');
    doAction('lostpw_do_1');
    $action = request_scalar_value($_POST, 'action');
    if ($action === 'request') {
        $mailIn = trim((string) request_scalar_value($_POST, 'mail'));
        if ($mailIn === '' || !checkMail($mailIn)) {
            msg('请输入正确的邮箱地址');
        }
        if ((string) option::get('mail_name') === '') {
            msg('本站未配置邮件服务，无法自助找回密码，请联系管理员');
        }
        $uRes = $m->pquery("SELECT * FROM `" . DB_NAME . "`.`" . DB_PREFIX . "users` WHERE `email` = ? LIMIT 1", array($mailIn));
        $u = $uRes ? $m->fetch_array($uRes) : array();
        if (!empty($u['id'])) {
            $lastKey = 'lostpw:last:' . (int) $u['id'];
            $last = (int) option::get($lastKey);
            if ($last > 0 && (time() - $last) < 300) {
                msg('重置请求过于频繁，请 5 分钟后再试');
            }
            $token = bin2hex(random_bytes(20));
            option::set('lostpw:token:' . $token, json_encode(array('uid' => (int) $u['id'], 'expire' => time() + 1800)));
            option::set($lastKey, (string) time());
            $link = SYSTEM_URL . 'index.php?mod=lostpw&token=' . $token;
            $site = htmlspecialchars(SYSTEM_NAME, ENT_QUOTES, 'UTF-8');
            $mailBody = '<p>您好！</p><p>我们收到了您在 <strong>' . $site . '</strong> 的密码重置请求（链接30分钟内有效）。请点击以下链接设置新密码：</p><p><a href="' . $link . '">' . $link . '</a></p><p>若非本人操作，请忽略本邮件，账户不会受影响。</p>';
            $sent = misc::mail($u['email'], $site . ' - 密码重置', $mailBody);
            if ($sent !== true) {
                msg('邮件发送失败：' . strip_tags((string) $sent));
            }
        }
        msg('如果该邮箱已注册，重置邮件已发出，请查收（包括垃圾邮件箱）');
    } elseif ($action === 'reset') {
        $token = trim((string) request_scalar_value($_POST, 'token'));
        $info = lostpw_token_info($token);
        if ($info === false) {
            msg('重置链接无效或已过期，请重新申请');
        }
        $pw = (string) request_scalar_value($_POST, 'pw');
        $rpw = (string) request_scalar_value($_POST, 'rpw');
        if ($pw === '' || $pw !== $rpw) {
            msg('两次输入的密码不一致或为空');
        }
        $m->pquery("UPDATE `" . DB_NAME . "`.`" . DB_PREFIX . "users` SET `pw` = ? WHERE `id` = ?", array(EncodePwd($pw), (int) $info['uid']));
        option::del('lostpw:token:' . $token);
        ReDirect('index.php?mod=login&msg=' . urlencode('密码重置成功，请使用新密码登录'));
    }
    msg('无效的请求');
} elseif (SYSTEM_PAGE == 'verify') {
//邮箱验证（注册后）
    if (defined('ROLE')) {
        ReDirect('index.php');
    }
    define('ROLE', 'visitor');
    $i['user']['role'] = 'visitor';
    if (option::get('reg_mail_verify') != '1') {
        ReDirect('index.php?mod=login');
    }
    doAction('verify_page_1');
    $token = isset($_GET['token']) ? trim((string) $_GET['token']) : '';
    $resendUid = isset($_GET['resend']) ? (int) $_GET['resend'] : 0;
    if ($token !== '') {
        $row = $m->once_fetch_array("SELECT `value` FROM `" . DB_NAME . "`.`" . DB_PREFIX . "options` WHERE `name` = 'regverify:token:{$token}' LIMIT 1");
        $info = !empty($row['value']) ? json_decode((string) $row['value'], true) : false;
        if (!is_array($info) || empty($info['uid']) || empty($info['expire']) || $info['expire'] < time()) {
            msg('验证链接无效或已过期，请通过登录页的提示重新发送验证邮件');
        }
        $vUid = (int) $info['uid'];
        $m->query("UPDATE `" . DB_NAME . "`.`" . DB_PREFIX . "users_options` SET `value` = '1' WHERE `uid` = {$vUid} AND `name` = 'email_verified'");
        $chk = $m->once_fetch_array("SELECT COUNT(*) AS c FROM `" . DB_NAME . "`.`" . DB_PREFIX . "users_options` WHERE `uid` = {$vUid} AND `name` = 'email_verified' AND `value` = '1'");
        if (empty($chk['c'])) {
            //UPDATE 因行不存在而未生效时兜底插入
            $m->query("INSERT INTO `" . DB_NAME . "`.`" . DB_PREFIX . "users_options` (`id`, `uid`, `name`, `value`) VALUES (NULL, {$vUid}, 'email_verified', '1')");
        }
        option::del('regverify:token:' . $token);
        option::del('regverify:uid:' . $vUid);
        msg('邮箱验证成功，现在可以使用账号登录了', SYSTEM_URL . 'index.php?mod=login');
    }
    if ($resendUid > 0) {
        $u = $m->once_fetch_array("SELECT * FROM `" . DB_NAME . "`.`" . DB_PREFIX . "users` WHERE `id` = {$resendUid} LIMIT 1");
        if (empty($u['id'])) {
            msg('账户不存在，请返回注册页重新操作');
        }
        $verRow = $m->once_fetch_array("SELECT `value` FROM `" . DB_NAME . "`.`" . DB_PREFIX . "users_options` WHERE `uid` = {$resendUid} AND `name` = 'email_verified' LIMIT 1");
        if (empty($verRow) || $verRow['value'] == '1') {
            msg('该账户已验证或无需验证，请直接登录', SYSTEM_URL . 'index.php?mod=login');
        }
        $last = (int) option::get('regverify:last:' . $resendUid);
        if ($last > 0 && (time() - $last) < 300) {
            msg('验证邮件发送过于频繁，请 5 分钟后再试');
        }
        if ((string) option::get('mail_name') === '') {
            msg('本站未配置邮件服务，请联系管理员完成验证');
        }
        if (!send_regverify_mail($resendUid, $u['email'])) {
            msg('验证邮件发送失败，请稍后重试或联系管理员');
        }
        msg('验证邮件已重新发送至 ' . htmlspecialchars($u['email'], ENT_QUOTES, 'UTF-8') . '，请查收（包括垃圾邮件箱）', SYSTEM_URL . 'index.php?mod=login');
    }
    msg('无效的访问');
} elseif (SYSTEM_PAGE == 'captcha') {
// 防止恶意用户通过get参数临时修改验证码等级使其更易于被识别
    // 只有管理员才能通过get参数临时修改验证码等级（用于设置页预览）
    define('SYSTEM_NO_ERROR', true);
    if (defined('ROLE') && ROLE == 'admin') {
        $level = isset($_GET['level']) ? $_GET['level'] : option::get('captcha');
    // 验证码等级。0关闭，1简单，2中等，3困难，4反人类
    } else {
        $level = option::get('captcha');
    }
    if ($level) {
// 不同的验证码等级不同的配置
        $data = array(
            1 => array('line' => 5, 'star' => 30, 'fontsize' => 25),
            2 => array('line' => 10, 'star' => 60, 'fontsize' => 20),
            3 => array('line' => 20, 'star' => 100, 'fontsize' => 17),
            4 => array('line' => 40, 'star' => 250, 'fontsize' => 15),
        );
        require_once SYSTEM_ROOT . '/lib/class.captcha.php';
        $c = new Captcha($data[$level]);
        $c->create();
        session_start();
        $_SESSION['captcha'] = $c->getCode();
    } else {
        require_once SYSTEM_ROOT . '/lib/class.captcha.php';
        $c = new Captcha();
        $c->createNoting();
    }
    exit;
} elseif (SYSTEM_PAGE == 'init') {
    // 首次使用初始化页
    if (defined('ROLE')) {
        ReDirect('index.php');
    }
    define('ROLE', 'visitor');
    $i['user']['role'] = 'visitor';
    doAction('init_page_1');
    $initAdminRow = $m->once_fetch_array('SELECT COUNT(*) AS total FROM `' . DB_NAME . '`.`' . DB_PREFIX . 'users` WHERE `role` = \'admin\'');
    if ((int) $initAdminRow['total'] > 0) {
        ReDirect('index.php?mod=login');
        die;
    }
    template('init');
    doAction('init_page_4');
    die;
} elseif (SYSTEM_PAGE == 'init:do') {
    // 首次使用初始化：创建首个管理员账号并自动登录
    if (defined('ROLE')) {
        ReDirect('index.php');
    }
    define('ROLE', 'visitor');
    doAction('init_do_1');
    $name = trim((string) request_scalar_value($_POST, 'user'));
    // 用户名不得含有@符号，避免登录时与邮箱混淆
    if (stripos($name, '@') !== false) {
        redirect('index.php?mod=init&error_msg=' . urlencode('用户名不得含有@符号'));
        die;
    }
    if (!preg_match('/^[A-Za-z0-9_\x{4e00}-\x{9fa5}]{1,20}$/u', $name)) {
        redirect('index.php?mod=init&error_msg=' . urlencode('用户名只允许字母、数字、下划线、中文，长度不超过 20'));
        die;
    }
    $mail = trim((string) request_scalar_value($_POST, 'mail'));
    $pw = request_scalar_value($_POST, 'pw');
    $repeatPw = request_scalar_value($_POST, 'rpw');
    if (empty($name) || empty($mail) || empty($pw)) {
        redirect('index.php?mod=init&error_msg=' . urlencode('请正确填写用户名、密码或邮箱'));
        die;
    }
    if ($pw !== $repeatPw) {
        redirect('index.php?mod=init&error_msg=' . urlencode('两次输入的密码不一致，请重新输入'));
        die;
    }
    if (!checkMail($mail)) {
        redirect('index.php?mod=init&error_msg=' . urlencode('邮箱格式不正确'));
        die;
    }
    if (strlen((string) $pw) < 8) {
        redirect('index.php?mod=init&error_msg=' . urlencode('管理员密码至少 8 位'));
        die;
    }
    $initDup = $m->fetch_array($m->pquery('SELECT COUNT(*) AS total FROM `' . DB_NAME . '`.`' . DB_PREFIX . 'users` WHERE `name` = ? OR `email` = ? LIMIT 1', array($name, $mail)));
    if ((int) $initDup['total'] > 0) {
        redirect('index.php?mod=init&error_msg=' . urlencode('用户名或邮箱已经被注册'));
        die;
    }
    $initAdminRow = $m->fetch_array($m->pquery('SELECT COUNT(*) AS total FROM `' . DB_NAME . '`.`' . DB_PREFIX . 'users` WHERE `role` = ?', array('admin')));
    if ((int) $initAdminRow['total'] > 0) {
        redirect('index.php?mod=login&msg=' . urlencode('系统已完成初始化，请直接登录'));
        die;
    }
    doAction('init_do_2');
    $initStoredHash = EncodePwd($pw);
    $m->pquery('INSERT INTO `' . DB_NAME . '`.`' . DB_PREFIX . 'users` (`name`, `pw`, `email`, `role`, `t`) VALUES (?, ?, ?, ?, ?)', array($name, $initStoredHash, $mail, 'admin', getfreetable()));
    $initUid = (int) $m->insert_id();
    doAction('init_do_3');
    // 自动登录并进入工作台
    $initCktime = time() + auth_cookie_lifetime();
    set_site_cookie('uid', $initUid, $initCktime);
    set_site_cookie('pwd', hash_hmac('sha256', $initStoredHash, $initUid . $initStoredHash), $initCktime);
    ReDirect('index.php');
} elseif (SYSTEM_PAGE == 'login') {
    if (defined('ROLE')) {
        ReDirect('index.php');
    }
    define('ROLE', 'visitor');
    $i['user']['role'] = 'visitor';
    template('login');
    doAction('login_page_4');
    die;
} elseif (SYSTEM_PAGE == 'reg') {
    if (defined('ROLE')) {
        ReDirect('index.php');
    }
    define('ROLE', 'visitor');
    $i['user']['role'] = 'visitor';
    template('reg');
    doAction('reg_page_4');
    die;
} elseif (isset($_GET['pub_plugin'])) {
    define('ROLE', 'visitor');
    define('SYSTEM_READY_LOAD_PUBPLUGIN', true);
} elseif (SYSTEM_PAGE == 'admin:2fa') {
    if (!defined('ROLE') || ROLE != 'admin') {
        ReDirect('index.php?mod=login');
        die;
    }
    if (!totp_enabled()) {
        ReDirect('index.php');
        die;
    }
    if (isset($_POST['code'])) {
        csrf();
        require_once SYSTEM_ROOT . '/lib/class.totp.php';
        if (TOTP::verify(totp_get_secret(), (string) $_POST['code'])) {
            totp_issue_cookie();
            ReDirect('index.php');
            die;
        }
        msg('验证码错误，请重试');
    }
    $i['mode'] = array('admin', '2fa');
    template('admin-2fa');
    die;
} elseif (SYSTEM_PAGE == 'admin:logout') {
    csrf();
    doAction('logout');
    clear_site_cookie('uid');
    clear_site_cookie('toolpw');
    clear_site_cookie('pwd');
    ReDirect('index.php?mod=login');
} elseif (!defined('UID') && !defined('SYSTEM_DO_NOT_LOGIN')) {
    define('ROLE', 'visitor');
    $i['user']['role'] = 'visitor';
    ReDirect('index.php?mod=login');
}
