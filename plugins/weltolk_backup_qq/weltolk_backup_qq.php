<?php if (!defined('SYSTEM_ROOT')) {
    die('Insufficient Permissions');
}
/*
Plugin Name: 每日数据库备份qq推送
Version: 1.0
Plugin URL: https://github.com/weltolk/weltolk_backup_qq
Description: 每日数据库备份qq推送，目前支持go-cqhttp的正向WebSocket和HTTP API，基于D丶L的版本重写
Author: Weltolk
Author Email: Null
Author URL: https://github.com/weltolk
For: V3.8+
*/

function weltolk_backup_qq_set_navi3()
{
    ?>
    <li><a href="index.php?mod=admin:setplug&plug=weltolk_backup_qq"><span
                    <svg class="sidebar-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><ellipse cx="12" cy="5" rx="9" ry="3"/><path d="M3 5v14a9 3 0 0 0 18 0V5"/><path d="M3 12a9 3 0 0 0 18 0"/></svg><span>每日数据库备份QQ推送管理</span></a>
    </li>
    <?php
}

//addAction('set_save1', 'weltolk_backup_qq_set');
addAction('navi_3', 'weltolk_backup_qq_set_navi3');
?>
