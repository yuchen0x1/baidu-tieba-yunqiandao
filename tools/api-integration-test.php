<?php
/**
 * 开放 API 合同集成测试（真实 HTTP + MySQL）
 * 以 PHP 内置服务器托管项目副本，端到端验证 V7.0 API 合同：
 *   认证（401/错误码）→ 成功响应信封（api_version）→ 方法门禁（405）→
 *   权限矩阵（403：全局/个人 Token 边界）→ unknown_action（400）→ openapi 文档完整性
 *
 * 用法与环境变量同 tools/migrate-integration-test.php；数据库不可达时自动跳过。
 */

if (PHP_SAPI !== 'cli') {
    die('仅支持命令行运行：php tools/api-integration-test.php');
}

require __DIR__ . '/lib-integration.php';

function check($name, $ok)
{
    it_check($name, $ok);
}

list($conn, $port) = it_connect_db();
$repoRoot = dirname(__DIR__);
$workRoot = sys_get_temp_dir() . '/tcs-it-api-' . getmypid() . '-' . mt_rand(1000, 9999);
$db = 'tcs_it_api_' . mt_rand(1000, 9999);
$prefix = 'tci_';
$serverPort = mt_rand(8200, 8999);
$globalToken = 'global-it-token-' . mt_rand(1000, 9999);
$userToken = 'user-it-token-' . mt_rand(1000, 9999);
$serverProc = null;

/**
 * 发起 HTTP 请求，返回 [status, decodedBody]
 */
function http($method, $url, $token = null)
{
    $options = array(
        'method' => $method,
        'ignore_errors' => true,
        'timeout' => 10,
        'protocol_version' => 1.0,
        'header' => "Content-Type: application/json\r\nConnection: close\r\n",
    );
    if ($token !== null) {
        $options['header'] .= "Authorization: Bearer {$token}\r\n";
    }
    $context = stream_context_create(array('http' => $options));
    $body = @file_get_contents($url, false, $context);
    $status = 0;
    foreach ($http_response_header ?? array() as $header) {
        if (preg_match('#^HTTP/\S+\s+(\d+)#', $header, $m)) {
            $status = (int) $m[1];
        }
    }
    $decoded = json_decode((string) $body, true);
    return array($status, $decoded);
}

