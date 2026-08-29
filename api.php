<?php
/**
 * 开放 API 入口
 * Bearer Token 认证，配置项 api_token
 *
 * 支持接口：
 *   GET /api.php?action=status         签到状态汇总
 *   GET /api.php?action=health         账号健康度
 *   POST /api.php?action=sign          立即执行签到
 *   POST /api.php?action=retry         执行复签
 *   POST /api.php?action=health_check  执行健康检查
 *   GET /api.php?action=logs&limit=50  最近日志
 *   GET /api.php?action=update       在线版本检查
 */

define('SYSTEM_NO_UI', true);
define('SYSTEM_NO_CHECK_LOGIN', true);
require dirname(__FILE__) . '/init.php';

header('Content-Type: application/json; charset=UTF-8');

$apiToken = option::get('api_token');
if (empty($apiToken)) {
    http_response_code(503);
    die(json_encode(array('error' => 'API 未启用，请在设置中心配置 api_token'), JSON_UNESCAPED_UNICODE));
}

$auth = isset($_SERVER['HTTP_AUTHORIZATION']) ? trim((string) $_SERVER['HTTP_AUTHORIZATION']) : '';
if (stripos($auth, 'Bearer ') === 0) {
    $auth = substr($auth, 7);
}
if (!hash_equals($apiToken, $auth)) {
    http_response_code(401);
    die(json_encode(array('error' => '认证失败'), JSON_UNESCAPED_UNICODE));
}

$action = isset($_GET['action']) ? trim(strip_tags($_GET['action'])) : '';
global $m, $i;

switch ($action) {
    case 'update':
        try {
            $release = ProjectUpdater::fetchLatestRelease();
            $status = ProjectUpdater::buildStatus(SYSTEM_VER, $release);
            echo json_encode(array('ok' => true, 'data' => array(
                'current_version' => $status['current_version'],
                'latest_version' => $status['latest_version'],
                'update_available' => $status['update_available'],
                'release' => $release
            )), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        } catch (Throwable $e) {
            error_log('API 在线更新检查失败：' . $e->getMessage());
            http_response_code(502);
            echo json_encode(array('error' => '无法获取最新版本信息'), JSON_UNESCAPED_UNICODE);
        }
        break;

    case 'status':
        require_once SYSTEM_ROOT . '/lib/class.SignManager.php';
        $stat = SignManager::signSummary();
        echo json_encode(array('ok' => true, 'data' => $stat), JSON_UNESCAPED_UNICODE);
        break;

    case 'health':
        $rows = array();
        $rs = $m->query("SELECT * FROM `" . DB_PREFIX . "baiduid_health` ORDER BY `status` ASC, `fail_count` DESC;");
        while ($r = $m->fetch_array($rs)) {
            $rows[] = array(
                'pid' => (int) $r['pid'],
                'name' => $r['name'],
                'status' => $r['status'],
                'last_ok' => (int) $r['last_ok'],
                'last_fail' => (int) $r['last_fail'],
                'fail_count' => (int) $r['fail_count'],
                'error_msg' => $r['error_msg'],
            );
        }
        echo json_encode(array('ok' => true, 'data' => $rows), JSON_UNESCAPED_UNICODE);
        break;

    case 'sign':
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            http_response_code(405);
            die(json_encode(array('error' => 'POST required'), JSON_UNESCAPED_UNICODE));
        }
        ignore_user_abort(true);
        set_time_limit(0);
        require_once SYSTEM_ROOT . '/lib/class.SignManager.php';
        require_once SYSTEM_ROOT . '/lib/class.notify.php';
        $start = microtime(true);
        foreach ($i['table'] as $value) {
            misc::DoSign($value);
        }
        $duration = intval((microtime(true) - $start) * 1000);
        $stat = SignManager::signSummary();
        $msg = "[签到结束 " . date('H:i:s') . "] 已签到 {$stat['signed']} 个贴吧，出错 {$stat['error']} 个";
        SignManager::log(0, 'sign_api', $stat['error'] > 0 ? 'partial' : 'ok', $msg, $duration);
        echo json_encode(array('ok' => true, 'data' => $stat, 'msg' => $msg, 'duration_ms' => $duration), JSON_UNESCAPED_UNICODE);
        break;

    case 'retry':
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            http_response_code(405);
            die(json_encode(array('error' => 'POST required'), JSON_UNESCAPED_UNICODE));
        }
        ignore_user_abort(true);
        set_time_limit(0);
        foreach ($i['table'] as $value) {
            misc::DoSign_retry($value);
        }
        require_once SYSTEM_ROOT . '/lib/class.SignManager.php';
        $stat = SignManager::signSummary();
        echo json_encode(array('ok' => true, 'data' => $stat), JSON_UNESCAPED_UNICODE);
        break;

    case 'health_check':
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            http_response_code(405);
            die(json_encode(array('error' => 'POST required'), JSON_UNESCAPED_UNICODE));
        }
        ignore_user_abort(true);
        set_time_limit(0);
        require_once SYSTEM_ROOT . '/lib/cron_system_health_check.php';
        $msg = cron_system_health_check();
        echo json_encode(array('ok' => true, 'msg' => $msg), JSON_UNESCAPED_UNICODE);
        break;

    case 'logs':
        $limit = isset($_GET['limit']) ? min(200, max(1, (int) $_GET['limit'])) : 50;
        $rows = array();
        $rs = $m->query("SELECT * FROM `" . DB_PREFIX . "cron_log_batch` ORDER BY `id` DESC LIMIT {$limit};");
        while ($r = $m->fetch_array($rs)) {
            $rows[] = $r;
        }
        echo json_encode(array('ok' => true, 'data' => $rows), JSON_UNESCAPED_UNICODE);
        break;

    default:
        http_response_code(400);
        echo json_encode(array('error' => '未知 action', 'available' => array('update', 'status', 'health', 'sign', 'retry', 'health_check', 'logs')), JSON_UNESCAPED_UNICODE);
        break;
}
