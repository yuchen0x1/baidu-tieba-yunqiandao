<?php

if (!defined('SYSTEM_ROOT')) {
    die('Insufficient Permissions');
}
//没有标注必填的，都是选填，但是按照规范，请保留该键，只需将值留空即可
return array(
    'plugin' => array(
        'name'        => 'AI 大模型发帖/回帖',
        'version'     => '1.0',
        'description' => '接入 OpenAI 兼容大模型（智谱/DeepSeek/中转等）：定时 AI 发主题帖（每日/每小时）、根据帖子内容智能回帖，人设与主题方向可配置，支持兜底内容库与账号级代理。',
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
        'setting'     => true,
        'show'        => false,
        'vip'         => false,
        'private'     => false,
        'public'      => false,
        'update'      => false,
    ),
    'page'       => array('setting'),
);
