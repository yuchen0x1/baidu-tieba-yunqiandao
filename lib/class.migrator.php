<?php

if (!defined('SYSTEM_ROOT')) {
    die('Insufficient Permissions');
}

/**
 * 统一数据库迁移器（V7.0 起）
 *
 * 迁移文件规范见 tools/migrate.php 头注释；本类负责状态模型、锁与校验：
 * - schema_migrations 记录每个迁移的 checksum 与状态（running/success/failed），旧记录按 legacy 成功兼容；
 * - 执行前通过 GET_LOCK 取得库级迁移锁，防止 Web/CLI/容器并发迁移；
 * - 任一迁移失败时写入 failed 与错误信息并中止，绝不更新 options.core_version；
 * - 已应用迁移的 checksum 发生变化时阻断执行，防止历史迁移被静默改写；
 * - core_version 仅在全部迁移成功且版本前置条件（6.6 或已是当前版本）满足后同步。
 */
class Migrator
{
    const MIGRATION_TABLE = 'schema_migrations';
    const MIGRATION_DIR = 'setup/migrations';
    const MIN_UPGRADE_VERSION = '6.6';

    const STATUS_SUCCESS = 'success';
    const STATUS_RUNNING = 'running';
    const STATUS_FAILED = 'failed';

    /** @var string|null 本次运行的锁名（释放时使用） */
    private static $lockName = null;

    /**
     * 带前缀的迁移记录表名（已转义，可直接拼入 SQL）
     */
    public static function tableName()
    {
        global $m;
        return '`' . $m->real_escape_string(DB_PREFIX . self::MIGRATION_TABLE) . '`';
    }

