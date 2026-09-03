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
 * 激活插件时会被调用：注册计划任务、写入站点级默认配置
 * （用户个人配置存于 users_options 的 uai_*，由用户在个人面板自行管理，禁用插件时保留）
 */
function callback_init()
{
    option::set('ver4_ai_user_enable', '1');
    option::set('ver4_ai_shared_enable', '0');
    option::set('ver4_ai_api_url', 'https://open.bigmodel.cn/api/coding/paas/v4');
    option::set('ver4_ai_api_key', '');
    option::set('ver4_ai_model', 'glm-5.3-flash');
    option::set('ver4_ai_temperature', '0.9');
    option::set('ver4_ai_max_tokens', '1500');
    option::set('ver4_ai_persona', '你是一个百度贴吧的普通用户，说话自然、口语化、友善，像一个真实的贴吧老哥，熟悉贴吧文化。输出必须是纯中文文本，不要使用 markdown、不要加引号、不要提及你是AI或模型、不要用括号做解释。');
    cron::set('ver4_ai_do', 'plugins/ver4_ai/cron/ai.php', 2, 'AI 大模型定时发主题帖/智能回帖（按用户配置）', 0);
}

/**
 * 禁用插件时会被调用：清理站点级配置并停用计划任务
 * 用户个人配置（uai_*）与执行记录（ver4_ai_log）保留，重新激活即恢复
 */
function callback_inactive()
{
    $keys = array(
        'ver4_ai_user_enable', 'ver4_ai_shared_enable', 'ver4_ai_api_url', 'ver4_ai_api_key',
        'ver4_ai_model', 'ver4_ai_temperature', 'ver4_ai_max_tokens', 'ver4_ai_persona',
    );
    foreach ($keys as $key) {
        option::del($key);
    }
    cron::del('ver4_ai_do');
}

/**
 * 卸载插件时会被调用
 * 卸载插件前，如果插件是激活的，会自动禁用并调用 callback_inactive()
 */
function callback_remove()
{
}
