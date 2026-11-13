<?php
// 仅由测试的本地 PHP 内置服务器使用，不向外部服务发送消息。
if (PHP_SAPI !== 'cli-server') {
    http_response_code(404);
    exit;
}
http_response_code((int) ($_GET['status'] ?? 200));
header('Content-Type: application/json');
echo $_GET['body'] ?? '';
