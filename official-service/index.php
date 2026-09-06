<?php

/**
 * 官方服务网关（参考实现）——部署在私有基础设施，不属于开源产品的一部分。
 *
 * ⚠️ 本文件不含任何密钥。全部凭据来自环境变量：
 *   GW_TOKEN_TTL            Access Token 有效期秒数（默认 600）
 *   GW_ADMIN_TOKEN          运维管理接口令牌（必填，否则管理端点 503）
 *   GW_LICENSE_PRIVATE_KEY  License 签发私钥（PEM，与开源侧 lib/official-config.php 的公钥配对）
 *   PROVIDER_BASE_URL       上游 AI Provider 的 OpenAI 兼容基址（如 https://open.bigmodel.cn/api/paas/v4）
 *   PROVIDER_API_KEY        上游 Provider Key（生产环境应来自 Secret Manager，而非进程 env——见 README）
 *   PROVIDER_MODEL          默认模型（客户端请求的 model 仅在 GW_ALLOW_CLIENT_MODEL=1 时生效）
 *   GW_ALLOW_CLIENT_MODEL   是否允许客户端指定模型（默认 0：模型路由由服务端决定）
 *   GW_DAILY_QUOTA          每实例每日默认调用配额（默认 200，可按实例覆盖）
 *   GW_RATE_PER_MIN         每实例每分钟请求上限（默认 10）
 *   GW_DATA_DIR             SQLite 数据目录（默认 ./data，不入版本库）
 *
 * 与开源客户端（lib/class.official.php）共享的协议：
 *   签名串 = timestamp \n nonce \n path \n sha256_hex(body)，HMAC-SHA256，密钥为实例 Secret；
 *   Token  = TCS1.<b64url(payload)>.<b64url(hmac)>，签名密钥 = sha256("tcs-official-token-v1:" . 实例Secret)；
 *   时间窗 ±300 秒；nonce 一次性；权限最终裁决在本端。
 */

if (PHP_SAPI === 'cli') {
    official_gateway_cli($argv);
    exit(0);
}

official_gateway_handle();

// ===================== HTTP 入口 =====================

function official_gateway_handle()
{
    $method = isset($_SERVER['REQUEST_METHOD']) ? strtoupper((string) $_SERVER['REQUEST_METHOD']) : 'GET';
    $path = isset($_SERVER['REQUEST_URI']) ? parse_url((string) $_SERVER['REQUEST_URI'], PHP_URL_PATH) : '/';
    $path = '/' . trim((string) $path, '/');
    $body = (string) file_get_contents('php://input');

    header('Content-Type: application/json; charset=UTF-8');
    header('X-Content-Type-Options: nosniff');

    try {
        switch (true) {
            case $method === 'GET' && $path === '/healthz':
                gw_json(array('ok' => true, 'protocol' => 'v1'));
                break;

            case $method === 'POST' && $path === '/v1/token':
                gw_route_token($path, $body);
                break;

            case $method === 'POST' && $path === '/v1/ai/chat':
                gw_route_ai_chat($path, $body);
                break;

            case $method === 'POST' && $path === '/v1/admin/instance':
                gw_route_admin('instance', $body);
                break;

            case $method === 'POST' && $path === '/v1/license/issue':
                gw_route_admin('license', $body);
                break;

            default:
                http_response_code(404);
                gw_json(gw_error('not_found', '未知端点'));
        }
    } catch (Throwable $e) {
        error_log('[official-gateway] ' . $e->getMessage());
        http_response_code(500);
        gw_json(gw_error('internal', '服务内部错误'));
    }
}

// ===================== 业务路由 =====================

/** 实例凭据换 Access Token */
function gw_route_token($path, $body)
{
    $req = json_decode($body, true);
    if (!is_array($req) || empty($req['instance_id']) || !isset($req['scope'])) {
        gw_bad_request('请求缺少 instance_id 或 scope');
    }
    $instance = gw_instance((string) $req['instance_id']);
    if ($instance === false || $instance['status'] !== 'active') {
        gw_forbidden('实例不存在或已停用');
    }
    if (!gw_verify_signature($path, $body, $instance['secret'], (string) $req['instance_id'])) {
        gw_forbidden('签名校验失败');
    }
    $scope = gw_canonical_scope((string) $req['scope']);
    if ($scope === '') {
        gw_bad_request('非法 scope');
    }
    $now = time();
    $ttl = gw_env_int('GW_TOKEN_TTL', 600);
    $payload = array(
        'sub'   => $instance['instance_id'],
        'iat'   => $now,
        'exp'   => $now + $ttl,
        'scope' => $scope,
        'jti'   => bin2hex(random_bytes(12)),
    );
    $token = 'TCS1.' . gw_b64url(json_encode($payload)) . '.' . gw_b64url(gw_token_sign($payload, $instance['secret']));
    gw_json(array(
        'access_token' => $token,
        'token_type'   => 'Bearer',
        'expires_at'   => $payload['exp'],
        'expires_in'   => $ttl,
        'scope'        => $scope,
    ));
}