try {
    // ---------- 准备副本与数据 ----------
    if (!it_copy_project($repoRoot, $workRoot)) {
        die("FAIL - 无法创建项目副本\n");
    }
    file_put_contents($workRoot . '/setup/install.lock', '1');
    check('写入测试配置', it_write_config($workRoot, $db, $prefix, $port));
    $conn->query("DROP DATABASE IF EXISTS `{$db}`");
    $conn->query("CREATE DATABASE `{$db}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $template = file_get_contents($workRoot . '/setup/install.template.sql');
    $template = str_replace('{VAR-PREFIX}', $prefix, $template);
    $template = str_replace('{VAR-DB}', $db, $template);
    $template = str_replace('{VAR-COOKIE-SALT}', bin2hex(random_bytes(32)), $template);
    $template = str_replace('{VAR-SYSTEM-URL}', 'http://127.0.0.1', $template);
    check('导入全新安装模板', it_import_sql($conn, $db, $template, $prefix));

    // 全局 Token 存哈希；个人 Token 建独立用户
    $conn->select_db($db);
    foreach (array(
        "UPDATE `{$prefix}options` SET `value` = '" . hash('sha256', $globalToken) . "' WHERE `name` = 'api_token'",
        "UPDATE `{$prefix}options` SET `value` = '5' WHERE `name` = 'api_rate_limit'",
        "INSERT INTO `{$prefix}users` (`name`, `pw`, `email`, `role`, `t`, `api_token_hash`) VALUES ('apiuser', 'x', 'api-it@example.test', 'user', 'tieba', '" . hash('sha256', $userToken) . "')",
    ) as $sql) {
        $conn->query($sql);
        it_drain($conn);
    }

    // ---------- 启动内置服务器 ----------
    // 服务器日志写入文件而非管道：管道无人读取时缓冲写满会阻塞内置服务器
    $nullDevice = DIRECTORY_SEPARATOR === '\\' ? 'NUL' : '/dev/null';
    $serverLog = sys_get_temp_dir() . '/tcs-it-api-server.log';
    $serverProc = proc_open(
        escapeshellarg(PHP_BINARY) . ' -S 127.0.0.1:' . $serverPort . ' -t ' . escapeshellarg($workRoot),
        array(
            0 => array('file', $nullDevice, 'r'),
            1 => array('file', $serverLog, 'w'),
            2 => array('file', $serverLog, 'w'),
        ),
        $pipes,
        null,
        null,
        // 绕过 cmd 包装：proc_get_status 的 PID 即服务器进程，taskkill 可直接命中
        array('bypass_shell' => true)
    );
    check('启动 PHP 内置服务器', is_resource($serverProc));
    usleep(800000);
    $base = "http://127.0.0.1:{$serverPort}/api.php";

    // ---------- 认证 ----------
    list($status, $body) = http('GET', $base . '?action=status');
    check('缺少 Token 返回 401/missing_token', $status === 401 && ($body['code'] ?? '') === 'missing_token');
    check('错误体携带 api_version', ($body['api_version'] ?? '') === 'v1');
    list($status, $body) = http('GET', $base . '?action=status', 'wrong-token');
    check('错误 Token 返回 401/auth_failed', $status === 401 && ($body['code'] ?? '') === 'auth_failed');

    // ---------- 成功响应与作用域 ----------
    list($status, $body) = http('GET', $base . '?action=status', $globalToken);
    check('全局 Token status 返回 200', $status === 200 && ($body['ok'] ?? false) === true);
    check('成功信封携带 api_version', ($body['api_version'] ?? '') === 'v1');
    check('data 为结构化统计数据', isset($body['data']) && is_array($body['data']));

    // ---------- 方法门禁 ----------
    list($status, $body) = http('GET', $base . '?action=sign', $globalToken);
    check('GET 访问 POST 接口返回 405/method_not_allowed', $status === 405 && ($body['code'] ?? '') === 'method_not_allowed');

    // ---------- 权限矩阵 ----------
    list($status, $body) = http('GET', $base . '?action=update', $userToken);
    check('个人 Token 访问 update 返回 403/forbidden_user_token', $status === 403 && ($body['code'] ?? '') === 'forbidden_user_token');
    list($status, $body) = http('GET', $base . '?action=webhook_test', $userToken);
    check('个人 Token 访问 webhook_test 返回 403', $status === 403 && ($body['code'] ?? '') === 'forbidden_user_token');
    list($status, $body) = http('GET', $base . '?action=monitor', $globalToken);
    check('全局 Token 访问 monitor 返回 403/forbidden_global_token', $status === 403 && ($body['code'] ?? '') === 'forbidden_global_token');
    list($status, $body) = http('GET', $base . '?action=monitor&limit=5', $userToken);
    check('个人 Token 访问 monitor 返回 200', $status === 200 && ($body['ok'] ?? false) === true && ($body['data'] ?? null) === array());
    list($status, $body) = http('GET', $base . '?action=health_check', $userToken);
    check('个人 Token 用 GET 访问 health_check 优先返回 405', $status === 405 && ($body['code'] ?? '') === 'method_not_allowed');

    // ---------- unknown action 与 openapi ----------
    list($status, $body) = http('GET', $base . '?action=no_such', $globalToken);
    check('未知 action 返回 400/unknown_action 并列出可用接口', $status === 400 && ($body['code'] ?? '') === 'unknown_action' && in_array('status', $body['available'] ?? array(), true));
    list($status, $body) = http('GET', $base . '?action=openapi', $globalToken);
    $endpoints = $body['data']['endpoints'] ?? array();
    $named = array_column($endpoints, null, 'action');
    check('openapi 返回 200', $status === 200);
    check('openapi 覆盖全部 action（除自身）', !isset($named['openapi']) && isset($named['status']) && isset($named['monitor']) && isset($named['update']));
    check('openapi 端点携带访问级别', ($named['update']['access'] ?? '') === 'global' && ($named['monitor']['access'] ?? '') === 'user');

    // ---------- 限流（api_rate_limit 调为 5：同一 Token 第 6 次起触发 429） ----------
    $rateStatus = 0;
    $rateBody = array();
    for ($i = 0; $i < 12; $i++) {
        list($rateStatus, $rateBody) = http('GET', $base . '?action=streak', $userToken);
        if ($rateStatus === 429 || $body === false) {
            break;
        }
    }
    check('超过限流阈值返回 429/rate_limited', $rateStatus === 429 && ($rateBody['code'] ?? '') === 'rate_limited');
} catch (Throwable $e) {
    check('无未捕获异常', false);
    echo '异常：' . $e->getMessage() . "\n";
} finally {
    if (is_resource($serverProc)) {
        // Windows 下 proc_terminate/proc_close 可能无限等待，按 PID 强杀
        $status = proc_get_status($serverProc);
        $pid = (int) $status['pid'];
        if (DIRECTORY_SEPARATOR === '\\') {
            exec('taskkill /PID ' . $pid . ' /F 2>NUL');
        } else {
            exec('kill -9 ' . $pid . ' 2>/dev/null');
        }
        proc_close($serverProc);
    }
    $conn->query("DROP DATABASE IF EXISTS `{$db}`");
    $conn->close();
    if (strpos($workRoot, 'tcs-it-api-') !== false && is_dir($workRoot)) {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($workRoot, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($iterator as $item) {
            $item->isDir() ? @rmdir((string) $item) : @unlink((string) $item);
        }
        @rmdir($workRoot);
    }
}

it_finish();
