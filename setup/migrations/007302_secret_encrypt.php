<?php
/**
 * 迁移 007302：敏感配置选项加密落库
 *
 * 背景：V7.4 已对 BDUSS/STOKEN/AI Key/实例 Secret 做了 AES-256-GCM 加密，
 * 但以下选项仍是明文落库 + 明文回显：
 *   OAuth Client Secret、SMTP 密码、打码平台凭据、各类通知渠道 Token/Secret、
 *   Webhook URL（含访问令牌）、代理密码、BBS 密码、计划任务密码。
 *
 * 处理：对上述选项做 P::encrypt（enc:v1: 前缀），幂等（已是密文的跳过）。
 * 直接走 $m->pquery 写 options 表，避开 option::set 的内存缓存转义。
 *
 * 注意：api_token / metrics_token 保持 SHA-256 哈希存储，本迁移不改它们
 * （认证走 hash_equals 比对，加密后认证逻辑会崩溃）。
 *
 * down：将密文解密回写（P::decrypt 可逆），供回滚到 V7.4 时使用。
 */
return array(
    'version' => '007302',
    'desc' => 'V7.5：敏感配置选项（通知凭据/SMTP/代理/计划任务密码等）加密落库',
    'up' => function ($m, $prefix) {
        if (!defined('SYSTEM_ROOT')) {
            //迁移由 Migrator 驱动，SYSTEM_ROOT 必然已定义；防御性兜底
            return 'SYSTEM_ROOT 未定义，跳过密文迁移';
        }
        require_once SYSTEM_ROOT . '/lib/class.P.php';

        //需要加密的选项键
        $secretKeys = array(
            'oauth_github_client_secret',
            'mail_smtppw',
            'dama_username',
            'dama_password',
            'notify_serverchan_key',
            'notify_telegram_token',
            'notify_dingtalk_secret',
            'notify_dingtalk_webhook',
            'notify_bark_url',
            'notify_pushplus_token',
            'notify_wecom_webhook',
            'notify_feishu_webhook',
            'notify_wxpusher_token',
            'notify_webhook_url',
            'webhook_secret',
            'proxy_pass',
            'bbs_pw',
            'cron_pw',
        );

        $encrypted = 0;
        $skipped = 0;
        foreach ($secretKeys as $key) {
            $row = $m->pquery("SELECT `value` FROM `{$prefix}options` WHERE `name` = ? LIMIT 1", array($key));
            $optRow = $row !== false ? $m->fetch_array($row) : false;
            if (empty($optRow['value'])) {
                $skipped++;
                continue;
            }
            $stored = (string) $optRow['value'];
            //已是密文则跳过
            if (strpos($stored, 'enc:v1:') === 0) {
                $skipped++;
                continue;
            }
            $cipher = P::encrypt($stored);
            //加密失败/无密钥时 P::encrypt 返回原文，不更新（避免写入重复）
            if ($cipher === $stored) {
                $skipped++;
                continue;
            }
            $m->pquery("UPDATE `{$prefix}options` SET `value` = ? WHERE `name` = ?", array($cipher, $key));
            $encrypted++;
        }

        return '敏感选项加密完成：加密 ' . $encrypted . ' 项，跳过 ' . $skipped . ' 项（空值/已有密文/无密钥）';
    },
    'down' => function ($m, $prefix) {
        if (!defined('SYSTEM_ROOT')) {
            return;
        }
        require_once SYSTEM_ROOT . '/lib/class.P.php';
        $secretKeys = array(
            'oauth_github_client_secret', 'mail_smtppw', 'dama_username', 'dama_password',
            'notify_serverchan_key', 'notify_telegram_token', 'notify_dingtalk_secret',
            'notify_dingtalk_webhook', 'notify_bark_url', 'notify_pushplus_token',
            'notify_wecom_webhook', 'notify_feishu_webhook', 'notify_wxpusher_token',
            'notify_webhook_url', 'webhook_secret', 'proxy_pass', 'bbs_pw', 'cron_pw',
        );
        foreach ($secretKeys as $key) {
            $row = $m->pquery("SELECT `value` FROM `{$prefix}options` WHERE `name` = ? LIMIT 1", array($key));
            $optRow = $row !== false ? $m->fetch_array($row) : false;
            if (empty($optRow['value']) || strpos((string) $optRow['value'], 'enc:v1:') !== 0) {
                continue;
            }
            $plain = P::decrypt((string) $optRow['value']);
            if ($plain !== (string) $optRow['value']) {
                $m->pquery("UPDATE `{$prefix}options` SET `value` = ? WHERE `name` = ?", array($plain, $key));
            }
        }
    },
);
