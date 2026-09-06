<?php

use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__) . '/lib/security.php';
require_once dirname(__DIR__) . '/lib/class.option.php';
require_once dirname(__DIR__) . '/lib/sfc.functions.php';

if (!defined('DB_NAME')) {
    define('DB_NAME', 'test_db');
}

/**
 * 安全加固回归测试：
 * 覆盖 MustAdminWarning/dataBak 注入面收敛、全局 API Token 哈希存取、
 * option 用户设置 uid 强制转型、SignManager 高频日志 DDL 守卫。
 */
final class SecurityHardeningTest extends TestCase
{
    /**
     * 记录 SQL 的假数据库对象
     */
    private function makeRecordingDb()
    {
        return new class {
            public array $queries = array();
            public array $prepared = array();

            public function query($sql)
            {
                $this->queries[] = $sql;
                return true;
            }

            public function pquery($sql, $params = array())
            {
                $this->prepared[] = array($sql, $params);
                return true;
            }

            public function once_fetch_array($sql)
            {
                $this->queries[] = $sql;
                return array('num' => 5);
            }

            public function fetch_array($result)
            {
                return array('options' => '');
            }
        };
    }

    public function testMustAdminWarningCastsIdsToIntBeforeSql(): void
    {
        global $m;
        $previous = $m ?? null;
        $db = $this->makeRecordingDb();
        $m = $db;

        try {
            MustAdminWarning(array("1) OR 1=1 --", "2", 'abc'));
            $this->assertCount(1, $db->queries);
            $sql = $db->queries[0];
            $this->assertStringContainsString('NOT IN (1,2)', $sql);
            $this->assertStringNotContainsString('OR 1=1', $sql);
            $this->assertStringNotContainsString('--', $sql);
        } finally {
            $m = $previous;
        }
    }

    public function testDataBakRejectsTableNamesOutsideWhitelist(): void
    {
        global $m;
        $previous = $m ?? null;
        $db = $this->makeRecordingDb();
        $m = $db;

        try {
            $this->assertFalse(dataBak('tieba`;DROP TABLE tc_users;--'));
            $this->assertFalse(dataBak('tieba WHERE 1=1'));
            $this->assertFalse(dataBak(array('evil')));
            $this->assertSame(array(), $db->queries, '非法表名不得触达数据库');
        } finally {
            $m = $previous;
        }
    }

    public function testApiTokenStorableHashesPlaintextAndKeepsHex(): void
    {
        $hashed = api_token_storable('my-secret-token');
        $this->assertSame(64, strlen($hashed));
        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $hashed);
        $this->assertSame($hashed, hash('sha256', 'my-secret-token'));

        // 已是 64 位十六进制（历史哈希）原样保留
        $hex = str_repeat('ab', 32);
        $this->assertSame($hex, api_token_storable($hex));

