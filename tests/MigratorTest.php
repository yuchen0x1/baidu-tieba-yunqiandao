<?php

use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__) . '/lib/class.migrator.php';

/**
 * 统一迁移器单元测试（V7.0）
 * 通过 FakeMigrateDB 模拟数据库，覆盖：建表升级、legacy 记录兼容、锁冲突、
 * running/success/failed 状态流转、失败重试、checksum 漂移阻断、core_version 同步门禁
 * 与 007000 基线迁移的校验/幂等行为。不连接真实数据库。
 */
final class FakeMigrateDB
{
    /** 表名（小写）=> 列名列表 */
    public $tables = array();
    /** schema_migrations 行：version => row */
    public $migrationRows = array();
    /** options 表内存数据 */
    public $options = array('core_version' => '6.6');
    /** GET_LOCK 是否可用（false 模拟并发占用） */
    public $lockAvailable = true;
    /** 当前连接是否持有迁移锁 */
    public $lockHeld = false;
    /** 查询记录（便于断言） */
    public $statements = array();

    private $selectBuffer = array();
    private $lockRow = null;

    public function real_escape_string($s)
    {
        return str_replace(array("'", "\\"), array("''", "\\\\"), (string) $s);
    }

    public function query($sql)
    {
        $this->statements[] = $sql;
        $sql = (string) $sql;
        if (preg_match('/CREATE TABLE (?:IF NOT EXISTS )?`([A-Za-z0-9_]+)`/i', $sql, $m)) {
            $this->tables[strtolower($m[1])] = $this->parseCreateColumns($sql);
            return true;
        }
        if (preg_match('/ALTER TABLE `([A-Za-z0-9_]+)` ADD COLUMN `([A-Za-z0-9_]+)`/i', $sql, $m)) {
            $table = strtolower($m[1]);
            if (!isset($this->tables[$table])) {
                $this->tables[$table] = array();
            }
            $this->tables[$table][] = strtolower($m[2]);
            return true;
        }
        if (preg_match('/SELECT \* FROM `([A-Za-z0-9_]+)`/i', $sql, $m) && strpos($m[1], 'schema_migrations') !== false) {
            $this->selectBuffer = array_values($this->migrationRows);
            return 'migrations';
        }
        return true; // UPDATE / INSERT IGNORE 等无结果集语句
    }

    public function once_fetch_array($sql)
    {
        $this->statements[] = $sql;
        $sql = (string) $sql;
        if (strpos($sql, 'information_schema.columns') !== false) {
            if (preg_match("/table_name = '([^']+)'\s+AND\s+column_name = '([^']+)'/i", $sql, $m)) {
                $exists = isset($this->tables[strtolower($m[1])]) && in_array(strtolower($m[2]), $this->tables[strtolower($m[1])], true);
                return array('c' => $exists ? 1 : 0);
            }
            return array('c' => 0);
        }
        if (strpos($sql, 'information_schema.tables') !== false) {
            if (preg_match("/table_name = '([^']+)'/i", $sql, $m)) {
                return array('c' => isset($this->tables[strtolower($m[1])]) ? 1 : 0);
            }
            return array('c' => 0);
        }
        if (preg_match("/`name` = 'core_version'/i", $sql)) {
            return array('value' => isset($this->options['core_version']) ? $this->options['core_version'] : null);
        }
        if (preg_match("/SELECT COUNT\(\*\) AS c FROM `([A-Za-z0-9_]+)` WHERE `name` = '([^']+)'/i", $sql, $m)) {
            if (strpos($m[1], 'options') !== false) {
                return array('c' => isset($this->options[$m[2]]) ? 1 : 0);
            }
            return array('c' => 0);
        }
        return null;
    }

