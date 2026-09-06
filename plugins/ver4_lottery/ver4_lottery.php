<?php

if (!defined('SYSTEM_ROOT')) {
    die('Insufficient Permissions');
}

function ver4_lottery_nav()
{
    echo '<li ';
    if (isset($_GET['plugin']) && $_GET['plugin'] == 'ver4_lottery') {
        echo 'class="active"';
    }
    echo '><a href="index.php?plugin=ver4_lottery"><svg class="sidebar-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="8" width="18" height="4" rx="1"/><path d="M12 8v13"/><path d="M19 12v7a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2v-7"/><path d="M7.5 8a2.5 2.5 0 0 1 0-5A4.8 8 0 0 1 12 8a4.8 8 0 0 1 4.5-5 2.5 2.5 0 0 1 0 5"/></svg><span>知道商城抽奖</span></a></li>';
}

addAction('navi_1', 'ver4_lottery_nav');
addAction('navi_7', 'ver4_lottery_nav');


function getToken($pid)
{
    $bduss = misc::getCookie($pid);
    $tc = new wcurl('https://zhidao.baidu.com/shop/lottery');
    $tc->addCookie('BDUSS=' . $bduss);
    $re = $tc->get();
    $token = textMiddle($re, '\'luckyToken\', \'', '\'');
    return $token;
}

function lottery($pid, $token)
{
    $bduss = misc::getCookie($pid);
    $head = [
        "x-ik-ssl" => "1",
        "Referer" =>  "https://zhidao.baidu.com/shop/lottery",
        "User-Agent" => "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36",
    ];
    $pl = new wcurl("https://zhidao.baidu.com/shop/submit/lottery", $head);
    $pl->addCookie('BDUSS=' . $bduss);
    $re = $pl->post(["type" => "0", "token" => $token]);
    $result = json_decode($re, true);
    return $result;
}
