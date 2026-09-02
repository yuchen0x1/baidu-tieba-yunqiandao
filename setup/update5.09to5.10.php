<?php

define('SYSTEM_DEV', true);
define('SYSTEM_NO_CHECK_VER', true);
define('SYSTEM_NO_CHECK_LOGIN', true);
define('SYSTEM_NO_PLUGIN', true);
include __DIR__ . '/../init.php';
global $m;

$cv = (string) option::get('core_version');
if ($cv !== '' && version_compare($cv, '5.10', '>=')) {
    msg('您的云签到已升级到 V5.10 版本，请勿重复更新<br/><br/>请立即删除 /setup/update5.09to5.10.php');
}
if ($cv !== '5.09') {
    msg('当前数据库版本为 ' . htmlspecialchars($cv ?: '未知', ENT_QUOTES, 'UTF-8') . '，请先完成前置升级到 V5.09。');
}

$prefix = DB_PREFIX;

$tableExists = static function ($table) use ($m, $prefix) {
    $name = $m->real_escape_string($prefix . $table);
    $row = $m->once_fetch_array("SELECT COUNT(*) AS c FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = '{$name}'");
    return !empty($row['c']);
};
$columnExists = static function ($table, $column) use ($m, $prefix) {
    $table = $m->real_escape_string($prefix . $table);
    $column = $m->real_escape_string($column);
    $row = $m->once_fetch_array("SELECT COUNT(*) AS c FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = '{$table}' AND column_name = '{$column}'");
    return !empty($row['c']);
};

foreach (array('baiduid', 'options') as $table) {
    if (!$tableExists($table)) {
        throw new RuntimeException('缺少基础数据表：' . $prefix . $table);
    }
}

// 账号级代理覆盖列
if (!$columnExists('baiduid', 'proxy')) {
    $m->query("ALTER TABLE `{$prefix}baiduid` ADD COLUMN `proxy` VARCHAR(255) NOT NULL DEFAULT '' AFTER `stoken`");
}

// V5.10 新增设置项默认值（已存在则跳过）
$newOptions = array(
    'sign_jitter'   => '0',
    'sign_shuffle'  => '1',
    'client_version' => '',
    'proxy_type'    => '0',
    'proxy_host'    => '',
    'proxy_user'    => '',
    'proxy_pass'    => '',
    'dama_type'     => 'none',
    'dama_username' => '',
    'dama_password' => '',
    'dama_softid'   => '',
);
foreach ($newOptions as $name => $value) {
    $row = $m->once_fetch_array("SELECT COUNT(*) AS c FROM `{$prefix}options` WHERE `name` = '" . $m->real_escape_string($name) . "'");
    if (empty($row['c'])) {
        option::add($name, $value);
    }
}

if (!option::set('core_version', '5.10')) {
    throw new RuntimeException('无法更新数据库版本标记');
}
@unlink(__FILE__);
msg('您的云签到已成功升级到 V5.10。新增：网络代理、打码平台、客户端版本号可配置、签到随机抖动与乱序。请立即删除 /setup/update5.09to5.10.php，谢谢', SYSTEM_URL);
