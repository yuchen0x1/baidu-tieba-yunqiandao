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
            return array('ok' => true, 'detail' => '通知未启用');
        }
        if ($event === 'sign_fail' && !option::get('notify_on_fail')) {
            return array('ok' => true, 'detail' => '签到失败提醒未启用');
        }
        if ($event === 'health_invalid' && !option::get('notify_on_health')) {
            return array('ok' => true, 'detail' => '账号失效提醒未启用');
        }

        switch (option::get('notify_channel')) {
            case 'serverchan':
                $key = option::get('notify_serverchan_key');
                if (empty($key)) {
                    return array('ok' => false, 'detail' => '未配置 Server酱 SendKey');
                }
                return self::sendServerChan($key, $title, $content);
            case 'telegram':
                $token = option::get('notify_telegram_token');
                $chatId = option::get('notify_telegram_chatid');
                if (empty($token) || empty($chatId)) {
                    return array('ok' => false, 'detail' => '未配置 Telegram Bot Token / Chat ID');
                }
                return self::sendTelegram($token, $chatId, $title, $content);
            case 'webhook':
                $url = option::get('notify_webhook_url');
                if (empty($url)) {
                    return array('ok' => false, 'detail' => '未配置 Webhook URL');
                }
                return self::sendWebhook($url, $title, $content);
            case 'bark':
                $barkUrl = option::get('notify_bark_url');
                if (empty($barkUrl)) {
                    return array('ok' => false, 'detail' => '未配置 Bark URL');
                }
                return self::sendBark($barkUrl, $title, $content);
            case 'pushplus':
                $ppToken = option::get('notify_pushplus_token');
                if (empty($ppToken)) {
                    return array('ok' => false, 'detail' => '未配置 PushPlus Token');
                }
                return self::sendPushPlus($ppToken, $title, $content);
            case 'dingtalk':
                $dtUrl = option::get('notify_dingtalk_webhook');
                if (empty($dtUrl)) {
                    return array('ok' => false, 'detail' => '未配置钉钉机器人 Webhook');
                }
                return self::sendDingTalk($dtUrl, option::get('notify_dingtalk_secret'), $title, $content);
            case 'wecom':
                $wcUrl = option::get('notify_wecom_webhook');
                if (empty($wcUrl)) {
                    return array('ok' => false, 'detail' => '未配置企业微信机器人 Webhook');
                }
                return self::sendWeCom($wcUrl, $title, $content);
            case 'feishu':
                $fsUrl = option::get('notify_feishu_webhook');
                if (empty($fsUrl)) {
                    return array('ok' => false, 'detail' => '未配置飞书机器人 Webhook');
                }
                return self::sendFeiShu($fsUrl, $title, $content);
            case 'wxpusher':
                $xpToken = option::get('notify_wxpusher_token');
                $xpUid = option::get('notify_wxpusher_uid');
                if (empty($xpToken) || empty($xpUid)) {
                    return array('ok' => false, 'detail' => '未配置 WxPusher AppToken / UID');
                }
                return self::sendWxPusher($xpToken, $xpUid, $title, $content);
            case 'ntfy':
                $ntfyTopic = option::get('notify_ntfy_topic');
                if (empty($ntfyTopic) || preg_match('/^[A-Za-z0-9_-]{1,128}$/', $ntfyTopic) !== 1) {
                    return array('ok' => false, 'detail' => '未配置或配置了无效的 ntfy Topic');
                }
                return self::sendNtfy(option::get('notify_ntfy_server'), $ntfyTopic, $title, $content);
            case 'mail':
                return self::sendMail($title, $content);
            default:
                return array('ok' => true, 'detail' => '未选择通知渠道');
        }
    }

    /**
     * Server酱 (https://sct.ftqq.com)
     */
    public static function sendServerChan($key, $title, $content)
    {
        try {
            $c = new wcurl('https://sctapi.ftqq.com/' . urlencode($key) . '.send');
            $c->set(CURLOPT_TIMEOUT, 15);
            $resp = $c->post(array('title' => $title, 'desp' => $content));
            $json = json_decode($resp, true);
            if (is_array($json) && isset($json['code']) && (int) $json['code'] === 0) {
                return array('ok' => true, 'detail' => 'Server酱 发送成功');
            }
            return array('ok' => false, 'detail' => 'Server酱 返回: ' . substr((string) $resp, 0, 200));
        } catch (Exception $e) {
            return array('ok' => false, 'detail' => 'Server酱 异常: ' . $e->getMessage());
        }
    }

    /**
     * Telegram Bot
     */
    public static function sendTelegram($token, $chatId, $title, $content)
    {
        try {
            $text = $title . "\n\n" . $content;
            $c = new wcurl('https://api.telegram.org/bot' . $token . '/sendMessage');
            $c->set(CURLOPT_TIMEOUT, 15);
            $resp = $c->post(array('chat_id' => $chatId, 'text' => $text, 'disable_web_page_preview' => '1'));
            $json = json_decode($resp, true);
            if (is_array($json) && !empty($json['ok'])) {
                return array('ok' => true, 'detail' => 'Telegram 发送成功');
            }
            return array('ok' => false, 'detail' => 'Telegram 返回: ' . substr((string) $resp, 0, 200));
        } catch (Exception $e) {
            return array('ok' => false, 'detail' => 'Telegram 异常: ' . $e->getMessage());
        }
    }

    /**
     * 通用 Webhook (JSON POST: {title, content, event})
     */
    public static function sendWebhook($url, $title, $content)
    {
        try {
            $payload = json_encode(array(
                'title' => $title,
                'content' => $content,
                'time' => date('Y-m-d H:i:s')
            ), JSON_UNESCAPED_UNICODE);
            $c = new wcurl($url);
            $c->set(CURLOPT_TIMEOUT, 15);
            $c->set(CURLOPT_POST, 1);
            $c->set(CURLOPT_POSTFIELDS, $payload);
            $c->set(CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
            $resp = $c->exec();
            if ($resp === false) {
                return array('ok' => false, 'detail' => 'Webhook 请求失败: ' . curl_error($c->conn));
            }
            return array('ok' => true, 'detail' => 'Webhook 已发送 (HTTP 响应: ' . substr((string) $resp, 0, 100) . ')');
        } catch (Exception $e) {
            return array('ok' => false, 'detail' => 'Webhook 异常: ' . $e->getMessage());
        }
    }

    /**
     * Bark (iOS 推送)
     */
    public static function sendBark($url, $title, $content)
    {
        try {
            $url = rtrim($url, '/') . '/' . urlencode($title) . '/' . urlencode($content);
            $c = new wcurl($url);
            $c->set(CURLOPT_TIMEOUT, 15);
            $resp = $c->exec();
            $json = json_decode($resp, true);
            if (is_array($json) && isset($json['code']) && (int) $json['code'] === 200) {
                return array('ok' => true, 'detail' => 'Bark 发送成功');
            }
            return array('ok' => false, 'detail' => 'Bark 返回: ' . substr((string) $resp, 0, 200));
        } catch (Exception $e) {
            return array('ok' => false, 'detail' => 'Bark 异常: ' . $e->getMessage());
        }
    }

    /**
     * PushPlus (微信推送)
     */
    public static function sendPushPlus($token, $title, $content)
    {
        try {
            $c = new wcurl('https://www.pushplus.plus/send');
            $c->set(CURLOPT_TIMEOUT, 15);
            $payload = json_encode(array('token' => $token, 'title' => $title, 'content' => $content, 'template' => 'txt'), JSON_UNESCAPED_UNICODE);
            $c->set(CURLOPT_POST, 1);
            $c->set(CURLOPT_POSTFIELDS, $payload);
            $c->set(CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
            $resp = $c->exec();
            $json = json_decode($resp, true);
            if (is_array($json) && isset($json['code']) && (int) $json['code'] === 200) {
                return array('ok' => true, 'detail' => 'PushPlus 发送成功');
            }
            return array('ok' => false, 'detail' => 'PushPlus 返回: ' . substr((string) $resp, 0, 200));
        } catch (Exception $e) {
            return array('ok' => false, 'detail' => 'PushPlus 异常: ' . $e->getMessage());
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
            $c = new wcurl($webhook);
            $c->set(CURLOPT_TIMEOUT, 15);
            $payload = json_encode(array(
                'msgtype' => 'text',
                'text' => array('content' => $title . "\n" . $content),
            ), JSON_UNESCAPED_UNICODE);
            $c->set(CURLOPT_POST, 1);
            $c->set(CURLOPT_POSTFIELDS, $payload);
            $c->set(CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
            $resp = $c->exec();
            $json = json_decode($resp, true);
            if (is_array($json) && isset($json['errcode']) && (int) $json['errcode'] === 0) {
                return array('ok' => true, 'detail' => '钉钉 发送成功');
            }
            return array('ok' => false, 'detail' => '钉钉 返回: ' . substr((string) $resp, 0, 200));
        } catch (Exception $e) {
            return array('ok' => false, 'detail' => '钉钉 异常: ' . $e->getMessage());
        }
    }

    /**
     * 企业微信群机器人
     */
    public static function sendWeCom($webhook, $title, $content)
    {
        try {
            $c = new wcurl($webhook);
            $c->set(CURLOPT_TIMEOUT, 15);
            $payload = json_encode(array(
                'msgtype' => 'text',
                'text' => array('content' => $title . "\n" . $content),
            ), JSON_UNESCAPED_UNICODE);
            $c->set(CURLOPT_POST, 1);
            $c->set(CURLOPT_POSTFIELDS, $payload);
            $c->set(CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
            $resp = $c->exec();
            $json = json_decode($resp, true);
            if (is_array($json) && isset($json['errcode']) && (int) $json['errcode'] === 0) {
                return array('ok' => true, 'detail' => '企业微信 发送成功');
            }
            return array('ok' => false, 'detail' => '企业微信 返回: ' . substr((string) $resp, 0, 200));
        } catch (Exception $e) {
            return array('ok' => false, 'detail' => '企业微信 异常: ' . $e->getMessage());
        }
    }

    /**
     * 飞书群机器人
     */
    public static function sendFeiShu($webhook, $title, $content)
    {
        try {
            $c = new wcurl($webhook);
            $c->set(CURLOPT_TIMEOUT, 15);
            $payload = json_encode(array(
                'msg_type' => 'text',
                'content' => array('text' => $title . "\n" . $content),
            ), JSON_UNESCAPED_UNICODE);
            $c->set(CURLOPT_POST, 1);
            $c->set(CURLOPT_POSTFIELDS, $payload);
            $c->set(CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
            $resp = $c->exec();
            $json = json_decode($resp, true);
            if (is_array($json) && ((isset($json['code']) && (int) $json['code'] === 0) || (isset($json['StatusCode']) && (int) $json['StatusCode'] === 0))) {
                return array('ok' => true, 'detail' => '飞书 发送成功');
            }
            return array('ok' => false, 'detail' => '飞书 返回: ' . substr((string) $resp, 0, 200));
        } catch (Exception $e) {
            return array('ok' => false, 'detail' => '飞书 异常: ' . $e->getMessage());
        }
    }

    /**
     * WxPusher (微信推送服务 https://wxpusher.zjiecode.com)
     */
    public static function sendWxPusher($appToken, $uid, $title, $content)
    {
        try {
            $c = new wcurl('https://wxpusher.zjiecode.com/api/send/message');
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
            $json = json_decode($resp, true);
            if (is_array($json) && isset($json['code']) && (int) $json['code'] === 1000) {
                return array('ok' => true, 'detail' => 'WxPusher 发送成功');
            }
            return array('ok' => false, 'detail' => 'WxPusher 返回: ' . substr((string) $resp, 0, 200));
        } catch (Exception $e) {
            return array('ok' => false, 'detail' => 'WxPusher 异常: ' . $e->getMessage());
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
            $c = new wcurl($server . '/' . rawurlencode($topic));
            $c->set(CURLOPT_TIMEOUT, 15);
            $c->set(CURLOPT_POST, 1);
            $c->set(CURLOPT_POSTFIELDS, $content);
            $c->set(CURLOPT_HTTPHEADER, array(
                'Title: ' . base64_encode($title),
                'Content-Type: text/plain; charset=utf-8',
                'Accept: application/json'
            ));
            $resp = $c->exec();
            $json = json_decode($resp, true);
            if (is_array($json) && isset($json['event']) && $json['event'] === 'message') {
                return array('ok' => true, 'detail' => 'ntfy 发送成功');
            }
            return array('ok' => false, 'detail' => 'ntfy 返回: ' . substr((string) $resp, 0, 200));
        } catch (Exception $e) {
            return array('ok' => false, 'detail' => 'ntfy 异常: ' . $e->getMessage());
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
        $result = misc::mail($to, $title, nl2br(htmlspecialchars($content, ENT_QUOTES, 'UTF-8')));
        if ($result === true) {
            return array('ok' => true, 'detail' => '邮件发送成功 (发往 ' . $to . ')');
        }
        return array('ok' => false, 'detail' => '邮件发送失败: ' . (string) $result);
    }
}
