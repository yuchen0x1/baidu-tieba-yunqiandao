<?php

define('SYSTEM_DEV', true);
define('SYSTEM_NO_CHECK_VER', true);
define('SYSTEM_NO_CHECK_LOGIN', true);
define('SYSTEM_NO_PLUGIN', true);
include __DIR__ . '/../init.php';
global $m;

$cv = (string) option::get('core_version');
if ($cv !== '' && version_compare($cv, '5.11', '>=')) {
    msg('您的云签到已升级到 V5.11 版本，请勿重复更新<br/><br/>请立即删除 /setup/update5.10to5.11.php');
}
if ($cv !== '5.10') {
    msg('当前数据库版本为 ' . htmlspecialchars($cv ?: '未知', ENT_QUOTES, 'UTF-8') . '，请先完成前置升级到 V5.10。');
}

$prefix = DB_PREFIX;

$tableExists = static function ($table) use ($m, $prefix) {
    $name = $m->real_escape_string($prefix . $table);
    $row = $m->once_fetch_array("SELECT COUNT(*) AS c FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = '{$name}'");
    return !empty($row['c']);
};
if (!$tableExists('options')) {
    throw new RuntimeException('缺少基础数据表：' . $prefix . 'options');
}

// V5.11 新增设置项默认值（已存在则跳过）
$newOptions = array(
    'reg_mail_verify' => '0',
);
foreach ($newOptions as $name => $value) {
    $row = $m->once_fetch_array("SELECT COUNT(*) AS c FROM `{$prefix}options` WHERE `name` = '" . $m->real_escape_string($name) . "'");
    if (empty($row['c'])) {
        option::add($name, $value);
    }
}

if (!option::set('core_version', '5.11')) {
    throw new RuntimeException('无法更新数据库版本标记');
}
@unlink(__FILE__);
msg('您的云签到已成功升级到 V5.11。新增：批量导入 BDUSS、找回密码、注册邮箱验证。请立即删除 /setup/update5.10to5.11.php，谢谢', SYSTEM_URL);
