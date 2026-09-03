<?php

define('SYSTEM_ROOT', dirname(__FILE__));
require_once SYSTEM_ROOT . '/lib/request.php';
require_once SYSTEM_ROOT . '/lib/health.php';

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store');
// 仅接受枚举值，杜绝任意输入传入探针
$modeMap = array('live' => 'live', 'ready' => 'ready');
$mode = isset($_GET['mode']) && isset($modeMap[(string) $_GET['mode']]) ? $modeMap[(string) $_GET['mode']] : 'ready';
$health = project_health_check(SYSTEM_ROOT, $mode);
http_response_code($health['ok'] ? 200 : 503);
echo json_encode($health, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
