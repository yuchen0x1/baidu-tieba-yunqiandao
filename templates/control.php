<?php

if (!defined('SYSTEM_ROOT')) {
    die('Insufficient Permissions');
}
global $i;
switch ($i['mode'][0]) {
    case 'baiduid':
                      loadhead('百度账号管理');
        template('baiduid');

        break;
    case 'showtb':
                      loadhead('云签到设置和日志');
        template('showtb');

        break;
    case 'calendar':
                      loadhead('签到日历');
        template('calendar');

        break;
    case 'signlogs':
                      loadhead('签到日志详情');
        template('sign-logs');

        break;
    case 'signlogs_export':
        // 会话级 CSV 导出：复用 sign-logs 的筛选逻辑，直接流式输出
        if (ROLE === 'visitor' || ROLE === 'banned') {
            msg('请先登录');
        }
        require_once SYSTEM_ROOT . '/lib/class.SignManager.php';
        $exportFilters = array(
            'uid'       => ISADMIN && defined('CON_UID') && CON_UID > 0 ? (int) CON_UID : (int) UID,
            'date_from' => isset($_GET['date_from']) ? trim((string) $_GET['date_from']) : '',
            'date_to'   => isset($_GET['date_to'])   ? trim((string) $_GET['date_to'])   : '',
            'tieba'     => isset($_GET['tieba'])     ? trim((string) $_GET['tieba'])     : '',
            'status'    => isset($_GET['status'])    ? trim((string) $_GET['status'])    : '',
        );
        $exportResult = SignManager::logs($exportFilters, 1, 5000);
        $exportRows   = $exportResult['rows'];
        $stamp = date('Ymd_His');
        $filename = 'sign-logs-' . $stamp . '.csv';
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        $out = fopen('php://output', 'w');
        // UTF-8 BOM 让 Excel 正确识别编码
        fwrite($out, "\xEF\xBB\xBF");
        fputcsv($out, array('时间', '贴吧', '状态', '错误码', '信息', 'PID'));
        foreach ($exportRows as $r) {
            fputcsv($out, array(
                date('Y-m-d H:i:s', $r['created_at']),
                $r['tieba'],
                $r['status'],
                $r['error_code'] ? (string) $r['error_code'] : '',
                $r['message'],
                (string) $r['pid'],
            ));
        }
        fclose($out);
        exit;

        break;
    case 'ranking':
                      loadhead('签到排行榜');
        template('ranking');

        break;
    case 'achievements':
                      loadhead('成就勋章');
        template('achievements');

        break;
    case 'notices':
                      loadhead('站内通知');
        template('notices');

        break;
    case 'log':
        //兼容老版本插件，重定向到showtb

                      Clean();
        ReDirect('index.php?mod=showtb');

        break;
    case 'account':
                      loadhead('账号中心');
        template('account');

        break;
    case 'set':
                      loadhead('个人设置');
        template('set');

        break;
    case 'admin':
        if (ROLE != 'admin') {
            msg('权限不足！');
        }
        switch ($i['mode'][1]) {
            case 'set':
                          loadhead('全局设置');
                template('admin-set');

                break;
            case 'tools':
                          loadhead('工具箱');
                template('admin-tools');

                break;
            case 'users':
                          loadhead('用户管理');
                template('admin-users');

                break;
            case 'invite':
                          loadhead('邀请码管理');
                template('admin-invite');

                break;
            case 'plugins':
                          loadhead('插件管理');
                template('admin-plugins');

                break;
            case 'cron':
                          loadhead('计划任务');
                template('admin-cron');

                break;
            case 'editcron':
                          loadhead('编辑计划任务');
                template('admin-editcron');

                break;
            case 'update':
                          loadhead('检查更新');
                template('admin-update');

                break;
            case 'setplug':
                          $plug = strip_tags($_GET['plug']);
                $pluginfo = getPluginInfo($plug);
                if (file_exists(SYSTEM_ROOT . '/plugins/' . $plug . '/' . $plug . '_setting.php') && in_array($plug, $i['plugins']['actived'])) {
                    loadhead($pluginfo['plugin']['name'] . ' - 插件管理');
                    require_once SYSTEM_ROOT . '/plugins/' . $plug . '/' . $plug . '_setting.php';
                    echo '<br/><br/><br/>';
                    if (!empty($pluginfo['plugin']['url'])) {
                            echo sanitize_html_link($pluginfo['plugin']['url'], $pluginfo['plugin']['name']);
                    }
                    echo ' V' . $pluginfo['plugin']['version'] . ' // 插件作者：' . sanitize_html_link($pluginfo['author']['url'], $pluginfo['author']['author']) . '<br/>' . SYSTEM_FN . ' V' . SYSTEM_VER . ' // 程序作者: <a href="https://kenvix.com" target="_blank">Kenvix</a>  &amp; <a href="http://www.mokeyjay.com/" target="_blank">mokeyjay</a> &amp;  <a href="http://fyy1999.lofter.com/" target="_blank">FYY</a> ';
                } else {
                    echo '<b>插件设置页面不存在</b>';
                }

                break;
                        case 'stat':
                          loadhead('统计信息');
                template('admin-stat');

                break;
            case 'sign':
                          loadhead('签到管理 - 健康度与日志');
                template('admin-sign-dashboard');

                break;
        }

        break;
    case 'thanks':
                      loadhead('开源致谢');
        template('thanks');

        break;
    default:
                      loadhead();
        template('index');

        break;
}
