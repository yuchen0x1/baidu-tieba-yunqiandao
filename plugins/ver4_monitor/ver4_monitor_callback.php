<?php

if (!defined('SYSTEM_ROOT')) {
    die('Insufficient Permissions');
}
/**
 * 安装插件时会被调用
 */
function callback_install()
{
}

/**
 * 激活插件时会被调用：注册计划任务（用户配置存于 users_options，由用户在个人面板管理）
 */
function callback_init()
{
    cron::set('ver4_monitor_do', 'plugins/ver4_monitor/cron/monitor.php', 4, '按用户配置扫描目标贴吧新帖并推送关键词命中提醒', 0);
}

/**
 * 禁用插件时会被调用：停用计划任务（用户配置与命中记录保留，重新激活即恢复）
 */
function callback_inactive()
{
    cron::del('ver4_monitor_do');
}

/**
 * 卸载插件时会被调用
 * 卸载插件前，如果插件是激活的，会自动禁用并调用 callback_inactive()
 */
function callback_remove()
{
}
