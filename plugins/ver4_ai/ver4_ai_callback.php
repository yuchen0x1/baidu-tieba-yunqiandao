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
    option::set('ver4_ai_api_url', 'https://open.bigmodel.cn/api/coding/paas/v4');
    option::set('ver4_ai_api_key', '');
    option::set('ver4_ai_model', 'glm-5.3-flash');
    option::set('ver4_ai_temperature', '0.9');
    option::set('ver4_ai_max_tokens', '1500');
    option::set('ver4_ai_persona', '你是一个百度贴吧的普通用户，说话自然、口语化、友善，像一个真实的贴吧老哥，熟悉贴吧文化。输出必须是纯中文文本，不要使用 markdown、不要加引号、不要提及你是AI或模型、不要用括号做解释。');
    //AI 回帖
    option::set('ver4_ai_reply_enable', '0');
    option::set('ver4_ai_reply_kws', '');
    option::set('ver4_ai_reply_pids', '');
    option::set('ver4_ai_reply_num', '2');
    option::set('ver4_ai_reply_daily', '10');
    option::set('ver4_ai_reply_interval', '60');
    option::set('ver4_ai_reply_floor_rate', '0');
    option::set('ver4_ai_reply_context', '1');
    option::set('ver4_ai_reply_fallback', '');
    //AI 发主题帖
    option::set('ver4_ai_thread_enable', '0');
    option::set('ver4_ai_thread_kws', '');
    option::set('ver4_ai_thread_pids', '');
    option::set('ver4_ai_thread_mode', 'daily');
    option::set('ver4_ai_thread_hour', '9');
    option::set('ver4_ai_thread_every', '1');
    option::set('ver4_ai_thread_daily', '3');
    option::set('ver4_ai_thread_topic', '分享日常见闻、生活感悟、兴趣爱好等积极向上的内容');
    cron::set('ver4_ai_do', 'plugins/ver4_ai/cron/ai.php', 2, 'AI 大模型定时发主题帖/智能回帖', 0);
}

/**
 * 禁用插件时会被调用
 */
function callback_inactive()
{
    $keys = array(
        'ver4_ai_api_url', 'ver4_ai_api_key', 'ver4_ai_model', 'ver4_ai_temperature', 'ver4_ai_max_tokens', 'ver4_ai_persona',
        'ver4_ai_reply_enable', 'ver4_ai_reply_kws', 'ver4_ai_reply_pids', 'ver4_ai_reply_num', 'ver4_ai_reply_daily',
        'ver4_ai_reply_interval', 'ver4_ai_reply_floor_rate', 'ver4_ai_reply_context', 'ver4_ai_reply_fallback', 'ver4_ai_reply_lastrun',
        'ver4_ai_thread_enable', 'ver4_ai_thread_kws', 'ver4_ai_thread_pids', 'ver4_ai_thread_mode', 'ver4_ai_thread_hour',
        'ver4_ai_thread_every', 'ver4_ai_thread_daily', 'ver4_ai_thread_topic',
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
