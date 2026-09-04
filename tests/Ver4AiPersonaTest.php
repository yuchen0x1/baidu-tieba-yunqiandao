<?php

use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__) . '/plugins/ver4_ai/ver4_ai_client.php';

/**
 * 批 A（AI 体验完善）纯函数回归测试：
 * 人设模板的解析/新增/删除、账号 ID 过滤的 strlen('0') 陷阱。
 * 仅调用不触网、不碰数据库的纯函数。
 */
final class Ver4AiPersonaTest extends TestCase
{
    public function testTplDecodeReturnsSanitizedTemplates(): void
    {
        $json = json_encode(array(
            array('name' => '  老哥人设 ', 'text' => ' 你是贴吧老哥。'),
            array('name' => '', 'text' => '缺名字应被丢弃'),
            array('name' => '缺正文', 'text' => ''),
            'junk',
        ), JSON_UNESCAPED_UNICODE);
        $tpls = ver4_ai_tpl_decode($json);
        $this->assertCount(1, $tpls);
        $this->assertSame('老哥人设', $tpls[0]['name']);
        $this->assertSame('你是贴吧老哥。', $tpls[0]['text']);

        $this->assertSame(array(), ver4_ai_tpl_decode('not-json'));
        $this->assertSame(array(), ver4_ai_tpl_decode(null));
    }

    public function testTplDecodeCapsAtTwentyAndTrimsLength(): void
    {
        $list = array();
        for ($i = 0; $i < 30; $i++) {
            $list[] = array('name' => 'tpl-' . $i, 'text' => str_repeat('t', 3000));
        }
        $tpls = ver4_ai_tpl_decode(json_encode($list, JSON_UNESCAPED_UNICODE));
        $this->assertCount(20, ver4_ai_tpl_decode(json_encode($list, JSON_UNESCAPED_UNICODE)));
        $this->assertLessThanOrEqual(2000, mb_strlen($tpls[0]['text']));
    }

    public function testTplAddAppendsAndOverwritesSameName(): void
    {
        $tpls = ver4_ai_tpl_add(array(), '模板A', '内容A');
        $tpls = ver4_ai_tpl_add($tpls, '模板B', '内容B');
        $this->assertCount(2, $tpls);

        // 同名覆盖，不新增
        $tpls = ver4_ai_tpl_add($tpls, '模板A', '内容A-新');
        $this->assertCount(2, $tpls);
        $this->assertSame('内容A-新', $tpls[0]['text']);

        // 空名字/空内容不写入
        $this->assertCount(2, ver4_ai_tpl_add($tpls, '', 'x'));
        $this->assertCount(2, ver4_ai_tpl_add($tpls, 'x', ''));
    }

    public function testTplAddKeepsNewestTwenty(): void
    {
        $tpls = array();
        for ($i = 0; $i < 25; $i++) {
            $tpls = ver4_ai_tpl_add($tpls, 'tpl-' . $i, 'text-' . $i);
        }
        $this->assertCount(20, $tpls);
        $this->assertSame('tpl-24', $tpls[19]['name']);
        $this->assertSame('tpl-5', $tpls[0]['name']);
    }

    public function testTplRemoveByIndexToleratesInvalidIndex(): void
    {
        $tpls = array(
            array('name' => 'A', 'text' => 'a'),
            array('name' => 'B', 'text' => 'b'),
        );
        $after = ver4_ai_tpl_remove($tpls, 0);
        $this->assertCount(1, $after);
        $this->assertSame('B', $after[0]['name']);

        // 非法下标原样返回，保持数组键规整
        $this->assertSame(array_values($tpls), ver4_ai_tpl_remove($tpls, 5));
        $this->assertSame(array_values($tpls), ver4_ai_tpl_remove($tpls, -1));
    }

    public function testPidFilterDropsZeroAndJunkKeepingPositiveIds(): void
    {
        // strlen('0') 陷阱回归：账号 ID "0" 不能被当作有效过滤值
        $this->assertSame(array(3, 7, 5), ver4_ai_pid_filter("3\n0\n7\nabc\n，5"));
        $this->assertSame(array(), ver4_ai_pid_filter(''));
    }
}
