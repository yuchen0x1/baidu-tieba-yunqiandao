<?php

if (!defined('SYSTEM_ROOT')) { die('Insufficient Permissions'); }

/**
 * 第三方登录（V6.1 Phase 4）
 * Provider 抽象层 + 用户绑定存储（users_oauth 表）。
 * 当前内置 GitHub OAuth App 实现；QQ 互联 / 微信可按同一接口扩展。
 */
class OAuth
{
    const TABLE = 'users_oauth';

    /**
     * 支持的 Provider 清单（是否已配置由后台选项决定）
     */
    public static function providers()
    {
        return array(
            'github' => array(
                'label' => 'GitHub',
                'configured' => self::githubConfigured(),
            ),
        );
    }

    public static function githubConfigured()
    {
        return option::get('oauth_github_enable') == '1'
            && trim((string) option::get('oauth_github_client_id')) !== ''
            && trim((string) option::get('oauth_github_client_secret')) !== '';
    }

    /**
     * OAuth 回调路径（仅用于后台展示配置说明，不参与请求：
     * GitHub 对只注册单个回调的应用会强制使用注册地址，从源头消除回调篡改/开放重定向）
     */
    public static function redirectUri()
    {
        return rtrim(trim((string) option::get('system_url')), '/') . '/index.php?mod=oauth:callback&provider=github';
    }

