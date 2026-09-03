<?php

if (!defined('SYSTEM_ROOT')) {
    die('Insufficient Permissions');
}

/** 用户侧导航（个人面板） */
function ver4_ai_user_nav()
{
    echo '<li ';
    if (isset($_GET['plugin']) && $_GET['plugin'] == 'ver4_ai') {
        echo 'class="active"';
    }
    echo '><a href="index.php?plugin=ver4_ai"><svg class="sidebar-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 2l2.4 7.2L22 12l-7.6 2.8L12 22l-2.4-7.2L2 12l7.6-2.8z"/></svg> AI 发帖/回帖</a></li>';
}

/** 管理侧导航（站点开关与共享配置） */
function ver4_ai_admin_nav()
{
    echo '<li ';
    if (isset($_GET['plug']) && $_GET['plug'] == 'ver4_ai') {
        echo 'class="active"';
    }
    echo '><a href="index.php?mod=admin:setplug&plug=ver4_ai"><svg class="sidebar-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 2l2.4 7.2L22 12l-7.6 2.8L12 22l-2.4-7.2L2 12l7.6-2.8z"/></svg> AI 发帖/回帖</a></li>';
}

addAction('navi_1', 'ver4_ai_user_nav');
addAction('navi_7', 'ver4_ai_user_nav');
addAction('navi_2', 'ver4_ai_admin_nav');
addAction('navi_8', 'ver4_ai_admin_nav');
