<?php

if (!defined('SYSTEM_ROOT')) {
    die('Insufficient Permissions');
}

/**
 * 通知中心 - 多渠道推送 (Server酱 / Telegram / 通用 Webhook / 邮件)
 * 配置项: notify_enable, notify_channel, notify_serverchan_key,
 *         notify_telegram_token, notify_telegram_chatid, notify_webhook_url,
 *         notify_on_fail, notify_on_health
 */
class Notify
{
    /** 旧插件仍可读取 ok；跳过不代表渠道已接受通知。 */
    public static function wasDelivered($result)
    {
        return !empty($result['ok']) && (!array_key_exists('delivered', $result) || $result['delivered'] === true);
    }

    protected static function createClient($url = '')
    {
        return new wcurl($url);
    }

    protected static function hasProviderCode($response, $field, $expected)
    {
        return is_array($response) && isset($response[$field])
            && (is_int($response[$field]) || is_string($response[$field]))
            && (string) $response[$field] === (string) $expected;
    }

    /** 不回显响应体、URL 或异常文本，避免渠道凭据进入页面和任务日志。 */
    protected static function transportFailure($client, $response, $channel)
    {
        $code = (int) curl_getinfo($client->conn, CURLINFO_HTTP_CODE);
        if ($response === false) {
            return array('ok' => false, 'delivered' => false, 'detail' => $channel . ' 请求失败（cURL ' . curl_errno($client->conn) . '）');
        }
        if ($code < 200 || $code >= 300) {
            return array('ok' => false, 'delivered' => false, 'detail' => $channel . ' 请求未被接受（HTTP ' . $code . '）');
        }
        return null;
    }

    /**
     * 按用户自己的通知渠道发送（个人面板配置，存于 users_options 的 ver4_notify_*）
     * 渠道为「跟随站点」或未配置时回退到站点渠道（self::send）；
     * 用户自有渠道不受站点 notify_enable 开关限制（是否推送由用户自行决定）。
     * 支持：serverchan / telegram / bark / pushplus / webhook / ntfy
     * @param int $uid 用户 UID
     * @param string $title 标题
     * @param string $content 内容 (纯文本)
     * @param string $event 触发事件
     * @return array [ok => bool, detail => string]
     */
    public static function sendForUser($uid, $title, $content, $event = '')
    {
        $channel = trim((string) option::uget('ver4_notify_channel', $uid));
        if ($channel === '' || $channel === 'follow_site') {
            return self::send($title, $content, $event);
        }
        $uget = function ($key) use ($uid) {
            $v = option::uget($key, $uid);
            return ($v === null || $v === '') ? '' : (string) $v;
        };
        switch ($channel) {
            case 'serverchan':
                $key = $uget('ver4_notify_serverchan_key');
                if ($key === '') {
                    return array('ok' => false, 'detail' => '未配置 Server酱 SendKey');
                }
                return static::sendServerChan($key, $title, $content);
            case 'telegram':
                $token = $uget('ver4_notify_telegram_token');
                $chatId = $uget('ver4_notify_telegram_chatid');
                if ($token === '' || $chatId === '') {
                    return array('ok' => false, 'detail' => '未配置 Telegram Bot Token / Chat ID');
                }
                return static::sendTelegram($token, $chatId, $title, $content);
            case 'bark':
                $url = $uget('ver4_notify_bark_url');
                if ($url === '') {
                    return array('ok' => false, 'detail' => '未配置 Bark URL');
                }
                return static::sendBark($url, $title, $content);
            case 'pushplus':
                $token = $uget('ver4_notify_pushplus_token');
                if ($token === '') {
                    return array('ok' => false, 'detail' => '未配置 PushPlus Token');
                }
                return static::sendPushPlus($token, $title, $content);
            case 'webhook':
                $url = $uget('ver4_notify_webhook_url');
                if ($url === '') {
                    return array('ok' => false, 'detail' => '未配置 Webhook URL');
                }
                return static::sendWebhook($url, $title, $content);
            case 'ntfy':
                $topic = $uget('ver4_notify_ntfy_topic');
                if ($topic === '' || preg_match('/^[A-Za-z0-9_-]{1,128}$/', $topic) !== 1) {
                    return array('ok' => false, 'detail' => '未配置或配置了无效的 ntfy Topic');
                }
                return static::sendNtfy($uget('ver4_notify_ntfy_server'), $topic, $title, $content);
            default:
                return array('ok' => false, 'detail' => '该渠道暂不支持个人配置');
        }
    }

