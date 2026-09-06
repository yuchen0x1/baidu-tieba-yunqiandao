<?php

/**
 * Single source of truth for the application release metadata.
 * Entry points define SYSTEM_ROOT before loading this file.
 */
if (!defined('SYSTEM_FN')) {
    define('SYSTEM_FN', '百度贴吧云签到');
}
if (!defined('SYSTEM_VER')) {
    define('SYSTEM_VER', '7.2.1');
}
if (!defined('SYSTEM_VER_NOTE')) {
    define('SYSTEM_VER_NOTE', 'V7.2.1：升级门禁提示以命令行为第一路径——/setup/ 被 Web 服务器封禁的站点不再被 404 卡死');
}
