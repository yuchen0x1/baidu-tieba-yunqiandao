<?php

if (!defined('SYSTEM_ROOT')) {
    die('Insufficient Permissions');
}
//没有标注必填的，都是选填，但是按照规范，请保留该键，只需将值留空即可
return array(
    'plugin' => array(
        'name'        => '云端自动回帖/发帖',
        'version'     => '1.1',
        'description' => '定时对目标贴吧自动回帖与发主题帖：内容库随机、楼中楼比例可调、按天去重、每吧限量、每账号每日上限、随机间隔，支持账号级代理。',
        'onsale'      => false,
        'url'         => 'https://github.com/kookxiang/Tieba_Sign',
        'for'         => 'all',
        'forphp'      => '7.0'
    ),
    'author' => array(
        'author'      => 'yuchen0x1',
        'email'       => '',
        'url'         => 'https://github.com/yuchen0x1'
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
