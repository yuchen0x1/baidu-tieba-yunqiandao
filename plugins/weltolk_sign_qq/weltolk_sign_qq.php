<?php if (!defined('SYSTEM_ROOT')) {
    die('Insufficient Permissions');
}
/*
Plugin Name: 每日签到结果qq推送
Version: 1.2
Plugin URL: https://github.com/weltolk/weltolk_sign_qq
Description: 每日用户签到结果qq推送，目前支持go-cqhttp的正向WebSocket和HTTP API，基于D丶L和quericy的版本重写
Author: Weltolk
Author Email: Null
Author URL: https://github.com/weltolk
For: V3.8+
*/
function weltolk_sign_qq_setting()
{
    global $i;
    $weltolk_sign_qq_report_url = SYSTEM_URL . 'index.php?pub_plugin=weltolk_sign_qq&username=' . $i['user']['name'] . '&token=' . md5(md5($i['user']['name'] . $i['user']['uid'] . date('Y-m-d')) . md5($i['user']['uid']));

    ?>
    <tr>
        <td>每日签到qq报告地址</td>
        <td>
            <a href="<?php echo $weltolk_sign_qq_report_url; ?>" target="_blank">点击查看</a>（有效期至<span
                    style="padding: 2px 4px;color: #c7254e;background-color: #f9f2f4;border-radius: 4px;"><?php echo date('Y-m-d 23:59:59'); ?></span>）
        </td>
    </tr>
    <?php
}

function weltolk_sign_qq_set_navi()
{
    ?>
    <li <?php if (isset($_GET['plugin']) && $_GET['plugin'] == 'weltolk_sign_qq') {
        echo 'class="active"';
        } ?>><a href="index.php?plugin=weltolk_sign_qq"><svg class="sidebar-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9"/><path d="M10.3 21a1.94 1.94 0 0 0 3.4 0"/></svg><span>每日签到QQ推送</span></a></li>

    <?php
}

function weltolk_sign_qq_set_navi3()
{
    ?>
    <li><a href="index.php?mod=admin:setplug&plug=weltolk_sign_qq"><span
                    <svg class="sidebar-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9"/><path d="M10.3 21a1.94 1.94 0 0 0 3.4 0"/></svg><span>每日签到QQ推送管理</span></a>
    </li>
    <?php
}

//addAction('set_save1', 'weltolk_sign_qq_set');
addAction('navi_1', 'weltolk_sign_qq_set_navi');
addAction('navi_7', 'weltolk_sign_qq_set_navi');
addAction('navi_3', 'weltolk_sign_qq_set_navi3');
addAction('set_2', 'weltolk_sign_qq_setting');
?>
