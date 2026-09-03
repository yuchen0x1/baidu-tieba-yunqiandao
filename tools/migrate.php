<?php
/**
 * 数据库迁移器（V5.13 B3）
 * 统一管理数据库结构升级，取代零散的手工 update*.php 脚本链。
 *
 * 迁移文件规范：setup/migrations/MMMNNN_description.php
 *   return array('version' => 'MMMNNN', 'desc' => '说明', 'up' => function($m, $prefix){...}, 'down' => function($m, $prefix){...});
 *   - version 建议 6 位：前 3 位主版本，后 3 位序号
 *   - up/down 内 SQL 必须幂等（可重复执行）
 *
 * 用法（命令行）：
 *   php tools/migrate.php status    查看已应用 / 待应用迁移
 *   php tools/migrate.php up        执行所有未应用的迁移
 *   php tools/migrate.php down 001001 回滚指定版本（按倒序回滚到该版本之前）
 */

if (PHP_SAPI !== 'cli') {
    die('仅支持命令行运行：php tools/migrate.php <status|up|down>');
}

define('SYSTEM_NO_UI', true);
define('SYSTEM_NO_CHECK_LOGIN', true);
define('SYSTEM_NO_CHECK_VER', true);
define('SYSTEM_NO_PLUGIN', true);
define('SYSTEM_DO_NOT_LOGIN', true);
require dirname(__DIR__) . '/init.php';

global $m;

const MIGRATION_TABLE = 'schema_migrations';
const MIGRATION_DIR = 'setup/migrations';

/**
 * 确保 schema_migrations 表存在
 */
function migrate_ensure_table()
{
    global $m;
    $m->query("CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . MIGRATION_TABLE . "` (
        `version` VARCHAR(64) NOT NULL DEFAULT '',
        `applied_at` INT UNSIGNED NOT NULL DEFAULT 0,
        PRIMARY KEY (`version`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
}

/**
 * 扫描迁移文件（按 version 排序）
 */
function migrate_scan()
{
    $dir = SYSTEM_ROOT . '/' . MIGRATION_DIR;
    $files = glob($dir . '/*.php');
    $list = array();
    if ($files) {
        foreach ($files as $file) {
            $mod = include $file;
            if (!is_array($mod) || !isset($mod['version'], $mod['up']) || !is_callable($mod['up'])) {
                fwrite(STDERR, '警告：跳过无效迁移文件 ' . basename($file) . PHP_EOL);
                continue;
            }
            $list[$mod['version']] = $mod;
        }
    }
    ksort($list);
    return $list;
}

/**
 * 读取已应用版本
 */
function migrate_applied()
{
    global $m;
    $applied = array();
    $rs = $m->query("SELECT `version` FROM `" . DB_PREFIX . MIGRATION_TABLE . "`");
    while ($r = $m->fetch_array($rs)) {
        $applied[] = $r['version'];
    }
    return array_flip($applied);
}

$cmd = isset($argv[1]) ? strtolower($argv[1]) : 'status';
migrate_ensure_table();
$all = migrate_scan();
$applied = migrate_applied();

switch ($cmd) {
    case 'up':
        $done = 0;
        foreach ($all as $version => $mod) {
            if (isset($applied[$version])) {
                continue;
            }
            echo '==> 应用迁移 ' . $version . '：' . $mod['desc'] . PHP_EOL;
            try {
                call_user_func($mod['up'], $m, DB_PREFIX);
                $m->pquery("INSERT INTO `" . DB_PREFIX . MIGRATION_TABLE . "` (`version`, `applied_at`) VALUES (?, ?)", array($version, time()));
                echo '    完成' . PHP_EOL;
                $done++;
            } catch (Throwable $e) {
                fwrite(STDERR, '    失败：' . $e->getMessage() . PHP_EOL);
                exit(1);
            }
        }
        if ($done === 0) {
            echo '无待应用迁移' . PHP_EOL;
        }
        // 同步数据库 core_version，避免 init.php 版本校验拦截（reg.php 校验 core_version == SYSTEM_VER）
        if (class_exists('option', false)) {
            option::set('core_version', SYSTEM_VER);
            echo '已同步 core_version=' . SYSTEM_VER . PHP_EOL;
        }
        break;

    case 'down':
        if (empty($argv[2])) {
            fwrite(STDERR, '用法：php tools/migrate.php down <version>' . PHP_EOL);
            exit(1);
        }
        $target = (string) $argv[2];
        // 按倒序回滚到 target 之前（含 target）
        foreach (array_reverse($all, true) as $version => $mod) {
            if (!isset($applied[$version])) {
                continue;
            }
            echo '==> 回滚 ' . $version . '：' . $mod['desc'] . PHP_EOL;
            if (isset($mod['down']) && is_callable($mod['down'])) {
                try {
                    call_user_func($mod['down'], $m, DB_PREFIX);
                } catch (Throwable $e) {
                    fwrite(STDERR, '    失败：' . $e->getMessage() . PHP_EOL);
                    exit(1);
                }
            }
            $m->pquery("DELETE FROM `" . DB_PREFIX . MIGRATION_TABLE . "` WHERE `version` = ?", array($version));
            echo '    已回滚' . PHP_EOL;
            if (version_compare($version, $target, '<=')) {
                break;
            }
        }
        break;

    case 'status':
    default:
        echo '=== 数据库迁移状态 ===' . PHP_EOL;
        if (empty($all)) {
            echo '没有迁移文件（' . MIGRATION_DIR . ' 为空）' . PHP_EOL;
        }
        foreach ($all as $version => $mod) {
            $state = isset($applied[$version]) ? '已应用' : '待应用';
            echo '[' . $state . '] ' . $version . '  ' . $mod['desc'] . PHP_EOL;
        }
        echo '=== 当前系统版本：' . SYSTEM_VER . ' ===' . PHP_EOL;
        break;
}
