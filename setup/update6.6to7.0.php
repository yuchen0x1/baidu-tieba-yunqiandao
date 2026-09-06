<?php
/**
 * V6.6 → V7.0 统一升级入口（Web）
 * 由 lib/reg.php 的版本门禁在 core_version=6.6、程序版本=7.0 时引导访问。
 * 实际迁移逻辑与 CLI（php tools/migrate.php up）完全一致：同一迁移器、同一状态记录。
 * 需以管理员身份登录 6.6 旧版本后访问；升级前请先备份数据库。
 */
if (empty($_COOKIE['uid']) || empty($_COOKIE['pwd'])) {
    http_response_code(302);
    header('Location: ../index.php', true, 302);
    die;
}
define('SYSTEM_DEV', true);
define('SYSTEM_NO_CHECK_VER', true);
require dirname(__DIR__) . '/init.php';
if (ROLE != 'admin') {
    msg('您需要先登录旧版本的云签到管理员账号，才能继续升级', '../index.php');
}

$dbVersion = (string) option::get('core_version');
header('content-type:text/html; charset=utf-8');
echo '<!DOCTYPE html><html lang="zh-CN"><head><meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />';
echo '<meta name="viewport" content="width=device-width, initial-scale=1.0" />';
echo '<title>升级到 V' . SYSTEM_VER . ' - ' . SYSTEM_FN . '</title>';
echo '<link rel="stylesheet" href="../source/css/bootstrap.min.css">';
echo '<style>body{font-family:"微软雅黑","Microsoft YaHei",sans-serif;background:#eee;margin:2em auto;max-width:760px;} pre{background:#fff;padding:1em;border:1px solid #ddd;white-space:pre-wrap;word-break:break-all;}</style>';
echo '</head><body><h2>升级到 V' . htmlspecialchars(SYSTEM_VER, ENT_QUOTES, 'UTF-8') . '</h2>';
echo '<p>当前数据库版本：<b>' . htmlspecialchars($dbVersion === '' ? '未知' : $dbVersion, ENT_QUOTES, 'UTF-8') . '</b></p>';

if ($dbVersion === SYSTEM_VER) {
    echo '<div class="alert alert-success">数据库已经是 V' . htmlspecialchars(SYSTEM_VER, ENT_QUOTES, 'UTF-8') . '，无需升级。<a href="../index.php">返回首页</a></div></body></html>';
    exit;
}

echo '<div class="alert alert-warning">升级前请确认已完成数据库备份。迁移过程具备防并发锁与失败记录，失败后修复问题即可重新执行。</div><pre>';
try {
    Migrator::runUp(function ($message) {
        echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8') . PHP_EOL;
        if (ob_get_level() > 0) {
            @ob_flush();
        }
        flush();
    });
    echo '</pre><div class="alert alert-success">升级成功，已同步 core_version=' . htmlspecialchars(SYSTEM_VER, ENT_QUOTES, 'UTF-8') . '。<a href="../index.php">进入 V' . htmlspecialchars(SYSTEM_VER, ENT_QUOTES, 'UTF-8') . '</a></div>';
} catch (Throwable $e) {
    echo '</pre><div class="alert alert-danger">升级失败：' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') . '<br/>失败状态已记录到 schema_migrations，修复问题后刷新本页可继续；也可以在服务器上运行 <code>php tools/migrate.php up</code>。</div>';
}
echo '</body></html>';
