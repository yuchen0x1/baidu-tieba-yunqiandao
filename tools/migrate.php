<?php
/**
 * 数据库迁移器 CLI 入口（V7.0 起）
 * 状态模型、迁移锁、checksum 校验与 core_version 同步逻辑统一在 lib/class.migrator.php。
 *
 * 迁移文件规范：setup/migrations/MMMNNN_description.php
 *   return array('version' => 'MMMNNN', 'desc' => '说明', 'up' => function($m, $prefix){...}, 'down' => function($m, $prefix){...});
 *   - version 建议 6 位：前 3 位主版本，后 3 位序号
 *   - up/down 内 SQL 必须幂等（可重复执行）
 *
 * 用法（命令行）：
 *   php tools/migrate.php status    查看已应用 / 待应用迁移
 *   php tools/migrate.php up        执行所有未应用的迁移（要求 core_version >= 6.6）
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

$cmd = isset($argv[1]) ? strtolower($argv[1]) : 'status';

switch ($cmd) {
    case 'up':
        try {
            $applied = Migrator::runUp(function ($message) {
                echo $message . PHP_EOL;
            });
            if (empty($applied)) {
                echo '无待应用迁移' . PHP_EOL;
            }
        } catch (Throwable $e) {
            fwrite(STDERR, '迁移失败：' . $e->getMessage() . PHP_EOL);
            fwrite(STDERR, '失败状态已记录到 schema_migrations，修复问题后可重新执行本命令继续。' . PHP_EOL);
            exit(1);
        }
        break;

    case 'down':
        if (empty($argv[2])) {
            fwrite(STDERR, '用法：php tools/migrate.php down <version>' . PHP_EOL);
            exit(1);
        }
        try {
            Migrator::runDown((string) $argv[2], function ($message) {
                echo $message . PHP_EOL;
            });
        } catch (Throwable $e) {
            fwrite(STDERR, '回滚失败：' . $e->getMessage() . PHP_EOL);
            exit(1);
        }
        break;

    case 'status':
    default:
        Migrator::ensureTable();
        $all = Migrator::scan();
        $records = Migrator::records();
        echo '=== 数据库迁移状态 ===' . PHP_EOL;
        if (empty($all)) {
            echo '没有迁移文件（' . Migrator::MIGRATION_DIR . ' 为空）' . PHP_EOL;
        }
        foreach ($all as $version => $mod) {
            if (!isset($records[$version])) {
                $state = '待应用';
            } elseif ($records[$version]['status'] === Migrator::STATUS_SUCCESS) {
                $state = '已应用';
            } elseif ($records[$version]['status'] === Migrator::STATUS_FAILED) {
                $state = '上次失败（可重试）';
            } else {
                $state = '中断（running，可重试）';
            }
            echo '[' . $state . '] ' . $version . '  ' . $mod['desc'] . PHP_EOL;
        }
        try {
            echo '=== 数据库 core_version：' . Migrator::precheck() . ' ===' . PHP_EOL;
        } catch (Throwable $e) {
            echo '=== 版本校验：' . $e->getMessage() . ' ===' . PHP_EOL;
        }
        echo '=== 当前程序版本：' . SYSTEM_VER . ' ===' . PHP_EOL;
        break;
}
