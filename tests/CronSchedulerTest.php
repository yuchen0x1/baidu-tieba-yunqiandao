<?php

use PHPUnit\Framework\TestCase;

/**
 * V5.13 调度引擎单元测试
 * 覆盖：isEnabled 判定、防重入锁、失败指数退避重试、一次性任务、runOnce 输入校验与锁冲突。
 * 通过 FakeDB 模拟数据库接口，不连接真实数据库。
 */

final class FakeCronDB
{
    /** 最近一次 pquery 调用 */
    public $lastPquery = array('sql' => '', 'params' => array());
    /** 所有 pquery 调用 */
    public $calls = array();
    /** 锁 UPDATE 的影响行数（可被测试控制） */
    public $lockResult = 1;
    /** 内存中的 cron 表数据：name => row */
    public $cronRows = array();
    /** 最近一次受影响行数 */
    private $affected = 1;

    public function real_escape_string($s)
    {
        return str_replace(array("'", "\\"), array("''", "\\\\"), (string) $s);
    }

    public function pquery($sql, $params = array())
    {
        $this->lastPquery = array('sql' => $sql, 'params' => $params);
        $this->calls[] = $this->lastPquery;
        $this->affected = 1;

        if (stripos($sql, 'INSERT') === 0) {
            // INSERT ... ON DUPLICATE KEY UPDATE：按列名映射参数到 cronRows
            if (preg_match('/INSERT INTO `[^`]*cron` \(([^)]*)\) VALUES \(([^)]*)\)/i', $sql, $mm)) {
                $cols = array_map(function ($c) {
                    return trim($c, '` ');
                }, preg_split('/,\s*/', trim($mm[1])));
                $name = isset($params[0]) ? $params[0] : '';
                if (!isset($this->cronRows[$name])) {
                    $this->cronRows[$name] = array('name' => $name);
                }
                for ($i = 0; $i < count($cols) && isset($params[$i]); $i++) {
                    $this->cronRows[$name][$cols[$i]] = $params[$i];
                }
            }
            return true;
        }
        if (stripos($sql, 'DELETE') === 0) {
            return true;
        }
        if (preg_match('/`running` = 1/i', $sql) && preg_match('/`running_at` < \?/i', $sql)) {
            // 获取锁
            $this->affected = $this->lockResult;
            return true;
        }
        if (preg_match('/`running` = 0/i', $sql)) {
            $this->affected = 1;
            return true;
        }
        return true;
    }

    public function affected_rows()
    {
        return $this->affected;
    }

    public function query($sql)
    {
        return true;
    }

    public function once_fetch_array($sql)
    {
        // SELECT `retry` FROM ... WHERE `name` = 'X'
        if (preg_match('/WHERE `name` = \'([^\']+)\'/i', $sql, $mm)) {
            $name = $mm[1];
            if (!isset($this->cronRows[$name])) {
                return null;
            }
            if (preg_match('/SELECT `retry`/i', $sql)) {
                return array('retry' => isset($this->cronRows[$name]['retry']) ? $this->cronRows[$name]['retry'] : 0);
            }
            return $this->cronRows[$name];
        }
        return null;
    }

    public function fetch_array($rs)
    {
        return false;
    }
}

final class CronSchedulerTest extends TestCase
{
    /** @var FakeCronDB */
    private $db;

    protected function setUp(): void
    {
        if (!defined('DB_NAME')) {
            define('DB_NAME', 'test_db');
        }
        require_once dirname(__DIR__) . '/lib/class.option.php';
        require_once dirname(__DIR__) . '/lib/class.logger.php';
        require_once dirname(__DIR__) . '/lib/class.cron.php';
        Logger::$quiet = true; // 避免失败告警写入 CLI stderr 干扰测试输出
        $this->db = new FakeCronDB();
        $GLOBALS['m'] = $this->db;
        $GLOBALS['i'] = array(
            'opt' => array(
                'cron_max_retry'      => '2',
                'cron_retry_backoff'  => '300',
                'cron_lock_timeout'   => '600',
                'cron_notify_on_fail' => '0',
            ),
        );
    }

