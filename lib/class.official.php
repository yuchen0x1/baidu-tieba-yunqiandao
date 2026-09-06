<?php

if (!defined('SYSTEM_ROOT')) {
    die('Insufficient Permissions');
}

require_once __DIR__ . '/official-config.php';

/**
 * 官方服务网关客户端（开源侧 SDK）。
 *
 * 职责边界（对应 docs/architecture-boundary.md）：
 *  - 客户端只持有：网关地址、实例 ID、实例 Secret（加密存库）、短期 Access Token、验签公钥。
 *  - Provider Key、License 签发私钥、配额/风控策略全部留在官方网关侧；此处不实现、不缓存、不落盘。
 *  - 签名防重放：string_to_sign = timestamp \n nonce \n path \n sha256_hex(body)，HMAC-SHA256。
 *    仅使用 openssl/hash 标准原语，不自造密码学。
 *  - 网关未配置时本类完全不参与请求路径（纯社区版零行为变化）。
 */
class official
{
    /** 时间戳容忍窗口（秒），网关侧同样校验 */
    const TIMESTAMP_WINDOW = 300;
    /** Token 提前刷新余量（秒） */
    const TOKEN_REFRESH_MARGIN = 30;

    /**
     * 官方服务是否已接入（网关地址 + 实例凭据齐备）
     * @return bool
     */
    public static function enabled()
    {
        return self::gatewayUrl() !== ''
            && trim((string) option::get('official_instance_id')) !== ''
            && self::instanceSecret() !== '';
    }

    /** 网关基址（强制 https，去尾斜杠；未配置返回空串） */
    public static function gatewayUrl()
    {
        $url = rtrim(trim((string) option::get('official_gateway_url')), '/');
        if ($url === '' || stripos($url, 'https://') !== 0) {
            return '';
        }
        return $url;
    }

    /** 实例 Secret（后台经 P::encrypt 加密存库，此处解密；兼容历史明文） */
    public static function instanceSecret()
    {
        $stored = trim((string) option::get('official_instance_secret'));
        if ($stored === '') {
            return '';
        }
        $plain = P::decrypt($stored);
        return $plain === '' ? $stored : $plain;
    }

    /**
     * 请求签名（纯函数，可离线测试）
     * @param string $secret    实例 Secret
     * @param string $timestamp 秒级时间戳字符串
     * @param string $nonce     一次性随机串
     * @param string $path      网关路径（如 /v1/ai/chat，不含 query）
     * @param string $body      原始请求体
     * @return string hex 签名
     */
    public static function sign($secret, $timestamp, $nonce, $path, $body)
    {
        $stringToSign = $timestamp . "\n" . $nonce . "\n" . $path . "\n" . hash('sha256', (string) $body);
        return hash_hmac('sha256', $stringToSign, (string) $secret);
    }

    /**
     * 校验网关返回的 Access Token（结构+签名+有效期），返回 payload 或 false。
     * Token 形如 TCS1.<b64url(payload)>.<b64url(hmac)>，客户端只做验签与过期判断，
     * 权限的最终裁决始终在网关。
     * @return array|false
     */
    public static function decodeToken($token, $timestampSkew = 0)
    {
        $parts = explode('.', (string) $token);
        if (count($parts) !== 3 || $parts[0] !== 'TCS1') {
            return false;
        }
        $payload = self::b64urlDecode($parts[1]);
        $sig = self::b64urlDecode($parts[2]);
        if ($payload === false || $sig === false) {
            return false;
        }
        $data = json_decode($payload, true);
        if (!is_array($data) || empty($data['exp']) || empty($data['sub'])) {
            return false;
        }
        $expected = hash_hmac('sha256', $parts[0] . '.' . $parts[1], self::tokenVerifyKey(), true);
        if (!hash_equals($expected, $sig)) {
            return false;
        }
        if ((int) $data['exp'] < time() + $timestampSkew) {
            return false;
        }
        return $data;
    }