/** AI 代理：鉴权 → 配额/限流 → 上游 Provider（Provider Key 永不出端） */
function gw_route_ai_chat($path, $body)
{
    $instance = gw_auth_bearer('ai:generate');
    if (isset($instance['code'])) {
        gw_json($instance, 401);
        return;
    }
    if ($instance['status'] !== 'active') {
        gw_forbidden('实例已停用');
    }
    if (!gw_verify_signature($path, $body, $instance['secret'], $instance['instance_id'])) {
        gw_forbidden('签名校验失败');
    }
    if (!gw_consume_rate($instance, gw_env_int('GW_RATE_PER_MIN', 10))
        || !gw_consume_quota($instance, gw_env_int('GW_DAILY_QUOTA', 200))) {
        http_response_code(429);
        gw_json(gw_error('quota_exceeded', '超出配额或频率限制，请稍后再试'));
        return;
    }

    $base = rtrim((string) getenv('PROVIDER_BASE_URL'), '/');
    $apiKey = (string) getenv('PROVIDER_API_KEY');
    if ($base === '' || $apiKey === '') {
        http_response_code(503);
        gw_json(gw_error('provider_unconfigured', '上游 Provider 未配置'));
        return;
    }
    $req = json_decode($body, true);
    if (!is_array($req) || empty($req['messages']) || !is_array($req['messages'])) {
        gw_bad_request('缺少 messages');
    }
    if (strlen($body) > 64 * 1024) {
        gw_bad_request('请求体过大');
    }
    $upstream = array(
        'model'       => gw_env_int('GW_ALLOW_CLIENT_MODEL', 0) && !empty($req['model']) ? (string) $req['model'] : (string) getenv('PROVIDER_MODEL'),
        'messages'    => array_values($req['messages']),
        'temperature' => isset($req['temperature']) ? min(2, max(0, (float) $req['temperature'])) : 0.9,
        'max_tokens'  => isset($req['max_tokens']) ? min(8000, max(64, (int) $req['max_tokens'])) : 1500,
        'stream'      => false,
    );
    if ($upstream['model'] === '') {
        http_response_code(503);
        gw_json(gw_error('provider_unconfigured', '上游模型未配置'));
        return;
    }

    $ch = curl_init($base . '/chat/completions');
    curl_setopt_array($ch, array(
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($upstream, JSON_UNESCAPED_UNICODE),
        CURLOPT_HTTPHEADER     => array('Content-Type: application/json', 'Authorization: Bearer ' . $apiKey),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 90,
    ));
    $raw = curl_exec($ch);
    $curlErr = curl_error($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);
    if ($raw === false) {
        http_response_code(502);
        gw_json(gw_error('upstream_unavailable', '上游请求失败：' . $curlErr));
        return;
    }
    //上游 429/5xx 时不计入配额（退还本次预扣）
    if ($status === 429 || $status >= 500) {
        gw_refund_quota($instance);
    }
    http_response_code($status >= 100 ? $status : 502);
    header('Content-Type: application/json; charset=UTF-8');
    echo $raw;
}

// ===================== 管理端点（运维令牌） =====================

