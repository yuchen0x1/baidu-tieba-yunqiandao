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
    echo '><a href="index.php?mod=admin:setplug&plug=ver4_reply"><span class="glyphicon glyphicon-comment"></span> 自动回帖</a></li>';
}

addAction('navi_2', 'ver4_reply_nav');
addAction('navi_8', 'ver4_reply_nav');
