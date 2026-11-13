<?php

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

require_once SYSTEM_ROOT . '/lib/class.notify.php';
require_once SYSTEM_ROOT . '/lib/class.wcurl.php';
require_once SYSTEM_ROOT . '/lib/class.option.php';

/** 仅测试中替换目标地址，生产的公开地址校验另行断言。 */
class LocalNotificationClient extends Notify
{
    public static string $target;

    protected static function createClient($url = '')
    {
        $client = new wcurl(self::$target);
        $client->set(CURLOPT_FOLLOWLOCATION, false);
        $client->set(CURLOPT_TIMEOUT, 2);
        return $client;
    }

    protected static function createPublicClient($url)
    {
        return static::createClient();
    }
}

final class NotificationDeliveryTest extends TestCase
{
    private static $server;
    private static string $base;

    public static function setUpBeforeClass(): void
    {
        $socket = stream_socket_server('tcp://127.0.0.1:0', $errno, $error);
        if (!$socket) { throw new RuntimeException($error); }
        $address = stream_socket_get_name($socket, false);
        fclose($socket);
        self::$base = 'http://' . $address;
        $dir = SYSTEM_ROOT . '/.evolution/runtime/notification-tests';
        if (!is_dir($dir)) { mkdir($dir, 0777, true); }
        self::$server = proc_open(array(PHP_BINARY, '-S', $address, SYSTEM_ROOT . '/tests/fixtures/notify-router.php'),
            array(0 => array('pipe', 'r'), 1 => array('file', $dir . '/http.log', 'a'), 2 => array('file', $dir . '/http.log', 'a')), $pipes);
        if (!is_resource(self::$server)) { throw new RuntimeException('Cannot start local HTTP fixture'); }
        fclose($pipes[0]);
        for ($n = 0; $n < 50; $n++) {
            $probe = @stream_socket_client('tcp://' . $address, $errno, $error, 0.1);
            if ($probe) { fclose($probe); return; }
            usleep(100000);
        }
        throw new RuntimeException('Local HTTP fixture did not become ready');
    }

    public static function tearDownAfterClass(): void
    {
        if (is_resource(self::$server)) { proc_terminate(self::$server); proc_close(self::$server); }
    }

    public static function providers(): array
    {
        return array(
            'webhook' => array('sendWebhook', array('https://example.test'), ''),
            'serverchan' => array('sendServerChan', array('test-key'), '{"code":0}'),
            'telegram' => array('sendTelegram', array('test-token', 'test-chat'), '{"ok":true}'),
            'bark' => array('sendBark', array('https://example.test'), '{"code":200}'),
            'pushplus' => array('sendPushPlus', array('test-token'), '{"code":200}'),
            'dingtalk' => array('sendDingTalk', array('https://example.test', ''), '{"errcode":0}'),
            'wecom' => array('sendWeCom', array('https://example.test'), '{"errcode":0}'),
            'feishu' => array('sendFeiShu', array('https://example.test'), '{"code":0}'),
            'wxpusher' => array('sendWxPusher', array('test-token', 'test-user'), '{"code":1000}'),
            'ntfy' => array('sendNtfy', array('https://example.test', 'test-topic'), '{"event":"message"}'),
        );
    }

    #[DataProvider('providers')]
    public function testHttpFailureCannotMasqueradeAsProviderSuccess(string $method, array $args, string $body): void
    {
        foreach (array(200, 302, 401, 429, 503) as $code) {
            LocalNotificationClient::$target = self::$base . '/?' . http_build_query(array('status' => $code, 'body' => $body));
            $r = LocalNotificationClient::$method(...array_merge($args, array('标题', '内容')));
            $this->assertSame($code === 200, Notify::wasDelivered($r), $method . ' HTTP ' . $code);
            if ($code !== 200) { $this->assertStringContainsString((string) $code, $r['detail']); }
        }
        LocalNotificationClient::$target = self::$base . '/?' . http_build_query(array('body' => '{"error":"private-token-and-response"}'));
        $r = LocalNotificationClient::$method(...array_merge($args, array('标题', '内容')));
        $this->assertSame($method === 'sendWebhook', Notify::wasDelivered($r));
        $this->assertStringNotContainsString('private-token-and-response', $r['detail']);
        foreach (array('not-json', '{"code":"invalid","StatusCode":false,"errcode":false,"ok":"false","event":true}') as $malformed) {
            LocalNotificationClient::$target = self::$base . '/?' . http_build_query(array('body' => $malformed));
            $r = LocalNotificationClient::$method(...array_merge($args, array('标题', '内容')));
            $this->assertSame($method === 'sendWebhook', Notify::wasDelivered($r), 'Malformed provider response');
        }
    }

    public function testWebhookAcceptsEmpty204AndDoesNotExposeResponse(): void
    {
        LocalNotificationClient::$target = self::$base . '/?status=204';
        $this->assertTrue(Notify::wasDelivered(LocalNotificationClient::sendWebhook('https://example.test', 'x', 'y')));
    }

    public function testConnectionFailureIsNotDeliveryAndDoesNotExposeTarget(): void
    {
        LocalNotificationClient::$target = 'http://127.0.0.1:1/private-channel-key';
        $r = LocalNotificationClient::sendWebhook('https://example.test', 'x', 'y');
        $this->assertFalse(Notify::wasDelivered($r));
        $this->assertStringNotContainsString('private-channel-key', $r['detail']);
    }

    public function testSkipsRemainCompatibleButAreNotDelivery(): void
    {
        $old = $GLOBALS['i'] ?? null;
        try {
            foreach (array(array(), array('notify_enable' => '1', 'notify_channel' => 'none'), array('notify_enable' => '1', 'notify_on_fail' => '0')) as $options) {
                $GLOBALS['i'] = array('opt' => $options);
                $r = Notify::send('x', 'y', 'sign_fail');
                $this->assertTrue($r['ok']);
                $this->assertFalse(Notify::wasDelivered($r));
            }
            $this->assertTrue(Notify::wasDelivered(array('ok' => true, 'detail' => 'legacy plugin')));
        } finally { $GLOBALS['i'] = $old; }
    }

    public function testPublicNotificationTargetsStillRejectLoopback(): void
    {
        foreach (array('sendWebhook', 'sendBark', 'sendWeCom', 'sendFeiShu') as $method) {
            $r = Notify::$method('http://127.0.0.1/secret', 'x', 'y');
            $this->assertFalse(Notify::wasDelivered($r));
            $this->assertStringContainsString('SSRF', $r['detail']);
        }
        $this->assertFalse(Notify::wasDelivered(Notify::sendDingTalk('http://127.0.0.1/secret', '', 'x', 'y')));
    }
}