    /**
     * Token 验签密钥。客户端与网关共享实例 Secret 时可由其派生；派生域与网关侧一致。
     * @return string
     */
    private static function tokenVerifyKey()
    {
        return hash('sha256', 'tcs-official-token-v1:' . self::instanceSecret());
    }

    /**
     * 获取（带缓存）指定 scope 的 Access Token
     * @param string $scope 如 ai:generate
     * @param string|null $err 失败原因
     * @return string|false
     */
    public static function accessToken($scope, &$err = null)
    {
        if (!self::enabled()) {
            $err = '官方服务未接入';
            return false;
        }
        $cached = json_decode((string) option::get('official_access_token'), true);
        if (is_array($cached) && !empty($cached['token']) && !empty($cached['exp'])
            && (int) $cached['exp'] > time() + self::TOKEN_REFRESH_MARGIN
            && isset($cached['scope']) && self::scopeCovers($cached['scope'], $scope)) {
            return (string) $cached['token'];
        }
        $path = '/v1/token';
        $nonce = bin2hex(random_bytes(16));
        $timestamp = (string) time();
        $body = json_encode(array(
            'instance_id' => trim((string) option::get('official_instance_id')),
            'scope'       => $scope,
        ), JSON_UNESCAPED_UNICODE);
        $sig = self::sign(self::instanceSecret(), $timestamp, $nonce, $path, $body);
        $resp = static::http($path, $body, array(
            'X-TCS-Timestamp: ' . $timestamp,
            'X-TCS-Nonce: ' . $nonce,
            'X-TCS-Signature: ' . $sig,
        ), $err);
        if ($resp === false) {
            return false;
        }
        if (empty($resp['access_token']) || empty($resp['expires_at'])) {
            $err = '网关返回缺少令牌字段';
            return false;
        }
        option::set('official_access_token', json_encode(array(
            'token' => (string) $resp['access_token'],
            'exp'   => (int) $resp['expires_at'],
            'scope' => (string) (isset($resp['scope']) ? $resp['scope'] : $scope),
        )));
        return (string) $resp['access_token'];
    }

    /**
     * 发起一次经过签名的官方网关调用
     * @param string $path   网关路径（/v1/...）
     * @param array  $payload 业务负载（json 编码后签名）
     * @param string $scope  所需权限
     * @param string|null $err 失败原因
     * @return array|false 成功返回网关 JSON 响应
     */
    public static function request($path, array $payload, $scope, &$err = null)
    {
        $token = self::accessToken($scope, $err);
        if ($token === false) {
            return false;
        }
        $body = json_encode($payload, JSON_UNESCAPED_UNICODE);
        if ($body === false) {
            $err = '请求负载编码失败';
            return false;
        }
        $nonce = bin2hex(random_bytes(16));
        $timestamp = (string) time();
        $sig = self::sign(self::instanceSecret(), $timestamp, $nonce, $path, $body);
        $resp = static::http($path, $body, array(
            'Authorization: Bearer ' . $token,
            'X-TCS-Timestamp: ' . $timestamp,
            'X-TCS-Nonce: ' . $nonce,
            'X-TCS-Signature: ' . $sig,
        ), $err);
        if ($resp === false) {
            return false;
        }
        if (isset($resp['error'])) {
            //令牌过期/被吊销时清缓存，下次调用自动重新获取
            if (isset($resp['code']) && in_array((string) $resp['code'], array('token_invalid', 'token_expired'), true)) {
                option::set('official_access_token', '');
            }
            $err = '网关拒绝：' . (string) $resp['error'];
            return false;
        }
        return $resp;
    }

