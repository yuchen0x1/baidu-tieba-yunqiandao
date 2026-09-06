<?php
/**
 * 发布门禁（V7.0）：一条命令跑完本版本全部发布前检查。
 *
 *   php tools/release-gate.php
 *
 * 检查项：
 *   1. 全仓语法与安全扫描（tools/lint.php）
 *   2. PHPUnit 单元测试（vendor 缺失时跳过并提示）
 *   3. 版本元数据一致性（version.php / 安装模板 / Windows 脚本 / CHANGELOG 头部）
 *   4. 迁移文件规范（文件名与 version 键一致、无重复版本号）
 *   5. 入口 SQL 注入面守卫（ajax/setting/api 禁止插值 SQL）
 *   6. 数据库集成链路（迁移 / 调度 / API / 备份恢复演练；数据库不可达时跳过）
 *
 * 全部通过（或仅出现 SKIP）时退出码 0，任一 FAIL 退出码 1。
 */

if (PHP_SAPI !== 'cli') {
    die('仅支持命令行运行：php tools/release-gate.php');
}

require __DIR__ . '/lib-integration.php';

$repoRoot = dirname(__DIR__);
$results = array();

function gate($name, $state, $detail = '')
{
    // $state: pass | fail | skip
    $GLOBALS['results'][] = array($name, $state, $detail);
    $mark = $state === 'pass' ? 'PASS' : ($state === 'fail' ? 'FAIL' : 'SKIP');
    echo "[{$mark}] {$name}" . ($detail !== '' ? " — {$detail}" : '') . "\n";
}

function runStep($script, $args = array())
{
    $output = array();
    $code = 0;
    $cmd = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($script);
    foreach ($args as $arg) {
        $cmd .= ' ' . escapeshellarg($arg);
    }
    exec($cmd . ' 2>&1', $output, $code);
    return array($code, implode("\n", $output));
}

// ---------- 1. 语法与安全扫描 ----------
list($code, $output) = runStep($repoRoot . '/tools/lint.php');
gate('全仓语法与安全扫描', $code === 0 ? 'pass' : 'fail', $code === 0 ? trim($output) : substr(trim($output), 0, 400));

// ---------- 1.5 Secret 扫描（Git 追踪文件凭据模式） ----------
list($code, $output) = runStep($repoRoot . '/tools/secret-scan.php');
gate('Secret 扫描', $code === 0 ? 'pass' : 'fail', $code === 0 ? '无疑似泄露' : substr(trim($output), 0, 400));

// ---------- 2. 单元测试 ----------
$phpunit = $repoRoot . '/vendor/bin/phpunit';
if (file_exists($phpunit)) {
    list($code, $output) = runStep($phpunit);
    preg_match('/OK \((\d+) tests?/', $output, $m);
    gate('PHPUnit 单元测试', $code === 0 ? 'pass' : 'fail', isset($m[1]) ? $m[1] . ' 项通过' : substr(trim($output), 0, 400));
} else {
    gate('PHPUnit 单元测试', 'skip', 'vendor 不存在：请先 composer install');
}

// ---------- 3. 版本元数据一致性 ----------
$versionFile = file_get_contents($repoRoot . '/version.php');
$versionOk = preg_match("/define\('SYSTEM_VER', '([^']+)'\)/", $versionFile, $vm) === 1;
$version = $versionOk ? $vm[1] : '';
$template = file_get_contents($repoRoot . '/setup/install.template.sql');
$changelog = (string) @file_get_contents($repoRoot . '/CHANGELOG.md');
$ps1 = (string) @file_get_contents($repoRoot . '/windows/check-update.ps1');
$consistency = array();
if (!$versionOk) {
    $consistency[] = 'version.php 缺少 SYSTEM_VER';
}
if (strpos($template, "('core_version', '{$version}')") === false) {
    $consistency[] = '安装模板 core_version 与版本源不一致';
}
if (strpos($ps1, 'CurrentVersion = "' . $version . '"') === false) {
    $consistency[] = 'check-update.ps1 默认版本与版本源不一致';
}
if (strpos($changelog, 'V' . $version) === false) {
    $consistency[] = 'CHANGELOG 未包含当前版本条目';
}
foreach (array('setup/install.php', 'setup/check.php') as $entry) {
    if (strpos((string) @file_get_contents($repoRoot . '/' . $entry), 'version.php') === false) {
        $consistency[] = $entry . ' 未使用统一版本源';
    }
}
gate('版本元数据一致性', $consistency === array() ? 'pass' : 'fail', $consistency === array() ? 'V' . $version : implode('；', $consistency));

