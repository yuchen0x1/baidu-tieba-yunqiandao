<?php

/**
 * Single source of truth for the application release metadata.
 * Entry points define SYSTEM_ROOT before loading this file.
 */
if (!defined('SYSTEM_FN')) {
    define('SYSTEM_FN', '百度贴吧云签到');
}
if (!defined('SYSTEM_VER')) {
    define('SYSTEM_VER', '7.3.1');
}
if (!defined('SYSTEM_VER_NOTE')) {
    define('SYSTEM_VER_NOTE', 'V7.3.1：更新通道限流回退与部署可靠性修复');
}
