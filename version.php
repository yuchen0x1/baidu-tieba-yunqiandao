<?php

/**
 * Single source of truth for the application release metadata.
 * Entry points define SYSTEM_ROOT before loading this file.
 */
if (!defined('SYSTEM_FN')) {
    define('SYSTEM_FN', '百度贴吧云签到');
}
if (!defined('SYSTEM_VER')) {
    define('SYSTEM_VER', '7.4.0');
}
if (!defined('SYSTEM_VER_NOTE')) {
    define('SYSTEM_VER_NOTE', 'V7.4.0：凭据掩码与恢复校验等安全加固');
}
