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
     * 组装事件载荷。签名覆盖 timestamp.body，接收方应校验时间窗并拒绝重放。
     * @param string $event
     * @param array  $payload
     * @param int    $timestamp
     * @return array
     */
    public static function buildPayload($event, $payload = array(), $timestamp = 0)
    {
        $timestamp = (int) $timestamp > 0 ? (int) $timestamp : time();
        return array(
            'id'        => self::eventId($event, $timestamp, $payload),
            'event'     => (string) $event,
            'ts'        => $timestamp,
            'data'      => is_array($payload) ? $payload : array(),
        );
    }

    /**
     * 稳定事件 ID：同一事件、同一秒、同一载荷得到同一 ID，便于接收方去重。
     */
    public static function eventId($event, $timestamp, $payload = array())
    {
        return hash('sha256', (string) $event . '|' . (int) $timestamp . '|' . json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    /**
     * 生成出站请求头。保留旧版 body 签名以兼容已有接收方，
     * 同时提供 timestamp+body 签名与事件 ID 供新接收方做防重放校验。
     * @return array
     */
    public static function headers($event, $body, $secret, $timestamp, $eventId)
    {
        $headers = array(
            'Content-Type: application/json',
            'X-Webhook-Event: ' . (string) $event,
            'X-Webhook-Id: ' . (string) $eventId,
            'X-Webhook-Timestamp: ' . (int) $timestamp,
            'User-Agent: TiebaCloudSign/Webhook',
        );
        if ((string) $secret !== '') {
            $signed = (int) $timestamp . '.' . $body;
            $headers[] = 'X-Webhook-Signature: sha256=' . hash_hmac('sha256', $body, (string) $secret);
            $headers[] = 'X-Webhook-Signature-V2: sha256=' . hash_hmac('sha256', $signed, (string) $secret);
        }
        return $headers;
    }

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
        // 回调地址与 Secret 一样通过 SecretBox 解密；兼容历史明文值。
        $url = trim((string) SecretBox::get('webhook_url'));
        if ($url === '' || !preg_match('~^https?://~i', $url)) {
            return array('ok' => false, 'detail' => '未配置有效的 Webhook URL');
        }
        $GLOBALS['webhook_current_event'] = $event;
        // 事件订阅过滤
        $events = trim((string) option::get('webhook_events'));
        if ($events !== '') {
            $subscribed = array_map('trim', explode(',', $events));
            if (!in_array($event, $subscribed, true)) {
                return array('ok' => true, 'detail' => '事件未订阅');
            }
        }
        $secret = SecretBox::get('webhook_secret');

        $timestamp = time();
        $payloadData = self::buildPayload($event, $payload, $timestamp);
        $body = json_encode($payloadData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $headers = self::headers($event, $body, $secret, $timestamp, $payloadData['id']);

        if (function_exists('curl_init')) {
            // 用户可配置任意 URL：必须拒绝内网/回环地址并把连接固定到已校验的 DNS 答案，
            // 否则管理员配置或插件写入的 URL 可被用来探测云元数据与内网服务。
            if (!class_exists('wcurl')) {
                require_once SYSTEM_ROOT . '/lib/class.wcurl.php';
            }
            try {
                $client = new wcurl('', $headers);
            } catch (Throwable $e) {
                return array('ok' => false, 'detail' => '推送失败：无法初始化 HTTP 客户端');
            }
            if (!$client->setPublicUrl($url)) {
                return array('ok' => false, 'detail' => 'Webhook 拒绝访问内网或非公开地址 (SSRF 防御)');
            }
            $client->set(CURLOPT_POST, true);
            $client->set(CURLOPT_POSTFIELDS, $body);
            $client->set(CURLOPT_HTTPHEADER, $headers);
            $client->set(CURLOPT_RETURNTRANSFER, true);
            $client->set(CURLOPT_CONNECTTIMEOUT, 5);
            $client->set(CURLOPT_TIMEOUT, 8);
            $resp = $client->exec();
            $err = $client->error();
            $code = (int) $client->getInfo(CURLINFO_HTTP_CODE);
            $client->close();
            if ($resp === false) {
                return array('ok' => false, 'detail' => '推送失败：' . $err);
            }
            $result = array('ok' => $code >= 200 && $code < 300, 'detail' => 'HTTP ' . $code . ' ' . substr((string) $resp, 0, 200));
            self::rememberLastResult($result);
            return $result;
        }

        // ext-curl 是项目运行时必需扩展。没有它时不要退回未绑定 DNS 的
        // stream 传输，否则 DNS 重绑定可能绕过上面的公网地址校验。
        return array('ok' => false, 'detail' => '服务器未启用 cURL，Webhook 已安全拒绝发送');
    }

    /**
     * 记录最近推送结果（只存脱敏摘要，不含 URL/Secret），最多保留 10 条。
     * @param array $result
     */
    protected static function rememberLastResult($result)
    {
        if (!class_exists('option', false)) {
            return;
        }
        $history = json_decode((string) option::get('webhook_recent_results'), true);
        if (!is_array($history)) {
            $history = array();
        }
        array_unshift($history, array(
            'ts'     => time(),
            'event'  => (string) ($GLOBALS['webhook_current_event'] ?? ''),
            'ok'     => !empty($result['ok']),
            'detail' => substr((string) ($result['detail'] ?? ''), 0, 200),
        ));
        option::set('webhook_recent_results', json_encode(array_slice($history, 0, 10), JSON_UNESCAPED_UNICODE));
    }

    /**
     * 支持的事件白名单
     */
    public static function events()
    {
        return array('task_fail', 'sign_done', 'sign_fail', 'account_offline', 'test');
    }

    /**
     * 事件的中文说明，供后台复选展示。
     * @return array<string,string>
     */
    public static function eventLabels()
    {
        return array(
            'task_fail'       => '计划任务连续失败',
            'sign_done'       => '签到完成（预留事件）',
            'sign_fail'       => '签到失败（预留事件）',
            'account_offline' => '百度账号失效（预留事件）',
            'test'            => '测试事件',
        );
    }

    /**
     * 最近一次推送结果（供后台显示，不包含 URL 与 Secret）。
     * @param array $result push() 返回值
     * @return string
     */
    public static function describeResult($result)
    {
        if (!is_array($result)) {
            return '未知结果';
        }
        if (!empty($result['ok'])) {
            return '成功：' . (string) ($result['detail'] ?? '服务已接受');
        }
        return '失败：' . (string) ($result['detail'] ?? '未知错误');
    }
}
