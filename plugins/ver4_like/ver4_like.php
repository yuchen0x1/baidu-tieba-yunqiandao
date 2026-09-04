<?php

if (!defined('SYSTEM_ROOT')) {
    die('Insufficient Permissions');
}

/** 用户侧导航（个人面板） */
function ver4_like_user_nav()
{
    echo '<li ';
    if (isset($_GET['plugin']) && $_GET['plugin'] == 'ver4_like') {
        echo 'class="active"';
    }
    echo '><a href="index.php?plugin=ver4_like"><svg class="sidebar-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M7 10v12M15 5.88L14 10h5.83a2 2 0 0 1 1.92 2.56l-2.33 8A2 2 0 0 1 17.5 22H4a2 2 0 0 1-2-2v-8a2 2 0 0 1 2-2h2.76a2 2 0 0 0 1.79-1.11L12 2a3.13 3.13 0 0 1 3 3.88z"/></svg> 云点赞</a></li>';
}

/**
 * 参数化写入用户选项（users_options 无 (uid,name) 唯一键，查改插全部走预处理语句）
 */
function ver4_like_uopt_set($uid, $name, $value)
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

addAction('navi_1', 'ver4_like_user_nav');
addAction('navi_7', 'ver4_like_user_nav');