    /**
     * 底层 HTTPS POST（强校验公网地址，复用 wcurl SSRF 门控）
     * @return array|false
     */
    private static function http($path, $body, array $headers, &$err = null)
    {
        $url = self::gatewayUrl() . $path;
        if ($url === $path || !wcurl::isAllowedPublicUrl($url) || stripos($url, 'https://') !== 0) {
            $err = '网关地址不可用（仅允许公网 https 地址）';
            return false;
        }
        try {
            $ch = new wcurl($url, array_merge(array(
                'Content-Type: application/json',
                'X-TCS-Protocol: ' . TCS_OFFICIAL_PROTOCOL_VERSION,
            ), $headers));
            $ch->setTimeOut(60000); //毫秒
            $raw = $ch->post($body);
        } catch (Throwable $e) {
            $err = '网关请求失败：' . mb_substr($e->getMessage(), 0, 120);
            return false;
        }
        $resp = json_decode((string) $raw, true);
        if (!is_array($resp)) {
            $err = '网关返回无法解析：' . mb_substr((string) $raw, 0, 120);
            return false;
        }
        return $resp;
    }

    // ===================== License（非对称验签） =====================

    /**
     * 解析并验证 License（payload 为规范化 JSON 的 base64，signature 为其 RSA-SHA256 签名）。
     * 验签公钥来自 lib/official-config.php；私钥只在官方授权服务器，绝不进本仓库。
     * @param string $blob option official_license 的 JSON 串
     * @return array|false 验证通过返回 License 字段数组
     */
    public static function licenseParse($blob)
    {
        if (trim((string) TCS_OFFICIAL_LICENSE_PUBKEY) === '') {
            return false; //未部署验签公钥：License 功能关闭
        }
        $data = json_decode((string) $blob, true);
        if (!is_array($data) || empty($data['payload']) || empty($data['signature'])) {
            return false;
        }
        $payload = self::b64urlDecode((string) $data['payload']);
        $sig = self::b64urlDecode((string) $data['signature']);
        if ($payload === false || $sig === false) {
            return false;
        }
        $key = openssl_pkey_get_public(TCS_OFFICIAL_LICENSE_PUBKEY);
        if ($key === false) {
            return false;
        }
        if (openssl_verify($payload, $sig, $key, OPENSSL_ALGO_SHA256) !== 1) {
            return false;
        }
        $license = json_decode($payload, true);
        if (!is_array($license) || empty($license['license_id']) || !isset($license['expires_at'])) {
            return false;
        }
        return $license;
    }

    /**
     * 当前实例的 License 是否有效（未过期且未被禁用）
     * @return bool
     */
    public static function licenseValid()
    {
        $license = self::licenseParse((string) option::get('official_license'));
        if ($license === false) {
            return false;
        }
        if ((int) $license['expires_at'] < time()) {
            return false;
        }
        if (isset($license['status']) && $license['status'] !== 'active') {
            return false;
        }
        return true;
    }

    /**
     * License 是否包含某特性（features 为白名单数组；admin 特性走独立判断）
     * @param string $feature
     * @return bool
     */
    public static function licenseFeature($feature)
    {
        $license = self::licenseParse((string) option::get('official_license'));
        if ($license === false || !isset($license['features']) || !is_array($license['features'])) {
            return false;
        }
        return in_array((string) $feature, array_map('strval', $license['features']), true);
    }

    // ===================== 内部工具 =====================

    /** RFC 4648 base64url 解码（兼容标准 base64 输入） */
    public static function b64urlDecode($data)
    {
        $raw = base64_decode(strtr((string) $data, '-_', '+/'), true);
        return $raw === false ? false : $raw;
    }

    /** base64url 编码（无填充） */
    public static function b64urlEncode($raw)
    {
        return rtrim(strtr(base64_encode((string) $raw), '+/', '-_'), '=');
    }

    /** scope 包含判断：admin:* 覆盖一切；其余仅「域:*」形式为通配 */
    public static function scopeCovers($granted, $needed)
    {
        $granted = (string) $granted;
        $needed = (string) $needed;
        if ($granted === $needed || $granted === 'admin:*') {
            return true;
        }
        if (substr($granted, -2) !== ':*') {
            return false;
        }
        return strncmp($needed, $granted, strlen($granted) - 1) === 0;
    }
}
