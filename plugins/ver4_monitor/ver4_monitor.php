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

addAction('navi_1', 'ver4_monitor_user_nav');
addAction('navi_7', 'ver4_monitor_user_nav');
