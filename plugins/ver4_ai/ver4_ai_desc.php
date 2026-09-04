<?php

if (!defined('SYSTEM_ROOT')) {
    die('Insufficient Permissions');
}
//没有标注必填的，都是选填，但是按照规范，请保留该键，只需将值留空即可
return array(
    'plugin' => array(
        'name'        => 'AI 大模型发帖/回帖',
        'version'     => '2.2',
        'description' => '用户在个人面板配置自己的大模型（OpenAI 兼容）后，可对自己的百度账号开启 AI 定时发主题帖（每日/每小时、全自动或生成候选半自动发布）与智能回帖；支持自定义人设模板与内容审查拦截记录排查；管理员可控制站点开关并提供可选共享配置。',
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
        'show'        => true,
        'vip'         => false,
        'private'     => false,
        'public'      => false,
        'update'      => false,
    ),
    'page'       => array('setting', 'show'),
);
