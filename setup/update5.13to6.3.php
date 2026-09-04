<?php

define('SYSTEM_DEV', true);
define('SYSTEM_NO_CHECK_VER', true);
define('SYSTEM_NO_CHECK_LOGIN', true);
define('SYSTEM_NO_PLUGIN', true);
include __DIR__ . '/../init.php';
global $m;

$cv = (string) option::get('core_version');
if ($cv !== '' && version_compare($cv, '6.3', '>=')) {
    msg('您的云签到已升级到 V6.3 版本，请勿重复更新<br/><br/>请立即删除 /setup/update5.13to6.3.php');
}
if ($cv !== '5.13') {
    msg('当前数据库版本为 ' . htmlspecialchars($cv ?: '未知', ENT_QUOTES, 'UTF-8') . '，请先完成前置升级到 V5.13。');
}

$prefix = DB_PREFIX;

// V5.13 → V6.3 无核心表结构变更：
// - 插件表（监控命中/AI 日志/回帖日志）由各插件按需自建并自动补列；
// - 新增选项（数据保留 cleanup_* 等）均有运行时默认值，无需落库即可生效；
// - 新增计划任务（自动清理 system_cleanup、签到周报 system_weekreport）
//   在后台「工具箱」一键注册（沿用 system_dbbackup 的注册模式）。

// 1) 清理设置默认值落库（存在则跳过，方便站长在工具箱直接看到当前生效值）
$newOptions = array(
    'cleanup_enable'             => '1',
    'cleanup_sign_history_days'  => '180',
    'cleanup_task_runs_days'     => '90',
    'cleanup_cron_log_days'      => '30',
    'cleanup_plugin_log_days'    => '90',
    'cleanup_api_rate_days'      => '7',
);
foreach ($newOptions as $name => $value) {
    $key = $m->real_escape_string($name);
    $row = $m->once_fetch_array("SELECT COUNT(*) AS c FROM `{$prefix}options` WHERE `name` = '{$key}'");
    if (empty($row['c'])) {
        option::add($name, $value);
    }
}

// 2) 注册新计划任务（已存在时无副作用）
$m->query("INSERT IGNORE INTO `{$prefix}cron` (`name`, `orde`, `file`, `no`, `desc`, `freq`, `lastdo`, `log`) VALUES
    ('system_cleanup', '10', 'lib/cron_system_cleanup.php', '0', '每日按保留天数清理签到历史/任务运行历史/插件日志等过期数据', '86400', '0', ''),
    ('system_weekreport', '11', 'lib/cron_system_weekreport.php', '0', '每周按用户配置推送签到周报（用户个人通知渠道）', '86400', '0', '')");

if (!option::set('core_version', '6.3')) {
    throw new RuntimeException('无法更新数据库版本标记');
}
@unlink(__FILE__);
msg('您的云签到已成功升级到 V6.3。本版本：帖子监控（关键词/排除词/个人通知渠道/每日汇总/联动 AI 自动回帖）、AI 发帖回帖用户面板化与安全防护、账号页增强、数据保留自动清理、每周签到报告、Glass v5 玻璃拟态界面。请立即删除 /setup/update5.13to6.3.php，谢谢', SYSTEM_URL);