    public function pquery($sql, $params = array())
    {
        $this->statements[] = $sql;
        $sql = (string) $sql;
        if (strpos($sql, 'GET_LOCK') !== false) {
            if (!$this->lockAvailable || $this->lockHeld) {
                $this->lockRow = array('l' => 0);
            } else {
                $this->lockHeld = true;
                $this->lockRow = array('l' => 1);
            }
            return 'lock';
        }
        if (strpos($sql, 'RELEASE_LOCK') !== false) {
            $this->lockHeld = false;
            return true;
        }
        if (strpos($sql, 'schema_migrations') !== false) {
            if (strpos(ltrim($sql), 'INSERT') === 0) {
                // markRunning：version, applied_at, description, checksum, status, started_at, batch
                $version = (string) $params[0];
                $this->migrationRows[$version] = array(
                    'version' => $version,
                    'applied_at' => (int) $params[1],
                    'description' => (string) $params[2],
                    'checksum' => (string) $params[3],
                    'status' => (string) $params[4],
                    'started_at' => (int) $params[5],
                    'finished_at' => 0,
                    'batch_id' => (int) $params[6],
                    'error_message' => '',
                );
                return true;
            }
            if (strpos(ltrim($sql), 'UPDATE') === 0) {
                // markSuccess/markFailed：status, finished_at, applied_at|error_message, version
                $version = (string) $params[3];
                if (isset($this->migrationRows[$version])) {
                    $this->migrationRows[$version]['status'] = (string) $params[0];
                    $this->migrationRows[$version]['finished_at'] = (int) $params[1];
                    if ($params[0] === Migrator::STATUS_SUCCESS) {
                        $this->migrationRows[$version]['applied_at'] = (int) $params[2];
                        $this->migrationRows[$version]['error_message'] = '';
                    } else {
                        $this->migrationRows[$version]['error_message'] = (string) $params[2];
                    }
                }
                return true;
            }
            if (strpos(ltrim($sql), 'DELETE') === 0) {
                unset($this->migrationRows[(string) $params[0]]);
                return true;
            }
        }
        if (preg_match("/VALUES \('core_version', \?\)/", $sql)) {
            $this->options['core_version'] = (string) $params[0];
            return true;
        }
        if (preg_match('/INSERT IGNORE INTO `([A-Za-z0-9_]+)`/i', $sql) && strpos($sql, 'options') !== false) {
            $name = (string) $params[0];
            if (!isset($this->options[$name])) {
                $this->options[$name] = (string) $params[1];
            }
            return true;
        }
        return true;
    }

    public function fetch_array($rs)
    {
        if ($rs === 'migrations') {
            return array_shift($this->selectBuffer);
        }
        if ($rs === 'lock') {
            return $this->lockRow;
        }
        return false;
    }

    private function parseCreateColumns($sql)
    {
        $columns = array();
        if (preg_match_all('/^\s*`([A-Za-z0-9_]+)`\s+[A-Za-z]/m', (string) $sql, $m)) {
            foreach ($m[1] as $column) {
                $columns[] = strtolower($column);
            }
        }
        return $columns;
    }
}

final class MigratorTest extends TestCase
{
    /** @var FakeMigrateDB */
    private $db;
    /** @var string 临时迁移目录 */
    private $migrationDir;

