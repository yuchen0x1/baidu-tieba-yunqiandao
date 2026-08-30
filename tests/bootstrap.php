<?php
// 冒烟测试引导：以最小环境加载核心类，避免依赖数据库与真实网络。
define('SYSTEM_ROOT', dirname(__DIR__));
define('DB_PREFIX', 'test_');
require_once SYSTEM_ROOT . '/lib/request.php';
require_once SYSTEM_ROOT . '/lib/health.php';
require_once SYSTEM_ROOT . '/lib/class.misc.php';
require_once SYSTEM_ROOT . '/lib/class.SignManager.php';
require_once SYSTEM_ROOT . '/lib/class.P.php';
require_once SYSTEM_ROOT . '/lib/class.loginguard.php';
