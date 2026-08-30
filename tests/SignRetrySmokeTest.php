<?php

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * 签到核心链路冒烟测试
 * 覆盖复签判定（misc::shouldRetry）与签到入口的可调用性。
 */
final class SignRetrySmokeTest extends TestCase
{
    public function testLegacyClassesLoad(): void
    {
        $this->assertTrue(class_exists('misc'));
        $this->assertTrue(class_exists('SignManager'));
        $this->assertTrue(is_callable(array('misc', 'DoSign')));
        $this->assertTrue(is_callable(array('misc', 'DoSign_retry')));
        $this->assertTrue(is_callable(array('misc', 'shouldRetry')));
    }

    #[DataProvider('retryGateProvider')]
    public function testRetryGate($retryMax, $signAgain, $today, $expected): void
    {
        $this->assertSame($expected, misc::shouldRetry($retryMax, $signAgain, $today));
    }

    public static function retryGateProvider(): array
    {
        $today = date('Y-m-d');
        $yesterday = date('Y-m-d', strtotime('-1 day'));

        return array(
            'unlimited always retries'      => array('0', array('lastdo' => $today, 'num' => 999), $today, true),
            'disabled never retries'        => array('-1', array('lastdo' => $today, 'num' => 1), $today, false),
            'within limit retries'          => array('3', array('lastdo' => $today, 'num' => 2), $today, true),
            'at limit retries'              => array('3', array('lastdo' => $today, 'num' => 3), $today, true),
            'over limit skips'              => array('3', array('lastdo' => $today, 'num' => 4), $today, false),
            'yesterday counter resets'      => array('3', array('lastdo' => $yesterday, 'num' => 0), $today, false),
            'missing state skips'           => array('3', array(), $today, false),
            'null state skips'              => array('3', null, $today, false),
        );
    }
}
