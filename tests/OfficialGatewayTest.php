<?php

use PHPUnit\Framework\TestCase;

/**
 * 官方服务边界单元测试：
 * 签名确定性（与参考网关同一公式）、scope 覆盖、Access Token 解码校验、
 * License 非对称验签、CSRF 密钥按需供应（硬编码回退盐回归）。
 * 不触网、不依赖真实数据库（option 走内存数组，写入用假 DB 记录器）。
 */
final class OfficialGatewayTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        //Windows 裸 zip 版 PHP 常缺 OPENSSL_CONF，导致密钥生成失败；指到发行包内置的 openssl.cnf
        if (getenv('OPENSSL_CONF') === false) {
            $candidates = array(
                dirname(PHP_BINARY) . '/extras/ssl/openssl.cnf',
            );
            foreach ($candidates as $candidate) {
                if (is_file($candidate)) {
                    putenv('OPENSSL_CONF=' . $candidate);
                    break;
                }
            }
        }
        //运行时生成 License 验签密钥对：公钥预定义后 official-config.php 的守卫不会覆盖
        $keypair = self::generateKeyPair();
        if ($keypair !== null) {
            self::$privatePem = $keypair['private'];
            if (!defined('TCS_OFFICIAL_LICENSE_PUBKEY')) {
                define('TCS_OFFICIAL_LICENSE_PUBKEY', $keypair['public']);
            }
        }

        require_once dirname(__DIR__) . '/lib/class.option.php';
        require_once dirname(__DIR__) . '/lib/class.P.php';
        require_once dirname(__DIR__) . '/lib/class.wcurl.php';
        require_once dirname(__DIR__) . '/lib/class.official.php';
        require_once dirname(__DIR__) . '/lib/security.php';
        require_once dirname(__DIR__) . '/lib/sfc.functions.php';

        if (!defined('DB_NAME')) {
            define('DB_NAME', 'test_db');
        }
        //option 读走内存数组；写操作由假 DB 记录，不落真库
        $GLOBALS['i'] = array('opt' => array());
        $GLOBALS['m'] = new class {
            public array $queries = array();
            public function query($sql)
            {
                $this->queries[] = $sql;
                return true;
            }
            public function pquery($sql, $params = array())
            {
                $this->queries[] = $sql;
                return true;
            }
        };
    }

    private static string $privatePem = '';

    /**
     * 运行时生成 RSA-2048 密钥对（测试专用，进程内使用后即弃，不落仓库）。
     * 主路径 openssl_pkey_new；部分 Windows NTS 构建在 OpenSSL 3 下有缺陷，回退 openssl CLI。
     * @return array{private:string, public:string}|null 不可用时返回 null（License 用例标记 SKIP）
     */
    private static function generateKeyPair(): ?array
    {
        if (getenv('OPENSSL_CONF') === false) {
            $candidate = dirname(PHP_BINARY) . '/extras/ssl/openssl.cnf';
            if (is_file($candidate)) {
                putenv('OPENSSL_CONF=' . $candidate);
            }
        }
        $key = @openssl_pkey_new(array('private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA));
        if ($key !== false && openssl_pkey_export($key, $privatePem)) {
            return array('private' => $privatePem, 'public' => openssl_pkey_get_details($key)['key']);
        }
        $pem = tempnam(sys_get_temp_dir(), 'tcs-lp-');
        $pub = $pem . '.pub';
        //genrsa 的输出走 stderr，不能以 exec 返回值判断成败，只看退出码与产物
        @exec(escapeshellcmd('openssl') . ' genrsa -out ' . escapeshellarg($pem) . ' 2048 2>NUL', $out, $code);
        $generated = $code === 0 && is_file($pem);
        if ($generated) {
            @exec(escapeshellcmd('openssl') . ' rsa -in ' . escapeshellarg($pem) . ' -pubout -out ' . escapeshellarg($pub) . ' 2>NUL', $out, $code);
            $generated = $code === 0 && is_file($pub);
        }
        if (!$generated) {
            return null;
        }
        $private = (string) file_get_contents($pem);
        $public = (string) file_get_contents($pub);
        @unlink($pem);
        @unlink($pub);
        if (strpos($private, 'PRIVATE KEY') === false || strpos($public, 'PUBLIC KEY') === false) {
            return null;
        }
        return array('private' => $private, 'public' => $public);
    }

    private function seedInstance(array $opt = array()): void
    {
        $GLOBALS['i']['opt'] = array_merge(array(
            'official_gateway_url'    => 'https://gw.example.com',
            'official_instance_id'    => 'inst-unit',
            'official_instance_secret' => 'unit-test-instance-secret',
            'official_access_token'   => '',
            'official_license'        => '',
        ), $opt);
    }

    // ---------- 请求签名 ----------

    public function testSignMatchesProtocolVector(): void
    {
        //固定向量，防止签名公式被无意改动（与 official-service 参考网关必须逐字节一致）
        $expected = 'ce25fe05c5136554da64edb5bafd0d4be66b9a3505855aee499ffe49c1c1f8a9';
        $this->assertSame(
            $expected,
            official::sign('unit-secret', '1700000000', 'aabbccddeeff00112233', '/v1/ai/chat', '{"a":1}')
        );
    }

    public function testSignChangesWithAnyComponent(): void
    {
        $base = official::sign('s', '1', 'nonce', '/v1/ai/chat', '{}');
        $this->assertNotSame($base, official::sign('s2', '1', 'nonce', '/v1/ai/chat', '{}'));
        $this->assertNotSame($base, official::sign('s', '2', 'nonce', '/v1/ai/chat', '{}'));
        $this->assertNotSame($base, official::sign('s', '1', 'nonce2', '/v1/ai/chat', '{}'));
        $this->assertNotSame($base, official::sign('s', '1', 'nonce', '/v1/ai/chat', '{ }'));
    }

    // ---------- scope ----------

    public function testScopeCoversMatrix(): void
    {
        $this->assertTrue(official::scopeCovers('ai:generate', 'ai:generate'));
        $this->assertTrue(official::scopeCovers('ai:*', 'ai:generate'));
        $this->assertTrue(official::scopeCovers('admin:*', 'billing:read'));
        $this->assertFalse(official::scopeCovers('ai:generate', 'ai:*'));
        $this->assertFalse(official::scopeCovers('chat:read', 'chat:write'));
        $this->assertFalse(official::scopeCovers('admin:*x', 'admin:y')); //非通配后缀不得前缀匹配
    }

    // ---------- Access Token（结构、签名、时效） ----------

    private function mintToken(array $overrides = array(), ?string $secret = null): string
    {
        $payload = array_merge(array(
            'sub'   => 'inst-unit',
            'iat'   => time(),
            'exp'   => time() + 600,
            'scope' => 'ai:generate',
            'jti'   => 'unit-jti',
        ), $overrides);
        $b64payload = official::b64urlEncode(json_encode($payload));
        $key = hash('sha256', 'tcs-official-token-v1:' . ($secret ?? 'unit-test-instance-secret'));
        $sig = hash_hmac('sha256', 'TCS1.' . $b64payload, $key, true);
        return 'TCS1.' . $b64payload . '.' . official::b64urlEncode($sig);
    }

    public function testDecodeTokenAcceptsValid(): void
    {
        $this->seedInstance();
        $data = official::decodeToken($this->mintToken());
        $this->assertIsArray($data);
        $this->assertSame('inst-unit', $data['sub']);
        $this->assertSame('ai:generate', $data['scope']);
    }

    public function testDecodeTokenRejectsExpiredAndTampered(): void
    {
        $this->seedInstance();
        $this->assertFalse(official::decodeToken($this->mintToken(array('exp' => time() - 10))));
        $tampered = $this->mintToken();
        $tampered[strlen($tampered) - 2] = $tampered[strlen($tampered) - 2] === 'A' ? 'B' : 'A';
        $this->assertFalse(official::decodeToken($tampered));
        //其他实例签发的令牌不得通过
        $this->assertFalse(official::decodeToken($this->mintToken(array(), 'another-secret')));
        $this->assertFalse(official::decodeToken('JWT.not.a.real.token'));
    }

    public function testAccessTokenUsesFreshCacheWithoutNetwork(): void
    {
        $this->seedInstance(array(
            'official_access_token' => json_encode(array(
                'token' => 'cached-token',
                'exp'   => time() + 600,
                'scope' => 'ai:generate',
            )),
        ));
        $err = null;
        $this->assertSame('cached-token', official::accessToken('ai:generate', $err));
    }

    public function testAccessTokenRefusesStaleScopeCache(): void
    {
        $this->seedInstance(array(
            'official_access_token' => json_encode(array(
                'token' => 'cached-token',
                'exp'   => time() + 600,
                'scope' => 'chat:read',
            )),
        ));
        $err = null;
        //scope 不覆盖时不得复用缓存（缓存未命中路径需要网络，此处只断言不返回旧值）
        $result = official::accessToken('ai:generate', $err);
        $this->assertTrue($result === false || $result !== 'cached-token');
    }

    public function testDisabledWhenGatewayMissing(): void
    {
        $this->seedInstance(array('official_gateway_url' => '', 'official_instance_secret' => ''));
        $this->assertFalse(official::enabled());
        $err = null;
        $this->assertFalse(official::accessToken('ai:generate', $err));
        $this->assertSame('官方服务未接入', $err);
    }

    public function testGatewayUrlRequiresHttps(): void
    {
        $this->seedInstance(array('official_gateway_url' => 'http://gw.example.com'));
        $this->assertSame('', official::gatewayUrl());
    }

    // ---------- License 非对称验签 ----------

    private function licenseBlob(array $overrides = array(), ?OpenSSLAsymmetricKey $signKey = null): string
    {
        $license = array_merge(array(
            'license_id' => 'L-UNIT',
            'user_id'    => 'inst-unit',
            'plan'       => 'pro',
            'features'   => array('ai:shared'),
            'issued_at'  => time(),
            'expires_at' => time() + 86400,
            'status'     => 'active',
        ), $overrides);
        if (self::$privatePem === '') {
            self::markTestSkipped('本机 openssl 无法生成密钥对（CI/Linux 必跑）');
        }
        $payload = json_encode($license, JSON_UNESCAPED_UNICODE);
        $key = openssl_pkey_get_private(self::$privatePem);
        openssl_sign($payload, $sig, $key, OPENSSL_ALGO_SHA256);
        return json_encode(array(
            'payload'   => official::b64urlEncode($payload),
            'signature' => official::b64urlEncode($sig),
        ), JSON_UNESCAPED_UNICODE);
    }

    public function testLicenseParseAcceptsSigned(): void
    {
        $license = official::licenseParse($this->licenseBlob());
        $this->assertIsArray($license);
        $this->assertSame('L-UNIT', $license['license_id']);
        $this->assertSame('pro', $license['plan']);
    }

    public function testLicenseParseRejectsTamperAndGarbage(): void
    {
        $blob = json_decode($this->licenseBlob(), true);
        $payload = json_decode(official::b64urlDecode($blob['payload']), true);
        $payload['plan'] = 'enterprise'; //篡改授权等级
        $tampered = json_encode(array(
            'payload'   => official::b64urlEncode(json_encode($payload, JSON_UNESCAPED_UNICODE)),
            'signature' => $blob['signature'],
        ), JSON_UNESCAPED_UNICODE);
        $this->assertFalse(official::licenseParse($tampered));
        $this->assertFalse(official::licenseParse('not-json'));
        $this->assertFalse(official::licenseParse(json_encode(array('payload' => 'x'))));
    }

    public function testLicenseValidityAndFeatureGates(): void
    {
        $this->seedInstance(array('official_license' => $this->licenseBlob()));
        $this->assertTrue(official::licenseValid());
        $this->assertTrue(official::licenseFeature('ai:shared'));
        $this->assertFalse(official::licenseFeature('billing:read'));

        $this->seedInstance(array('official_license' => $this->licenseBlob(array('expires_at' => time() - 100))));
        $this->assertFalse(official::licenseValid());

        $this->seedInstance(array('official_license' => $this->licenseBlob(array('status' => 'suspended'))));
        $this->assertFalse(official::licenseValid());

        $this->seedInstance(array('official_license' => ''));
        $this->assertFalse(official::licenseValid());
        $this->assertFalse(official::licenseFeature('ai:shared'));
    }

    // ---------- CSRF 密钥按需供应（硬编码回退盐回归） ----------

    public function testCsrfTokenUsesSeededCryptoKey(): void
    {
        $GLOBALS['i']['opt']['crypto_key'] = 'seeded-key';
        $_COOKIE['tc_csrf'] = 'nonce-abc';
        $token = csrf_token();
        $this->assertSame($token, csrf_token());
        $this->assertSame(
            hash_hmac('sha256', 'csrf:visitor:nonce-abc', 'seeded-key'),
            $token
        );
    }

    public function testCsrfTokenProvisionsSecretInsteadOfHardcodedFallback(): void
    {
        unset($GLOBALS['i']['opt']['crypto_key']);
        $_COOKIE['tc_csrf'] = 'nonce-xyz';
        /** @var object{queries:string[]} $db */
        $db = $GLOBALS['m'];
        $db->queries = array();
        $token = csrf_token();
        //必须已触发 crypto_key 供应（INSERT INTO options）
        $this->assertNotEmpty(
            array_filter($db->queries, fn($q) => stripos($q, 'INSERT IGNORE INTO') !== false && stripos($q, "options") !== false),
            '空 crypto_key 时应自动生成密钥而不是落入硬编码回退盐'
        );
        //旧回退盐算出的令牌不得再有效
        $this->assertNotSame(hash_hmac('sha256', 'csrf:visitor:nonce-xyz', 'tieba-cloud-sign-csrf-fallback'), $token);
        //同请求内二次调用（供应完成后）必须稳定
        $this->assertSame($token, csrf_token());
    }
}
