<?php

use PHPUnit\Framework\TestCase;

final class CronHistoryTest extends TestCase
{
    public function testFiltersAreBoundAndOutOfRangePageIsClamped(): void
    {
        require_once SYSTEM_ROOT . '/lib/class.cron.php';
        $previous = $GLOBALS['m'] ?? null;
        $db = new class {
            public $calls = array();
            public function query($sql) { return true; }
            public function pquery($sql, $params = array()) {
                $this->calls[] = array($sql, $params);
                return new ArrayIterator(strpos($sql, 'COUNT(*)') !== false ? array(array('total' => 41)) : array(array('name' => 'task', 'status' => 'error')));
            }
            public function fetch_array($rs) {
                if (!$rs->valid()) { return false; }
                $row = $rs->current(); $rs->next(); return $row;
            }
        };
        $GLOBALS['m'] = $db;
        try {
            $hostileName = "deleted_task' OR 1=1 --";
            $history = cron::runHistory(array('name' => $hostileName, 'status' => 'error', 'since' => 123), PHP_INT_MAX, 20);
            $this->assertSame(41, $history['total']);
            $this->assertSame(3, $history['page']);
            $this->assertCount(1, $history['rows']);
            $this->assertSame(array($hostileName, 'error', 123, 123), $db->calls[0][1]);
            $this->assertStringContainsString('`started_at` = 0 AND `finished_at` >= ?', $db->calls[0][0]);
            $this->assertStringNotContainsString($hostileName, $db->calls[0][0]);
            $this->assertStringContainsString('LIMIT 20 OFFSET 40', $db->calls[1][0]);
            $db->calls = array();
            cron::runHistory(array('status' => "error' OR 1=1"), -5, 1000);
            $this->assertSame(array(), $db->calls[0][1]);
            $this->assertStringContainsString('LIMIT 100 OFFSET 0', $db->calls[1][0]);
        } finally { $GLOBALS['m'] = $previous; }
    }
}
