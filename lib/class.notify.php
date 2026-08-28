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
            $c->post(array('title' => $title, 'desp' => $content));
            $resp = $c->exec();
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
            $c->post(array('chat_id' => $chatId, 'text' => $text, 'disable_web_page_preview' => '1'));
            $resp = $c->exec();
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
