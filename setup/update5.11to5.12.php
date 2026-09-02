<?php

define('SYSTEM_DEV', true);
define('SYSTEM_NO_CHECK_VER', true);
define('SYSTEM_NO_CHECK_LOGIN', true);
define('SYSTEM_NO_PLUGIN', true);
include __DIR__ . '/../init.php';
global $m;

$cv = (string) option::get('core_version');
if ($cv !== '' && version_compare($cv, '5.12', '>=')) {
    msg('您的云签到已升级到 V5.12 版本，请勿重复更新<br/><br/>请立即删除 /setup/update5.11to5.12.php');
}
if ($cv !== '5.11') {
    msg('当前数据库版本为 ' . htmlspecialchars($cv ?: '未知', ENT_QUOTES, 'UTF-8') . '，请先完成前置升级到 V5.11。');
}

$prefix = DB_PREFIX;
$tableExists = static function ($table) use ($m, $prefix) {
    $name = $m->real_escape_string($prefix . $table);
    $row = $m->once_fetch_array("SELECT COUNT(*) AS c FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = '{$name}'");
    return !empty($row['c']);
};
$columnExists = static function ($table, $column) use ($m, $prefix) {
    $tableName = $m->real_escape_string($prefix . $table);
    $columnName = $m->real_escape_string($column);
    $row = $m->once_fetch_array("SELECT COUNT(*) AS c FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = '{$tableName}' AND column_name = '{$columnName}'");
    return !empty($row['c']);
};
$indexExists = static function ($table, $index) use ($m, $prefix) {
    $tableName = $m->real_escape_string($prefix . $table);
    $indexName = $m->real_escape_string($index);
    $row = $m->once_fetch_array("SELECT COUNT(*) AS c FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = '{$tableName}' AND index_name = '{$indexName}'");
    return !empty($row['c']);
};

foreach (array('baiduid', 'options', 'users') as $table) {
    if (!$tableExists($table)) {
        throw new RuntimeException('缺少基础数据表：' . $prefix . $table);
    }
}
if (!$columnExists('users', 'api_token_hash')) {
    $m->query("ALTER TABLE `{$prefix}users` ADD COLUMN `api_token_hash` CHAR(64) DEFAULT NULL AFTER `auth_version`");
}
if (!$indexExists('users', 'api_token_hash')) {
    $m->query("ALTER TABLE `{$prefix}users` ADD UNIQUE KEY `api_token_hash` (`api_token_hash`)");
}

$newOptions = array(
    'health_auto_disable' => '0',
    'health_fail_max' => '3',
    'notify_dingtalk_webhook' => '',
    'notify_dingtalk_secret' => '',
    'notify_wecom_webhook' => '',
    'notify_feishu_webhook' => '',
    'notify_wxpusher_token' => '',
    'notify_wxpusher_uid' => '',
    'notify_ntfy_server' => 'https://ntfy.sh',
    'notify_ntfy_topic' => '',
);
foreach ($newOptions as $name => $value) {
    $key = $m->real_escape_string($name);
    $row = $m->once_fetch_array("SELECT COUNT(*) AS c FROM `{$prefix}options` WHERE `name` = '{$key}'");
    if (empty($row['c'])) {
        option::add($name, $value);
    }
}

require_once SYSTEM_ROOT . '/lib/class.SignManager.php';
SignManager::ensureHistoryTable();

if (!option::set('core_version', '5.12')) {
    throw new RuntimeException('无法更新数据库版本标记');
}
@unlink(__FILE__);
msg('您的云签到已成功升级到 V5.12。新增全渠道通知、云点赞、逐用户 API Token、签到日历和失效账号恢复。请立即删除 /setup/update5.11to5.12.php，谢谢', SYSTEM_URL);
