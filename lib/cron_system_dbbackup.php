<?php

if (!defined('SYSTEM_ROOT')) {
    die('Insufficient Permissions');
}

/**
 * 数据库定时备份计划任务
 * 将本站全部 tc_ 前缀表导出为 SQL 存档到 data/backups 目录，保留最近 N 份，
 * 可选将备份结果邮件通知站长。计划任务行 system_dbbackup 需已注册（安装自带，
 * 旧版本可在工具箱点击"注册定时备份任务"补齐）。
 */
function cron_system_dbbackup()
{
    global $m;
    if (option::get('db_backup_enable') != '1') {
        return '定时备份未启用，跳过执行';
    }
    $keep = (int) option::get('db_backup_keep');
    if ($keep <= 0) {
        $keep = 7;
    }
    $keep = min(90, $keep);
    $dir = SYSTEM_ROOT . '/data/backups';
    if (!is_dir($dir) && !mkdir($dir, 0755, true)) {
        return '创建备份目录失败：' . $dir;
    }
    //防止 Web 直接下载备份：Apache 由 .htaccess 拦截；Nginx 等其他环境需自行屏蔽 data 目录
    $htaccess = $dir . '/.htaccess';
    if (!file_exists($htaccess)) {
        @file_put_contents($htaccess, "Require all denied\nOrder allow,deny\nDeny from all\n");
    }
    $index = $dir . '/index.html';
    if (!file_exists($index)) {
        @file_put_contents($index, '');
    }
    $tables = array();
    $rs = $m->query("SHOW TABLES FROM `" . DB_NAME . "` LIKE '" . str_replace('_', '\\_', DB_PREFIX) . "%'");
    while ($row = $m->fetch_row($rs)) {
        $tables[] = $row[0];
    }
    if (empty($tables)) {
        return '未找到 ' . DB_PREFIX . ' 前缀的数据表，跳过备份';
    }
    $dump = '#Tieba-Cloud-Sign Scheduled Database Backup' . "\n";
    $dump .= '#Version:' . SYSTEM_VER . "\n";
    $dump .= '#Date:' . date('Y-m-d H:i:s') . "\n";
    $dump .= '############## Start ##############' . "\n";
    foreach ($tables as $table) {
        $dump .= dataBak($table);
    }
    $dump .= "\n" . '############## End ##############';
    $file = $dir . '/dbbackup_' . date('Y-m-d_His') . '.sql';
    if (file_put_contents($file, $dump) === false) {
        return '写入备份文件失败：' . $file;
    }
    //清理超量的旧备份
    $files = glob($dir . '/dbbackup_*.sql');
    if (is_array($files) && count($files) > $keep) {
        sort($files);
        foreach (array_slice($files, 0, count($files) - $keep) as $old) {
            @unlink($old);
        }
    }
    $summary = '备份完成：' . basename($file) . '（' . count($tables) . ' 张表，' . round(strlen($dump) / 1024, 1) . ' KB）';
    //可选邮件通知站长
    $to = trim((string) option::get('db_backup_email'));
    if ($to !== '' && checkMail($to)) {
        if (misc::mail($to, SYSTEM_FN . ' 数据库定时备份', '定时备份已完成：<br/>' . htmlspecialchars($summary, ENT_QUOTES, 'UTF-8') . '<br/>备份文件存放于服务器 data/backups 目录。') === true) {
            $summary .= '，通知邮件已发送';
        } else {
            $summary .= '，通知邮件发送失败';
        }
    }
    return $summary;
}
