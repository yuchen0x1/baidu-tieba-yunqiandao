<?php

use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__) . '/lib/api-contract.php';
require_once dirname(__DIR__) . '/lib/plugins.php';

if (!defined('SYSTEM_VER')) {
    define('SYSTEM_VER', '7.0');
}

/**
 * V7.0 合同测试：开放 API（版本/错误码/权限矩阵）与插件 ABI（启用前兼容检查）。
 * 矩阵是 api.php 门禁与 openapi 文档的共同来源；插件兼容性决定能否启用。
 */
final class ApiContractTest extends TestCase
{
    public function testIncompletePluginIndexHasNoActivePlugins(): void
    {
        $this->assertSame(array(), plugin_active_ids(array()));
        $this->assertSame(array(), plugin_active_ids(array('actived' => null)));
        $this->assertSame(array(), plugin_active_ids(array('actived' => 'not-an-array')));
        $this->assertSame(array('ver4_like'), plugin_active_ids(array('actived' => array('ver4_like'))));
    }

    // ---------- 版本标识 ----------

    public function testContractVersionIsStableAndSemverLike(): void
    {
        $this->assertSame('v1', api_contract_version());
        $this->assertMatchesRegularExpression('/^v\d+$/', api_contract_version());
    }

    public function testErrorPayloadCarriesCodeMessageAndVersion(): void
    {
        $payload = api_error_payload('rate_limited', '请求过于频繁', array('limit' => 60, 'window' => 60));
        $this->assertSame('请求过于频繁', $payload['error']);
        $this->assertSame('rate_limited', $payload['code']);
        $this->assertSame('v1', $payload['api_version']);
        $this->assertSame(60, $payload['limit']);
        $this->assertNotNull(json_encode($payload), '错误体必须可 JSON 序列化');
    }

    public function testOkEnvelopeCarriesVersionAndData(): void
    {
        $envelope = api_ok(array('foo' => 'bar'), array('msg' => 'hi'));
        $this->assertTrue($envelope['ok']);
        $this->assertSame('v1', $envelope['api_version']);
        $this->assertSame('bar', $envelope['data']['foo']);
        $this->assertSame('hi', $envelope['msg']);
    }

    // ---------- 权限矩阵完整性 ----------

    public function testMatrixEntriesAreWellFormed(): void
    {
        foreach (api_actions() as $action => $meta) {
            $this->assertMatchesRegularExpression('/^[A-Za-z0-9_]+$/', (string) $action);
            $this->assertContains($meta['method'], array('GET', 'POST'), $action . ' 的 method 不合法');
            $this->assertContains($meta['access'], array('any', 'global', 'user'), $action . ' 的 access 不合法');
            $this->assertNotSame('', $meta['desc'], $action . ' 缺少描述');
        }
    }

    public function testSensitiveActionsHaveExpectedAccess(): void
    {
        $this->assertSame('global', api_actions()['update']['access']);
        $this->assertSame('global', api_actions()['health_check']['access']);
        $this->assertSame('global', api_actions()['webhook_test']['access']);
        $this->assertSame('user', api_actions()['monitor']['access']);
        foreach (array('sign', 'retry') as $writeAction) {
            $this->assertSame('POST', api_actions()[$writeAction]['method'], $writeAction . ' 是写操作必须 POST');
        }
    }

    public function testActionAllowedFollowsMatrix(): void
    {
        $this->assertTrue(api_action_allowed('update', 0), '全局 Token 可访问 update');
        $this->assertFalse(api_action_allowed('update', 5), '个人 Token 不可访问 update');
        $this->assertFalse(api_action_allowed('monitor', 0), '全局 Token 不可访问 monitor');
        $this->assertTrue(api_action_allowed('monitor', 5), '个人 Token 可访问 monitor');
        $this->assertTrue(api_action_allowed('status', 0));
        $this->assertTrue(api_action_allowed('status', 5));
        $this->assertFalse(api_action_allowed('no_such_action', 0));
    }

    public function testReadmeDocumentsCoverTheWholeMatrix(): void
    {
        // README 的 API 表格必须覆盖矩阵中除 openapi 外的全部 action，且方法一致，
        // 防止文档与实现漂移（新增 action 未登记 README → 本测试失败）
        $readme = file_get_contents(dirname(__DIR__) . '/README.md');
        $this->assertNotFalse($readme);
        preg_match_all('/\| *`\/api\.php\?action=([a-z_]+)(?:&[^`]*)?` *\| *`([A-Z]+)` */', $readme, $matches, PREG_SET_ORDER);
        $documented = array();
        foreach ($matches as $row) {
            $action = $row[1];
            $method = $row[2];
            $this->assertArrayHasKey($action, api_actions(), "README 记录了矩阵中不存在的 action：{$action}");
            $this->assertSame(api_actions()[$action]['method'], $method, "README 中 {$action} 的方法与矩阵不一致");
            $documented[] = $action;
        }
        $undocumented = array_diff(array_keys(api_actions()), $documented, array('openapi'));
        $this->assertSame(array(), array_values($undocumented), '矩阵中以下 action 未在 README 记录：' . implode(', ', $undocumented));
    }

    // ---------- 插件 ABI：启用前兼容检查 ----------

    public function testPluginCompatibilityAcceptsAllAndLowerRequirements(): void
    {
        $ok = plugin_compatibility_errors(array('plugin' => array('for' => 'all', 'forphp' => '7.0')));
        $this->assertTrue($ok['ok'], 'for=all/低版本要求应通过（当前 PHP ' . PHP_VERSION . '）');

        $ok = plugin_compatibility_errors(array('plugin' => array('for' => '1.0', 'forphp' => 'all')));
        $this->assertTrue($ok['ok']);

        $ok = plugin_compatibility_errors(array());
        $this->assertTrue($ok['ok'], '空清单视为兼容（manifest 完整性由 plugin_manifest_validate 负责）');
    }

    public function testPluginCompatibilityRejectsNewerCoreRequirement(): void
    {
        $result = plugin_compatibility_errors(array('plugin' => array('for' => '99.0')));
        $this->assertFalse($result['ok']);
        $this->assertStringContainsString('99.0', $result['errors'][0]);
    }

    public function testPluginCompatibilityRejectsNewerPhpRequirement(): void
    {
        $futurePhp = (int) PHP_MAJOR_VERSION + 1 . '.0';
        $result = plugin_compatibility_errors(array('plugin' => array('forphp' => $futurePhp)));
        $this->assertFalse($result['ok']);
        $this->assertStringContainsString('PHP', $result['errors'][0]);
    }

    public function testPluginCompatibilityCheckCombinesManifestAndCompat(): void
    {
        // 指向仓库内真实插件：manifest 完整且兼容 → ok
        $real = plugin_compatibility_check('ver4_like');
        if (is_dir(dirname(__DIR__) . '/plugins/ver4_like')) {
            $this->assertTrue($real['ok'], '内置插件 ver4_like 应能通过兼容检查：' . implode(';', $real['errors']));
        } else {
            $this->assertFalse($real['ok'], '插件目录不存在时应检查失败');
        }
    }
}
