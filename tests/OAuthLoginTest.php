<?php

use PHPUnit\Framework\TestCase;

/**
 * 第三方登录（V6.1 Phase 4）冒烟测试
 * 无 DB 环境，验证接入点与安全关键代码存在。
 */
final class OAuthLoginTest extends TestCase
{
    public function testOAuthClassFileExists(): void
    {
        $this->assertFileExists(SYSTEM_ROOT . '/lib/class.OAuth.php');
        $src = file_get_contents(SYSTEM_ROOT . '/lib/class.OAuth.php');
        $this->assertNotFalse($src);
        // 防重放 state 与回调地址必须存在
        $this->assertStringContainsString('oauth_state', $src);
        $this->assertStringContainsString('hash_equals', $src);
        $this->assertStringContainsString('login/oauth/authorize', $src);
        $this->assertStringContainsString('users_oauth', $src);
    }

    public function testOAuthRoutesRegistered(): void
    {
        $globals = file_get_contents(SYSTEM_ROOT . '/lib/globals.php');
        $this->assertNotFalse($globals);
        $this->assertStringContainsString("SYSTEM_PAGE == 'oauth:start'", $globals);
        $this->assertStringContainsString("SYSTEM_PAGE == 'oauth:callback'", $globals);
        // 回调豁免常规 CSRF（state 即该流程的防 CSRF 手段）
        $this->assertStringContainsString("SYSTEM_PAGE != 'oauth:callback'", $globals);
    }

    public function testOAuthUnbindRegistered(): void
    {
        $setting = file_get_contents(SYSTEM_ROOT . '/setting.php');
        $this->assertNotFalse($setting);
        $this->assertStringContainsString("case 'oauth:unbind'", $setting);
        $this->assertStringContainsString('oauth_github_client_secret', $setting);
    }

    public function testOAuthUiRegistered(): void
    {
        $login = file_get_contents(SYSTEM_ROOT . '/templates/login.php');
        $this->assertStringContainsString('oauth:start', $login);
        $account = file_get_contents(SYSTEM_ROOT . '/templates/account.php');
        $this->assertStringContainsString('oauth:unbind', $account);
        $adminSet = file_get_contents(SYSTEM_ROOT . '/templates/admin-set.php');
        $this->assertStringContainsString('oauth_auto_register', $adminSet);
    }
}