function gw_route_admin($kind, $body)
{
    $admin = (string) getenv('GW_ADMIN_TOKEN');
    if ($admin === '') {
        http_response_code(503);
        gw_json(gw_error('admin_disabled', 'GW_ADMIN_TOKEN 未配置，管理端点关闭'));
        return;
    }
    $given = isset($_SERVER['HTTP_AUTHORIZATION']) ? trim((string) $_SERVER['HTTP_AUTHORIZATION']) : '';
    if (stripos($given, 'Bearer ') === 0) {
        $given = trim(substr($given, 7));
    }
    if ($given === '' || !hash_equals($admin, $given)) {
        gw_forbidden('管理令牌校验失败');
    }
    $req = json_decode($body, true);
    if (!is_array($req)) {
        gw_bad_request('请求体不是合法 JSON');
    }

    if ($kind === 'instance') {
        $action = isset($req['action']) ? (string) $req['action'] : 'create';
        if ($action === 'create') {
            if (empty($req['instance_id'])) {
                gw_bad_request('缺少 instance_id');
            }
            $secret = bin2hex(random_bytes(32));
            gw_execute('INSERT OR REPLACE INTO gw_instances (instance_id, secret, plan, quota_daily, status, created_at)
                VALUES (?, ?, ?, ?, "active", ?)', array(
                (string) $req['instance_id'],
                $secret,
                (string) (isset($req['plan']) ? $req['plan'] : 'community'),
                isset($req['quota_daily']) ? (int) $req['quota_daily'] : gw_env_int('GW_DAILY_QUOTA', 200),
                time(),
            ));
            gw_json(array('instance_id' => (string) $req['instance_id'], 'secret' => $secret,
                'notice' => 'secret 仅本次返回，请立即写入实例后台'));
            return;
        }
        if ($action === 'revoke') {
            if (empty($req['instance_id'])) {
                gw_bad_request('缺少 instance_id');
            }
            gw_execute("UPDATE gw_instances SET status = 'revoked' WHERE instance_id = ?", array((string) $req['instance_id']));
            gw_json(array('ok' => true));
            return;
        }
        gw_bad_request('未知 action');
    }

    if ($kind === 'license') {
        $keyPem = (string) getenv('GW_LICENSE_PRIVATE_KEY');
        if ($keyPem === '' && getenv('GW_LICENSE_PRIVATE_KEY_FILE') !== false) {
            $keyPem = (string) file_get_contents((string) getenv('GW_LICENSE_PRIVATE_KEY_FILE'));
        }
        if ($keyPem === '') {
            http_response_code(503);
            gw_json(gw_error('license_disabled', 'GW_LICENSE_PRIVATE_KEY 未配置'));
            return;
        }
        foreach (array('license_id', 'plan', 'expires_at') as $field) {
            if (empty($req[$field])) {
                gw_bad_request('缺少 ' . $field);
            }
        }
        $license = array(
            'license_id' => (string) $req['license_id'],
            'user_id'    => (string) (isset($req['user_id']) ? $req['user_id'] : ''),
            'plan'       => (string) $req['plan'],
            'features'   => isset($req['features']) && is_array($req['features']) ? array_values(array_map('strval', $req['features'])) : array(),
            'issued_at'  => isset($req['issued_at']) ? (int) $req['issued_at'] : time(),
            'expires_at' => (int) $req['expires_at'],
            'device_binding' => (string) (isset($req['device_binding']) ? $req['device_binding'] : ''),
            'status'     => (string) (isset($req['status']) ? $req['status'] : 'active'),
        );
        $payload = json_encode($license, JSON_UNESCAPED_UNICODE);
        $key = openssl_pkey_get_private($keyPem);
        if ($key === false || !openssl_sign($payload, $sig, $key, OPENSSL_ALGO_SHA256)) {
            http_response_code(500);
            gw_json(gw_error('sign_failed', '私钥不可用或签名失败'));
            return;
        }
        gw_json(array(
            'payload'   => gw_b64url($payload),
            'signature' => gw_b64url($sig),
            'license'   => $license,
        ));
    }
}

// ===================== 鉴权原语 =====================

/** Bearer Token 校验；成功返回实例行，失败返回错误数组（调用方决定状态码） */
function gw_auth_bearer($neededScope)
{
    $header = isset($_SERVER['HTTP_AUTHORIZATION']) ? trim((string) $_SERVER['HTTP_AUTHORIZATION']) : '';
    if (stripos($header, 'Bearer ') !== 0) {
        return gw_error('token_invalid', '缺少 Bearer Token');
    }
    $token = trim(substr($header, 7));
    $parts = explode('.', $token);
    if (count($parts) !== 3 || $parts[0] !== 'TCS1') {
        return gw_error('token_invalid', '令牌格式非法');
    }
    $payload = json_decode(gw_b64url_decode($parts[1]), true);
    if (!is_array($payload) || empty($payload['sub']) || empty($payload['exp'])) {
        return gw_error('token_invalid', '令牌负载非法');
    }
    $instance = gw_instance((string) $payload['sub']);
    if ($instance === false) {
        return gw_error('token_invalid', '实例不存在');
    }
    $expected = gw_b64url(gw_token_sign($payload, $instance['secret']));
    if (!hash_equals($expected, $parts[2])) {
        return gw_error('token_invalid', '令牌签名不符');
    }
    if ((int) $payload['exp'] < time()) {
        return gw_error('token_expired', '令牌已过期');
    }
    if (!gw_scope_covers((string) ($payload['scope'] ?? ''), $neededScope)) {
        return gw_error('token_invalid', '令牌权限不足');
    }
    return $instance;
}

