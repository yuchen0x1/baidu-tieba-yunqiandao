<?php

use PHPUnit\Framework\TestCase;

require_once SYSTEM_ROOT . '/lib/api-rate-limit.php';

final class ApiRateLimitTest extends TestCase
{
    public function testExistingWindowIsIncrementedAtomically(): void
    {
        $db = new class {
            public $calls = array();
            public function pquery($sql, $params = array()) { $this->calls[] = array($sql, $params); return true; }
            public function affected_rows() { return 1; }
        };
        $this->assertTrue(api_rate_limit_consume($db, str_repeat('a', 64), 120, 60));
        $this->assertCount(1, $db->calls);
        $this->assertStringContainsString('`count` < ?', $db->calls[0][0]);
        $this->assertSame(60, $db->calls[0][1][2]);
    }

    public function testConcurrentFirstRequestUsesUpsertPath(): void
    {
        $db = new class {
            public $calls = array();
            public function pquery($sql, $params = array()) { $this->calls[] = array($sql, $params); return true; }
            public function affected_rows() { return count($this->calls) === 1 ? 0 : 1; }
        };
        $this->assertTrue(api_rate_limit_consume($db, str_repeat('b', 64), 180, 2));
        $this->assertCount(2, $db->calls);
        $this->assertStringContainsString('ON DUPLICATE KEY UPDATE', $db->calls[1][0]);
    }

    public function testAtLimitIsRejectedWithoutIncrement(): void
    {
        $db = new class {
            public $calls = array();
            public function pquery($sql, $params = array()) { $this->calls[] = array($sql, $params); return true; }
            public function affected_rows() { return 0; }
        };
        $this->assertFalse(api_rate_limit_consume($db, str_repeat('c', 64), 240, 1));
        $this->assertCount(2, $db->calls);
    }

    public function testStorageFailureIsFailClosed(): void
    {
        $db = new class {
            public function pquery($sql, $params = array()) { return false; }
            public function affected_rows() { return 0; }
        };
        $this->assertNull(api_rate_limit_consume($db, str_repeat('d', 64), 300, 60));
    }
}
