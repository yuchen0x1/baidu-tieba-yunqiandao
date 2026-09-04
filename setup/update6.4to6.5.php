<?php

define('SYSTEM_DEV', true);
define('SYSTEM_NO_CHECK_VER', true);
define('SYSTEM_NO_CHECK_LOGIN', true);
define('SYSTEM_NO_PLUGIN', true);
include __DIR__ . '/../init.php';
global $m;

$cv = (string) option::get('core_version');
if ($cv !== '' && version_compare($cv, '6.5', '>=')) {
    msg('您的云签到已升级到 V6.5 版本，请勿重复更新<br/><br/>请立即删除 /setup/update6.4to6.5.php');
}
if ($cv !== '6.4') {
    msg('当前数据库版本为 ' . htmlspecialchars($cv ?: '未知', ENT_QUOTES, 'UTF-8') . '，请先完成前置升级到 V6.4。');
}

// V6.4 → V6.5 无核心表结构变更：
// - 账号健康度每日快照表 tc_baiduid_health_snapshot 由 SignManager 按需自建（健康检查运行时自动补齐）；
// - 监控命中统计读取 ver4_monitor 插件的既有命中表，插件未安装时分析页自动隐藏对应区块；
// - 吧维度明细导出为纯代码功能，无数据迁移。

if (!option::set('core_version', '6.5')) {
    throw new RuntimeException('无法更新数据库版本标记');
}
@unlink(__FILE__);
msg('您的云签到已成功升级到 V6.5。本版本：数据分析页吧维度明细 CSV 导出、帖子监控命中统计与趋势纳入报表、账号健康度每日趋势（随健康检查自动积累快照）。请立即删除 /setup/update6.4to6.5.php，谢谢', SYSTEM_URL);
