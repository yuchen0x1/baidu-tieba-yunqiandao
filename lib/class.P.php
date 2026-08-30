<?php

/**
 * 密码和加密类
 * @copyright (c) Kenvix
 */
class P
{
    const PWDMODE  = true; //允许从数据库获得密码加密方式？
    const PASSWORD_COST = 12;
    public $pwdmode;
    public $salt   = '';

    /**
     * @param string $salt 盐，留空将从config.php读取
     */
    public function __construct($salt = '')
    {
        if (self::PWDMODE && class_exists('option')) {
            $this->pwdmode = option::get('pwdmode');
        } else {
            $this->pwdmode = 'md5(md5(md5($pwd)))';
        }
        /*
        if (!empty($salt)) {
            $this->salt    = $salt;
        } else {
            $this->salt    = SYSTEM_SALT;
        }
        */
    }

    /**
     * [弃用] 对数据（通常是密码）进行不可逆加密
     * @param string $pwd 密码
     * @return string 加密的密码
     */
    public function legacy_pwd($pwd)
    {
        // pwdmode is stored in the database, so it must never be executed as PHP.
        // Keep compatibility with legacy formats shipped by older releases.
        $mode = trim((string) $this->pwdmode);
        if ($mode === 'md5(md5(md5($pwd)))') {
            return md5(md5(md5($pwd)));
        }
        if ($mode === 'md5(md5($pwd))') {
            return md5(md5($pwd));
        }
        if ($mode === 'sha1(sha1($pwd))') {
            return sha1(sha1($pwd));
        }
        if ($mode === 'sha1($pwd)') {
            return sha1($pwd);
        }
        return '';
    }

    /**
     * 对数据（通常是密码）进行不可逆加密
     * @param string $pwd 密码
     * @param boolean $with_legacy 是否包含旧密码(不支持 `password_hash()` 时无效)
     * @return string|object 新密码(和带旧版密码的 array)
     */
    public function pwd($pwd, $with_legacy = false)
    {
        if (function_exists("password_hash")) {
            $newHash = password_hash($pwd, PASSWORD_BCRYPT, array('cost' => self::PASSWORD_COST));
            if ($with_legacy) {
                $legacy_hash = $this->legacy_pwd($pwd);
                return [
                    "new" => $newHash,
                    "legacy" => $legacy_hash,
                ];
            } else {
                return $newHash;
            }
        } else {
            return $this->legacy_pwd($pwd);
        }
    }

    /**
     * 校验密码是否合法
     * @param string $pwd 密码
     * @return boolean 密码是否合法
     */
    public function pwd_verify($pwd, $hash)
    {
        if (function_exists("password_verify") && password_verify($pwd, $hash)) {
            return true;
        }
        // legacy
        $legacyHash = $this->legacy_pwd($pwd);
        return $legacyHash !== '' && $hash === $legacyHash;
    }

    public static function needsRehash($hash)
    {
        return function_exists('password_needs_rehash')
            && password_needs_rehash((string) $hash, PASSWORD_BCRYPT, array('cost' => self::PASSWORD_COST));
    }

    /**
     * 加密存储（AES-256-GCM）
     * @param string $plain 原文
     * @return string enc:v1:base64 或者空值原样返回
     */
    public static function encrypt($plain)
    {
        if ($plain === '' || $plain === null) {
            return $plain;
        }
        $key = self::cryptoKey();
        if ($key === '') {
            return $plain;
        }
        $iv = random_bytes(12);
        $tag = '';
        $cipher = openssl_encrypt($plain, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag);
        if ($cipher === false) {
            return $plain;
        }
        return 'enc:v1:' . base64_encode($iv . $tag . $cipher);
    }

    /**
     * 解密存储（兼容明文旧数据）
     * @param string $stored 数据库中的值
     * @return string 原文
     */
    public static function decrypt($stored)
    {
        if (!is_string($stored) || strpos($stored, 'enc:v1:') !== 0) {
            return (string) $stored;
        }
        $key = self::cryptoKey();
        if ($key === '') {
            return $stored;
        }
        $raw = base64_decode(substr($stored, 7), true);
        if ($raw === false || strlen($raw) < 29) {
            return $stored;
        }
        $iv = substr($raw, 0, 12);
        $tag = substr($raw, 12, 16);
        $cipher = substr($raw, 28);
        $plain = openssl_decrypt($cipher, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag);
        return $plain === false ? $stored : $plain;
    }

    /**
     * 获取加密密钥（SYSTEM_SALT 或自生成 crypto_key）
     * @return string 32 字节密钥，空表示无密钥不加密
     */
    private static function cryptoKey()
    {
        $salt = defined('SYSTEM_SALT') ? SYSTEM_SALT : '';
        if ($salt !== '') {
            return hash('sha256', 'tieba-cloud-sign-bduss-v1:' . $salt, true);
        }
        if (class_exists('option', false)) {
            $key = option::get('crypto_key');
            if (!empty($key)) {
                return hash('sha256', 'tieba-cloud-sign-bduss-v1:' . $key, true);
            }
            $key = bin2hex(random_bytes(32));
            option::add('crypto_key', $key);
            return hash('sha256', 'tieba-cloud-sign-bduss-v1:' . $key, true);
        }
        return '';
    }
}
