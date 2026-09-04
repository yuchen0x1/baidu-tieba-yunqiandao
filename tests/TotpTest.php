<?php

namespace Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TOTP;

require_once dirname(__DIR__) . '/lib/class.totp.php';

/**
 * TOTP 双因素认证实现测试（V5.13 B5）
 * 使用 RFC 6238 附录 B 官方测试向量（secret = "12345678901234567890"）。
 */
class TotpTest extends TestCase
{
    /** RFC 6238 secret：ASCII "12345678901234567890" */
    private const RFC_SECRET = 'GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ';

    public function testBase32RoundTrip()
    {
        $raw = "Hello, 贴吧云签到!";
        $enc = TOTP::base32Encode($raw);
        $this->assertSame($raw, TOTP::base32Decode($enc));
    }

    public function testBase32RfcVector()
    {
        $this->assertSame('GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ', TOTP::base32Encode('12345678901234567890'));
    }

    /**
     * RFC 6238 官方测试向量。
     * 表中 Time(sec) 需换算为 counter：T = floor(sec / 30)。
     */
    public static function rfcVectorProvider()
    {
        return array(
            array(1, '287082'),        // sec=59        -> 94287082
            array(37037036, '081804'), // sec=1111111109 -> 07081804
            array(37037037, '050471'), // sec=1111111111 -> 14050471
            array(41152263, '005924'), // sec=1234567890 -> 89005924 (6位=89005924%1000000)
            array(66666666, '279037'), // sec=2000000000 -> 69279037
            array(666666666, '353130'), // sec=20000000000 -> 65353130
        );
    }

    /**
     */
    #[DataProvider('rfcVectorProvider')]
    public function testRfc6238Vectors($timeSlice, $expected)
    {
        $this->assertSame($expected, TOTP::code(self::RFC_SECRET, $timeSlice));
    }

    public function testVerifyAcceptsCurrentCode()
    {
        $secret = TOTP::generateSecret();
        $slice = intdiv(time(), TOTP::PERIOD);
        $this->assertTrue(TOTP::verify($secret, TOTP::code($secret, $slice)));
    }

    public function testVerifyRejectsWrongCode()
    {
        $secret = TOTP::generateSecret();
        $this->assertFalse(TOTP::verify($secret, '000000'));
    }

    public function testVerifyRejectsMalformedCode()
    {
        $secret = TOTP::generateSecret();
        $this->assertFalse(TOTP::verify($secret, 'abc123'));
        $this->assertFalse(TOTP::verify($secret, ''));
    }

    public function testGenerateSecretIsBase32AndUnique()
    {
        $a = TOTP::generateSecret();
        $b = TOTP::generateSecret();
        $this->assertSame(strlen($a), strlen($b));
        $this->assertMatchesRegularExpression('/^[A-Z2-7]+$/', $a);
        $this->assertNotSame($a, $b);
        // 20 字节 -> 32 个 base32 字符（RFC 4648 无填充）
        $this->assertSame(32, strlen($a));
    }

    public function testOtpauthUriShape()
    {
        $uri = TOTP::otpauthUri('SECRET123', 'admin@example.com', 'TiebaCloudSign');
        $this->assertStringStartsWith('otpauth://totp/', $uri);
        $this->assertStringContainsString('secret=SECRET123', $uri);
        $this->assertStringContainsString('issuer=TiebaCloudSign', $uri);
        $this->assertStringContainsString('period=30', $uri);
        $this->assertStringContainsString('digits=6', $uri);
    }

    public function testWindowRejectsFutureCodeOutsideWindow()
    {
        $secret = TOTP::generateSecret();
        $slice = intdiv(time(), TOTP::PERIOD);
        $future = TOTP::code($secret, $slice + 5);
        $this->assertFalse(TOTP::verify($secret, $future, 1));
        $this->assertTrue(TOTP::verify($secret, $future, 5));
    }
}
