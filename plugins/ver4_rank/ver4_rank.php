<?php

if (!defined('SYSTEM_ROOT')) {
    die('Insufficient Permissions');
}

function ver4_rank_nav()
{
    echo '<li ';
    if (isset($_GET['plugin']) && $_GET['plugin'] == 'ver4_rank') {
        echo 'class="active"';
    }
    echo '><a href="index.php?plugin=ver4_rank"><svg class="sidebar-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M22 2L11 13"/><path d="M22 2l-7 20-4-9-9-4 20-7z"/></svg><span>贴吧名人堂助攻</span></a></li>';
}

addAction('navi_1', 'ver4_rank_nav');
addAction('navi_7', 'ver4_rank_nav');

function dorank($bduss, $fid, $nid)
{
    $tbs = misc::getTbs(0, $bduss);
    $pz = new wcurl("http://tieba.baidu.com/celebrity/submit/support");
    $pz->addCookie(array('BDUSS' => $bduss));
    $data = array(
        'tbs'      => $tbs,
        'forum_id' => $fid,
        'npc_id'   => $nid
    );
    $result = $pz->post($data);
    return $result;
}