// ---------- 4. 迁移文件规范 ----------
$migrationIssues = array();
$versions = array();
foreach (glob($repoRoot . '/setup/migrations/*.php') ?: array() as $file) {
    $base = basename($file, '.php');
    $code2 = include $file;
    if (!is_array($code2) || !isset($code2['version'], $code2['up'])) {
        $migrationIssues[] = basename($file) . ' 结构不合法';
        continue;
    }
    if (strpos($base, $code2['version'] . '_') !== 0) {
        $migrationIssues[] = basename($file) . ' 文件名前缀与 version 不一致';
    }
    if (!preg_match('/^\d{6}$/', (string) $code2['version'])) {
        $migrationIssues[] = basename($file) . ' version 必须为 6 位数字';
    }
    $versions[] = $code2['version'];
}
if (count($versions) !== count(array_unique($versions))) {
    $migrationIssues[] = '存在重复的迁移版本号';
}
gate('迁移文件规范', $migrationIssues === array() ? 'pass' : 'fail', $migrationIssues === array() ? count($versions) . ' 个迁移' : implode('；', $migrationIssues));

// ---------- 5. 入口 SQL 注入面守卫 ----------
$sqlDrift = array();
foreach (array('ajax.php', 'setting.php', 'api.php') as $entry) {
    $source = (string) @file_get_contents($repoRoot . '/' . $entry);
    if (preg_match('/->(?:query|once_fetch_array)\("[^"]*\{\$/', $source) === 1) {
        $sqlDrift[] = $entry;
    }
}
gate('入口 SQL 注入面守卫', $sqlDrift === array() ? 'pass' : 'fail', $sqlDrift === array() ? 'ajax/setting/api 无插值 SQL' : implode('；', $sqlDrift) . ' 出现插值 SQL');

// ---------- 6. 数据库集成链路 ----------
$dbReachable = false;
if (class_exists('mysqli')) {
    try {
        $probe = new mysqli(getenv('TCS_IT_DB_HOST') ?: '127.0.0.1', getenv('TCS_IT_DB_USER') ?: 'root', getenv('TCS_IT_DB_PASS') ?: '', '', (int) (getenv('TCS_IT_DB_PORT') ?: '3306'));
        $probe->close();
        $dbReachable = true;
    } catch (mysqli_sql_exception $e) {
        $dbReachable = false;
    }
}
if ($dbReachable) {
    foreach (array(
        '数据库集成：迁移' => 'migrate-integration-test.php',
        '数据库集成：调度' => 'scheduler-integration-test.php',
        '数据库集成：API 合同' => 'api-integration-test.php',
        '数据库集成：备份恢复演练' => 'backup-restore-drill.php',
    ) as $name => $script) {
        list($code, $output) = runStep(__DIR__ . '/' . $script);
        preg_match('/(\d+) 通过，(\d+) 失败/', $output, $m);
        $detail = isset($m[1]) ? $m[1] . ' 通过 / ' . $m[2] . ' 失败' : substr(trim($output), 0, 300);
        gate($name, $code === 0 ? 'pass' : 'fail', $detail);
    }
} else {
    gate('数据库集成链路（迁移/调度/API/备份恢复）', 'skip', '数据库不可达（TCS_IT_DB_* 未配置或服务未启动）');
}

// ---------- 汇总 ----------
$failed = 0;
$skipped = 0;
echo "\n=== 发布门禁汇总 ===\n";
foreach ($results as $row) {
    if ($row[1] === 'fail') {
        $failed++;
        echo "FAIL {$row[0]}\n";
    } elseif ($row[1] === 'skip') {
        $skipped++;
    }
}
$total = count($results);
echo "共 {$total} 项：" . ($total - $failed - $skipped) . ' 通过，' . $skipped . " 跳过，{$failed} 失败\n";
if ($failed === 0) {
    echo "发布门禁通过" . ($skipped > 0 ? "（含 {$skipped} 项跳过，请确认跳过原因是否可接受）" : '') . "\n";
}
exit($failed === 0 ? 0 : 1);