    /**
     * 按配置发送通知
     * @param string $title 标题
     * @param string $content 内容 (纯文本)
     * @param string $event 触发事件: sign_fail / health_invalid / test / 其他
     * @return array [ok => bool, detail => string]
     */
    public static function send($title, $content, $event = '')
    {
        if (!option::get('notify_enable')) {
            return array('ok' => true, 'delivered' => false, 'detail' => '通知未启用');
        }
        if ($event === 'sign_fail' && !option::get('notify_on_fail')) {
            return array('ok' => true, 'delivered' => false, 'detail' => '签到失败提醒未启用');
        }
        if ($event === 'health_invalid' && !option::get('notify_on_health')) {
            return array('ok' => true, 'delivered' => false, 'detail' => '账号失效提醒未启用');
        }

        switch (option::get('notify_channel')) {
            case 'serverchan':
                $key = SecretBox::get('notify_serverchan_key');
                if (empty($key)) {
                    return array('ok' => false, 'detail' => '未配置 Server酱 SendKey');
                }
                return static::sendServerChan($key, $title, $content);
            case 'telegram':
                $token = SecretBox::get('notify_telegram_token');
                $chatId = option::get('notify_telegram_chatid');
                if (empty($token) || empty($chatId)) {
                    return array('ok' => false, 'detail' => '未配置 Telegram Bot Token / Chat ID');
                }
                return static::sendTelegram($token, $chatId, $title, $content);
            case 'webhook':
                $url = SecretBox::get('notify_webhook_url');
                if (empty($url)) {
                    return array('ok' => false, 'detail' => '未配置 Webhook URL');
                }
                return static::sendWebhook($url, $title, $content);
            case 'bark':
                $barkUrl = SecretBox::get('notify_bark_url');
                if (empty($barkUrl)) {
                    return array('ok' => false, 'detail' => '未配置 Bark URL');
                }
                return static::sendBark($barkUrl, $title, $content);
            case 'pushplus':
                $ppToken = SecretBox::get('notify_pushplus_token');
                if (empty($ppToken)) {
                    return array('ok' => false, 'detail' => '未配置 PushPlus Token');
                }
                return static::sendPushPlus($ppToken, $title, $content);
            case 'dingtalk':
                $dtUrl = SecretBox::get('notify_dingtalk_webhook');
                if (empty($dtUrl)) {
                    return array('ok' => false, 'detail' => '未配置钉钉机器人 Webhook');
                }
                return static::sendDingTalk($dtUrl, SecretBox::get('notify_dingtalk_secret'), $title, $content);
            case 'wecom':
                $wcUrl = SecretBox::get('notify_wecom_webhook');
                if (empty($wcUrl)) {
                    return array('ok' => false, 'detail' => '未配置企业微信机器人 Webhook');
                }
                return static::sendWeCom($wcUrl, $title, $content);
            case 'feishu':
                $fsUrl = SecretBox::get('notify_feishu_webhook');
                if (empty($fsUrl)) {
                    return array('ok' => false, 'detail' => '未配置飞书机器人 Webhook');
                }
                return static::sendFeiShu($fsUrl, $title, $content);
            case 'wxpusher':
                $xpToken = SecretBox::get('notify_wxpusher_token');
                $xpUid = option::get('notify_wxpusher_uid');
                if (empty($xpToken) || empty($xpUid)) {
                    return array('ok' => false, 'detail' => '未配置 WxPusher AppToken / UID');
                }
                return static::sendWxPusher($xpToken, $xpUid, $title, $content);
            case 'ntfy':
                $ntfyTopic = option::get('notify_ntfy_topic');
                if (empty($ntfyTopic) || preg_match('/^[A-Za-z0-9_-]{1,128}$/', $ntfyTopic) !== 1) {
                    return array('ok' => false, 'detail' => '未配置或配置了无效的 ntfy Topic');
                }
                return static::sendNtfy(option::get('notify_ntfy_server'), $ntfyTopic, $title, $content);
            case 'mail':
                return static::sendMail($title, $content);
            default:
                return array('ok' => true, 'delivered' => false, 'detail' => '未选择通知渠道');
        }
    }

    /**
     * Server酱 (https://sct.ftqq.com)
     */
    public static function sendServerChan($key, $title, $content)
    {
        try {
            $c = static::createClient('https://sctapi.ftqq.com/' . urlencode($key) . '.send');
            $c->set(CURLOPT_TIMEOUT, 15);
            $resp = $c->post(array('title' => $title, 'desp' => $content));
            $failure = self::transportFailure($c, $resp, 'Server酱');
            if ($failure !== null) { return $failure; }
            $json = json_decode($resp, true);
            if (self::hasProviderCode($json, 'code', 0)) {
                return array('ok' => true, 'delivered' => true, 'detail' => 'Server酱 发送成功');
            }
            return array('ok' => false, 'detail' => 'Server酱 返回无效响应或业务错误，请检查渠道配置');
        } catch (Throwable $e) {
            return array('ok' => false, 'detail' => 'Server酱 发送异常，请检查渠道配置与网络');
        }
    }

