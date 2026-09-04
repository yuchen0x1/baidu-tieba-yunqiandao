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
    cron::set('ver4_reply_do', 'plugins/ver4_reply/cron/reply.php', 3, '按用户配置对目标贴吧自动回帖/发主题帖', 0);
}

/**
 * 禁用插件时会被调用：停用计划任务（用户配置保留，重新激活即恢复）
 */
function callback_inactive()
{
    cron::del('ver4_reply_do');
}

/**
 * 卸载插件时会被调用
 * 卸载插件前，如果插件是激活的，会自动禁用并调用 callback_inactive()
 */
function callback_remove()
{
}
