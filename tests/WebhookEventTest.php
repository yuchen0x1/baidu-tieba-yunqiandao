<?php

use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__) . '/lib/class.webhook.php';

/**
 * Webhook 出站事件的回归测试：
 * - 载荷包含稳定事件 ID，供接收方去重；
 * - 签名同时覆盖旧版 body 与 v2 的 timestamp.body，旧接收方不受影响；
 * - 未配置 Secret 时不产生签名头，避免出现空 HMAC 被误判为可信。
 */
final class WebhookEventTest extends TestCase
{
    public function testPayloadContainsStableIdAndTimestamp(): void
    {
        $payload = Webhook::buildPayload('task_fail', array('task' => 'system_sign'), 1700000000);
        $this->assertSame('task_fail', $payload['event']);
        $this->assertSame(1700000000, $payload['ts']);
        $this->assertSame(array('task' => 'system_sign'), $payload['data']);
        $this->assertSame(64, strlen($payload['id']));
        $this->assertSame(
            Webhook::eventId('task_fail', 1700000000, array('task' => 'system_sign')),
            $payload['id'],
            '同一事件同一秒同一载荷必须得到相同 ID，便于接收方去重'
        );
        $this->assertNotSame(
            $payload['id'],
            Webhook::eventId('task_fail', 1700000001, array('task' => 'system_sign')),
            '时间戳变化必须产生不同 ID'
        );
    }

    public function testHeadersCarryLegacyAndReplayResistantSignatures(): void
    {
        $body = json_encode(array('event' => 'test'));
        $headers = Webhook::headers('test', $body, 'top-secret', 1700000000, 'abc123');
        $joined = implode("\n", $headers);

        $this->assertStringContainsString('X-Webhook-Event: test', $joined);
        $this->assertStringContainsString('X-Webhook-Id: abc123', $joined);
        $this->assertStringContainsString('X-Webhook-Timestamp: 1700000000', $joined);
        $this->assertStringContainsString(
            'X-Webhook-Signature: sha256=' . hash_hmac('sha256', $body, 'top-secret'),
            $joined
        );
        $this->assertStringContainsString(
            'X-Webhook-Signature-V2: sha256=' . hash_hmac('sha256', '1700000000.' . $body, 'top-secret'),
            $joined
        );
    }

    public function testHeadersOmitSignaturesWithoutSecret(): void
    {
        $headers = Webhook::headers('test', '{}', '', 1700000000, 'abc123');
        $joined = implode("\n", $headers);
        $this->assertStringNotContainsString('X-Webhook-Signature', $joined);
        $this->assertStringContainsString('X-Webhook-Timestamp: 1700000000', $joined);
    }

    public function testEventLabelsCoverEveryAllowedEvent(): void
    {
        $labels = Webhook::eventLabels();
        foreach (Webhook::events() as $event) {
            $this->assertArrayHasKey($event, $labels, '事件 ' . $event . ' 必须在后台有可读说明');
        }
    }
}