    /**
     * 确保迁移表存在且具备完整状态列（幂等，兼容仅含 version/applied_at 的旧表）
     */
    public static function ensureTable()
    {
        global $m;
        $table = self::tableName();
        $m->query("CREATE TABLE IF NOT EXISTS {$table} (
            `version` VARCHAR(64) NOT NULL DEFAULT '',
            `applied_at` INT UNSIGNED NOT NULL DEFAULT 0,
            `description` VARCHAR(255) NOT NULL DEFAULT '',
            `checksum` CHAR(64) NOT NULL DEFAULT '',
            `status` VARCHAR(20) NOT NULL DEFAULT '',
            `started_at` INT UNSIGNED NOT NULL DEFAULT 0,
            `finished_at` INT UNSIGNED NOT NULL DEFAULT 0,
            `batch_id` INT UNSIGNED NOT NULL DEFAULT 0,
            `error_message` TEXT,
            PRIMARY KEY (`version`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

        $columns = array(
            'description'   => "VARCHAR(255) NOT NULL DEFAULT ''",
            'checksum'      => "CHAR(64) NOT NULL DEFAULT ''",
            'status'        => "VARCHAR(20) NOT NULL DEFAULT ''",
            'started_at'    => 'INT UNSIGNED NOT NULL DEFAULT 0',
            'finished_at'   => 'INT UNSIGNED NOT NULL DEFAULT 0',
            'batch_id'      => 'INT UNSIGNED NOT NULL DEFAULT 0',
            'error_message' => 'TEXT',
        );
        foreach ($columns as $column => $definition) {
            if (!self::columnExists(self::MIGRATION_TABLE, $column)) {
                $m->query("ALTER TABLE {$table} ADD COLUMN `{$column}` {$definition}");
            }
        }
    }

    /**
     * 扫描迁移目录，按版本号排序
     * @param string|null $dir 迁移目录，默认 setup/migrations（可注入便于测试）
     * @return array version => array(version, desc, up, down?, checksum, file)
     */
    public static function scan($dir = null)
    {
        $dir = $dir === null ? SYSTEM_ROOT . '/' . self::MIGRATION_DIR : $dir;
        $files = glob($dir . '/*.php');
        $list = array();
        if ($files) {
            foreach ($files as $file) {
                $mod = include $file;
                if (!is_array($mod) || !isset($mod['version'], $mod['up']) || !is_callable($mod['up'])) {
                    fwrite(STDERR, '警告：跳过无效迁移文件 ' . basename($file) . PHP_EOL);
                    continue;
                }
                $mod['checksum'] = hash_file('sha256', $file);
                $mod['file'] = $file;
                $list[$mod['version']] = $mod;
            }
        }
        ksort($list);
        return $list;
    }

    /**
     * 读取迁移记录：version => 行（status 为空视为 legacy 成功）
     */
    public static function records()
    {
        global $m;
        $records = array();
        $rs = $m->query("SELECT * FROM " . self::tableName());
        while ($r = $m->fetch_array($rs)) {
            if (!isset($r['status']) || (string) $r['status'] === '') {
                $r['status'] = self::STATUS_SUCCESS;
                $r['checksum'] = isset($r['checksum']) ? (string) $r['checksum'] : '';
            }
            $records[$r['version']] = $r;
        }
        return $records;
    }

    /**
     * 迁移前置校验：仅允许 6.6 升级到当前版本，或已在当前版本时幂等执行。
     * @return string 当前 core_version
     * @throws RuntimeException 版本不满足时
     */
    public static function precheck()
    {
        global $m;
        $row = $m->once_fetch_array("SELECT `value` FROM `" . $m->real_escape_string(DB_PREFIX) . "options` WHERE `name` = 'core_version' LIMIT 1");
        $current = isset($row['value']) ? trim((string) $row['value']) : '';
        if ($current === SYSTEM_VER) {
            return $current;
        }
        if (version_compare($current, self::MIN_UPGRADE_VERSION, '<') || version_compare($current, SYSTEM_VER, '>')) {
            throw new RuntimeException(
                '当前数据库版本为 ' . ($current === '' ? '未知' : $current)
                . '，' . self::MIN_UPGRADE_VERSION . ' 以下的版本请先通过 setup/update.php 逐步升级到 ' . self::MIN_UPGRADE_VERSION . '，再执行 ' . SYSTEM_VER . ' 迁移'
            );
        }
        return $current;
    }

    /**
     * 取得迁移锁（连接级 GET_LOCK，进程结束自动释放，另在 finally 中显式释放）
     */
    public static function acquireLock($timeoutSeconds = 10)
    {
        global $m;
        self::$lockName = 'tc_migrate_' . DB_NAME . '_' . DB_PREFIX;
        $rs = $m->pquery('SELECT GET_LOCK(?, ?) AS l', array(self::$lockName, (int) $timeoutSeconds));
        $row = $rs ? $m->fetch_array($rs) : false;
        if (!$row || empty($row['l'])) {
            self::$lockName = null;
            return false;
        }
        return true;
    }

    /**
     * 释放迁移锁（未持锁时为安全空操作）
     */
    public static function releaseLock()
    {
        global $m;
        if (self::$lockName === null) {
            return;
        }
        $m->pquery('SELECT RELEASE_LOCK(?)', array(self::$lockName));
        self::$lockName = null;
    }

    /**
     * 执行全部待应用迁移；返回应用的版本列表。
     * 失败时记录 failed 状态并抛出异常；全部成功后同步 core_version。
     * @param callable|null $notify 每步回调 function(string $message)
     * @param string|null $migrationDir 迁移目录，默认 setup/migrations
     * @return array 已应用版本
     */
    public static function runUp($notify = null, $migrationDir = null)
    {
        global $m;
        self::ensureTable();
        $currentVersion = self::precheck();
        if (!self::acquireLock()) {
            throw new RuntimeException('无法取得数据库迁移锁，可能已有另一个迁移进程在运行，请稍后重试');
        }
        $appliedVersions = array();
        try {
            $all = self::scan($migrationDir);
            $records = self::records();
            $batch = time();
            foreach ($all as $version => $mod) {
                if (isset($records[$version]) && $records[$version]['status'] === self::STATUS_SUCCESS) {
                    $recorded = (string) $records[$version]['checksum'];
                    if ($recorded !== '' && $recorded !== $mod['checksum']) {
                        throw new RuntimeException('迁移 ' . $version . ' 的文件内容与执行时不一致（checksum 漂移），请恢复原文件或联系开发者');
                    }
                    continue;
                }
                if ($notify) {
                    call_user_func($notify, '==> 应用迁移 ' . $version . '：' . $mod['desc']);
                }
                self::markRunning($version, $mod, $batch);
                try {
                    call_user_func($mod['up'], $m, DB_PREFIX);
                } catch (Throwable $e) {
                    self::markFailed($version, $e->getMessage());
                    throw new RuntimeException('迁移 ' . $version . ' 执行失败：' . $e->getMessage(), 0, $e);
                }
                self::markSuccess($version, $mod);
                $appliedVersions[] = $version;
                if ($notify) {
                    call_user_func($notify, '    完成');
                }
            }
            // 全部成功后才同步版本标记
            if ($currentVersion !== SYSTEM_VER) {
                self::syncCoreVersion();
                if ($notify) {
                    call_user_func($notify, '已同步 core_version=' . SYSTEM_VER);
                }
            }
            return $appliedVersions;
        } finally {
            self::releaseLock();
        }
    }

    /**
     * 按倒序回滚到指定版本之前（含该版本）；不修改 core_version。
     */
    public static function runDown($target, $notify = null, $migrationDir = null)
    {
        global $m;
        self::ensureTable();
        $all = self::scan($migrationDir);
        $records = self::records();
        foreach (array_reverse($all, true) as $version => $mod) {
            if (!isset($records[$version]) || $records[$version]['status'] !== self::STATUS_SUCCESS) {
                continue;
            }
            if ($notify) {
                call_user_func($notify, '==> 回滚 ' . $version . '：' . $mod['desc']);
            }
            if (isset($mod['down']) && is_callable($mod['down'])) {
                call_user_func($mod['down'], $m, DB_PREFIX);
            }
            $m->pquery('DELETE FROM ' . self::tableName() . ' WHERE `version` = ?', array($version));
            if ($notify) {
                call_user_func($notify, '    已回滚');
            }
            if (version_compare($version, $target, '<=')) {
                break;
            }
        }
    }

    private static function markRunning($version, $mod, $batch)
    {
        global $m;
        $m->pquery(
            'INSERT INTO ' . self::tableName() . ' (`version`, `applied_at`, `description`, `checksum`, `status`, `started_at`, `finished_at`, `batch_id`, `error_message`)
             VALUES (?, ?, ?, ?, ?, ?, 0, ?, \'\')
             ON DUPLICATE KEY UPDATE `checksum` = VALUES(`checksum`), `status` = VALUES(`status`), `started_at` = VALUES(`started_at`), `finished_at` = 0, `batch_id` = VALUES(`batch_id`), `error_message` = \'\'',
            array((string) $version, time(), mb_substr((string) $mod['desc'], 0, 255), $mod['checksum'], self::STATUS_RUNNING, time(), (int) $batch)
        );
    }

    private static function markSuccess($version, $mod)
    {
        global $m;
        $m->pquery(
            'UPDATE ' . self::tableName() . ' SET `status` = ?, `finished_at` = ?, `applied_at` = ?, `error_message` = \'\' WHERE `version` = ?',
            array(self::STATUS_SUCCESS, time(), time(), (string) $version)
        );
    }

    private static function markFailed($version, $message)
    {
        global $m;
        $m->pquery(
            'UPDATE ' . self::tableName() . ' SET `status` = ?, `finished_at` = ?, `error_message` = ? WHERE `version` = ?',
            array(self::STATUS_FAILED, time(), mb_substr((string) $message, 0, 1000), (string) $version)
        );
    }

    /**
     * 直接 upsert core_version（迁移器自包含，不依赖 option 类）。
     */
    private static function syncCoreVersion()
    {
        global $m;
        $m->pquery(
            'INSERT INTO `' . $m->real_escape_string(DB_PREFIX) . 'options` (`name`, `value`) VALUES (\'core_version\', ?) ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)',
            array(SYSTEM_VER)
        );
    }

    private static function columnExists($table, $column)
    {
        global $m;
        $t = $m->real_escape_string(DB_PREFIX . $table);
        $c = $m->real_escape_string($column);
        $row = $m->once_fetch_array("SELECT COUNT(*) AS c FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = '{$t}' AND column_name = '{$c}'");
        return !empty($row['c']);
    }
}
