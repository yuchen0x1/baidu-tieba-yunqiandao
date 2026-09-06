<?php

/**
 * Single source of truth for the application release metadata.
 * Entry points define SYSTEM_ROOT before loading this file.
 */
if (!defined('SYSTEM_FN')) {
    define('SYSTEM_FN', '百度贴吧云签到');
}
if (!defined('SYSTEM_VER')) {
    define('SYSTEM_VER', '7.0');
}
if (!defined('SYSTEM_VER_NOTE')) {
    define('SYSTEM_VER_NOTE', 'V7.0：升级治理、可恢复数据库迁移与发布校验');
}
