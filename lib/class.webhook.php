<?php

if (!defined('SYSTEM_ROOT')) {
    die('Insufficient Permissions');
}

/**
 * Webhook 事件推送（V5.13 B6）
 * 将系统事件以 JSON POST 推送到外部回调地址（n8n / Home Assistant / 自建服务等）。
 *
 * 配置项：
 *   webhook_enable  '1'/'0'    总开关
 *   webhook_url     https://... 回调地址
 *   webhook_secret              可选，用于 HMAC-SHA256 签名（X-Webhook-Signature: sha256=<hex>）
 *   webhook_events               逗号分隔的订阅事件；为空表示订阅全部事件
 *
 * 内置事件：task_fail（计划任务连续失败）、sign_done / sign_fail / account_offline（预留，可扩展接入）
 *
 * 示例：Webhook::push('task_fail', array('task' => 'system_sign', 'message' => '...'));
 */
class Webhook
{
    /**
     * 推送事件
     * @param string $event   事件名（白名单见 events()）
     * @param array  $payload 业务数据
     * @return array [ok => bool, detail => string]
     */
    public static function push($event, $payload = array())
    {
        $event = (string) $event;
        $allowed = self::events();
        if (!in_array($event, $allowed, true)) {
            return array('ok' => false, 'detail' => '未知事件：' . $event);
        }
        if (option::get('webhook_enable') !== '1') {
            return array('ok' => true, 'detail' => 'Webhook 未启用');
        }
        $url = trim((string) option::get('webhook_url'));
        if ($url === '' || !preg_match('~^https?://~i', $url)) {
            return array('ok' => false, 'detail' => '未配置有效的 Webhook URL');
        }
        // 事件订阅过滤
        $events = trim((string) option::get('webhook_events'));
        if ($events !== '') {
            $subscribed = array_map('trim', explode(',', $events));
            if (!in_array($event, $subscribed, true)) {
                return array('ok' => true, 'detail' => '事件未订阅');
            }
        }
        $secret = (string) option::get('webhook_secret');

        $body = json_encode(array(
            'event' => $event,
            'ts'    => time(),
            'data'  => is_array($payload) ? $payload : array(),
        ), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $headers = array(
            'Content-Type: application/json',
            'X-Webhook-Event: ' . $event,
            'User-Agent: TiebaCloudSign/Webhook',
        );
        if ($secret !== '') {
            $headers[] = 'X-Webhook-Signature: sha256=' . hash_hmac('sha256', $body, $secret);
        }

        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt_array($ch, array(
                CURLOPT_POST           => true,
                CURLOPT_POSTFIELDS     => $body,
                CURLOPT_HTTPHEADER     => $headers,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CONNECTTIMEOUT => 5,
                CURLOPT_TIMEOUT        => 8,
                CURLOPT_FOLLOWLOCATION => false,
            ));
            $resp = curl_exec($ch);
            $err = curl_error($ch);
            $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            if ($resp === false) {
                return array('ok' => false, 'detail' => '推送失败：' . $err);
            }
            return array('ok' => $code >= 200 && $code < 300, 'detail' => 'HTTP ' . $code . ' ' . substr((string) $resp, 0, 200));
        }

        // 无 curl 时回退 stream 上下文
        $ctx = stream_context_create(array('http' => array(
            'method'  => 'POST',
            'header'  => implode("\r\n", $headers),
            'content' => $body,
            'timeout' => 8,
            'ignore_errors' => true,
        )));
        $resp = @file_get_contents($url, false, $ctx);
        if ($resp === false) {
            return array('ok' => false, 'detail' => '推送失败（stream 模式）');
        }
        return array('ok' => true, 'detail' => substr((string) $resp, 0, 200));
    }

    /**
     * 支持的事件白名单
     */
    public static function events()
    {
        return array('task_fail', 'sign_done', 'sign_fail', 'account_offline', 'test');
    }
}