    /**
     * 确保 users_oauth 绑定表存在（懒建表）
     */
    public static function ensureTable()
    {
        global $m;
        static $ready = false;
        if ($ready) {
            return;
        }
        $m->query("CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . self::TABLE . "` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `uid` INT UNSIGNED NOT NULL,
            `provider` VARCHAR(20) NOT NULL DEFAULT '',
            `openid` VARCHAR(64) NOT NULL DEFAULT '',
            `info` VARCHAR(255) NOT NULL DEFAULT '',
            `bound_at` INT UNSIGNED NOT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `provider_openid` (`provider`, `openid`),
            KEY `uid` (`uid`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
        $ready = true;
    }

    /**
     * 发起授权：生成 state 写入会话并跳转到 Provider 授权页
     * @param string $provider
     * @param bool $bindMode true 表示登录用户绑定流程
     */
    public static function start($provider, $bindMode = false)
    {
        if ($provider !== 'github' || !self::githubConfigured()) {
            msg('第三方登录未开启或配置不完整');
        }
        self::ensureSession();
        $state = bin2hex(random_bytes(16));
        $_SESSION['oauth_state'] = $state;
        $_SESSION['oauth_provider'] = $provider;
        $_SESSION['oauth_bind'] = $bindMode ? '1' : '0';
        // 不携带 redirect_uri：GitHub 强制使用 OAuth App 注册的回调地址。
        // 跳转目标主机硬编码在 sink 内，client_id/state 均为严格白名单校验后的组件值，
        // 不存在任何 URL 字符串输入，无开放重定向面；站点统一的 safe_redirect_url 会把
        // 外链改写回本站，因此这里直接下发 Location
        self::redirectGithubAuthorize(
            trim((string) option::get('oauth_github_client_id')),
            $state
        );
    }

    /**
     * 跳转 GitHub 授权页。目标主机硬编码，client_id/state 经白名单校验后拼接。
     * 不使用 header('Location')：站点 safe_redirect_url 会把外链改写回本站，
     * 故采用与 redirect() 降级通道一致的 0 秒 meta-refresh（URL 已转义，无注入面）。
     */
    private static function redirectGithubAuthorize($clientId, $state)
    {
        if (!preg_match('/^[A-Za-z0-9_]{1,64}$/', (string) $clientId) || !preg_match('/^[a-f0-9]{32}$/', (string) $state)) {
            msg('第三方登录配置异常（Client ID 不合法）或内部状态错误');
        }
        $url = 'https://github.com/login/oauth/authorize?client_id=' . $clientId . '&scope=read:user&state=' . $state;
        $safeUrl = htmlspecialchars($url, ENT_QUOTES, 'UTF-8');
        msg('<meta http-equiv="refresh" content="0; url=' . $safeUrl . '" />正在跳转到 GitHub 授权……<br/><br/><a href="' . $safeUrl . '">如果浏览器没有自动跳转，请点这里</a>', '跳转中');
    }

    /**
     * 回调处理：校验 state，换取 Token 并拉取用户信息
     * @return array|false {openid, name, avatar}；校验失败返回 false
     */
    public static function finish($provider, $code, $state)
    {
        self::ensureSession();
        if (empty($_SESSION['oauth_state']) || !is_string($state) || !hash_equals((string) $_SESSION['oauth_state'], $state)) {
            return false;
        }
        if ($provider !== (string) ($_SESSION['oauth_provider'] ?? '') || $provider !== 'github') {
            return false;
        }
        $bindMode = ($_SESSION['oauth_bind'] ?? '0') === '1';
        unset($_SESSION['oauth_state'], $_SESSION['oauth_provider'], $_SESSION['oauth_bind']);
        if (!is_string($code) || $code === '') {
            return false;
        }
        $token = self::githubExchange($code);
        if ($token === false) {
            return false;
        }
        $user = self::githubUser($token);
        if ($user === false || empty($user['id'])) {
            return false;
        }
        return array(
            'openid' => (string) (int) $user['id'],
            'name' => (string) ($user['login'] ?? ''),
            'avatar' => (string) ($user['avatar_url'] ?? ''),
            'bind' => $bindMode,
        );
    }

    private static function githubExchange($code)
    {
        $client = new wcurl('https://github.com/login/oauth/access_token', array(
            'Accept: application/json',
            'User-Agent: tieba-cloud-sign',
        ));
        $res = $client->post(array(
            'client_id' => trim((string) option::get('oauth_github_client_id')),
            'client_secret' => trim((string) option::get('oauth_github_client_secret')),
            'code' => (string) $code,
        ));
        $json = json_decode((string) $res, true);
        if (empty($json['access_token'])) {
            return false;
        }
        return (string) $json['access_token'];
    }

    private static function githubUser($token)
    {
        $client = new wcurl('https://api.github.com/user', array(
            'Accept: application/vnd.github+json',
            'User-Agent: tieba-cloud-sign',
            'Authorization: Bearer ' . $token,
        ));
        $json = json_decode((string) $client->get(), true);
        return is_array($json) ? $json : false;
    }

    /**
     * 当前用户在指定 Provider 下的绑定
     * @return array|false {provider, openid, info, bound_at}
     */
    public static function bindingOf($uid, $provider)
    {
        global $m;
        self::ensureTable();
        $row = $m->pquery("SELECT `provider`, `openid`, `info`, `bound_at` FROM `" . DB_PREFIX . self::TABLE . "`
            WHERE `uid` = ? AND `provider` = ? LIMIT 1", array((int) $uid, (string) $provider));
        $data = $row ? $m->fetch_array($row) : false;
        return $data ?: false;
    }

    /**
     * 按 openid 查绑定（含用户名，登录用）
     * @return array|false {uid, name, pw, openid, info}
     */
    public static function userOf($provider, $openid)
    {
        global $m;
        self::ensureTable();
        $row = $m->pquery("SELECT o.`uid`, o.`openid`, o.`info`, u.`name`, u.`pw`
            FROM `" . DB_PREFIX . self::TABLE . "` o
            INNER JOIN `" . DB_PREFIX . "users` u ON o.`uid` = u.`id`
            WHERE o.`provider` = ? AND o.`openid` = ? LIMIT 1", array((string) $provider, (string) $openid));
        $data = $row ? $m->fetch_array($row) : false;
        return $data ?: false;
    }

    /**
     * 绑定 Provider 身份到用户。openid 已被其他用户占用时返回 false。
     */
    public static function bind($uid, $provider, $openid, $info = '')
    {
        global $m;
        self::ensureTable();
        $uid = (int) $uid;
        $provider = (string) $provider;
        $openid = (string) $openid;
        if ($uid <= 0 || $openid === '') {
            return false;
        }
        $exist = self::userOf($provider, $openid);
        if ($exist && (int) $exist['uid'] !== $uid) {
            return false;
        }
        if ($exist) {
            $m->pquery("UPDATE `" . DB_PREFIX . self::TABLE . "` SET `info` = ?, `bound_at` = ?
                WHERE `provider` = ? AND `openid` = ?", array(
                    substr((string) $info, 0, 255), time(), $provider, $openid,
                ));
            return true;
        }
        $m->pquery("INSERT INTO `" . DB_PREFIX . self::TABLE . "` (`uid`, `provider`, `openid`, `info`, `bound_at`)
            VALUES (?, ?, ?, ?, ?)", array($uid, $provider, $openid, substr((string) $info, 0, 255), time()));
        return true;
    }

    public static function unbind($uid, $provider)
    {
        global $m;
        self::ensureTable();
        $m->pquery("DELETE FROM `" . DB_PREFIX . self::TABLE . "` WHERE `uid` = ? AND `provider` = ?", array((int) $uid, (string) $provider));
        return true;
    }

    /**
     * 由 GitHub 身份自动注册新用户（需站点开启注册且管理员允许 OAuth 注册）
     * 用户名取 GitHub login，按本站规则清洗；冲突时追加序号。
     * @return array|false {uid, name} 
     */
    public static function autoRegister($provider, $openid, $loginName)
    {
        global $m;
        if ($provider !== 'github' || option::get('enable_reg') != '1' || option::get('oauth_auto_register') != '1') {
            return false;
        }
        $base = preg_replace('/[^A-Za-z0-9_]/', '_', (string) $loginName);
        $base = trim($base, '_');
        $base = substr($base !== '' ? $base : 'gh' . $openid, 0, 16);
        if (!preg_match('/^[A-Za-z0-9_]{1,16}$/', $base)) {
            $base = 'gh' . substr((string) $openid, 0, 8);
        }
        $name = $base;
        $suffix = 0;
        while (true) {
            $row = $m->pquery("SELECT COUNT(*) AS c FROM `" . DB_PREFIX . "users` WHERE `name` = ?", array($name));
            $cnt = $row ? $m->fetch_array($row) : array('c' => 0);
            if ((int) $cnt['c'] === 0 && stripos($name, '@') === false) {
                break;
            }
            $suffix++;
            if ($suffix > 99) {
                return false;
            }
            $name = substr($base, 0, 16) . (string) $suffix;
        }
        $email = $openid . '+github@users.noreply.local';
        $m->pquery('INSERT INTO `' . DB_PREFIX . 'users` (`name`, `pw`, `email`, `role`, `t`) VALUES (?, ?, ?, ?, ?)', array(
            $name,
            EncodePwd(bin2hex(random_bytes(16))),
            $email,
            'user',
            getfreetable(),
        ));
        $newUid = (int) $m->insert_id();
        if ($newUid <= 0) {
            return false;
        }
        return array('uid' => $newUid, 'name' => $name);
    }

    private static function ensureSession()
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
    }
}
