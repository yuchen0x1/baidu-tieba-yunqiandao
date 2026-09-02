<?php

if (!defined('SYSTEM_ROOT')) {
    die('Insufficient Permissions');
}
//没有标注必填的，都是选填，但是按照规范，请保留该键，只需将值留空即可
return array(
    'plugin' => array(
        'name'        => '云点赞',                                  //插件名称，必填
        'version'     => '1.0',                                    //插件版本号
        'description' => '自动为指定贴吧的最新帖子点赞（客户端接口），支持多账号、多贴吧、每吧限量与随机间隔。',  //插件描述
        'onsale'      => false,                                    //bool 插件是否已在产品中心上架
        'url'         => 'https://github.com/Starry-OvO/aiotieba', //接口实现参考 aiotieba
        'for'         => 'all',                                    //适用的云签到版本
        'forphp'      => '7.0'                                     //适用的最低PHP版本
    ),
    'author' => array(
        'author'      => 'yuchen0x1',                              //作者名称
        'email'       => '',                                       //作者邮箱
        'url'         => 'https://github.com/yuchen0x1'            //作者网站
    ),
    'view'   => array(
        'setting'     => true,
        'show'        => false,
        'vip'         => false,
        'private'     => false,
        'public'      => false,
        'update'      => false,
    ),
    'page'       => array('setting'),  //插件自定义页面的代号，此键必填，可省略（省略后无法使用自定义页面）
);
