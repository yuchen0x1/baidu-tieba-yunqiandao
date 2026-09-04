<?php

if (!defined('SYSTEM_ROOT')) {
    die('Insufficient Permissions');
}
//没有标注必填的，都是选填，但是按照规范，请保留该键，只需将值留空即可
return array(
    'plugin' => array(
        'name'        => '帖子监控',
        'version'     => '1.0',
        'description' => '在个人面板配置目标贴吧与关键词，系统定时扫描最新主题帖，命中关键词立即通过站点通知渠道推送提醒，并保留命中历史（支持监控全部新帖）。',
        'onsale'      => false,
        'url'         => 'https://gitee.com/yuchen0x1/baidu-tieba-yunqiandao',
        'for'         => 'all',
        'forphp'      => '7.0'
    ),
    'author' => array(
        'author'      => 'yuchen0x1',
        'email'       => '',
        'url'         => 'https://gitee.com/yuchen0x1'
    ),
    'view'   => array(
        'setting'     => false,
        'show'        => true,
        'vip'         => false,
        'private'     => false,
        'public'      => false,
        'update'      => false,
    ),
    'page'       => array('show'),
);
