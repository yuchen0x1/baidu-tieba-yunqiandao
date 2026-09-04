<?php
/**
 * 可观测性指标端点（V5.13 A4）
 * 输出 Prometheus text/plain 指标，供 Prometheus / Uptime Kuma 等抓取。
 *
 * 认证（按优先级）：
 *   1. 选项 metrics_token（推荐，可独立于 API Token 配置）
 *   2. 兼容回退：api_token（全局 API Token）
 * 未配置任一 Token 时返回 403（未启用）。
 *
 * 调用方式：GET /metrics.php?token=xxx  或  Header: Authorization: Bearer xxx
 */

define('SYSTEM_NO_UI', true);
define('SYSTEM_NO_CHECK_LOGIN', true);
require dirname(__FILE__) . '/init.php';

// ---------- 认证 ----------
$provided = isset($_GET['token']) ? trim((string) $_GET['token']) : '';
if ($provided === '' && isset($_SERVER['HTTP_AUTHORIZATION'])) {
    $auth = trim((string) $_SERVER['HTTP_AUTHORIZATION']);
    if (stripos($auth, 'Bearer ') === 0) {
        $provided = trim(substr($auth, 7));
    }
}
if ($provided === '' && isset($_SERVER['HTTP_X_API_TOKEN'])) {
    $provided = trim((string) $_SERVER['HTTP_X_API_TOKEN']);
}
$metricsToken = (string) option::get('metrics_token');
require_once SYSTEM_ROOT . '/lib/security.php';
$valid = false;
if ($metricsToken !== '') {
    //兼容哈希存储与明文存储（历史手工配置），推荐库里只存 SHA-256
    $valid = api_token_matches($metricsToken, $provided);
} else {
    $apiToken = (string) option::get('api_token');
    if ($apiToken !== '') {
        //与 api.php 一致：兼容哈希存储与升级前旧站的明文存储两种形态
        $valid = api_token_matches($apiToken, $provided);
    }
}
if (!$valid) {
    http_response_code(403);
    header('Content-Type: text/plain; charset=UTF-8');
    die("# metrics 未启用或 Token 无效\n");
}

header('Content-Type: text/plain; version=0.0.4; charset=UTF-8');
header('Cache-Control: no-store');

global $m;
$lines = array();
$esc = static function ($v) {
    return str_replace('\\', '\\\\', str_replace('"', '\\"', (string) $v));
};

// ---------- 基础 ----------
$up = $m->ping() ? 1 : 0;
$lines[] = '# HELP tieba_cloud_up 数据库连通性';
$lines[] = '# TYPE tieba_cloud_up gauge';
$lines[] = 'tieba_cloud_up ' . $up;
$lines[] = '# HELP tieba_cloud_version 当前系统版本';
$lines[] = '# TYPE tieba_cloud_version gauge';
$lines[] = 'tieba_cloud_version{version="' . $esc(SYSTEM_VER) . '"} 1';

// ---------- 计划任务 ----------
$cronRows = array();
$rs = $m->query("SELECT `name`, `no`, `enable`, `last_status`, `running`, `nextrun` FROM `" . DB_PREFIX . "cron`");
while ($r = $m->fetch_array($rs)) {
    $cronRows[] = $r;
}
$enabledCount = 0;
$disabledCount = 0;
foreach ($cronRows as $r) {
    if ((int) $r['no'] === 1 || (int) $r['enable'] === 0) {
        $disabledCount++;
    } else {
        $enabledCount++;
    }
}
$lines[] = '# HELP tieba_cloud_cron_total 计划任务总数';
$lines[] = '# TYPE tieba_cloud_cron_total gauge';
$lines[] = 'tieba_cloud_cron_total ' . count($cronRows);
$lines[] = 'tieba_cloud_cron_enabled ' . $enabledCount;
$lines[] = 'tieba_cloud_cron_disabled ' . $disabledCount;

$lines[] = '# HELP tieba_cloud_cron_last_status 各任务最近执行状态（success/error/空）';
$lines[] = '# TYPE tieba_cloud_cron_last_status gauge';
foreach ($cronRows as $r) {
    $ls = (string) $r['last_status'];
    $name = $esc($r['name']);
    $lines[] = 'tieba_cloud_cron_last_status{name="' . $name . '",status="success"} ' . (($ls === 'success') ? '1' : '0');
    $lines[] = 'tieba_cloud_cron_last_status{name="' . $name . '",status="error"} ' . (($ls === 'error') ? '1' : '0');
    $lines[] = 'tieba_cloud_cron_running{name="' . $name . '"} ' . ((int) $r['running'] === 1 ? '1' : '0');
}

// ---------- 任务运行历史（task_runs） ----------
$tableExists = false;
$t = $m->once_fetch_array("SELECT COUNT(*) AS c FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = '" . $m->real_escape_string(DB_PREFIX . 'task_runs') . "'");
if (!empty($t['c'])) {
    $tableExists = true;
}
if ($tableExists) {
    $since = time() - 86400;
    $lines[] = '# HELP tieba_cloud_task_runs_total 任务运行历史各状态累计次数';
    $lines[] = '# TYPE tieba_cloud_task_runs_total counter';
    $rs = $m->query("SELECT `status`, COUNT(*) AS c FROM `" . DB_PREFIX . "task_runs` GROUP BY `status`");
    while ($r = $m->fetch_array($rs)) {
        $lines[] = 'tieba_cloud_task_runs_total{status="' . $esc($r['status']) . '"} ' . (int) $r['c'];
    }
    $lines[] = '# HELP tieba_cloud_task_runs_24h 近 24 小时任务运行各状态次数';
    $lines[] = '# TYPE tieba_cloud_task_runs_24h gauge';
    $rs = $m->query("SELECT `status`, COUNT(*) AS c FROM `" . DB_PREFIX . "task_runs` WHERE `started_at` >= {$since} GROUP BY `status`");
    $found = false;
    while ($r = $m->fetch_array($rs)) {
        $found = true;
        $lines[] = 'tieba_cloud_task_runs_24h{status="' . $esc($r['status']) . '"} ' . (int) $r['c'];
    }
    if (!$found) {
        $lines[] = 'tieba_cloud_task_runs_24h{status="none"} 0';
    }
}

// ---------- 账号与贴吧 ----------
$cnt = $m->once_fetch_array("SELECT COUNT(*) AS c FROM `" . DB_PREFIX . "baiduid`");
$lines[] = '# HELP tieba_cloud_account_total 绑定百度账号数';
$lines[] = '# TYPE tieba_cloud_account_total gauge';
$lines[] = 'tieba_cloud_account_total ' . (int) $cnt['c'];

$cnt = $m->once_fetch_array("SELECT COUNT(*) AS c FROM `" . DB_PREFIX . "tieba`");
$lines[] = '# HELP tieba_cloud_tieba_total 关注贴吧总数';
$lines[] = '# TYPE tieba_cloud_tieba_total gauge';
$lines[] = 'tieba_cloud_tieba_total ' . (int) $cnt['c'];

// ---------- 上次调度 ----------
$lastDo = (string) option::get('cron_last_do');
$lines[] = '# HELP tieba_cloud_last_cron_do 上次 do.php 调度执行时间戳';
$lines[] = '# TYPE tieba_cloud_last_cron_do gauge';
$lines[] = 'tieba_cloud_last_cron_do ' . ($lastDo !== '' ? (int) $lastDo : 0);

echo implode("\n", $lines) . "\n";
