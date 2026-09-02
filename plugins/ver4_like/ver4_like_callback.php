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
    option::set('ver4_like_kws', '');
    option::set('ver4_like_num', '5');
    option::set('ver4_like_sleep', '1500');
    cron::set('ver4_like_do', 'plugins/ver4_like/cron/like.php', 2, '自动为指定贴吧的最新帖子点赞', 0);
}

/**
 * 禁用插件时会被调用
 */
function callback_inactive()
{
    option::del('ver4_like_kws');
    option::del('ver4_like_num');
    option::del('ver4_like_sleep');
    cron::del('ver4_like_do');
}

/**
 * 卸载插件时会被调用
 * 卸载插件前，如果插件是激活的，会自动禁用并调用 callback_inactive()
 */
function callback_remove()
{
}