    protected function setUp(): void
    {
        if (!defined('DB_NAME')) {
            define('DB_NAME', 'test_db');
        }
        if (!defined('DB_PREFIX')) {
            define('DB_PREFIX', 'test_');
        }
        if (!defined('SYSTEM_VER')) {
            define('SYSTEM_VER', '7.0');
        }
        $this->db = new FakeMigrateDB();
        $GLOBALS['m'] = $this->db;
        $this->migrationDir = sys_get_temp_dir() . '/tcs-migrations-' . getmypid() . '-' . mt_rand(1000, 9999);
        mkdir($this->migrationDir, 0777, true);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->migrationDir . '/*') ?: array() as $file) {
            @unlink($file);
        }
        @rmdir($this->migrationDir);
    }

    private function writeMigration($version, $body)
    {
        $file = $this->migrationDir . '/' . $version . '_test.php';
        $code = "<?php\nreturn array(\n    'version' => '" . $version . "',\n    'desc' => '测试迁移 " . $version . "',\n" . $body . "\n);\n";
        file_put_contents($file, $code);
        return $file;
    }

    // ---------- ensureTable ----------

    public function testEnsureTableCreatesFullSchema(): void
    {
        Migrator::ensureTable();
        $columns = $this->db->tables['test_schema_migrations'];
        foreach (array('version', 'applied_at', 'description', 'checksum', 'status', 'started_at', 'finished_at', 'batch_id', 'error_message') as $column) {
            $this->assertContains($column, $columns);
        }
    }

    public function testLegacyTableIsUpgradedInPlace(): void
    {
        $this->db->tables['test_schema_migrations'] = array('version', 'applied_at');
        Migrator::ensureTable();
        foreach (array('checksum', 'status', 'batch_id', 'error_message') as $column) {
            $this->assertContains($column, $this->db->tables['test_schema_migrations']);
        }
    }

    // ---------- runUp：正常路径 ----------

    public function testRunUpAppliesPendingAndSyncsCoreVersion(): void
    {
        $this->writeMigration('002000', "    'up' => function (\$m, \$prefix) { \$m->query(\"UPDATE \`{\$prefix}options\` SET \`value\` = '1' WHERE \`name\` = 'flag'\"); },");
        $applied = Migrator::runUp(null, $this->migrationDir);
        $this->assertSame(array('002000'), $applied);
        $this->assertSame(Migrator::STATUS_SUCCESS, $this->db->migrationRows['002000']['status']);
        $this->assertNotSame('', $this->db->migrationRows['002000']['checksum']);
        $this->assertSame('7.0', $this->db->options['core_version']);
        $this->assertFalse($this->db->lockHeld, '迁移结束后应释放迁移锁');
    }

    public function testRunUpIsIdempotentOnSecondCall(): void
    {
        $this->writeMigration('002000', "    'up' => function (\$m, \$prefix) { },");
        Migrator::runUp(null, $this->migrationDir);
        $applied = Migrator::runUp(null, $this->migrationDir);
        $this->assertSame(array(), $applied);
        $this->assertSame('7.0', $this->db->options['core_version']);
    }

    public function testLegacyRowIsTreatedAsAppliedWithoutRerunning(): void
    {
        $marker = $this->migrationDir . '/marker.txt';
        $this->writeMigration('002000', "    'up' => function (\$m, \$prefix) { file_put_contents(" . var_export($marker, true) . ", 'ran'); },");
        // 旧版迁移器只写 version + applied_at，无 status/checksum
        $this->db->migrationRows['002000'] = array('version' => '002000', 'applied_at' => 12345);
        $applied = Migrator::runUp(null, $this->migrationDir);
        $this->assertSame(array(), $applied);
        $this->assertFileDoesNotExist($marker);
        $this->assertSame('7.0', $this->db->options['core_version']);
    }

    // ---------- runUp：失败路径 ----------

    public function testFailedMigrationRecordsFailedStateAndKeepsCoreVersion(): void
    {
        $this->writeMigration('002000', "    'up' => function (\$m, \$prefix) { throw new RuntimeException('boom'); },");
        try {
            Migrator::runUp(null, $this->migrationDir);
            $this->fail('迁移失败应抛出异常');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('boom', $e->getMessage());
        }
        $this->assertSame(Migrator::STATUS_FAILED, $this->db->migrationRows['002000']['status']);
        $this->assertStringContainsString('boom', (string) $this->db->migrationRows['002000']['error_message']);
        $this->assertSame('6.6', $this->db->options['core_version'], '迁移失败时绝不能更新 core_version');
        $this->assertFalse($this->db->lockHeld, '失败后也应释放迁移锁');
    }

    public function testFailedMigrationCanBeRetried(): void
    {
        $this->writeMigration('002000', "    'up' => function (\$m, \$prefix) { throw new RuntimeException('boom'); },");
        try {
            Migrator::runUp(null, $this->migrationDir);
            $this->fail('迁移失败应抛出异常');
        } catch (RuntimeException $e) {
            // 首次失败
        }
        // 修复后重试（同一版本号，内容不同 → checksum 以最新运行为准）
        $this->writeMigration('002000', "    'up' => function (\$m, \$prefix) { },");
        $applied = Migrator::runUp(null, $this->migrationDir);
        $this->assertSame(array('002000'), $applied);
        $this->assertSame(Migrator::STATUS_SUCCESS, $this->db->migrationRows['002000']['status']);
        $this->assertSame('7.0', $this->db->options['core_version']);
    }

    public function testChecksumDriftOnAppliedMigrationBlocksRun(): void
    {
        $this->writeMigration('002000', "    'up' => function (\$m, \$prefix) { },");
        Migrator::runUp(null, $this->migrationDir);
        // 修改已应用迁移的内容（模拟改写历史迁移）
        $this->writeMigration('002000', "    'up' => function (\$m, \$prefix) { \$m->query('UPDATE 1'); },");
        try {
            Migrator::runUp(null, $this->migrationDir);
            $this->fail('checksum 漂移应阻断执行');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('checksum', $e->getMessage());
        }
    }

    public function testLockUnavailableAbortsBeforeAnyMigration(): void
    {
        $this->db->lockAvailable = false;
        $this->writeMigration('002000', "    'up' => function (\$m, \$prefix) { },");
        try {
            Migrator::runUp(null, $this->migrationDir);
            $this->fail('锁不可用应抛出异常');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('迁移锁', $e->getMessage());
        }
        $this->assertSame(array(), $this->db->migrationRows, '未取得锁时不得写入任何迁移记录');
        $this->assertSame('6.6', $this->db->options['core_version']);
    }

    // ---------- 版本门禁 ----------

    public function testPrecheckBlocksVersionsBelow66(): void
    {
        $this->db->options['core_version'] = '5.13';
        $this->writeMigration('002000', "    'up' => function (\$m, \$prefix) { },");
        try {
            Migrator::runUp(null, $this->migrationDir);
            $this->fail('低于 6.6 的版本应被阻断');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('6.6', $e->getMessage());
        }
        $this->assertSame('5.13', $this->db->options['core_version']);
    }

    public function testPrecheckAllowsIdempotentRunAtCurrentVersion(): void
    {
        $this->db->options['core_version'] = '7.0';
        $this->writeMigration('002000', "    'up' => function (\$m, \$prefix) { },");
        $applied = Migrator::runUp(null, $this->migrationDir);
        $this->assertSame(array('002000'), $applied);
        $this->assertSame('7.0', $this->db->options['core_version']);
    }

    // ---------- down ----------

    public function testRunDownRemovesRecordsWithoutTouchingCoreVersion(): void
    {
        $this->writeMigration('002000', "    'up' => function (\$m, \$prefix) { },\n    'down' => function (\$m, \$prefix) { },");
        Migrator::runUp(null, $this->migrationDir);
        Migrator::runDown('002000', null, $this->migrationDir);
        $this->assertArrayNotHasKey('002000', $this->db->migrationRows);
        $this->assertSame('7.0', $this->db->options['core_version'], 'down 不负责回退 core_version');
    }

    // ---------- 007000 基线迁移 ----------

    private function baselineDb()
    {
        $this->db->tables['test_baiduid'] = array('id');
        $this->db->tables['test_cron'] = array('name', 'no', 'enable');
        $this->db->tables['test_options'] = array('name', 'value');
        $this->db->tables['test_plugins'] = array('name');
        $this->db->tables['test_tieba'] = array('id');
        $this->db->tables['test_users'] = array('id', 'api_token_hash');
        $this->db->tables['test_users_options'] = array('id');
        $this->db->tables['test_baiduid_health'] = array('pid');
        $this->db->tables['test_cron_log_batch'] = array('id');
        $this->db->tables['test_sign_history'] = array('id');
    }

    public function testV70BaselineRejectsMissingCoreTables(): void
    {
        $mod = include dirname(__DIR__) . '/setup/migrations/007000_v70_foundation.php';
        $this->baselineDb();
        unset($this->db->tables['test_sign_history']);
        try {
            call_user_func($mod['up'], $this->db, 'test_');
            $this->fail('缺少基线核心表应失败');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('基线核心表', $e->getMessage());
        }
    }

    public function testV70BaselineRejectsMissingApiTokenHash(): void
    {
        $mod = include dirname(__DIR__) . '/setup/migrations/007000_v70_foundation.php';
        $this->baselineDb();
        $this->db->tables['test_users'] = array('id');
        try {
            call_user_func($mod['up'], $this->db, 'test_');
            $this->fail('缺少 users.api_token_hash 应失败');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('api_token_hash', $e->getMessage());
        }
    }

    public function testV70BaselineAppliesStructuresIdempotently(): void
    {
        $mod = include dirname(__DIR__) . '/setup/migrations/007000_v70_foundation.php';
        $this->baselineDb();
        call_user_func($mod['up'], $this->db, 'test_');
        $this->assertContains('cron_max_retry', array_keys($this->db->options));
        $this->assertContains('cleanup_enable', array_keys($this->db->options));
        // 幂等：重复执行不再报错，选项值不被覆盖
        $this->db->options['cron_max_retry'] = '9';
        call_user_func($mod['up'], $this->db, 'test_');
        $this->assertSame('9', $this->db->options['cron_max_retry']);
    }
}