/** HMAC 请求签名 + 时间窗 + nonce 防重放（nonce 按实例隔离） */
function gw_verify_signature($path, $body, $secret, $instanceId)
{
    $timestamp = isset($_SERVER['HTTP_X_TCS_TIMESTAMP']) ? (string) $_SERVER['HTTP_X_TCS_TIMESTAMP'] : '';
    $nonce = isset($_SERVER['HTTP_X_TCS_NONCE']) ? (string) $_SERVER['HTTP_X_TCS_NONCE'] : '';
    $sig = isset($_SERVER['HTTP_X_TCS_SIGNATURE']) ? (string) $_SERVER['HTTP_X_TCS_SIGNATURE'] : '';
    if ($timestamp === '' || $nonce === '' || $sig === '' || strlen($nonce) < 16) {
        return false;
    }
    if (abs(time() - (int) $timestamp) > 300) {
        return false;
    }
    $stringToSign = $timestamp . "\n" . $nonce . "\n" . $path . "\n" . hash('sha256', $body);
    if (!hash_equals(hash_hmac('sha256', $stringToSign, $secret), $sig)) {
        return false;
    }
    if (!gw_nonce_consume($instanceId, $nonce)) {
        return false; //nonce 已用过：拒绝重放
    }
    return true;
}

/** Token 签名密钥：由实例 Secret 派生（与开源客户端 lib/class.official.php 对齐） */
function gw_token_sign(array $payload, $secret)
{
    $key = hash('sha256', 'tcs-official-token-v1:' . $secret);
    return hash_hmac('sha256', 'TCS1.' . gw_b64url(json_encode($payload)), $key, true);
}

function gw_canonical_scope($scope)
{
    $allowed = array('ai:generate', 'ai:*', 'license:read', 'admin:*');
    return in_array($scope, $allowed, true) ? $scope : '';
}

function gw_scope_covers($granted, $needed)
{
    if ($granted === $needed || $granted === 'admin:*') {
        return true;
    }
    $pos = strrpos($granted, ':*');
    return $pos !== false && strncmp($needed, $granted, $pos + 1) === 0;
}

// ===================== 存储（SQLite，零外部依赖） =====================

function gw_db()
{
    static $pdo = null;
    if ($pdo === null) {
        $dir = rtrim((string) (getenv('GW_DATA_DIR') !== false ? getenv('GW_DATA_DIR') : __DIR__ . '/data'), '/');
        if (!is_dir($dir)) {
            mkdir($dir, 0700, true);
        }
        $pdo = new PDO('sqlite:' . $dir . '/gateway.sqlite');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec("CREATE TABLE IF NOT EXISTS gw_instances (
            instance_id TEXT PRIMARY KEY,
            secret TEXT NOT NULL,
            plan TEXT NOT NULL DEFAULT 'community',
            quota_daily INTEGER NOT NULL DEFAULT 200,
            status TEXT NOT NULL DEFAULT 'active',
            created_at INTEGER NOT NULL
        )");
        $pdo->exec('CREATE TABLE IF NOT EXISTS gw_nonces (
            instance_id TEXT NOT NULL,
            nonce TEXT NOT NULL,
            created_at INTEGER NOT NULL,
            PRIMARY KEY (instance_id, nonce)
        )');
        $pdo->exec('CREATE TABLE IF NOT EXISTS gw_usage (
            instance_id TEXT NOT NULL,
            day TEXT NOT NULL,
            ai_calls INTEGER NOT NULL DEFAULT 0,
            PRIMARY KEY (instance_id, day)
        )');
        $pdo->exec('CREATE TABLE IF NOT EXISTS gw_rate (
            instance_id TEXT NOT NULL,
            window_start INTEGER NOT NULL,
            count INTEGER NOT NULL DEFAULT 0,
            PRIMARY KEY (instance_id, window_start)
        )');
        $pdo->exec('DELETE FROM gw_nonces WHERE created_at < ' . (time() - 3600));
    }
    return $pdo;
}

