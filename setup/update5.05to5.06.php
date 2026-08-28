<?php

define('SYSTEM_DEV', true);
define('SYSTEM_NO_CHECK_VER', true);
define('SYSTEM_NO_CHECK_LOGIN', true);
define('SYSTEM_NO_PLUGIN', true);
include __DIR__ . '/../init.php';
global $m,$i;

$cv = option::get('core_version');
if (!empty($cv) && version_compare($cv, '5.06', '>=')) {
    msg('您的云签到已升级到 V5.06 版本，请勿重复更新<br/><br/>请立即删除 /setup/update5.05to5.06.php');
}

$prefix = DB_PREFIX;

// 1. MyISAM -> InnoDB (??/????)
$tables = array('baiduid', 'cron', 'options', 'plugins', 'tieba', 'users', 'users_options');
foreach ($tables as $t) {
    $m->query("ALTER TABLE `{$prefix}{$t}` ENGINE=InnoDB;", true);
}

// 2. ?????? (??)
$indexes = array(
    array('tieba', 'idx_uid_status', '(`uid`, `status`)'),
    array('tieba', 'idx_pid', '(`pid`)'),
    array('baiduid', 'idx_uid', '(`uid`)'),
);
foreach ($indexes as $idx) {
    $rs = $m->once_fetch_array("SELECT COUNT(*) AS c FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = '{$prefix}{$idx[0]}' AND index_name = '{$idx[1]}';");
    if (empty($rs['c'])) {
        $m->query("ALTER TABLE `{$prefix}{$idx[0]}` ADD INDEX `{$idx[1]}` {$idx[2]};", true);
    }
}

// 3. 登录审计表
require_once SYSTEM_ROOT . '/lib/class.loginguard.php';
LoginGuard::table();

// 4. 通知默认配置
$notifyDefaults = array(
    'notify_enable' => '0',
    'notify_channel' => 'none',
    'notify_serverchan_key' => '',
    'notify_telegram_token' => '',
    'notify_telegram_chatid' => '',
    'notify_webhook_url' => '',
    'notify_on_fail' => '1',
    'notify_on_health' => '1',
);
foreach ($notifyDefaults as $nk => $nv) {
    if (option::get($nk) === null) {
        option::add($nk, $nv);
    }
}

option::set('core_version', '5.06');
unlink(__FILE__);
msg('您的云签到已成功升级到 V5.06 版本，请立即删除 /setup/update5.05to5.06.php，谢谢', SYSTEM_URL);
