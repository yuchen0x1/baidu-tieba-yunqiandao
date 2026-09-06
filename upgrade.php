<?php

/**
 * 一键升级入口（站点根目录）。
 *
 * 为什么在根目录：许多站点按安装指引在 Web 服务器封禁了 /setup/（如 nginx
 * location ^~ /setup/ { return 404; }），setup/upgrade.php 在这些站点永远 404；
 * 根目录入口与 index.php 同级，任何部署形态都可达。
 *
 * 能力：管理员登录后访问即可「自动备份数据库 → 运行统一迁移器 → 清理 opcache」，
 * 与 CLI（php tools/migrate.php up）完全同一迁移器与状态记录。带 ?autostart=1
 * 时页面自动提交执行（在线更新器完成页会跳转到这里，实现点一次「立即更新」
 * 即全自动完成升级）。6.6 以下旧版本仍会被迁移器阻断，请先用 setup/update.php 逐步升级。
 */
if (empty($_COOKIE['uid']) || empty($_COOKIE['pwd'])) {
    http_response_code(302);
    header('Location: index.php', true, 302);
    die;
}
define('SYSTEM_DEV', true);
define('SYSTEM_NO_CHECK_VER', true);
require dirname(__FILE__) . '/init.php';
if (ROLE != 'admin') {
    msg('您需要先登录云签到管理员账号，才能继续升级', 'index.php');
}

header('content-type:text/html; charset=utf-8');

function upgrade_page_head($dbVersion)
{
    echo '<!DOCTYPE html><html lang="zh-CN"><head><meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />';
    echo '<meta name="viewport" content="width=device-width, initial-scale=1.0" />';
    echo '<title>一键升级到 V' . htmlspecialchars(SYSTEM_VER, ENT_QUOTES, 'UTF-8') . ' - ' . htmlspecialchars(SYSTEM_FN, ENT_QUOTES, 'UTF-8') . '</title>';
    echo '<link rel="stylesheet" href="source/css/bootstrap.min.css">';
    echo '<style>body{font-family:"微软雅黑","Microsoft YaHei",sans-serif;background:#eee;margin:2em auto;max-width:760px;} pre{background:#fff;padding:1em;border:1px solid #ddd;white-space:pre-wrap;word-break:break-all;}</style>';
    echo '</head><body><h2>一键升级到 V' . htmlspecialchars(SYSTEM_VER, ENT_QUOTES, 'UTF-8') . '</h2>';
    echo '<p>当前数据库版本：<b>' . htmlspecialchars($dbVersion === '' ? '未知' : $dbVersion, ENT_QUOTES, 'UTF-8') . '</b></p>';
}

function upgrade_page_foot()
{
    echo '</body></html>';
}

/** 升级前自动备份（尽力而为）：与前缀定时备份同一 dataBak 通道，独立于定时任务开关 */
function upgrade_auto_backup()
{
    global $m;
    $dir = SYSTEM_ROOT . '/data/backups';
    if (!is_dir($dir) && !mkdir($dir, 0755, true)) {
        return '创建备份目录失败，已跳过自动备份';
    }
    $tables = array();
    $rs = $m->query("SHOW TABLES FROM `" . DB_NAME . "` LIKE '" . str_replace('_', '\\_', DB_PREFIX) . "%'");
    while ($row = $m->fetch_row($rs)) {
        $tables[] = $row[0];
    }
    if (empty($tables)) {
        return '未找到数据表，已跳过自动备份';
    }
    $dump = '#Tieba-Cloud-Sign Pre-Upgrade Backup' . "\n#Version:" . SYSTEM_VER . "\n#Date:" . date('Y-m-d H:i:s') . "\n";
    foreach ($tables as $table) {
        $dump .= dataBak($table);
    }
    $file = $dir . '/pre-upgrade_' . date('Y-m-d_His') . '.sql';
    if (file_put_contents($file, $dump) === false) {
        return '备份写入失败，已跳过自动备份';
    }
    //自动备份额外保留最近 3 份，不占用定时备份的保留额度
    $files = glob($dir . '/pre-upgrade_*.sql');
    if (is_array($files) && count($files) > 3) {
        sort($files);
        foreach (array_slice($files, 0, count($files) - 3) as $old) {
            @unlink($old);
        }
    }
    return '已自动备份：' . basename($file) . '（' . count($tables) . ' 张表，' . round(strlen($dump) / 1024, 1) . ' KB）';
}

$dbVersion = (string) option::get('core_version');
upgrade_page_head($dbVersion);

if ($dbVersion === SYSTEM_VER) {
    if (function_exists('opcache_reset')) {
        @opcache_reset(); //文件刚被更新器覆盖时，确保加载的是新代码
    }
    echo '<div class="alert alert-success">数据库已经是 V' . htmlspecialchars(SYSTEM_VER, ENT_QUOTES, 'UTF-8') . '，无需升级。<a href="index.php">进入首页</a></div>';
    upgrade_page_foot();
    exit;
}

$isRun = ($_SERVER['REQUEST_METHOD'] === 'POST');

if (!$isRun) {
    $autostart = isset($_GET['autostart']) && $_GET['autostart'] === '1';
    $action = 'upgrade.php' . ($autostart ? '?autostart=1' : '');
    echo '<div class="alert alert-warning">即将自动完成：<b>备份数据库 → 运行迁移器 → 清理缓存</b>。迁移具备防并发锁与失败记录，失败后修复问题重新执行即可。</div>';
    echo '<form id="upgrade-go" method="post" action="' . htmlspecialchars($action, ENT_QUOTES, 'UTF-8') . '">';
    echo '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') . '">';
    echo '<button type="submit" class="btn btn-primary btn-lg">开始升级到 V' . htmlspecialchars(SYSTEM_VER, ENT_QUOTES, 'UTF-8') . '</button></form>';
    if ($autostart) {
        echo '<script>document.getElementById("upgrade-go").submit();</script>';
    }
    upgrade_page_foot();
    exit;
}

csrf(true, true);
echo '<div class="alert alert-info">正在升级，请勿关闭页面……</div><pre>';
flush();
$backupMsg = upgrade_auto_backup();
echo htmlspecialchars($backupMsg, ENT_QUOTES, 'UTF-8') . PHP_EOL;
@ob_flush();
flush();
try {
    Migrator::runUp(function ($message) {
        echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8') . PHP_EOL;
        if (ob_get_level() > 0) {
            @ob_flush();
        }
        flush();
    });
    if (function_exists('opcache_reset')) {
        @opcache_reset(); //迁移结束即重置字节码缓存，后续请求全部加载新代码
        echo 'opcache 已重置。' . PHP_EOL;
    }
    echo '</pre><div class="alert alert-success"><b>升级完成</b>，已同步 core_version=V' . htmlspecialchars(SYSTEM_VER, ENT_QUOTES, 'UTF-8') . '。'
        . '<a class="btn btn-success" href="index.php">进入 V' . htmlspecialchars(SYSTEM_VER, ENT_QUOTES, 'UTF-8') . '</a><br/>'
        . '<span class="text-muted">若你的部署使用了 do.php --loop 常驻进程，请重启该进程以加载新代码；普通计划任务部署无需任何操作。</span></div>';
} catch (Throwable $e) {
    echo '</pre><div class="alert alert-danger">升级失败：' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') . '<br/>失败状态已记录到 schema_migrations，修复问题后刷新本页可继续；也可以在服务器上运行 <code>php tools/migrate.php up</code>。</div>';
}
upgrade_page_foot();
