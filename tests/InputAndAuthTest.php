<?php

use PHPUnit\Framework\TestCase;

final class InputAndAuthTest extends TestCase
{
    public function testScalarRequestValuesAreReadWithoutArrayCoercion(): void
    {
        $source = array('text' => ' <b>admin</b> ', 'array' => array('admin'));

        $this->assertSame('admin', request_text_value($source, 'text'));
        $this->assertSame('', request_scalar_value($source, 'array'));
        $this->assertSame('fallback', request_scalar_value($source, 'missing', 'fallback'));
    }

    public function testLoginIdentityNormalizationMatchesAuditAndLockKeys(): void
    {
        $this->assertSame('Admin@example.com', LoginGuard::normalizeUsername(' Admin+()@example.com '));
        $this->assertSame('', LoginGuard::normalizeUsername(array('admin')));
        $this->assertSame(100, strlen(LoginGuard::normalizeUsername(str_repeat('a', 120))));
    }

    public function testLoginAuditPersistsTheNormalizedIdentity(): void
    {
        global $m;
        $previous = $m ?? null;
        $database = new class {
            public array $statements = array();

            public function query($sql)
            {
                return true;
            }

            public function pquery($sql, $params = array())
            {
                $this->statements[] = $params;
                return true;
            }
        };
        $m = $database;
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
        $_SERVER['HTTP_USER_AGENT'] = 'PHPUnit';

        try {
            LoginGuard::auditLogin(false, ' Admin+()@example.com ');
            $this->assertCount(1, $database->statements);
            $this->assertSame('Admin@example.com', $database->statements[0][0]);
            $this->assertSame('127.0.0.1', $database->statements[0][1]);
            $this->assertSame('PHPUnit', $database->statements[0][2]);
            $this->assertSame(0, $database->statements[0][3]);
        } finally {
            $m = $previous;
            unset($_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_USER_AGENT']);
        }
    }

    public function testBcryptCostDoesNotTriggerRepeatedRehash(): void
    {
        $hash = password_hash('test-password', PASSWORD_BCRYPT, array('cost' => P::PASSWORD_COST));

        $this->assertTrue(password_verify('test-password', $hash));
        $this->assertFalse(P::needsRehash($hash));
        $this->assertTrue(P::needsRehash(password_hash('test-password', PASSWORD_BCRYPT, array('cost' => 10))));
    }

    public function testDatabaseEndpointSupportsPortsAndIpv6(): void
    {
        $this->assertSame(array('host' => '127.0.0.1', 'port' => 3307), parse_database_endpoint('127.0.0.1:3307'));
        $this->assertSame(array('host' => '::1', 'port' => 3307), parse_database_endpoint('[::1]:3307'));
        $this->assertSame(array('host' => 'db', 'port' => null), parse_database_endpoint('db'));
    }

    public function testProxyParsingSupportsIpv4DomainAndIpv6(): void
    {
        $this->assertSame(
            array('type' => 'http', 'host' => '127.0.0.1', 'port' => '7890', 'user' => '', 'pass' => ''),
            misc::parseProxyUrl('http://127.0.0.1:7890')
        );
        $this->assertSame(
            array('type' => 'socks5', 'host' => '2001:db8::1', 'port' => '1080', 'user' => 'user', 'pass' => 'pass'),
            misc::parseProxyUrl('socks5://user:pass@[2001:db8::1]:1080')
        );
        $this->assertFalse(misc::parseProxyUrl('http://host:70000'));
        $this->assertFalse(misc::parseProxyUrl('http://host'));
    }

    public function testPluginIdsRejectTraversalAndInvalidCharacters(): void
    {
        global $i;
        $previous = isset($i) ? $i : null;
        $i = array('plugins' => array('actived' => array()));
        require_once SYSTEM_ROOT . '/lib/plugins.php';

        try {
            $this->assertTrue(valid_plugin_id('ver4_like'));
            $this->assertTrue(valid_plugin_id('plugin-name_2'));
            $this->assertFalse(valid_plugin_id('../outside'));
            $this->assertFalse(valid_plugin_id('plugin/name'));
            $this->assertFalse(valid_plugin_id('plugin name'));
        } finally {
            $i = $previous;
        }
    }

    public function testLiveHealthDoesNotRequireInstallationOrDatabase(): void
    {
        $health = project_health_check(dirname(__DIR__) . '/does-not-exist', 'live');

        $this->assertTrue($health['ok']);
        $this->assertSame('live', $health['mode']);
        $this->assertTrue($health['checks']['php']);
    }
}
