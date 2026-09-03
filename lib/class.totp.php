<?php

if (!defined('SYSTEM_ROOT')) {
    die('Insufficient Permissions');
}

/**
 * TOTP 双因素认证（V5.13 B5，RFC 6238）
 * 基于 HMAC-SHA1、30 秒周期、6 位数字，兼容 Google Authenticator / Microsoft Authenticator。
 * 纯 PHP 实现，无第三方依赖。
 */
class TOTP
{
    /** 默认周期（秒） */
    const PERIOD = 30;
    /** 默认码长 */
    const DIGITS = 6;
    /** RFC 6238 规定 TOTP 默认摘要为 HMAC-SHA-1（兼容所有标准验证器应用），并非弱哈希口令存储场景 */
    const HMAC_ALGO = 'sha1';

    /**
     * 生成随机 Base32 secret（160 bit，与 Google Authenticator 兼容）
     */
    public static function generateSecret($bytes = 20)
    {
        try {
            $raw = random_bytes(max(16, (int) $bytes));
        } catch (Throwable $e) {
            $raw = substr(hash('sha256', uniqid('', true) . microtime(), true), 0, max(16, (int) $bytes));
        }
        return self::base32Encode($raw);
    }

    /**
     * 计算某时间片对应的验证码
     * @param string $secret  Base32 secret
     * @param int    $timeSlice 时间片序号
     */
    public static function code($secret, $timeSlice = 0)
    {
        if ($timeSlice === 0) {
            $timeSlice = intdiv(time(), self::PERIOD);
        }
        $key = self::base32Decode(strtoupper(preg_replace('/[^A-Z2-7]/i', '', (string) $secret)));
        if ($key === '') {
            return '';
        }
        $msg = pack('N*', 0, (int) $timeSlice);
        $hash = hash_hmac(self::HMAC_ALGO, $msg, $key, true);
        $offset = ord($hash[strlen($hash) - 1]) & 0x0f;
        $binary = ((ord($hash[$offset]) & 0x7f) << 24)
            | ((ord($hash[$offset + 1]) & 0xff) << 16)
            | ((ord($hash[$offset + 2]) & 0xff) << 8)
            | (ord($hash[$offset + 3]) & 0xff);
        $otp = $binary % pow(10, self::DIGITS);
        return str_pad((string) $otp, self::DIGITS, '0', STR_PAD_LEFT);
    }

    /**
     * 校验验证码（允许前后各 window 个周期的时间偏移）
     * @return bool
     */
    public static function verify($secret, $code, $window = 1)
    {
        $code = trim((string) $code);
        if ($code === '' || !preg_match('/^\d{6}$/', $code)) {
            return false;
        }
        $slice = intdiv(time(), self::PERIOD);
        for ($i = -abs((int) $window); $i <= abs((int) $window); $i++) {
            if (hash_equals(self::code($secret, $slice + $i), $code)) {
                return true;
            }
        }
        return false;
    }

    /**
     * 生成 otpauth:// 链接（供二维码 / 手动录入）
     */
    public static function otpauthUri($secret, $account, $issuer = '')
    {
        $label = ($issuer !== '' ? $issuer . ':' : '') . $account;
        $params = array(
            'secret' => $secret,
            'issuer' => $issuer,
            'period' => self::PERIOD,
            'digits' => self::DIGITS,
        );
        return 'otpauth://totp/' . rawurlencode($label) . '?' . http_build_query($params);
    }

    // ---------- Base32 ----------

    /**
     * Base32 编码（RFC 4648，无填充）
     */
    public static function base32Encode($data)
    {
        static $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $data = (string) $data;
        if ($data === '') {
            return '';
        }
        $bits = '';
        $len = strlen($data);
        for ($i = 0; $i < $len; $i++) {
            $bits .= str_pad(decbin(ord($data[$i])), 8, '0', STR_PAD_LEFT);
        }
        $out = '';
        for ($i = 0; $i < strlen($bits); $i += 5) {
            $chunk = substr($bits, $i, 5);
            $out .= $alphabet[bindec(str_pad($chunk, 5, '0', STR_PAD_RIGHT))];
        }
        return $out;
    }

    /**
     * Base32 解码（RFC 4648）
     */
    public static function base32Decode($data)
    {
        static $reverse = array(
            'A'=>0,'B'=>1,'C'=>2,'D'=>3,'E'=>4,'F'=>5,'G'=>6,'H'=>7,
            'I'=>8,'J'=>9,'K'=>10,'L'=>11,'M'=>12,'N'=>13,'O'=>14,'P'=>15,
            'Q'=>16,'R'=>17,'S'=>18,'T'=>19,'U'=>20,'V'=>21,'W'=>22,'X'=>23,
            'Y'=>24,'Z'=>25,'2'=>26,'3'=>27,'4'=>28,'5'=>29,'6'=>30,'7'=>31,
        );
        $data = strtoupper(preg_replace('/[^A-Z2-7]/i', '', (string) $data));
        $bits = '';
        $len = strlen($data);
        for ($i = 0; $i < $len; $i++) {
            if (isset($reverse[$data[$i]])) {
                $bits .= str_pad(decbin($reverse[$data[$i]]), 5, '0', STR_PAD_LEFT);
            }
        }
        $out = '';
        for ($i = 0; $i + 8 <= strlen($bits); $i += 8) {
            $out .= chr(bindec(substr($bits, $i, 8)));
        }
        return $out;
    }
}
