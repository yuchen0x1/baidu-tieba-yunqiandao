<?php

define('SYSTEM_DEV', true);
define('SYSTEM_NO_CHECK_VER', true);
define('SYSTEM_NO_CHECK_LOGIN', true);
define('SYSTEM_NO_PLUGIN', true);
include __DIR__ . '/../init.php';
global $m, $i;

$cv = option::get('core_version');
if (!empty($cv) && version_compare($cv, '5.07', '>=')) {
    msg('您的云签到已升级到 V5.07 版本，请勿重复更新<br/><br/>请立即删除 /setup/update5.06to5.07.php');
}

$prefix = DB_PREFIX;

// 1. 加密存量 BDUSS/STOKEN
$bds = $m->query("SELECT `id`, `bduss`, `stoken` FROM `{$prefix}baiduid`");
$encrypted = 0;
while ($row = $m->fetch_array($bds)) {
    $needUpdate = false;
    $newBduss = $row['bduss'];
    $newStoken = $row['stoken'];
    if ($row['bduss'] !== '' && $row['bduss'] !== null && strpos($row['bduss'], 'enc:v1:') !== 0) {
        $newBduss = P::encrypt($row['bduss']);
        $needUpdate = true;
    }
    if ($row['stoken'] !== '' && $row['stoken'] !== null && strpos($row['stoken'], 'enc:v1:') !== 0) {
        $newStoken = P::encrypt($row['stoken']);
        $needUpdate = true;
    }
    if ($needUpdate) {
        $m->query("UPDATE `{$prefix}baiduid` SET `bduss` = '" . $m->real_escape_string($newBduss) . "', `stoken` = '" . $m->real_escape_string((string) $newStoken) . "' WHERE `id` = " . (int) $row['id'] . ";");
        $encrypted++;
    }
}

// 2. tieba.latest: tinyint -> VARCHAR(10) （存 Y-m-d 或 d）
// 注意：实际签到逻辑仍使用 date('d') 对比，
// 字段类型从 tinyint 扩展为 VARCHAR(10) 兼容旧新数据。
$tables = array('tieba');
require_once SYSTEM_ROOT . '/lib/security.php';
$fb = safe_table_names(option::get('fb_tables'));
if (!empty($fb)) {
    $tables = array_merge($tables, $fb);
}
foreach ($tables as $t) {
    $m->query("ALTER TABLE `{$prefix}{$t}` MODIFY `latest` VARCHAR(10) NOT NULL DEFAULT '0';", true);
}

// 3. users 表加 created_at / last_login / auth_version
$m->query("ALTER TABLE `{$prefix}users` ADD COLUMN `created_at` INT UNSIGNED NOT NULL DEFAULT 0 AFTER `t`;", true);
$m->query("ALTER TABLE `{$prefix}users` ADD COLUMN `last_login` INT UNSIGNED NOT NULL DEFAULT 0 AFTER `created_at`;", true);
$m->query("ALTER TABLE `{$prefix}users` ADD COLUMN `auth_version` INT UNSIGNED NOT NULL DEFAULT 0 AFTER `last_login`;", true);

// 4. 新配置项
$defaults = array(
    'api_token' => '',
    'notify_bark_url' => '',
    'notify_pushplus_token' => '',
    'crypto_key' => bin2hex(random_bytes(32)),
);
foreach ($defaults as $nk => $nv) {
    if (option::get($nk) === null) {
        option::add($nk, $nv);
    }
}

option::set('core_version', '5.07');
unlink(__FILE__);
msg("您的云签到已成功升级到 V5.07 版本。<br/>已加密 {$encrypted} 个百度账号凭证。<br/>请立即删除 /setup/update5.06to5.07.php，谢谢", SYSTEM_URL);
