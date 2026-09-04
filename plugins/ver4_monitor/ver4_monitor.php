<?php

if (!defined('SYSTEM_ROOT')) {
    die('Insufficient Permissions');
}

/** 用户侧导航（个人面板） */
function ver4_monitor_user_nav()
{
    echo '<li ';
    if (isset($_GET['plugin']) && $_GET['plugin'] == 'ver4_monitor') {
        echo 'class="active"';
    }
    echo '><a href="index.php?plugin=ver4_monitor"><svg class="sidebar-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg> 帖子监控</a></li>';
}

/**
 * 参数化写入用户选项（users_options 无 (uid,name) 唯一键，查改插全部走预处理语句）
 */
function ver4_monitor_uopt_set($uid, $name, $value)
{
    global $m;
    $uid = (int) $uid;
    $exists = $m->pquery("SELECT `uid` FROM `" . DB_PREFIX . "users_options` WHERE `uid` = ? AND `name` = ? LIMIT 1", array($uid, (string) $name));
    if ($exists !== false && $exists->fetch_array()) {
        $m->pquery("UPDATE `" . DB_PREFIX . "users_options` SET `value` = ? WHERE `uid` = ? AND `name` = ?", array((string) $value, $uid, (string) $name));
    } else {
        $m->pquery("INSERT INTO `" . DB_PREFIX . "users_options` (`uid`, `name`, `value`) VALUES (?, ?, ?)", array($uid, (string) $name, (string) $value));
    }
}

/**
 * 命中记录表结构（主文件内定义：插件主文件全局加载，API/用户页/计划任务均可调用）
 */
function ver4_monitor_ensure_table()
{
    static $ready = false;
    if ($ready) {
        return;
    }
    global $m;
    //notified：实时推送已发送或无需推送记 1；每日汇总模式下待推送记 0
    $m->query("CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "ver4_monitor_hit` (
        `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        `uid` INT UNSIGNED NOT NULL DEFAULT 0,
        `pid` INT UNSIGNED NOT NULL DEFAULT 0,
        `forum` VARCHAR(60) NOT NULL DEFAULT '',
        `kw` VARCHAR(60) NOT NULL DEFAULT '',
        `tid` BIGINT UNSIGNED NOT NULL DEFAULT 0,
        `title` VARCHAR(255) NOT NULL DEFAULT '',
        `notified` TINYINT NOT NULL DEFAULT 1,
        `created_at` INT UNSIGNED NOT NULL,
        PRIMARY KEY (`id`),
        UNIQUE KEY `uid_tid` (`uid`, `tid`),
        KEY `uid_created` (`uid`, `created_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
    //旧版本表结构补齐 notified 列
    $m->addColumn('ver4_monitor_hit', 'notified', 'tinyint', 'NOT NULL DEFAULT 1');
    $ready = true;
}

addAction('navi_1', 'ver4_monitor_user_nav');
addAction('navi_7', 'ver4_monitor_user_nav');
