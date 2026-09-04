<?php

define('SYSTEM_DEV', true);
define('SYSTEM_NO_CHECK_VER', true);
define('SYSTEM_NO_CHECK_LOGIN', true);
define('SYSTEM_NO_PLUGIN', true);
include __DIR__ . '/../init.php';
global $m;

$cv = (string) option::get('core_version');
if ($cv !== '' && version_compare($cv, '6.6', '>=')) {
    msg('您的云签到已升级到 V6.6 版本，请勿重复更新<br/><br/>请立即删除 /setup/update6.5to6.6.php');
}
if ($cv !== '6.5') {
    msg('当前数据库版本为 ' . htmlspecialchars($cv ?: '未知', ENT_QUOTES, 'UTF-8') . '，请先完成前置升级到 V6.5。');
}

// V6.5 → V6.6 无核心表结构变更：
// 本版本全部为界面与移动端适配改动（CSS 缓存戳随 SYSTEM_VER 自动更新，浏览器强制拉新样式）。

if (!option::set('core_version', '6.6')) {
    throw new RuntimeException('无法更新数据库版本标记');
}
@unlink(__FILE__);
msg('您的云签到已成功升级到 V6.6。本版本：移动端表单/表格深度适配、遗留 <font> 标签全面替换为 cs-badge/cs-text 视觉体系、工作台首屏快捷操作（立即签到/绑定账号/签到记录/数据分析）。请立即删除 /setup/update6.5to6.6.php，谢谢', SYSTEM_URL);
