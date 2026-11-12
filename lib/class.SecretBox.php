<?php

if (!defined('SYSTEM_ROOT')) {
    die('Insufficient Permissions');
}

/**
 * 敏感配置选项的统一加密读写门面（V7.5）
 *
 * 背景：V7.4 已对 BDUSS/STOKEN/AI Key 做了 AES-256-GCM 加密，
 * 但通知渠道 Token、SMTP 密码、OAuth Client Secret、代理密码等
 * 选项仍是明文落库 + 明文回显（页面源码与截图可泄露）。
 *
 * 用法：
 *   SecretBox::put('notify_telegram_token', $plain);   // 加密落库
 *   $v = SecretBox::get('notify_telegram_token');      // 解密返回明文
 *   SecretBox::masked('notify_telegram_token');        // 只返回掩码供回显
 *   SecretBox::clear('notify_telegram_token');         // 清空
 *
 * 设计约束：
 * - 不改动 api_token / metrics_token 的哈希语义（认证走 hash_equals，
 *   加密后认证逻辑会崩），两者继续用 api_token_storable() 存储。
 * - 解密后值在请求内 memo，避免重复 openssl_decrypt。
 * - 无密钥（SYSTEM_SALT 与 crypto_key 均空）时 P::encrypt 静默返回原文，
 *   本类的 has() 会据此返回 false，避免误报"已配置"。
 */
class SecretBox
{
    /** 请求内解密缓存：选项名 => 明文 */
    private static $cache = array();

    /** 该类管理的选项键全集（供迁移与测试对账） */
    public static function managedKeys()
    {
        return array(
            'oauth_github_client_secret',
            'mail_smtppw',
            'dama_username',
            'dama_password',
            'notify_serverchan_key',
            'notify_telegram_token',
            'notify_dingtalk_secret',
            'notify_dingtalk_webhook',
            'notify_bark_url',
            'notify_pushplus_token',
            'notify_wecom_webhook',
            'notify_feishu_webhook',
            'notify_wxpusher_token',
            'notify_webhook_url',
            'webhook_secret',
            'proxy_pass',
            'bbs_pw',
            'cron_pw',
        );
    }

    /**
     * 写入并加密。空字符串表示"清空"，与 put('') 等价。
     * @return bool
     */
    public static function put($name, $plain)
    {
        $name = (string) $name;
        $plain = (string) $plain;
        if ($plain === '') {
            return self::clear($name);
        }
        $stored = P::encrypt($plain);
        self::$cache[$name] = $plain;
        return option::set($name, $stored);
    }

    /**
     * 读取并解密。非密文形态（历史明文）原样返回，便于升级后渐进迁移。
     * @return string
     */
    public static function get($name)
    {
        $name = (string) $name;
        if (isset(self::$cache[$name])) {
            return self::$cache[$name];
        }
        $stored = (string) option::get($name);
        $plain = P::decrypt($stored);
        self::$cache[$name] = $plain;
        return $plain;
    }

    /**
     * 是否已配置非空凭据。
     * @return bool
     */
    public static function has($name)
    {
        return self::get($name) !== '';
    }

    /**
     * 只返回掩码形态，供页面回显（绝不输出明文中段）。
     * 依赖 lib/security.php 的 credential_mask()。
     * @return string 已配置时返回掩码，否则返回空串
     */
    public static function masked($name)
    {
        if (!function_exists('credential_mask')) {
            require_once SYSTEM_ROOT . '/lib/security.php';
        }
        $plain = self::get($name);
        return $plain === '' ? '' : credential_mask($plain);
    }

    /**
     * 清空该选项。
     * @return bool
     */
    public static function clear($name)
    {
        $name = (string) $name;
        unset(self::$cache[$name]);
        return option::set($name, '');
    }

    /**
     * 按表单约定保存敏感字段：空输入表示"保持不变"，
     * 仅当显式传入 clear_<name>=1 时才清空。
     *
     * @param array  $sou  $_POST 数组
     * @param string $name 选项名
     * @param string $clearKey 清空标记的 POST 键名（缺省 clear_<name>）
     * @return bool 是否执行了写入
     */
    public static function saveFromForm(array $sou, $name, $clearKey = null)
    {
        $clearKey = $clearKey === null ? 'clear_' . $name : (string) $clearKey;
        if (isset($sou[$clearKey]) && (string) $sou[$clearKey] === '1') {
            self::clear($name);
            return true;
        }
        $value = isset($sou[$name]) ? (string) $sou[$name] : '';
        if ($value === '') {
            //空提交 = 保持不变
            return false;
        }
        self::put($name, $value);
        return true;
    }

    /**
     * 测试与迁移用：清空请求内缓存（下一次 get() 重新读库解密）。
     */
    public static function flushCache()
    {
        self::$cache = array();
    }
}
