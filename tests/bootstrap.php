<?php
// 冒烟测试引导：以最小环境加载核心类，避免依赖数据库与真实网络。
define('SYSTEM_ROOT', dirname(__DIR__));
require_once SYSTEM_ROOT . '/lib/class.misc.php';
require_once SYSTEM_ROOT . '/lib/class.SignManager.php';