        $this->assertSame('', api_token_storable(''));
        $this->assertSame('', api_token_storable(null));
    }

    public function testSafeTableNameRejectsNonIdentifierValues(): void
    {
        $this->assertSame('tieba', safe_table_name('tieba'));
        $this->assertSame('my_table2', safe_table_name(' My_Table2 '), '合法表名应归一化为小写');
        $this->assertSame('tieba', safe_table_name('tieba; DROP TABLE tc_users'));
        $this->assertSame('tieba', safe_table_name('tieba` WHERE 1=1 --'));
        $this->assertSame('tieba', safe_table_name("tieba' OR '1'='1"));
        $this->assertSame('tieba', safe_table_name(''));
        $this->assertSame('tieba', safe_table_name(null));
        $this->assertSame('tieba', safe_table_name(array('tieba')));
        $this->assertSame('tieba', safe_table_name(str_repeat('a', 65)), '超过 64 位一律回退');
        $this->assertSame('other', safe_table_name('bad name', 'other'), '自定义回退表名');
    }

    public function testEntryPointsContainNoInterpolatedSql(): void
    {
        // V7.0 收敛：ajax/setting/api 三个入口的 SQL 一律参数化或白名单标识符，
        // 双引号 SQL 中出现 {$var} 插值即回归，必须走 pquery 绑定
        foreach (array('ajax.php', 'setting.php', 'api.php') as $file) {
            $source = file_get_contents(dirname(__DIR__) . '/' . $file);
            $this->assertNotFalse($source, '无法读取 ' . $file);
            $this->assertSame(
                0,
                preg_match_all('/->(?:query|once_fetch_array)\("[^"]*\{\$/', $source),
                $file . ' 出现插值拼接 SQL，请改用 pquery 参数绑定'
            );
        }
    }

    public function testApiTokenMatchesBothHashedAndLegacyPlaintext(): void
    {
        $storedHash = api_token_storable('my-secret-token');
        $this->assertTrue(api_token_matches($storedHash, 'my-secret-token'));
        $this->assertFalse(api_token_matches($storedHash, 'wrong-token'));

        // 升级前旧站明文存储形态
        $this->assertTrue(api_token_matches('legacy-plain-token', 'legacy-plain-token'));
        $this->assertFalse(api_token_matches('legacy-plain-token', 'other'));

        $this->assertFalse(api_token_matches('', 'anything'));
        $this->assertFalse(api_token_matches($storedHash, ''));

        // 哈希形态下，「哈希本身」不能作为凭据通过校验（防拖库直接调用 API）
        $this->assertFalse(api_token_matches($storedHash, $storedHash));
        $this->assertFalse(api_token_matches(strtoupper($storedHash), strtoupper($storedHash)));
    }

    public function testOptionUsetForcesUidToInt(): void
    {
        global $m, $i;
        $previousM = $m ?? null;
        $previousI = $i ?? null;
        $db = $this->makeRecordingDb();
        $m = $db;
        $i = array('user' => array('uid' => 9, 'opt' => array()));

        try {
            option::uset('some_key', 'some_value', '3 OR 1=1');
            // 第 1 条是存在性 SELECT，第 2 条是 UPDATE
            $this->assertCount(2, $db->queries);
            $updateSql = $db->queries[1];
            $this->assertStringNotContainsString('OR 1=1', $updateSql);
            $this->assertStringContainsString('`uid` = 3', $updateSql);
        } finally {
            $m = $previousM;
            $i = $previousI;
        }
    }

    public function testOptionPluginGetAndSetUseParameterBinding(): void
    {
        global $m, $i;
        $previousM = $m ?? null;
        $previousI = $i ?? null;
        $db = $this->makeRecordingDb();
        $m = $db;
        $i = array('opt' => array());

        try {
            option::set('plugin_v', "x'; DROP TABLE tc_plugins;--");
            $this->assertCount(1, $db->prepared);
            $this->assertStringContainsString('WHERE `name` = ?', $db->prepared[0][0]);
            $this->assertSame("x'; DROP TABLE tc_plugins;--", $db->prepared[0][1][0]);

            // 清掉运行时缓存，迫使 get() 走数据库查询
            $i['opt'] = array();
            option::get('plugin_v');
            $this->assertNotEmpty($db->prepared);
            $bound = $db->prepared[count($db->prepared) - 1];
            $this->assertStringContainsString('SELECT `options` FROM', $bound[0]);
            $this->assertStringContainsString('`name` = ?', $bound[0]);
        } finally {
            $m = $previousM;
            $i = $previousI;
        }
    }

    public function testSignManagerLogRunsCreateTableAtMostOncePerRequest(): void
    {
        global $m;
        $previous = $m ?? null;
        $db = new class {
            public int $ddl = 0;
            public int $inserts = 0;

            public function query($sql)
            {
                if (stripos($sql, 'CREATE TABLE') === 0) {
                    $this->ddl++;
                } elseif (stripos($sql, 'INSERT INTO') === 0) {
                    $this->inserts++;
                }
                return true;
            }

            public function real_escape_string($s)
            {
                return addslashes((string) $s);
            }
        };
        $m = $db;

        try {
            SignManager::log(1, 'sign', 'success', 'ok');
            SignManager::log(2, 'sign', 'error', "msg with 'quote'");
            $this->assertLessThanOrEqual(1, $db->ddl, '同一请求内重复 log() 不得再次执行建表 DDL');
            $this->assertSame(2, $db->inserts);
        } finally {
            $m = $previous;
        }
    }

    public function testLogsTiebaFilterEscapesLikeWildcards(): void
    {
        global $m;
        $previous = $m ?? null;
        // 有状态 fake：fetch_array 先回 COUNT 行，再回 false 结束行循环
        $db = new class {
            public array $likeParams = array();
            private array $queue = array(array('c' => 0), false, false);

            public function query($sql)
            {
                return true;
            }

            public function pquery($sql, $params = array())
            {
                if (stripos($sql, 'LIKE') !== false) {
                    $this->likeParams = $params;
                }
                return true;
            }

            public function real_escape_string($s)
            {
                return addslashes((string) $s);
            }

            public function fetch_array($result)
            {
                return array_shift($this->queue);
            }
        };
        $m = $db;

        try {
            $input = 'tb%50_name';
            SignManager::logs(array('tieba' => $input), 1, 30);
            $this->assertNotEmpty($db->likeParams, '含 tieba 筛选时必须产生 LIKE 参数');
            $expected = '%' . addslashes(addcslashes($input, '%_')) . '%';
            $this->assertSame($expected, $db->likeParams[0], 'LIKE 通配符必须先转义再绑定，% _ 按字面匹配');
        } finally {
            $m = $previous;
        }
    }
}
