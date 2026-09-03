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
 *   GET /api.php?action=streak         连续签到（当前/最大连签）
 *   GET /api.php?action=ranking&limit=20 用户签到排行榜
 *   GET /api.php?action=failures&limit=10 失败原因分布
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

// ---------- 按 Token 限流（V5.13 B6，选项 api_rate_limit，0=不限） ----------
$rateLimit = (int) option::get('api_rate_limit');
if ($rateLimit > 0) {
    $m->query("CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "api_rate_limits` (
        `token_hash` VARCHAR(64) NOT NULL DEFAULT '',
        `window_start` INT UNSIGNED NOT NULL DEFAULT 0,
        `count` INT UNSIGNED NOT NULL DEFAULT 0,
        PRIMARY KEY (`token_hash`, `window_start`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
    $window = (int) floor(time() / 60) * 60;
    $th = $m->real_escape_string($tokenHash);
    $rateRow = $m->once_fetch_array("SELECT `count` FROM `" . DB_PREFIX . "api_rate_limits` WHERE `token_hash` = '{$th}' AND `window_start` = {$window}");
    $rateCount = !empty($rateRow) ? (int) $rateRow['count'] : 0;
    if ($rateCount >= $rateLimit) {
        http_response_code(429);
        die(json_encode(array('error' => '请求过于频繁，请稍后再试', 'limit' => $rateLimit, 'window' => 60), JSON_UNESCAPED_UNICODE));
    }
    if ($rateCount === 0) {
        $m->pquery("INSERT INTO `" . DB_PREFIX . "api_rate_limits` (`token_hash`, `window_start`, `count`) VALUES (?, ?, 1) ON DUPLICATE KEY UPDATE `count` = `count` + 1", array($tokenHash, $window));
    } else {
        $m->pquery("UPDATE `" . DB_PREFIX . "api_rate_limits` SET `count` = `count` + 1 WHERE `token_hash` = ? AND `window_start` = ?", array($tokenHash, $window));
    }
}


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

    case 'streak':
        require_once SYSTEM_ROOT . '/lib/class.SignManager.php';
        echo json_encode(array('ok' => true, 'data' => SignManager::streak($apiUserId)), JSON_UNESCAPED_UNICODE);
        break;

    case 'ranking':
        require_once SYSTEM_ROOT . '/lib/class.SignManager.php';
        $limit = isset($_GET['limit']) ? (int) $_GET['limit'] : 20;
        echo json_encode(array('ok' => true, 'data' => SignManager::ranking($limit, $apiUserId)), JSON_UNESCAPED_UNICODE);
        break;

    case 'failures':
        require_once SYSTEM_ROOT . '/lib/class.SignManager.php';
        $limit = isset($_GET['limit']) ? (int) $_GET['limit'] : 10;
        echo json_encode(array('ok' => true, 'data' => SignManager::failureBreakdown($apiUserId, $limit)), JSON_UNESCAPED_UNICODE);
        break;

    case 'trend':
        require_once SYSTEM_ROOT . '/lib/class.SignManager.php';
        $days = isset($_GET['days']) ? max(1, min(365, (int) $_GET['days'])) : 30;
        echo json_encode(array('ok' => true, 'data' => SignManager::trend($days, $apiUserId)), JSON_UNESCAPED_UNICODE);
        break;

    case 'tieba_health':
        require_once SYSTEM_ROOT . '/lib/class.SignManager.php';
        $days = isset($_GET['days']) ? max(1, min(365, (int) $_GET['days'])) : 30;
        echo json_encode(array('ok' => true, 'data' => SignManager::tiebaHealth($apiUserId, $days)), JSON_UNESCAPED_UNICODE);
        break;

    case 'export':
        // CSV 导出（API Token 访问）。用户 Token 仅导出本人；全局 Token 导出全站。
        require_once SYSTEM_ROOT . '/lib/class.SignManager.php';
        $exportFilters = array(
            'uid'       => $apiUserId,
            'date_from' => isset($_GET['date_from']) ? trim((string) $_GET['date_from']) : '',
            'date_to'   => isset($_GET['date_to'])   ? trim((string) $_GET['date_to'])   : '',
            'tieba'     => isset($_GET['tieba'])     ? trim((string) $_GET['tieba'])     : '',
            'status'    => isset($_GET['status'])    ? trim((string) $_GET['status'])    : '',
        );
        $exportResult = SignManager::logs($exportFilters, 1, 5000);
        $exportRows   = $exportResult['rows'];
        $stamp = date('Ymd_His');
        $filename = 'sign-logs-' . $stamp . '.csv';
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        // UTF-8 BOM 让 Excel 正确识别编码
        echo "\xEF\xBB\xBF";
        echo csv_line(array('时间', '贴吧', '状态', '错误码', '信息', 'PID', 'UID'));
        foreach ($exportRows as $r) {
            echo csv_line(array(
                date('Y-m-d H:i:s', $r['created_at']),
                $r['tieba'],
                $r['status'],
                $r['error_code'] ? (string) $r['error_code'] : '',
                $r['message'],
                (string) $r['pid'],
                (string) $r['uid'],
            ));
        }
        exit;


    case 'webhook_test':
        if ($apiUserId > 0) {
            http_response_code(403);
            die(json_encode(array('error' => '仅管理员全局 Token 可测试 Webhook'), JSON_UNESCAPED_UNICODE));
        }
        require_once SYSTEM_ROOT . '/lib/class.webhook.php';
        $res = Webhook::push('test', array('version' => SYSTEM_VER));
        echo json_encode(array('ok' => $res['ok'], 'detail' => $res['detail']), JSON_UNESCAPED_UNICODE);
        break;

    case 'openapi':
        $doc = array(
            'name' => SYSTEM_FN,
            'version' => SYSTEM_VER,
            'auth' => 'Bearer Token（管理员全局 Token 或逐用户 Token）',
            'rate_limit_per_minute' => (int) option::get('api_rate_limit'),
            'endpoints' => array(
                array('action' => 'status', 'method' => 'GET', 'desc' => '签到状态汇总'),
                array('action' => 'health', 'method' => 'GET', 'desc' => '账号健康度'),
                array('action' => 'sign', 'method' => 'POST', 'desc' => '立即执行签到'),
                array('action' => 'retry', 'method' => 'POST', 'desc' => '执行复签'),
                array('action' => 'health_check', 'method' => 'POST', 'desc' => '执行健康检查'),
                array('action' => 'logs', 'method' => 'GET', 'desc' => '最近日志'),
                array('action' => 'calendar', 'method' => 'GET', 'desc' => '个人签到日历'),
                array('action' => 'trend', 'method' => 'GET', 'desc' => '签到趋势（每日成功/失败）'),
                array('action' => 'tieba_health', 'method' => 'GET', 'desc' => '每个贴吧的健康度统计'),
                array('action' => 'export', 'method' => 'GET', 'desc' => '导出签到日志 CSV'),
                array('action' => 'update', 'method' => 'GET', 'desc' => '在线版本检查'),
                array('action' => 'webhook_test', 'method' => 'GET', 'desc' => '测试 Webhook 推送（管理员）'),
            ),
        );
        echo json_encode(array('ok' => true, 'data' => $doc), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        break;

    default:
        http_response_code(400);
        echo json_encode(array('error' => '未知 action', 'available' => array('update', 'status', 'health', 'sign', 'retry', 'health_check', 'logs', 'calendar', 'export', 'streak', 'ranking', 'failures', 'trend', 'tieba_health', 'webhook_test', 'openapi')), JSON_UNESCAPED_UNICODE);
        break;
}
