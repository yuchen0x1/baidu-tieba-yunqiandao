<?php

if (!defined('SYSTEM_ROOT')) {
    die('Insufficient Permissions');
}

/**
 * 开放 API 合同（V7.0）：版本标识、稳定错误码与权限矩阵的唯一事实来源。
 * - 所有响应携带 api_version；发生破坏性变更时递增（v1 → v2），旧字段语义不变；
 * - 错误响应统一为 {error, code, api_version, ...extra}：code 供程序判断，
 *   error 为人类可读文本（文本可随版本优化，code 不轻易变更）；
 * - api_actions() 同时驱动方法/权限门禁与 openapi 文档，新增 action 必须先在此登记。
 */

define('API_CONTRACT_VERSION', 'v1');

function api_contract_version()
{
    return API_CONTRACT_VERSION;
}

/**
 * 权限矩阵：action => array(method, access, desc)
 * access: any=任意有效 Token（作用域不同，全局=全站、个人=本人数据）；
 *         global=仅管理员全局 Token；user=仅个人 Token
 */
function api_actions()
{
    return array(
        'status'       => array('method' => 'GET',  'access' => 'any',    'desc' => '签到状态汇总'),
        'health'       => array('method' => 'GET',  'access' => 'any',    'desc' => '账号健康度'),
        'sign'         => array('method' => 'POST', 'access' => 'any',    'desc' => '立即执行签到'),
        'retry'        => array('method' => 'POST', 'access' => 'any',    'desc' => '执行复签'),
        'health_check' => array('method' => 'POST', 'access' => 'global', 'desc' => '执行全站健康检查'),
        'logs'         => array('method' => 'GET',  'access' => 'any',    'desc' => '最近日志'),
        'calendar'     => array('method' => 'GET',  'access' => 'any',    'desc' => '个人签到日历'),
        'streak'       => array('method' => 'GET',  'access' => 'any',    'desc' => '连续签到统计'),
        'ranking'      => array('method' => 'GET',  'access' => 'any',    'desc' => '用户签到排行榜'),
        'failures'     => array('method' => 'GET',  'access' => 'any',    'desc' => '失败原因分布'),
        'monitor'      => array('method' => 'GET',  'access' => 'user',   'desc' => '帖子监控命中记录（个人 Token）'),
        'trend'        => array('method' => 'GET',  'access' => 'any',    'desc' => '签到趋势（每日成功/失败）'),
        'tieba_health' => array('method' => 'GET',  'access' => 'any',    'desc' => '每个贴吧的健康度统计'),
        'export'       => array('method' => 'GET',  'access' => 'any',    'desc' => '导出签到日志 CSV'),
        'update'       => array('method' => 'GET',  'access' => 'global', 'desc' => '在线版本检查'),
        'webhook_test' => array('method' => 'GET',  'access' => 'global', 'desc' => '测试 Webhook 推送（管理员）'),
        'openapi'      => array('method' => 'GET',  'access' => 'any',    'desc' => '接口文档'),
    );
}

/**
 * 判定当前 Token 是否有权访问该 action（$apiUserId > 0 表示个人 Token）
 */
function api_action_allowed($action, $apiUserId)
{
    $actions = api_actions();
    if (!isset($actions[$action])) {
        return false;
    }
    $access = $actions[$action]['access'];
    if ($access === 'global') {
        return (int) $apiUserId <= 0;
    }
    if ($access === 'user') {
        return (int) $apiUserId > 0;
    }
    return true;
}

/**
 * 标准成功响应体（不输出）
 */
function api_ok($data = array(), $extra = array())
{
    return array_merge(array(
        'ok' => true,
        'api_version' => api_contract_version(),
        'data' => $data,
    ), $extra);
}

/**
 * 标准错误响应体（不输出）。$code 为稳定错误码（如 missing_token/rate_limited）。
 */
function api_error_payload($code, $message, $extra = array())
{
    return array_merge(array(
        'error' => (string) $message,
        'code' => (string) $code,
        'api_version' => api_contract_version(),
    ), $extra);
}

/**
 * 输出标准错误响应并终止（$httpStatus <= 0 时不设状态码也不终止，仅供特殊调用点）
 */
function api_error($code, $message, $httpStatus, $extra = array())
{
    if ($httpStatus > 0) {
        http_response_code($httpStatus);
    }
    echo json_encode(api_error_payload($code, $message, $extra), JSON_UNESCAPED_UNICODE);
    if ($httpStatus > 0) {
        exit;
    }
}
