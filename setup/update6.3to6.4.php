<?php

define('SYSTEM_DEV', true);
define('SYSTEM_NO_CHECK_VER', true);
define('SYSTEM_NO_CHECK_LOGIN', true);
define('SYSTEM_NO_PLUGIN', true);
include __DIR__ . '/../init.php';
global $m;

$cv = (string) option::get('core_version');
if ($cv !== '' && version_compare($cv, '6.4', '>=')) {
    msg('您的云签到已升级到 V6.4 版本，请勿重复更新<br/><br/>请立即删除 /setup/update6.3to6.4.php');
}
if ($cv !== '6.3') {
    msg('当前数据库版本为 ' . htmlspecialchars($cv ?: '未知', ENT_QUOTES, 'UTF-8') . '，请先完成前置升级到 V6.3。');
}

$prefix = DB_PREFIX;

// V6.3 → V6.4 无核心表结构变更：
// - 全局 API Token 改为哈希存储为代码级兼容（api_token_matches 同时兼容哈希与旧明文），
//   站长下次在「设置中心 → 系统」重新保存 Token 后即自动转为哈希落库；
// - AI 半自动发帖候选表 tc_ver4_ai_draft 由 ver4_ai 插件按需自建（ensure 逻辑静态守卫）；
// - 其余全部为安全加固与既有功能修复，无数据迁移。

if (!option::set('core_version', '6.4')) {
    throw new RuntimeException('无法更新数据库版本标记');
}
@unlink(__FILE__);
msg('您的云签到已成功升级到 V6.4。本版本：6 处注入/越权高危修复、CSRF 硬化、全局 API Token 哈希存储、AI 人设模板/半自动发帖候选/审查拦截记录。建议站长在设置中心重新保存一次开放 API Token 以完成哈希化。请立即删除 /setup/update6.3to6.4.php，谢谢', SYSTEM_URL);
