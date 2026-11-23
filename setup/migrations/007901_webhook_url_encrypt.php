<?php
/**
 * 迁移 007901：Webhook 回调地址纳入 SecretBox 加密管理。
 *
 * 旧版本虽已加密 webhook_secret，但 webhook_url 仍可能以明文存储；
 * 该迁移只处理非空明文，已加密值和无密钥环境保持不变，确保可重复执行。
 */
return array(
    'version' => '007901',
    'desc' => 'V7.9：Webhook 回调地址加密落库',
    'up' => function ($m, $prefix) {
        if (!defined('SYSTEM_ROOT')) {
            return 'SYSTEM_ROOT 未定义，跳过 Webhook URL 加密';
        }
        require_once SYSTEM_ROOT . '/lib/class.P.php';
        $row = $m->pquery("SELECT `value` FROM `{$prefix}options` WHERE `name` = ? LIMIT 1", array('webhook_url'));
        $data = $row !== false ? $m->fetch_array($row) : false;
        if (empty($data['value'])) {
            return 'Webhook URL 未配置，无需迁移';
        }
        $stored = (string) $data['value'];
        if (strpos($stored, 'enc:v1:') === 0) {
            return 'Webhook URL 已是密文，跳过';
        }
        $encrypted = P::encrypt($stored);
        if ($encrypted === $stored) {
            return '当前无可用加密密钥，保留原值';
        }
        $m->pquery("UPDATE `{$prefix}options` SET `value` = ? WHERE `name` = ?", array($encrypted, 'webhook_url'));
        return 'Webhook URL 已加密';
    },
    'down' => function ($m, $prefix) {
        if (!defined('SYSTEM_ROOT')) {
            return 'SYSTEM_ROOT 未定义，跳过回滚';
        }
        require_once SYSTEM_ROOT . '/lib/class.P.php';
        $row = $m->pquery("SELECT `value` FROM `{$prefix}options` WHERE `name` = ? LIMIT 1", array('webhook_url'));
        $data = $row !== false ? $m->fetch_array($row) : false;
        if (empty($data['value']) || strpos((string) $data['value'], 'enc:v1:') !== 0) {
            return 'Webhook URL 不是密文，无需回滚';
        }
        $plain = P::decrypt((string) $data['value']);
        $m->pquery("UPDATE `{$prefix}options` SET `value` = ? WHERE `name` = ?", array($plain, 'webhook_url'));
        return 'Webhook URL 已恢复为兼容格式';
    },
);
