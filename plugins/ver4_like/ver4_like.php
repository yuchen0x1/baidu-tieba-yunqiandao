<?php

if (!defined('SYSTEM_ROOT')) {
    die('Insufficient Permissions');
}

function ver4_like_nav()
{
    echo '<li ';
    if (isset($_GET['plug']) && $_GET['plug'] == 'ver4_like') {
        echo 'class="active"';
    }
    echo '><a href="index.php?mod=admin:setplug&plug=ver4_like"><span class="glyphicon glyphicon-thumbs-up"></span> 云点赞</a></li>';
}

addAction('navi_2', 'ver4_like_nav');
addAction('navi_8', 'ver4_like_nav');