    /**
     * Telegram Bot
     */
    public static function sendTelegram($token, $chatId, $title, $content)
    {
        try {
            $text = $title . "\n\n" . $content;
            $c = static::createClient('https://api.telegram.org/bot' . $token . '/sendMessage');
            $c->set(CURLOPT_TIMEOUT, 15);
            $resp = $c->post(array('chat_id' => $chatId, 'text' => $text, 'disable_web_page_preview' => '1'));
            $failure = self::transportFailure($c, $resp, 'Telegram');
            if ($failure !== null) { return $failure; }
            $json = json_decode($resp, true);
            if (is_array($json) && ($json['ok'] ?? null) === true) {
                return array('ok' => true, 'delivered' => true, 'detail' => 'Telegram 发送成功');
            }
            return array('ok' => false, 'detail' => 'Telegram 返回无效响应或业务错误，请检查渠道配置');
        } catch (Throwable $e) {
            return array('ok' => false, 'detail' => 'Telegram 发送异常，请检查渠道配置与网络');
        }
    }

    /**
     * Create a cURL handle pinned to a validated public notification target.
     *
     * All user-configurable webhook/Bark/ntfy targets use this single entry
     * point so DNS validation and transport pinning cannot diverge by channel.
     *
     * @param string $url
     * @return wcurl|false
     */
    protected static function createPublicClient($url)
    {
        $client = static::createClient();
        if (!$client->setPublicUrl($url)) {
            return false;
        }
        $client->set(CURLOPT_TIMEOUT, 15);
        return $client;
    }

