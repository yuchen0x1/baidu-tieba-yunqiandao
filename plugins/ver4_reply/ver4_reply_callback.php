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
 * 激活插件时会被调用
 */
function callback_init()
{
    option::set('ver4_reply_kws', '');
    option::set('ver4_reply_contents', '');
    option::set('ver4_reply_num', '3');
    option::set('ver4_reply_daily', '10');
    option::set('ver4_reply_sleep', '3000');
    cron::set('ver4_reply_do', 'plugins/ver4_reply/cron/reply.php', 3, '自动对目标贴吧的最新主题帖回帖', 0);
}

/**
 * 禁用插件时会被调用
 */
function callback_inactive()
{
    option::del('ver4_reply_kws');
    option::del('ver4_reply_contents');
    option::del('ver4_reply_num');
    option::del('ver4_reply_daily');
    option::del('ver4_reply_sleep');
    cron::del('ver4_reply_do');
}

/**
 * 卸载插件时会被调用
 * 卸载插件前，如果插件是激活的，会自动禁用并调用 callback_inactive()
 */
function callback_remove()
{
}
