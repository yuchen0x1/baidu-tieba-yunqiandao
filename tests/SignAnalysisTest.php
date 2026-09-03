<?php

use PHPUnit\Framework\TestCase;

/**
 * 账号分析（V6.1 Phase 3）冒烟测试
 * 验证 SignManager 分析查询方法与前端接入点存在。
 * 涉及数据库的方法不做真实查询，仅验证可调用性（无 DB 测试环境）。
 */
final class SignAnalysisTest extends TestCase
{
    public function testAnalysisMethodsAreCallable(): void
    {
        $methods = array(
            'overview', 'trend', 'tiebaHealth', 'failureBreakdown',
            'hourDistribution', 'accountCompare', 'accountHealth',
            'streak', 'calendar',
        );
        foreach ($methods as $method) {
            $this->assertTrue(
                is_callable(array('SignManager', $method)),
                "SignManager::{$method} 应存在且可调用"
            );
        }
    }

    public function testAnalysisTemplateExists(): void
    {
        $this->assertFileExists(SYSTEM_ROOT . '/templates/analysis.php');
    }

    public function testAnalysisRouteRegistered(): void
    {
        $control = file_get_contents(SYSTEM_ROOT . '/templates/control.php');
        $this->assertNotFalse($control);
        $this->assertStringContainsString("case 'analysis'", $control);
    }

    public function testAnalysisNavRegistered(): void
    {
        $navi = file_get_contents(SYSTEM_ROOT . '/templates/navi.php');
        $this->assertNotFalse($navi);
        $this->assertStringContainsString("mod=analysis", $navi);
    }
}
