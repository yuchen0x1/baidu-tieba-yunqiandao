<?php

use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__) . '/lib/class.SignManager.php';

/**
 * 批 B（数据与报表）统计方法回归测试：
 * 监控命中统计的表缺失容错与聚合、健康度趋势的空白天填充、健康快照的参数绑定。
 * 通过可配置的假 $m 隔离数据库。
 */
final class ReportStatsTest extends TestCase
{
    private function makeDb(array $onceMap, array $fetchQueue)
    {
        return new class($onceMap, $fetchQueue) {
            public array $selects = array();
            public array $prepared = array();
            public array $queries = array();
            private array $onceMap;
            private array $fetchQueue;

            public function __construct(array $onceMap, array $fetchQueue)
            {
                $this->onceMap = $onceMap;
                $this->fetchQueue = $fetchQueue;
            }

            public function query($sql)
            {
                $this->queries[] = $sql;
                return true;
            }

            public function real_escape_string($s)
            {
                return addslashes((string) $s);
            }

            public function pquery($sql, $params = array())
            {
                $this->prepared[] = array($sql, $params);
                return true;
            }

            public function once_fetch_array($sql)
            {
                $this->selects[] = $sql;
                foreach ($this->onceMap as $needle => $row) {
                    if (strpos($sql, $needle) !== false) {
                        return $row;
                    }
                }
                return false;
            }

            public function fetch_array($result)
            {
                if (empty($this->fetchQueue)) {
                    return false;
                }
                return array_shift($this->fetchQueue);
            }
        };
    }

    public function testMonitorHitStatsUnavailableWhenPluginTableMissing(): void
    {
        global $m;
        $previous = $m ?? null;
        $db = $this->makeDb(
            array('information_schema.tables' => array('c' => 0)),
            array()
        );
        $m = $db;

        try {
            $stats = SignManager::monitorHitStats(7, 30);
            $this->assertFalse($stats['available']);
            $this->assertSame(0, $stats['total']);
            $this->assertSame(array(), $stats['top']);
            $this->assertSame(1, count($db->selects), '表不存在时不得继续发起聚合查询');
        } finally {
            $m = $previous;
        }
    }

    public function testMonitorHitStatsAggregatesTopAndFillsTrendDays(): void
    {
        global $m;
        $previous = $m ?? null;
        $db = $this->makeDb(
            array(
                'information_schema.tables' => array('c' => 1),
                'COUNT(*) AS c FROM `' . DB_PREFIX . 'ver4_monitor_hit`' => array('c' => 4),
            ),
            array(
                array('forum' => '钓鱼', 'hits' => 3, 'replied' => 1),
                array('forum' => '数码', 'hits' => 1, 'replied' => 0),
                false,
                array('day' => date('Y-m-d'), 'hits' => 4),
                false,
            )
        );
        $m = $db;

        try {
            $stats = SignManager::monitorHitStats(7, 7);
            $this->assertTrue($stats['available']);
            $this->assertSame(4, $stats['total']);
            $this->assertCount(2, $stats['top']);
            $this->assertSame('钓鱼', $stats['top'][0]['forum']);
            $this->assertSame(3, $stats['top'][0]['hits']);
            $this->assertSame(1, $stats['top'][0]['replied']);
            // 趋势按天填充：7 天窗口返回 7 条，最后一天有命中
            $this->assertCount(7, $stats['trend']);
            $this->assertSame(0, $stats['trend'][0]['hits']);
            $this->assertSame(4, $stats['trend'][6]['hits']);
            $this->assertSame(date('Y-m-d'), $stats['trend'][6]['date']);
        } finally {
            $m = $previous;
        }
    }

    public function testHealthTrendFillsBlankDaysAndBindsUidScope(): void
    {
        global $m;
        $previous = $m ?? null;
        $db = $this->makeDb(
            array(),
            array(
                array('day' => date('Ymd'), 'ok_cnt' => 2, 'total' => 3),
                false,
            )
        );
        $m = $db;

        try {
            $rows = SignManager::healthTrend(7, 7);
            $this->assertCount(7, $rows);
            $this->assertSame(0, $rows[0]['total']);
            $this->assertSame(date('Y-m-d'), $rows[6]['date']);
            $this->assertSame(2, $rows[6]['ok']);
            $this->assertSame(3, $rows[6]['total']);
            $this->assertSame(66.7, $rows[6]['rate']);

            // 绑定参数：uid 限定 + day 范围
            $this->assertNotEmpty($db->prepared);
            list($sql, $params) = $db->prepared[0];
            $this->assertStringContainsString('INNER JOIN', $sql);
            $this->assertStringContainsString('b.`uid` = ?', $sql);
            $this->assertSame(7, $params[0]);
            $this->assertSame(date('Ymd', strtotime('-6 days midnight')), $params[1]);
            $this->assertSame(date('Ymd'), $params[2]);
        } finally {
            $m = $previous;
        }
    }

    public function testSnapshotBaiduidHealthUsesReplaceWithBoundParams(): void
    {
        global $m;
        $previous = $m ?? null;
        $db = $this->makeDb(array(), array());
        $m = $db;

        try {
            SignManager::snapshotBaiduidHealth(12, true, 0);
            SignManager::snapshotBaiduidHealth(13, false, 4);
            $this->assertCount(2, $db->prepared);
            foreach ($db->prepared as $i => $p) {
                $this->assertStringContainsString('REPLACE INTO', $p[0]);
                $this->assertStringContainsString(DB_PREFIX . 'baiduid_health_snapshot`', $p[0]);
            }
            $this->assertSame(12, $db->prepared[0][1][0]);
            $this->assertSame(date('Ymd'), $db->prepared[0][1][1]);
            $this->assertSame(1, $db->prepared[0][1][2]);
            $this->assertSame(0, $db->prepared[0][1][3]);
            $this->assertSame(13, $db->prepared[1][1][0]);
            $this->assertSame(0, $db->prepared[1][1][2]);
            $this->assertSame(4, $db->prepared[1][1][3]);
        } finally {
            $m = $previous;
        }
    }
}
