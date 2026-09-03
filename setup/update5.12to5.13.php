<?php

define('SYSTEM_DEV', true);
define('SYSTEM_NO_CHECK_VER', true);
define('SYSTEM_NO_CHECK_LOGIN', true);
define('SYSTEM_NO_PLUGIN', true);
include __DIR__ . '/../init.php';
global $m;

$cv = (string) option::get('core_version');
if ($cv !== '' && version_compare($cv, '5.13', '>=')) {
    msg('您的云签到已升级到 V5.13 版本，请勿重复更新<br/><br/>请立即删除 /setup/update5.12to5.13.php');
}
if ($cv !== '5.12') {
    msg('当前数据库版本为 ' . htmlspecialchars($cv ?: '未知', ENT_QUOTES, 'UTF-8') . '，请先完成前置升级到 V5.12。');
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

if (!$tableExists('cron')) {
    throw new RuntimeException('缺少基础数据表：' . $prefix . 'cron');
}

// 1) cron 表新增调度增强字段（幂等）
$cronColumns = array(
    'enable'          => "TINYINT(1) UNSIGNED NOT NULL DEFAULT 1",
    'nextrun'         => "INT UNSIGNED NOT NULL DEFAULT 0",
    'retry'           => "TINYINT(3) UNSIGNED NOT NULL DEFAULT 0",
    'max_retry'       => "TINYINT(3) UNSIGNED NOT NULL DEFAULT 2",
    'last_status'     => "VARCHAR(20) NOT NULL DEFAULT ''",
    'last_duration'   => "INT UNSIGNED NOT NULL DEFAULT 0",
    'running'         => "TINYINT(1) UNSIGNED NOT NULL DEFAULT 0",
    'running_at'      => "INT UNSIGNED NOT NULL DEFAULT 0",
);
foreach ($cronColumns as $col => $def) {
    if (!$columnExists('cron', $col)) {
        $m->query("ALTER TABLE `{$prefix}cron` ADD COLUMN `{$col}` {$def} AFTER `log`");
    }
}

// 2) 存量任务回填：enable 与旧 no 对齐；nextrun 按上次执行+频率推算
$m->query("UPDATE `{$prefix}cron` SET `enable` = IF(`no` = 1, 0, 1) WHERE `enable` = 1 AND `no` = 1");
$m->query("UPDATE `{$prefix}cron` SET `nextrun` = IF(`freq` > 0 AND `lastdo` > 0, `lastdo` + `freq`, 0) WHERE `nextrun` = 0");

// 3) 新建任务级运行历史表 task_runs
require_once SYSTEM_ROOT . '/lib/class.cron.php';
cron::ensureTables();

// 4) 新增调度选项（存在则跳过）
$newOptions = array(
    'cron_max_retry'      => '2',
    'cron_retry_backoff'  => '300',
    'cron_lock_timeout'   => '600',
    'cron_notify_on_fail' => '1',
);
foreach ($newOptions as $name => $value) {
    $key = $m->real_escape_string($name);
    $row = $m->once_fetch_array("SELECT COUNT(*) AS c FROM `{$prefix}options` WHERE `name` = '{$key}'");
    if (empty($row['c'])) {
        option::add($name, $value);
    }
}

if (!option::set('core_version', '5.13')) {
    throw new RuntimeException('无法更新数据库版本标记');
}
@unlink(__FILE__);
msg('您的云签到已成功升级到 V5.13。调度引擎已升级：任务级运行历史（task_runs）、防重入锁、按 nextrun 调度、失败指数退避重试与失败通知。请立即删除 /setup/update5.12to5.13.php，谢谢', SYSTEM_URL);
