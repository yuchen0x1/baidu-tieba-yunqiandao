<?php
/**
 * 开放 API 入口
 * Bearer Token 认证：支持管理员全局 Token 与按用户隔离的个人 Token
 *
 * 支持接口：
 *   GET /api.php?action=status         签到状态汇总
 *   GET /api.php?action=health         账号健康度
 *   POST /api.php?action=sign          立即执行签到
 *   POST /api.php?action=retry         执行复签
 *   POST /api.php?action=health_check  执行健康检查
 *   GET /api.php?action=logs&limit=50  最近日志
 *   GET /api.php?action=calendar&days=90  个人签到日历
 *   GET /api.php?action=update       在线版本检查
 */

define('SYSTEM_NO_UI', true);
define('SYSTEM_NO_CHECK_LOGIN', true);
require dirname(__FILE__) . '/init.php';

header('Content-Type: application/json; charset=UTF-8');

$auth = isset($_SERVER['HTTP_AUTHORIZATION']) ? trim((string) $_SERVER['HTTP_AUTHORIZATION']) : '';
if (stripos($auth, 'Bearer ') === 0) {
    $auth = trim(substr($auth, 7));
}
if ($auth === '' && isset($_SERVER['HTTP_X_API_TOKEN'])) {
    $auth = trim((string) $_SERVER['HTTP_X_API_TOKEN']);
}
if ($auth === '') {
    http_response_code(401);
    die(json_encode(array('error' => '缺少 API Token'), JSON_UNESCAPED_UNICODE));
}

global $m, $i;
$apiUserId = 0;
$apiUser = array();
$tokenHash = hash('sha256', $auth);
$tokenRow = array();
$usersHasTokenColumn = $m->once_fetch_array("SELECT COUNT(*) AS c FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = '" . $m->real_escape_string(DB_PREFIX . 'users') . "' AND column_name = 'api_token_hash'");
if (!empty($usersHasTokenColumn['c'])) {
    $tokenRow = $m->once_fetch_array("SELECT `id`, `name`, `t`, `role` FROM `" . DB_PREFIX . "users` WHERE `api_token_hash` = '" . $m->real_escape_string($tokenHash) . "' LIMIT 1");
}
if (!empty($tokenRow['id'])) {
    $apiUserId = (int) $tokenRow['id'];
    $apiUser = $tokenRow;
} else {
    //兼容旧版全局 Token；用户 Token 不存在时才回退到它。
    $globalToken = (string) option::get('api_token');
    if ($globalToken === '' || !hash_equals($globalToken, $auth)) {
        http_response_code(401);
        die(json_encode(array('error' => '认证失败'), JSON_UNESCAPED_UNICODE));
    }
}

/**
 * 返回 API Token 所属用户可访问的贴吧表。
 */
function api_user_tables($uid, $user)
{
    if ((int) $uid <= 0) {
        global $i;
        return isset($i['table']) && is_array($i['table']) ? $i['table'] : array('tieba');
    }
    $table = isset($user['t']) ? strtolower(trim((string) $user['t'])) : 'tieba';
    return preg_match('/^[a-z0-9_]{1,64}$/', $table) ? array($table) : array('tieba');
}

$action = request_text_value($_GET, 'action');
$apiTables = api_user_tables($apiUserId, $apiUser);

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
        $stat = SignManager::signSummary($apiUserId);
        echo json_encode(array('ok' => true, 'data' => $stat), JSON_UNESCAPED_UNICODE);
        break;

    case 'health':
        $rows = array();
        $healthWhere = $apiUserId > 0 ? " WHERE b.uid = {$apiUserId}" : '';
        $rs = $m->query("SELECT h.* FROM `" . DB_PREFIX . "baiduid_health` h LEFT JOIN `" . DB_PREFIX . "baiduid` b ON b.id = h.pid{$healthWhere} ORDER BY h.status ASC, h.fail_count DESC;");
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
        foreach ($apiTables as $value) {
            misc::DoSign($value, $apiUserId);
        }
        $duration = intval((microtime(true) - $start) * 1000);
        $stat = SignManager::signSummary($apiUserId);
        $msg = "[签到结束 " . date('H:i:s') . "] 已签到 {$stat['signed']} 个贴吧，出错 {$stat['error']} 个";
        SignManager::log($apiUserId, 'sign_api', $stat['error'] > 0 ? 'partial' : 'ok', $msg, $duration);
        echo json_encode(array('ok' => true, 'data' => $stat, 'msg' => $msg, 'duration_ms' => $duration), JSON_UNESCAPED_UNICODE);
        break;

    case 'retry':
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            http_response_code(405);
            die(json_encode(array('error' => 'POST required'), JSON_UNESCAPED_UNICODE));
        }
        ignore_user_abort(true);
        set_time_limit(0);
        foreach ($apiTables as $value) {
            misc::DoSign_retry($value, $apiUserId);
        }
        require_once SYSTEM_ROOT . '/lib/class.SignManager.php';
        $stat = SignManager::signSummary($apiUserId);
        echo json_encode(array('ok' => true, 'data' => $stat), JSON_UNESCAPED_UNICODE);
        break;

    case 'health_check':
        if ($apiUserId > 0) {
            http_response_code(403);
            die(json_encode(array('error' => '用户 API Token 无权执行全站健康检查'), JSON_UNESCAPED_UNICODE));
        }
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
        if ($apiUserId > 0) {
            $historyTable = DB_PREFIX . 'sign_history';
            $rs = $m->pquery("SELECT * FROM `{$historyTable}` WHERE `uid` = ? ORDER BY `id` DESC LIMIT ?", array($apiUserId, $limit));
        } else {
            //旧全局 Token 保持原有批次日志接口兼容
            $historyTable = DB_PREFIX . 'cron_log_batch';
            $rs = $m->pquery("SELECT * FROM `{$historyTable}` ORDER BY `id` DESC LIMIT ?", array($limit));
        }
        while ($r = $m->fetch_array($rs)) {
            $rows[] = $r;
        }
        echo json_encode(array('ok' => true, 'data' => $rows), JSON_UNESCAPED_UNICODE);
        break;

    case 'calendar':
        require_once SYSTEM_ROOT . '/lib/class.SignManager.php';
        $days = isset($_GET['days']) ? (int) $_GET['days'] : 90;
        echo json_encode(array('ok' => true, 'data' => SignManager::calendar($days, $apiUserId)), JSON_UNESCAPED_UNICODE);
        break;

    default:
        http_response_code(400);
        echo json_encode(array('error' => '未知 action', 'available' => array('update', 'status', 'health', 'sign', 'retry', 'health_check', 'logs', 'calendar')), JSON_UNESCAPED_UNICODE);
        break;
}