    // ---------- isEnabled ----------

    public function testIsEnabledAllCombos(): void
    {
        $this->assertTrue(cron::isEnabled(array('no' => 0, 'enable' => 1)));
        $this->assertFalse(cron::isEnabled(array('no' => 1, 'enable' => 0)));
        $this->assertFalse(cron::isEnabled(array('no' => 1, 'enable' => 1)));
        $this->assertFalse(cron::isEnabled(array('no' => 0, 'enable' => 0)));
        // 旧数据（无新字段）：仅按 no 判定
        $this->assertTrue(cron::isEnabled(array('no' => 0)));
        $this->assertFalse(cron::isEnabled(array('no' => 1)));
    }

    // ---------- 防重入锁 ----------

    public function testAcquireLockGained(): void
    {
        $this->db->lockResult = 1;
        $this->assertTrue(cron::acquireLock('system_health_check'));
    }

    public function testAcquireLockBlocked(): void
    {
        $this->db->lockResult = 0;
        $this->assertFalse(cron::acquireLock('system_health_check'));
    }

    // ---------- scheduleAfterRun：成功 ----------

    public function testScheduleSuccessSetsNextrun(): void
    {
        $now = time();
        cron::scheduleAfterRun('my_task', '3600', true, 'ok log', 120);
        $row = $this->db->cronRows['my_task'];
        $this->assertSame('success', $row['last_status']);
        $this->assertSame('0', (string) $row['retry']);
        $this->assertSame((string) ($now + 3600), (string) $row['nextrun']);
        $this->assertSame('120', (string) $row['last_duration']);
        $this->assertSame('ok log', $row['log']);
    }

    public function testScheduleSuccessAlwaysTask(): void
    {
        cron::scheduleAfterRun('freq0', '0', true, 'ok', 0);
        $this->assertSame('0', (string) $this->db->cronRows['freq0']['nextrun']);
    }

    // ---------- scheduleAfterRun：失败退避 ----------

    public function testScheduleFailBackoffFirst(): void
    {
        $now = time();
        cron::scheduleAfterRun('task', '3600', false, 'fail', 50);
        $row = $this->db->cronRows['task'];
        $this->assertSame('error', $row['last_status']);
        $this->assertSame('1', (string) $row['retry']);
        // 第一次失败：nextrun = now + 300*1
        $this->assertSame((string) ($now + 300), (string) $row['nextrun']);
    }

    public function testScheduleFailBackoffSecond(): void
    {
        // 预置 retry=1（此前已失败一次）
        $this->db->cronRows['task'] = array('name' => 'task', 'retry' => '1');
        $now = time();
        cron::scheduleAfterRun('task', '3600', false, 'fail', 50);
        $row = $this->db->cronRows['task'];
        $this->assertSame('2', (string) $row['retry']);
        // 第二次失败：nextrun = now + 300*2
        $this->assertSame((string) ($now + 600), (string) $row['nextrun']);
    }

    public function testScheduleFailExhaustedResetsToNormalCycle(): void
    {
        // max_retry=2，已失败 2 次，本次第 3 次失败 → 进入正常周期
        $this->db->cronRows['task'] = array('name' => 'task', 'retry' => '2');
        $now = time();
        cron::scheduleAfterRun('task', '3600', false, 'fail', 50);
        $row = $this->db->cronRows['task'];
        $this->assertSame('0', (string) $row['retry']);
        $this->assertSame('error', $row['last_status']);
        $this->assertSame((string) ($now + 3600), (string) $row['nextrun']);
    }

    public function testScheduleFailExhaustedAlwaysTask(): void
    {
        // freq=0 任务达到上限：nextrun = now + max(300, backoff)
        $this->db->cronRows['task'] = array('name' => 'task', 'retry' => '2');
        $now = time();
        cron::scheduleAfterRun('task', '0', false, 'fail', 0);
        $this->assertSame((string) ($now + 300), (string) $this->db->cronRows['task']['nextrun']);
    }

    // ---------- scheduleAfterRun：一次性任务 ----------

