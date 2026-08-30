<?php

define('SYSTEM_ROOT', dirname(__FILE__));
require_once SYSTEM_ROOT . '/lib/request.php';
require_once SYSTEM_ROOT . '/lib/health.php';

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store');
$health = project_health_check(SYSTEM_ROOT, request_text_value($_GET, 'mode', 'ready'));
http_response_code($health['ok'] ? 200 : 503);
echo json_encode($health, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
