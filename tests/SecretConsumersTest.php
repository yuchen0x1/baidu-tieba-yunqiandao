<?php

use PHPUnit\Framework\TestCase;

final class SecretConsumersTest extends TestCase
{
    public function testEncryptedGlobalProxyPasswordIsDecryptedBeforeUse(): void
    {
        require_once SYSTEM_ROOT . '/lib/class.option.php';
        require_once SYSTEM_ROOT . '/lib/class.SecretBox.php';
        $previous = $GLOBALS['i'] ?? null;
        $GLOBALS['i'] = array('opt' => array('crypto_key' => 'unit-key', 'proxy_type' => '1', 'proxy_host' => 'proxy.example.test:8080', 'proxy_user' => 'proxy-user'));
        SecretBox::flushCache();
        try {
            $GLOBALS['i']['opt']['proxy_pass'] = P::encrypt('proxy-pass');
            $this->assertStringStartsWith('enc:v1:', option::get('proxy_pass'));
            $this->assertSame('proxy-pass', misc::getProxy()['pass']);
            $GLOBALS['i']['opt']['proxy_pass'] = 'legacy-pass';
            SecretBox::flushCache();
            $this->assertSame('legacy-pass', misc::getProxy()['pass']);
        } finally { SecretBox::flushCache(); $GLOBALS['i'] = $previous; }
    }
}