    public function testScheduleOneShotDeletesTask(): void
    {
        $this->db->cronRows['tmp_task'] = array('name' => 'tmp_task');
        cron::scheduleAfterRun('tmp_task', '-1', true, 'done', 10);
        $deleted = false;
        foreach ($this->db->calls as $call) {
            if (stripos($call['sql'], 'DELETE') === 0 && in_array('tmp_task', $call['params'], true)) {
                $deleted = true;
            }
        }
        $this->assertTrue($deleted, '一次性任务执行后应删除');
    }

    // ---------- runOnce ----------

    public function testRunOnceRejectsInvalidName(): void
    {
        $this->assertFalse(cron::runOnce('bad-name; DROP'));
    }

    public function testRunOnceTaskNotFound(): void
    {
        $this->assertFalse(cron::runOnce('no_such_task'));
    }

    public function testRunOnceLockedRecordsSkipped(): void
    {
        $this->db->cronRows['locked_task'] = array('name' => 'locked_task', 'file' => 'lib/cron_system_health_check.php', 'freq' => '3600');
        $this->db->lockResult = 0; // 锁被占用
        $this->assertFalse(cron::runOnce('locked_task'));
        // 锁冲突应写入 task_runs 的 skipped 记录
        $skipped = false;
        foreach ($this->db->calls as $call) {
            if (stripos($call['sql'], 'INSERT') === 0 && stripos($call['sql'], 'task_runs') !== false
                && isset($call['params'][1]) && $call['params'][1] === 'skipped') {
                $skipped = true;
            }
        }
        $this->assertTrue($skipped, '锁冲突应记录 skipped 运行历史');
    }

    // ---------- 失败通知去重（V7.0） ----------

    /** 已处于耗尽边界（retry == max_retry），本次失败触发耗尽分支 */
    private function exhaustOnce(string $name): void
    {
        $this->db->cronRows[$name] = array('name' => $name, 'retry' => '2');
        cron::scheduleAfterRun($name, '3600', false, 'fail', 0);
    }

    public function testExhaustionRecordsNotifyMarker(): void
    {
        $this->exhaustOnce('task');
        $this->assertArrayHasKey('cron_notify_last_task', $GLOBALS['i']['opt'], '耗尽应写入告警时间标记');
    }

    public function testRetryPathDoesNotTouchNotifyMarker(): void
    {
        $this->db->cronRows['task'] = array('name' => 'task', 'retry' => '0');
        cron::scheduleAfterRun('task', '3600', false, 'fail', 0);
        $this->assertArrayNotHasKey('cron_notify_last_task', $GLOBALS['i']['opt'], '重试阶段不得触发渠道告警');
    }

    public function testNotifyDedupSuppressesWithinInterval(): void
    {
        $this->exhaustOnce('task');
        $first = $GLOBALS['i']['opt']['cron_notify_last_task'];
        $this->exhaustOnce('task');
        $this->assertSame($first, $GLOBALS['i']['opt']['cron_notify_last_task'], '去重窗口内的第二次耗尽不应更新告警标记');
    }

    public function testNotifyDedupDisabledWithZeroInterval(): void
    {
        $GLOBALS['i']['opt']['cron_notify_interval'] = '0';
        $this->exhaustOnce('task');
        $first = (int) $GLOBALS['i']['opt']['cron_notify_last_task'];
        sleep(1);
        $this->exhaustOnce('task');
        $this->assertGreaterThan($first, (int) $GLOBALS['i']['opt']['cron_notify_last_task'], 'interval=0 时每次耗尽都应告警');
    }

    public function testSuccessPathKeepsMarkerHistory(): void
    {
        $this->exhaustOnce('task');
        cron::scheduleAfterRun('task', '3600', true, 'ok', 0);
        $row = $this->db->cronRows['task'];
        $this->assertSame('success', $row['last_status']);
        $this->assertArrayHasKey('cron_notify_last_task', $GLOBALS['i']['opt'], '历史告警标记保留，供下次耗尽判断间隔');
    }
}
