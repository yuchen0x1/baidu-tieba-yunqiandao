<?php

if (!defined('SYSTEM_ROOT')) {
    die('Insufficient Permissions');
}

function ver4_reply_nav()
{
    echo '<li ';
    if (isset($_GET['plug']) && $_GET['plug'] == 'ver4_reply') {
        echo 'class="active"';
    }
    echo '><a href="index.php?mod=admin:setplug&plug=ver4_reply"><svg class="sidebar-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg> 自动回帖</a></li>';
}

addAction('navi_2', 'ver4_reply_nav');
addAction('navi_8', 'ver4_reply_nav');
