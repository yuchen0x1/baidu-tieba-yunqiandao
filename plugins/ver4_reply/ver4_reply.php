<?php

if (!defined('SYSTEM_ROOT')) {
    die('Insufficient Permissions');
}

/** 用户侧导航（个人面板） */
function ver4_reply_user_nav()
{
    echo '<li ';
    if (isset($_GET['plugin']) && $_GET['plugin'] == 'ver4_reply') {
        echo 'class="active"';
    }
    echo '><a href="index.php?plugin=ver4_reply"><svg class="sidebar-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg> 自动回帖</a></li>';
}

/**
 * 参数化写入用户选项（users_options 无 (uid,name) 唯一键，查改插全部走预处理语句）
 */
function ver4_reply_uopt_set($uid, $name, $value)
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

addAction('navi_1', 'ver4_reply_user_nav');
addAction('navi_7', 'ver4_reply_user_nav');