/** 参数化写操作（PDO::exec 不接受参数，统一走预处理） */
function gw_execute($sql, array $params = array())
{
    $st = gw_db()->prepare($sql);
    $st->execute($params);
    return $st->rowCount();
}

function gw_instance($id)
{
    $st = gw_db()->prepare('SELECT * FROM gw_instances WHERE instance_id = ?');
    $st->execute(array((string) $id));
    $row = $st->fetch(PDO::FETCH_ASSOC);
    return $row === false ? false : $row;
}

function gw_nonce_consume($instanceId, $nonce)
{
    try {
        gw_execute('INSERT INTO gw_nonces (instance_id, nonce, created_at) VALUES (?, ?, ?)', array((string) $instanceId, (string) $nonce, time()));
        return true;
    } catch (Throwable $e) {
        return false; //主键冲突 = 重放
    }
}

function gw_consume_quota($instance, $defaultQuota)
{
    $day = date('Y-m-d');
    $quota = (int) $instance['quota_daily'] > 0 ? (int) $instance['quota_daily'] : $defaultQuota;
    gw_execute('INSERT INTO gw_usage (instance_id, day, ai_calls) VALUES (?, ?, 1)
        ON CONFLICT(instance_id, day) DO UPDATE SET ai_calls = ai_calls + 1', array($instance['instance_id'], $day));
    $st = gw_db()->prepare('SELECT ai_calls FROM gw_usage WHERE instance_id = ? AND day = ?');
    $st->execute(array($instance['instance_id'], $day));
    $row = $st->fetch(PDO::FETCH_ASSOC);
    return $row !== false && (int) $row['ai_calls'] <= $quota;
}

function gw_refund_quota($instance)
{
    gw_execute('UPDATE gw_usage SET ai_calls = MAX(ai_calls - 1, 0) WHERE instance_id = ? AND day = ?',
        array($instance['instance_id'], date('Y-m-d')));
}

function gw_consume_rate($instance, $perMinute)
{
    $window = (int) (time() / 60) * 60;
    gw_execute('INSERT INTO gw_rate (instance_id, window_start, count) VALUES (?, ?, 1)
        ON CONFLICT(instance_id, window_start) DO UPDATE SET count = count + 1', array($instance['instance_id'], $window));
    $st = gw_db()->prepare('SELECT count FROM gw_rate WHERE instance_id = ? AND window_start = ?');
    $st->execute(array($instance['instance_id'], $window));
    $row = $st->fetch(PDO::FETCH_ASSOC);
    return $row !== false && (int) $row['count'] <= $perMinute;
}

// ===================== 工具 =====================

function gw_json($data, $code = 200)
{
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

function gw_error($code, $message)
{
    return array('error' => $message, 'code' => $code);
}

function gw_bad_request($message)
{
    http_response_code(400);
    gw_json(gw_error('bad_request', $message));
    exit;
}

function gw_forbidden($message)
{
    http_response_code(403);
    gw_json(gw_error('forbidden', $message));
    exit;
}

function gw_b64url($raw)
{
    return rtrim(strtr(base64_encode((string) $raw), '+/', '-_'), '=');
}

function gw_b64url_decode($data)
{
    return base64_decode(strtr((string) $data, '-_', '+/'), true);
}

function gw_env_int($name, $default)
{
    $v = getenv($name);
    return $v === false || $v === '' ? $default : (int) $v;
}

// ===================== CLI（运维初始化，仅限本机/内网执行） =====================

function official_gateway_cli($argv)
{
    $cmd = isset($argv[1]) ? (string) $argv[1] : '';
    if ($cmd === 'initdb') {
        gw_db();
        echo "数据库已初始化：" . (getenv('GW_DATA_DIR') !== false ? getenv('GW_DATA_DIR') : __DIR__ . '/data') . "/gateway.sqlite\n";
        return;
    }
    echo "用法：\n";
    echo "  php index.php initdb          初始化 SQLite 存储\n";
    echo "实例注册/吊销/签发 License 通过管理端点（GW_ADMIN_TOKEN）调用，见 README.md。\n";
}
