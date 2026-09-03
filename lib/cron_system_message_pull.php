<?php

if (!defined('SYSTEM_ROOT')) {
    die('Insufficient Permissions');
}

/**
 * 贴吧消息中心拉取计划任务（V6.1 Phase 1）
 */
function cron_system_message_pull()
{
    require_once SYSTEM_ROOT . '/lib/class.MessageCenter.php';
    return MessageCenter::pullAll();
}
