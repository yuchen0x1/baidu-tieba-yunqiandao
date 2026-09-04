<?php

if (!defined('SYSTEM_ROOT')) {
    die('Insufficient Permissions');
}
//没有标注必填的，都是选填，但是按照规范，请保留该键，只需将值留空即可
return array(
    'plugin' => array(
        'name'        => '帖子监控',
        'version'     => '1.3',
        'description' => '在个人面板配置目标贴吧与关键词（支持排除词），系统定时扫描最新主题帖，命中后推送到你的个人通知渠道（Server酱/TG/Bark 等，可回退站点渠道），并保留命中历史。',
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
