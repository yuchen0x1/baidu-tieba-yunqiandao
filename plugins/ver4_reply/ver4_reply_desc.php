<?php

if (!defined('SYSTEM_ROOT')) {
    die('Insufficient Permissions');
}
//没有标注必填的，都是选填，但是按照规范，请保留该键，只需将值留空即可
return array(
    'plugin' => array(
        'name'        => '云端自动回帖/发帖',
        'version'     => '1.2',
        'description' => '在个人面板配置目标贴吧与内容库，为自己的百度账号定时自动回帖与发主题帖：楼中楼比例可调、按天去重、每吧限量、每日上限、随机间隔。',
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
        'setting'     => false,
        'show'        => true,
        'vip'         => false,
        'private'     => false,
        'public'      => false,
        'update'      => false,
    ),
    'page'       => array('show'),
);