    /**
     * 通用 Webhook (JSON POST: {title, content, event})
     */
    public static function sendWebhook($url, $title, $content)
    {
        try {
            $c = static::createPublicClient($url);
            if ($c === false) {
                return array('ok' => false, 'detail' => 'Webhook 拒绝访问内网或非公开地址 (SSRF 防御)');
            }
            $payload = json_encode(array(
                'title' => $title,
                'content' => $content,
                'time' => date('Y-m-d H:i:s')
            ), JSON_UNESCAPED_UNICODE);
            $c->set(CURLOPT_POST, 1);
            $c->set(CURLOPT_POSTFIELDS, $payload);
            $c->set(CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
            $resp = $c->exec();
            $failure = self::transportFailure($c, $resp, 'Webhook');
            if ($failure !== null) { return $failure; }
            return array('ok' => true, 'delivered' => true, 'detail' => 'Webhook 服务已接受（HTTP ' . (int) curl_getinfo($c->conn, CURLINFO_HTTP_CODE) . '）');
        } catch (Throwable $e) {
            return array('ok' => false, 'detail' => 'Webhook 发送异常，请检查渠道配置与网络');
        }
    }

    /**
     * Bark (iOS 推送)
     */
    public static function sendBark($url, $title, $content)
    {
        try {
            $url = rtrim($url, '/') . '/' . urlencode($title) . '/' . urlencode($content);
            $c = static::createPublicClient($url);
            if ($c === false) {
                return array('ok' => false, 'detail' => 'Bark 拒绝访问内网或非公开地址 (SSRF 防御)');
            }
            $resp = $c->exec();
            $failure = self::transportFailure($c, $resp, 'Bark');
            if ($failure !== null) { return $failure; }
            $json = json_decode($resp, true);
            if (self::hasProviderCode($json, 'code', 200)) {
                return array('ok' => true, 'delivered' => true, 'detail' => 'Bark 发送成功');
            }
            return array('ok' => false, 'detail' => 'Bark 返回无效响应或业务错误，请检查渠道配置');
        } catch (Throwable $e) {
            return array('ok' => false, 'detail' => 'Bark 发送异常，请检查渠道配置与网络');
        }
    }

    /**
     * PushPlus (微信推送)
     */
    public static function sendPushPlus($token, $title, $content)
    {
        try {
            $c = static::createClient('https://www.pushplus.plus/send');
            $c->set(CURLOPT_TIMEOUT, 15);
            $payload = json_encode(array('token' => $token, 'title' => $title, 'content' => $content, 'template' => 'txt'), JSON_UNESCAPED_UNICODE);
            $c->set(CURLOPT_POST, 1);
            $c->set(CURLOPT_POSTFIELDS, $payload);
            $c->set(CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
            $resp = $c->exec();
            $failure = self::transportFailure($c, $resp, 'PushPlus');
            if ($failure !== null) { return $failure; }
            $json = json_decode($resp, true);
            if (self::hasProviderCode($json, 'code', 200)) {
                return array('ok' => true, 'delivered' => true, 'detail' => 'PushPlus 发送成功');
            }
            return array('ok' => false, 'detail' => 'PushPlus 返回无效响应或业务错误，请检查渠道配置');
        } catch (Throwable $e) {
            return array('ok' => false, 'detail' => 'PushPlus 发送异常，请检查渠道配置与网络');
        }
    }


    /**
     * 钉钉群机器人（支持加签安全设置）
     */
    public static function sendDingTalk($webhook, $secret, $title, $content)
    {
        try {
            if (!empty($secret)) {
                $timestamp = time() * 1000;
                $stringToSign = $timestamp . "\n" . $secret;
                $sign = urlencode(base64_encode(hash_hmac('sha256', $stringToSign, $secret, true)));
                $webhook .= (strpos($webhook, '?') === false ? '?' : '&') . 'timestamp=' . $timestamp . '&sign=' . $sign;
            }
            $c = static::createPublicClient($webhook);
            if ($c === false) {
                return array('ok' => false, 'detail' => '机器人 Webhook 拒绝访问内网或非公开地址 (SSRF 防御)');
            }
            $c->set(CURLOPT_TIMEOUT, 15);
            $payload = json_encode(array(
                'msgtype' => 'text',
                'text' => array('content' => $title . "\n" . $content),
            ), JSON_UNESCAPED_UNICODE);
            $c->set(CURLOPT_POST, 1);
            $c->set(CURLOPT_POSTFIELDS, $payload);
            $c->set(CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
            $resp = $c->exec();
            $failure = self::transportFailure($c, $resp, '钉钉');
            if ($failure !== null) { return $failure; }
            $json = json_decode($resp, true);
            if (self::hasProviderCode($json, 'errcode', 0)) {
                return array('ok' => true, 'delivered' => true, 'detail' => '钉钉 发送成功');
            }
            return array('ok' => false, 'detail' => '钉钉 返回无效响应或业务错误，请检查渠道配置');
        } catch (Throwable $e) {
            return array('ok' => false, 'detail' => '钉钉 发送异常，请检查渠道配置与网络');
        }
    }

    /**
     * 企业微信群机器人
     */
    public static function sendWeCom($webhook, $title, $content)
    {
        try {
            $c = static::createPublicClient($webhook);
            if ($c === false) {
                return array('ok' => false, 'detail' => '机器人 Webhook 拒绝访问内网或非公开地址 (SSRF 防御)');
            }
            $c->set(CURLOPT_TIMEOUT, 15);
            $payload = json_encode(array(
                'msgtype' => 'text',
                'text' => array('content' => $title . "\n" . $content),
            ), JSON_UNESCAPED_UNICODE);
            $c->set(CURLOPT_POST, 1);
            $c->set(CURLOPT_POSTFIELDS, $payload);
            $c->set(CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
            $resp = $c->exec();
            $failure = self::transportFailure($c, $resp, '企业微信');
            if ($failure !== null) { return $failure; }
            $json = json_decode($resp, true);
            if (self::hasProviderCode($json, 'errcode', 0)) {
                return array('ok' => true, 'delivered' => true, 'detail' => '企业微信 发送成功');
            }
            return array('ok' => false, 'detail' => '企业微信 返回无效响应或业务错误，请检查渠道配置');
        } catch (Throwable $e) {
            return array('ok' => false, 'detail' => '企业微信 发送异常，请检查渠道配置与网络');
        }
    }

    /**
     * 飞书群机器人
     */
    public static function sendFeiShu($webhook, $title, $content)
    {
        try {
            $c = static::createPublicClient($webhook);
            if ($c === false) {
                return array('ok' => false, 'detail' => '机器人 Webhook 拒绝访问内网或非公开地址 (SSRF 防御)');
            }
            $c->set(CURLOPT_TIMEOUT, 15);
            $payload = json_encode(array(
                'msg_type' => 'text',
                'content' => array('text' => $title . "\n" . $content),
            ), JSON_UNESCAPED_UNICODE);
            $c->set(CURLOPT_POST, 1);
            $c->set(CURLOPT_POSTFIELDS, $payload);
            $c->set(CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
            $resp = $c->exec();
            $failure = self::transportFailure($c, $resp, '飞书');
            if ($failure !== null) { return $failure; }
            $json = json_decode($resp, true);
            if (self::hasProviderCode($json, 'code', 0) || self::hasProviderCode($json, 'StatusCode', 0)) {
                return array('ok' => true, 'delivered' => true, 'detail' => '飞书 发送成功');
            }
            return array('ok' => false, 'detail' => '飞书 返回无效响应或业务错误，请检查渠道配置');
        } catch (Throwable $e) {
            return array('ok' => false, 'detail' => '飞书 发送异常，请检查渠道配置与网络');
        }
    }

    /**
     * WxPusher (微信推送服务 https://wxpusher.zjiecode.com)
     */
    public static function sendWxPusher($appToken, $uid, $title, $content)
    {
        try {
            $c = static::createClient('https://wxpusher.zjiecode.com/api/send/message');
            $c->set(CURLOPT_TIMEOUT, 15);
            $payload = json_encode(array(
                'appToken' => $appToken,
                'content' => $title . "\n" . $content,
                'summary' => mb_substr($title, 0, 99, 'UTF-8'),
                'contentType' => 1,
                'uids' => array($uid),
            ), JSON_UNESCAPED_UNICODE);
            $c->set(CURLOPT_POST, 1);
            $c->set(CURLOPT_POSTFIELDS, $payload);
            $c->set(CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
            $resp = $c->exec();
            $failure = self::transportFailure($c, $resp, 'WxPusher');
            if ($failure !== null) { return $failure; }
            $json = json_decode($resp, true);
            if (self::hasProviderCode($json, 'code', 1000)) {
                return array('ok' => true, 'delivered' => true, 'detail' => 'WxPusher 发送成功');
            }
            return array('ok' => false, 'detail' => 'WxPusher 返回无效响应或业务错误，请检查渠道配置');
        } catch (Throwable $e) {
            return array('ok' => false, 'detail' => 'WxPusher 发送异常，请检查渠道配置与网络');
        }
    }

    /**
     * ntfy 通知（默认公共服务器，也支持自托管 HTTPS 实例）
     */
    public static function sendNtfy($server, $topic, $title, $content)
    {
        try {
            $server = rtrim(trim((string) $server), '/');
            $parts = parse_url($server);
            if (empty($parts['scheme']) || strtolower($parts['scheme']) !== 'https' || empty($parts['host'])) {
                return array('ok' => false, 'detail' => 'ntfy 服务器必须是 HTTPS 地址');
            }
            $c = static::createPublicClient($server . '/' . rawurlencode($topic));
            if ($c === false) {
                return array('ok' => false, 'detail' => 'ntfy 拒绝访问内网或非公开地址 (SSRF 防御)');
            }
            $c->set(CURLOPT_POST, 1);
            $c->set(CURLOPT_POSTFIELDS, $content);
            $c->set(CURLOPT_HTTPHEADER, array(
                'Title: ' . base64_encode($title),
                'Content-Type: text/plain; charset=utf-8',
                'Accept: application/json'
            ));
            $resp = $c->exec();
            $failure = self::transportFailure($c, $resp, 'ntfy');
            if ($failure !== null) { return $failure; }
            $json = json_decode($resp, true);
            if (is_array($json) && isset($json['event']) && $json['event'] === 'message') {
                return array('ok' => true, 'delivered' => true, 'detail' => 'ntfy 发送成功');
            }
            return array('ok' => false, 'detail' => 'ntfy 返回无效响应或业务错误，请检查渠道配置');
        } catch (Throwable $e) {
            return array('ok' => false, 'detail' => 'ntfy 发送异常，请检查渠道配置与网络');
        }
    }

    /**
     * 邮件 (发往管理员邮箱)
     */
    public static function sendMail($title, $content)
    {
        global $m;
        $admin = $m->once_fetch_array("SELECT `email` FROM `" . DB_PREFIX . "users` WHERE `role` = 'admin' ORDER BY `id` ASC LIMIT 1;");
        $to = isset($admin['email']) ? $admin['email'] : '';
        if (empty($to)) {
            return array('ok' => false, 'detail' => '未找到管理员邮箱');
        }
        try {
            $result = misc::mail($to, $title, nl2br(htmlspecialchars($content, ENT_QUOTES, 'UTF-8')));
        } catch (Throwable $e) {
            return array('ok' => false, 'detail' => '邮件发送异常，请检查邮件配置');
        }
        if ($result === true) {
            return array('ok' => true, 'delivered' => true, 'detail' => '邮件发送成功 (发往 ' . $to . ')');
        }
        return array('ok' => false, 'detail' => '邮件发送失败，请检查邮件配置');
    }
}
