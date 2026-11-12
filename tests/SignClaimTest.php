<?php

use PHPUnit\Framework\TestCase;

if (!defined('DB_NAME')) {
    define('DB_NAME', 'test_db');
}

final class SignClaimTest extends TestCase
{
    public function testClaimUsesAtomicUpdateAndBoundValues(): void
    {
        global $m;
        $previous = $m ?? null;
        $db = new class {
            public array $calls = array();
            public function pquery($sql, $params = array())
            {
                $this->calls[] = array($sql, $params);
                return true;
            }
            public function affected_rows()
            {
                return 1;
            }
        };
        $m = $db;

        try {
            $this->assertTrue(misc::claimSignRow('tieba', 42, 900));
            $this->assertCount(1, $db->calls);
            $this->assertStringContainsString('SET `claim_owner` = ?, `claimed_at` = ?', $db->calls[0][0]);
            $this->assertStringContainsString('`claimed_at` < ?', $db->calls[0][0]);
            $this->assertSame(4, count($db->calls[0][1]));
            $this->assertSame(42, $db->calls[0][1][2]);
        } finally {
            $m = $previous;
        }
    }

    public function testClaimRejectsInvalidRowIdWithoutTouchingDatabase(): void
    {
        global $m;
        $previous = $m ?? null;
        $db = new class {
            public int $calls = 0;
            public function pquery($sql, $params = array())
            {
                $this->calls++;
                return true;
            }
            public function affected_rows()
            {
                return 1;
            }
        };
        $m = $db;

        try {
            $this->assertFalse(misc::claimSignRow('tieba', 0));
            $this->assertSame(0, $db->calls);
        } finally {
            $m = $previous;
        }
    }
}
