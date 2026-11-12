<?php

if (!defined('SYSTEM_ROOT')) {
    die('Insufficient Permissions');
}

/**
 * Session 统一加固（V7.5）
 *
 * 背景：V7.5 之前全仓 4 处裸 session_start()（lib/globals.php、lib/class.OAuth.php），
 * session cookie 的安全属性完全取决于 php.ini 默认值（常见默认 use_strict_mode=0、
 * cookie_httponly=0、无 SameSite），使 OAuth state 与验证码暴露在会话固定攻击下。
 *
 * 本文件集中管理 session 启动/再生/销毁，所有 session_start() 调用点必须经由
 * session_boot()，禁止裸调用。
 */

/**
 * 以安全默认值启动 session。
 *
 * - use_strict_mode=1：拒绝未初始化的 session id（防会话固定）
 * - use_only_cookies=1：禁止 session id 走 URL
 * - cookie_httponly=1：JS 不可读 session cookie
 * - cookie_samesite=Lax：跨站 POST 不携带，防 CSRF 会话利用
 * - cookie_secure：HTTPS 下自动开启（支持反向代理 X-Forwarded-Proto）
 * - session.name 前缀 tcs_：与同域其他应用隔离
 *
 * 幂等：session 已启动时直接返回。
 */
function session_boot()
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    if (session_status() === PHP_SESSION_DISABLED) {
        return;
    }
    //仅 Web 请求需要 session，CLI 不启（避免误生成 session 文件）
    if (defined('SYSTEM_ISCONSOLE') && SYSTEM_ISCONSOLE) {
        return;
    }

    //统一在任何输出前设置 ini
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.use_trans_sid', '0');
    ini_set('session.cookie_httponly', '1');
    ini_set('session.cookie_samesite', 'Lax');
    ini_set('session.gc_maxlifetime', '1800');
    ini_set('session.name', 'tcs_sid');

    //cookie_secure 跟随实际请求协议
    if (function_exists('request_is_https') && request_is_https()) {
        ini_set('session.cookie_secure', '1');
    } else {
        ini_set('session.cookie_secure', '0');
    }

    session_start();
}

/**
 * 登录成功后调用：重新生成 session id，使旧 id 立即失效（防会话固定）。
 */
function session_regenerate()
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        return;
    }
    session_regenerate_id(true);
}

/**
 * 登出后调用：销毁 session 数据与 cookie。
 *
 * 命名避开 PHP 内置 session_destroy()，故用 _all 后缀。
 */
function session_destroy_all()
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        return;
    }
    $_SESSION = array();
    //清除 cookie（含 HttpOnly/SameSite 属性，确保浏览器真正删除）
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', array(
            'expires'  => time() - 42000,
            'path'     => $params['path'],
            'domain'   => $params['domain'],
            'secure'   => $params['secure'],
            'httponly' => $params['httponly'],
            'samesite' => isset($params['samesite']) ? $params['samesite'] : 'Lax',
        ));
    }
    session_destroy();
}
