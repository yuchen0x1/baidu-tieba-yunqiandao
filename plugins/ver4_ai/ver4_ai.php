<?php

if (!defined('SYSTEM_ROOT')) {
    die('Insufficient Permissions');
}

function ver4_ai_nav()
{
    echo '<li ';
    if (isset($_GET['plug']) && $_GET['plug'] == 'ver4_ai') {
        echo 'class="active"';
    }
    echo '><a href="index.php?mod=admin:setplug&plug=ver4_ai"><svg class="sidebar-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 2l2.4 7.2L22 12l-7.6 2.8L12 22l-2.4-7.2L2 12l7.6-2.8z"/></svg> AI 发帖/回帖</a></li>';
}

addAction('navi_2', 'ver4_ai_nav');
addAction('navi_8', 'ver4_ai_nav');
