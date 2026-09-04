<?php

if (!defined('SYSTEM_ROOT')) {
    die('Insufficient Permissions');
}

/**
 * 数据保留与自动清理计划任务
 * 按工具箱配置的保留天数清理历史数据，0 表示该类数据永久保留：
 *   - sign_history 签到历史          cleanup_sign_history_days（默认 180）
 *   - task_runs 任务运行历史         cleanup_task_runs_days（默认 90）
 *   - cron_log_batch 批量签到日志    cleanup_cron_log_days（默认 30）
 *   - ver4_ai_log AI 执行记录        cleanup_plugin_log_days（默认 90）
 *   - ver4_monitor_hit 帖子监控命中  cleanup_plugin_log_days（默认 90）
 *   - ver4_reply_log 自动回帖记录    cleanup_plugin_log_days（默认 90）
 *   - api_rate_limits API 限流记录   cleanup_api_rate_days（默认 7）
 * 插件表不存在时（未安装对应插件）自动跳过。计划任务行 system_cleanup 需已注册，
 * 旧版本可在工具箱点击「注册自动清理任务」补齐。
 */
function cron_system_cleanup()
{
    global $m;
    if (option::get('cleanup_enable') != '1') {
        return '自动清理未启用，跳过执行';
    }
    $jobs = array(
        array('sign_history', (int) option::get('cleanup_sign_history_days'), '签到历史', 'created_at'),
        array('task_runs', (int) option::get('cleanup_task_runs_days'), '任务运行历史', 'started_at'),
        array('cron_log_batch', (int) option::get('cleanup_cron_log_days'), '批量签到日志', 'created_at'),
        array('ver4_ai_log', (int) option::get('cleanup_plugin_log_days'), 'AI 执行记录', 'created_at'),
        array('ver4_monitor_hit', (int) option::get('cleanup_plugin_log_days'), '帖子监控命中', 'created_at'),
        array('ver4_reply_log', (int) option::get('cleanup_plugin_log_days'), '自动回帖记录', 'created_at'),
        array('api_rate_limits', (int) option::get('cleanup_api_rate_days'), 'API 限流记录', 'window_start'),
    );
    $summary = array();
    $removed = 0;
    foreach ($jobs as $job) {
        list($table, $days, $label, $col) = $job;
        if ($days <= 0) {
            continue; //0=永久保留
        }
        //表可能不存在（未安装对应插件/旧版本），跳过
        $exists = $m->once_fetch_array("SHOW TABLES LIKE '" . str_replace('_', '\\_', DB_PREFIX . $table) . "'");
        if (empty($exists)) {
            continue;
        }
        $cutoff = time() - $days * 86400;
        $m->pquery("DELETE FROM `" . DB_PREFIX . $table . "` WHERE `" . $col . "` < ?", array($cutoff));
        $deleted = (int) $m->affected_rows();
        if ($deleted > 0) {
            $summary[] = $label . ' ' . $deleted . ' 条（保留 ' . $days . ' 天）';
        }
        $removed += $deleted;
    }
    return $removed > 0 ? '已清理 ' . $removed . ' 条过期数据：' . implode('、', $summary) : '没有需要清理的过期数据';
}
