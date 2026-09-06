<?php

/**
 * Single source of truth for the application release metadata.
 * Entry points define SYSTEM_ROOT before loading this file.
 */
if (!defined('SYSTEM_FN')) {
    define('SYSTEM_FN', '百度贴吧云签到');
}
if (!defined('SYSTEM_VER')) {
    define('SYSTEM_VER', '7.2.3');
}
if (!defined('SYSTEM_VER_NOTE')) {
    define('SYSTEM_VER_NOTE', 'V7.2.3：升级完成后自动返回首页，无需手动确认');
}
