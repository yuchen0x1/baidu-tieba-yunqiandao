<?php

/**
 * 设置保存页面
 */

require dirname(__FILE__) . '/init.php';
csrf();
if (ROLE != 'user' && ROLE != 'admin' && ROLE != 'vip') {
    msg('权限不足');
}

if (ROLE != 'admin' && strpos(SYSTEM_PAGE, 'admin:') === 0) {
    msg('权限不足');
}

global $i;
global $m;
switch (SYSTEM_PAGE) {
    case 'admin:plugins':
        doAction('plugin_setting_1');
        if (isset($_GET['dis'])) {
            inactivePlugin(valid_plugin_id((string) $_GET['dis']) ? (string) $_GET['dis'] : '');
        } elseif (isset($_GET['act'])) {
            activePlugin(valid_plugin_id((string) $_GET['act']) ? (string) $_GET['act'] : '');
        } elseif (isset($_GET['upd'])) {
            if (!valid_plugin_id((string) $_GET['upd'])) {
                msg('插件标识不合法');
            }
            if (updatePlugin((string) $_GET['upd']) == false) {
                Redirect('index.php?mod=admin:plugins&error_msg=' . urlencode("插件更新失败"));
            }
        } elseif (isset($_GET['uninst'])) {
            uninstallPlugin(valid_plugin_id((string) $_GET['uninst']) ? (string) $_GET['uninst'] : '');
        } elseif (isset($_GET['clean'])) {
            uninstallPlugin(valid_plugin_id((string) $_GET['clean']) ? (string) $_GET['clean'] : '', false);
        } elseif (isset($_GET['install'])) {
            $installPlug = (string) $_GET['install'];
            if (!valid_plugin_id($installPlug)) {
                msg('插件标识不合法');
            }
            if (!empty($_REQUEST['ver'])) {
                msg('该插件仅适用于 V' . htmlspecialchars((string) $_REQUEST['ver'], ENT_QUOTES, 'UTF-8') . ' 及以上的版本，您的云签到版本低于插件所需最低版本，是否强制安装（强制安装可能造成云签到损坏）<br/><br/><a href="setting.php?mod=admin:plugins&install=' . urlencode($installPlug) . '">强制安装</a>　　<a href="setting.php?mod=admin:plugins">取消安装</a><br/>', false, true);
            }
            installPlugin($installPlug);
        } elseif (isset($_GET['xorder'])) {
            global $m;
            foreach ($_POST as $id => $order) {
                $id = preg_replace('/[^A-Za-z0-9_-]/', '', (string) $id);
                $order = (int) $order;
                if ($id !== '' && $id !== 'csrf_token') {
                    $m->pquery('UPDATE `' . DB_NAME . '`.`' . DB_PREFIX . 'plugins` SET `order` = ? WHERE `name` = ?', array($order, $id));
                }
            }
        }
        doAction('plugin_setting_2');
        Redirect('index.php?mod=admin:plugins&ok');

        break;
    // case 'admin:cloud':
    //     doAction('plugin_update_1');
    //     global $i;
    //     $plug = $i['plugins']['desc'][$_GET['upd']];
    //     if (!file_exists(UPDATE_CACHE)) {
    //         mkdir(UPDATE_CACHE, 0777, true);
    //     }
    // 
    //     $up_url = SUPPORT_URL . 'getplug.php?m=up&pname=' . $_GET['upd'] . '&user=' . option::get('bbs_us') . '&pw=' . option::get('bbs_pw');
    //     $c = new wcurl($up_url);
    //     $file = $c->exec();
    //     $c->close();
    //     if ($file == 'WRONG') {
    //         msg('错误 - 更新失败：<br/><br/>产品中心拒绝了下载<br/>请检查全局设置中的账号是否正确以及是否购买过此插件');
    //     }
    // 
    //     $zipPath = UPDATE_CACHE . 'update_plug_' . time() . '.zip';
    //     if (file_put_contents($zipPath, $file) === false) {
    //         DeleteFile(UPDATE_CACHE);
    //         msg('错误 - 更新失败：<br/><br/>无法下载更新包');
    //     }
    // 
    //     //解压缩
    //     $z = new zip();
    //     $z->open($zipPath);
    //     $z->extract(UPDATE_CACHE);
    //     $z->close();
    // //检查更新文件
    //     $floderName = UPDATE_CACHE . $_GET['upd'];
    //     if (!is_dir($floderName)) {
    //         DeleteFile(UPDATE_CACHE);
    //         msg('错误 - 更新失败：<br/><br/>无法解压缩更新包');
    //     }
    // 
    //     //覆盖文件
    //     if (CopyAll($floderName, SYSTEM_ROOT . '/plugins/' . $_GET['upd']) !== true) {
    //         DeleteFile(UPDATE_CACHE);
    //         msg('错误 - 更新失败：<br/><br/>无法更新文件');
    //     }
    //     DeleteFile(UPDATE_CACHE);
    //     doAction('plugin_update_2');
    //     msg('（1/2）已成功下载最新版本的 ' . $plug['plugin']['name'] . ' 插件。请单击下一步，以完成更新<br/><br/><a href="setting.php?mod=admin:plugins&upd=' . $_GET['upd'] . '">>> 下一步</a>', false);
    // 
    //     break;
    case 'admin:set':
        global $m;
        $sou = $_POST;
        if ($_GET['type'] == 'sign') {
            @option::set('cron_limit', $sou['cron_limit']);
            @option::set('tb_max', $sou['tb_max']);
            @option::set('bduss_num', $sou['bduss_num']);
            @option::set('sign_mode', serialize($sou['sign_mode']));
            @option::set('enable_addtieba', $sou['enable_addtieba']);
            @option::set('retry_max', $sou['retry_max']);
            @option::set('sign_hour', $sou['sign_hour']);
            @option::set('fb', $sou['fb']);
            @option::set('sign_sleep', $sou['sign_sleep']);
            @option::set('sign_scan', $sou['sign_scan']);
            @option::set('same_pid', $sou['same_pid']);
            @option::set('sign_jitter', $sou['sign_jitter']);
            @option::set('client_version', isset($sou['client_version']) ? trim($sou['client_version']) : '');
            @option::set('client_rotate', isset($sou['client_rotate']) ? $sou['client_rotate'] : '1');
            @option::set('sign_shuffle', isset($sou['sign_shuffle']) ? $sou['sign_shuffle'] : '0');
            @option::set('proxy_type', isset($sou['proxy_type']) ? $sou['proxy_type'] : '0');
            @option::set('proxy_host', isset($sou['proxy_host']) ? trim($sou['proxy_host']) : '');
            @option::set('proxy_user', isset($sou['proxy_user']) ? $sou['proxy_user'] : '');
            @option::set('proxy_pass', isset($sou['proxy_pass']) ? $sou['proxy_pass'] : '');
            @option::set('dama_type', isset($sou['dama_type']) ? $sou['dama_type'] : 'none');
            @option::set('dama_username', isset($sou['dama_username']) ? $sou['dama_username'] : '');
            @option::set('dama_password', isset($sou['dama_password']) ? $sou['dama_password'] : '');
            @option::set('dama_softid', isset($sou['dama_softid']) ? trim($sou['dama_softid']) : '');
            @option::set('health_auto_disable', isset($sou['health_auto_disable']) ? $sou['health_auto_disable'] : '0');
            @option::set('health_fail_max', max(1, min(20, (int) $sou['health_fail_max'])));
            if (empty($sou['fb_tables'])) {
                @option::set('fb_tables', null);
            } else {
                $fb_tables = explode("\n", $sou['fb_tables']);
                $fb_tab = array();
                $n = 0;
                foreach ($fb_tables as $value) {
                    $n++;
                    $value = strtolower(trim($value));
                    //表名进入建表语句前做白名单校验，杜绝拼接注入
                    if (!preg_match('/^[a-z0-9_]{1,64}$/', $value)) {
                        msg('分表名不合法：' . htmlspecialchars($value, ENT_QUOTES, 'UTF-8') . '<br/><br/>只允许小写字母、数字、下划线，长度不超过 64');
                    }
                    $sql = str_ireplace('{VAR-DB}', DB_NAME, str_ireplace('{VAR-TABLE}', DB_PREFIX . $value, file_get_contents(SYSTEM_ROOT . '/setup/template.table.sql')));
                    $m->query($sql);
                    $fb_tab[$n] .= $value;
                }
                @option::set('fb_tables', serialize($fb_tab));
            }
        } else {
            @option::set('system_url', $sou['system_url']);
            @option::set('system_name', $sou['system_name']);
            @option::set('system_keywords', $sou['system_keywords']);
            @option::set('system_description', $sou['system_description']);
            @option::set('footer', $sou['footer']);
            @option::set('ann', $sou['ann']);
            @option::set('enable_reg', $sou['enable_reg']);
            isset($sou['captcha']) and @option::set('captcha', $sou['captcha']);
            @option::set('yr_reg', $sou['yr_reg']);
            @option::set('reg_mail_verify', isset($sou['reg_mail_verify']) ? $sou['reg_mail_verify'] : '0');
            @option::set('stop_reg', $sou['stop_reg']);
            @option::set('icp', $sou['icp']);
            @option::set('bbs_us', $sou['bbs_us']);
            @option::set('bbs_pw', $sou['bbs_pw']);
            @option::set('mail_mode', $sou['mail_mode']);
            @option::set('mail_name', $sou['mail_name']);
            @option::set('mail_yourname', $sou['mail_yourname']);
            @option::set('mail_host', $sou['mail_host']);
            @option::set('mail_port', $sou['mail_port']);
            @option::set('mail_auth', $sou['mail_auth']);
            @option::set('mail_secure', $sou['mail_secure']);
            @option::set('mail_smtpname', $sou['mail_smtpname']);
            isset($sou['mail_smtppw']) and @option::set('mail_smtppw', $sou['mail_smtppw']);
            @option::set('dev', $sou['dev']);
            @option::set('cron_pw', $sou['cron_pw']);
            isset($sou['cron_asyn']) and @option::set('cron_asyn', $sou['cron_asyn']);
            @option::set('sign_multith', $sou['sign_multith']);
            @option::set('cktime', $sou['cktime']);
            @option::set('notify_enable', isset($sou['notify_enable']) && $sou['notify_enable'] == '1' ? 1 : 0);
            @option::set('notify_channel', isset($sou['notify_channel']) ? $sou['notify_channel'] : 'none');
            @option::set('notify_serverchan_key', isset($sou['notify_serverchan_key']) ? $sou['notify_serverchan_key'] : '');
            @option::set('notify_telegram_token', isset($sou['notify_telegram_token']) ? $sou['notify_telegram_token'] : '');
            @option::set('notify_telegram_chatid', isset($sou['notify_telegram_chatid']) ? $sou['notify_telegram_chatid'] : '');
            @option::set('notify_webhook_url', isset($sou['notify_webhook_url']) ? $sou['notify_webhook_url'] : '');
            @option::set('notify_on_fail', isset($sou['notify_on_fail']) ? 1 : 0);
            @option::set('notify_on_health', isset($sou['notify_on_health']) ? 1 : 0);
            @option::set('notify_bark_url', isset($sou['notify_bark_url']) ? $sou['notify_bark_url'] : '');
            @option::set('notify_pushplus_token', isset($sou['notify_pushplus_token']) ? $sou['notify_pushplus_token'] : '');
            @option::set('notify_dingtalk_webhook', isset($sou['notify_dingtalk_webhook']) ? trim($sou['notify_dingtalk_webhook']) : '');
            @option::set('notify_dingtalk_secret', isset($sou['notify_dingtalk_secret']) ? trim($sou['notify_dingtalk_secret']) : '');
            @option::set('notify_wecom_webhook', isset($sou['notify_wecom_webhook']) ? trim($sou['notify_wecom_webhook']) : '');
            @option::set('notify_feishu_webhook', isset($sou['notify_feishu_webhook']) ? trim($sou['notify_feishu_webhook']) : '');
            @option::set('notify_wxpusher_token', isset($sou['notify_wxpusher_token']) ? trim($sou['notify_wxpusher_token']) : '');
            @option::set('notify_wxpusher_uid', isset($sou['notify_wxpusher_uid']) ? trim($sou['notify_wxpusher_uid']) : '');
            @option::set('notify_ntfy_server', isset($sou['notify_ntfy_server']) ? trim($sou['notify_ntfy_server']) : 'https://ntfy.sh');
            @option::set('notify_ntfy_topic', isset($sou['notify_ntfy_topic']) ? trim($sou['notify_ntfy_topic']) : '');
            @option::set('api_token', isset($sou['api_token']) ? $sou['api_token'] : '');
        }
        doAction('admin_set_save');
        Redirect('index.php?mod=admin:set:' . $_GET['type'] . '&ok');

        break;
    case 'admin:tools':
        /*
         $toolpw = option::get('toolpw');
           if(!empty($_POST['toolpw'])){
          $cookies = md5(md5(md5($_POST['toolpw'])));
            if(empty($toolpw)){
                option::add('toolpw',$cookies);
                setcookie('toolpw',$cookies);
              Redirect('index.php?mod=admin:tools&ok');
          } else {
               setcookie('toolpw',$cookies);
              Redirect('index.php?mod=admin:tools');
         }
          }
          if($_COOKIE['toolpw'] != $toolpw || empty($toolpw)){
           Redirect('index.php?mod=admin:tools');
         }
        */

        switch (strip_tags($_GET['setting'])) {
            case 'optim':
                global $m;
                $rs = $m->query("SHOW TABLES FROM `" . DB_NAME . '`');
                while ($row = $m->fetch_row($rs)) {
                    $m->query('OPTIMIZE TABLE  `' . DB_NAME . '`.`' . $row[0] . '`');
                }

                break;
            case 'fixdoing':
                option::set('cron_isdoing', 0);

                break;
            case 'reftable':
                option::set('freetable', getfreetable());

                break;
            case 'cron_sign_again':
                option::set('cron_sign_again', '');

                break;
            case 'backup':
                global $m;
                $list  = !empty($_POST['tab']) ? array_map('addslashes', $_POST['tab']) : msg('请至少选择一个需要导出的表');
                $dump  = '#Warning: Do not change the comments!!!'  . "\n";
                $dump .= '#Tieba-Cloud-Sign Database Backup' . "\n";
                $dump .= '#Version:' . SYSTEM_VER . "\n";
                $dump .= '#Date:' . date('Y-m-d H:i:s') . "\n";
                $dump .= '############## Start ##############' . "\n";
                foreach ($list as $table) {
                    $dump .= dataBak($table);
                }
                $dump .= "\n" . '############## End ##############';
                $file  = 'cloud_sign_' . date('Y-m-d_H-m-s') . '.sql';
                if (!empty($_POST['zip'])) {
                    if (!is_dir(SYSTEM_ROOT . '/source/cache')) {
                        mkdir(SYSTEM_ROOT . '/source/cache', 0777, true);
                    }
                    if (CreateZip($file, $dump, SYSTEM_ROOT . '/source/cache/' . $file . '.zip') === true) {
                        header('Content-Type: application/zip');
                        header('Content-Disposition: attachment; filename=' . $file . '.zip');
                        echo file_get_contents(SYSTEM_ROOT . '/source/cache/' . $file . '.zip');
                        unlink(SYSTEM_ROOT . '/source/cache/' . $file . '.zip');
                        die;
                    }
                }
                header('Content-Type: text/x-sql');
                header('Content-Disposition: attachment; filename=' . $file);
                echo $dump;

                die;
            break;
            case 'dbbackup_save':
                option::set('db_backup_enable', isset($_POST['db_backup_enable']) && $_POST['db_backup_enable'] == '1' ? '1' : '0');
                option::set('db_backup_keep', min(90, max(1, (int) request_scalar_value($_POST, 'db_backup_keep', 7))));
                $dbBackupEmail = trim((string) request_text_value($_POST, 'db_backup_email'));
                if ($dbBackupEmail !== '' && !checkMail($dbBackupEmail)) {
                    msg('备份通知邮箱格式不正确');
                }
                option::set('db_backup_email', $dbBackupEmail);
                Redirect('index.php?mod=admin:tools&ok');

                break;
            case 'dbbackup_run':
                require_once SYSTEM_ROOT . '/lib/cron_system_dbbackup.php';
                msg(cron_system_dbbackup(), 'index.php?mod=admin:tools');

                break;
            case 'dbbackup_init':
                $m->query("INSERT IGNORE INTO `" . DB_PREFIX . "cron` (`name`, `orde`, `file`, `no`, `desc`, `freq`, `lastdo`, `log`) VALUES ('system_dbbackup', '9', 'lib/cron_system_dbbackup.php', '0', '每日自动备份数据库到 data/backups 目录', '86400', '0', '')");
                Redirect('index.php?mod=admin:tools&ok');

                break;
            case 'remtab':
                global $m;
                $tabs = isset($_POST['tab']) ? (array) $_POST['tab'] : array();
                foreach ($tabs as $tab) {
                    $tab = (string) $tab;
                    if (!preg_match('/^[A-Za-z0-9_]+$/', $tab)) {
                        continue;
                    }
                    $m->query('DROP TABLE IF EXISTS `' . $tab . '`');
                }

                break;
            case 'truntab':
                global $m;
                $tabs = isset($_POST['tab']) ? (array) $_POST['tab'] : array();
                foreach ($tabs as $tab) {
                    $tab = (string) $tab;
                    if (!preg_match('/^[A-Za-z0-9_]+$/', $tab)) {
                        continue;
                    }
                    $m->query('TRUNCATE TABLE `' . $tab . '`');
                }

                break;
/*
        case 'updatefid':
          global $m;
         global $i;
         $fbs = $i['tabpart'];
if (!isset($_GET['ok'])) {
$step = $_GET['step'] + 30;
$next = isset($_GET['in']) ? strip_tags($_GET['in']) : 'tieba';
$c  = $m->once_fetch_array("SELECT COUNT(*) AS `x` FROM `".DB_PREFIX.$next."`");
$c2 = $m->once_fetch_array("SELECT COUNT(*) AS `x` FROM `".DB_PREFIX.$next."` WHERE `fid` = '0'");
if ($c2['x'] >= 1) {
$x  = $m->query("SELECT * FROM `".DB_PREFIX.$next."` WHERE `fid` = '0' LIMIT 30");
while ($r = $m->fetch_array($x)) {
$fid = misc::getFid($r['tieba']);
$m->query("UPDATE `".DB_PREFIX.$next."` SET  `fid` =  '{$fid}' WHERE `".DB_PREFIX.$next."`.`id` = {$r['id']};");
}
} else {
$step = 0;
if ($next == 'tieba') {
$next = $fbs[1];
} else {
foreach ($fbs as $ke => $va) {
if ($va == $next) {
$newkey = $ke + 1;
$next = $ke[$newkey];
break;
}

}
}
}
if (empty($next)) {
msg('<meta http-equiv="refresh" content="0; url='.SYSTEM_URL.'index.php?mod=admin:tools&ok" />即将完成更新......程序将自动继续<br/><br/><a href="'.SYSTEM_URL.'index.php?mod=admin:tools&ok">如果你的浏览器没有自动跳转，请点击这里</a>',false);
}

msg('<meta http-equiv="refresh" content="0; url=setting.php?mod=updatefid&step='.$step.'&in='.$next.'" />已经更新表：'.DB_PREFIX.$next.' / 即将更新表：'.DB_PREFIX.$next.'<br/><br/>当前进度：'.$step.' / '.$c['x'].' <progress style="width:60%" value="'.$step.'" max="'.$c['x'].'"></progress><br/><br/>切勿关闭浏览器，程序将自动继续<br/><br/><a href="setting.php?mod=updatefid&step='.$step.'&in='.$next.'">如果你的浏览器没有自动跳转，请点击这里</a>',false);
}
          break;
            */

            case 'install_plugin_online':
                doAction('plugin_install_online_1');
                if (!class_exists('ZipArchive')) {
                    msg('插件安装失败：你的主机不支持 ZipArchive 类，请返回');
                }
                if (!is_writable(SYSTEM_ROOT . '/plugins')) {
                    msg('插件安装失败：你的主机不支持文件写入，请手动安装插件');
                }
                $onlineUrl = trim((string) (isset($_POST['plugin_gitee_url']) ? $_POST['plugin_gitee_url'] : ''));
                if ($onlineUrl === '') {
                    msg('请填写插件包的 Gitee 下载地址');
                }
                $parts = parse_url($onlineUrl);
                if (empty($parts['host']) || strtolower($parts['host']) !== 'gitee.com' || (isset($parts['scheme']) && strtolower($parts['scheme']) !== 'https')) {
                    msg('仅允许从 https://gitee.com 下载插件包');
                }
                if (!preg_match('/\.zip$/i', (string) (isset($parts['path']) ? $parts['path'] : ''))) {
                    msg('下载地址必须指向 ZIP 格式的插件包');
                }
                if (!defined('UPDATE_CACHE')) {
                    msg('系统缓存目录未定义，无法在线安装');
                }
                if (!is_dir(UPDATE_CACHE) && !@mkdir(UPDATE_CACHE, 0755, true)) {
                    msg('无法创建下载缓存目录，请检查写入权限');
                }
                $zipPath = rtrim(UPDATE_CACHE, '/\\') . '/plugin_online_' . bin2hex(random_bytes(8)) . '.zip';
                try {
                    $dl = new wcurl($onlineUrl);
                    $dl->set(CURLOPT_TIMEOUT, 120);
                    //不跟随跨域重定向，避免 Gitee URL 被利用为 SSRF 下载器
                    $dl->set(CURLOPT_FOLLOWLOCATION, false);
                    $data = $dl->exec();
                } catch (Exception $e) {
                    msg('插件下载失败：' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8'));
                }
                if (empty($data)) {
                    msg('插件下载失败：网络返回为空或地址发生了重定向');
                }
                if (strlen($data) > 52428800) {
                    msg('插件下载失败：压缩包超过 50 MB 限制');
                }
                if (@file_put_contents($zipPath, $data, LOCK_EX) === false) {
                    msg('插件下载失败：无法写入缓存文件');
                }
                $z = new ZipArchive();
                if (!$z->open($zipPath)) {
                    @unlink($zipPath);
                    msg('插件安装失败：无法打开压缩包，请确认地址指向有效的 ZIP 文件');
                }
                $pluginId = extractPluginArchive($z, SYSTEM_ROOT . '/plugins');
                if ($pluginId === false) {
                    $z->close();
                    @unlink($zipPath);
                    msg('插件安装失败：ZIP 目录结构不合法、入口文件不存在或目标插件已存在');
                }
                $z->close();
                @unlink($zipPath);
                doAction('plugin_install_online_2');
                msg('插件包下载并解压成功（插件标识：' . htmlspecialchars($pluginId, ENT_QUOTES, 'UTF-8') . '），请回到插件列表点击「安装插件」完成注册');
                break;
            case 'install_plugin':
                doAction('plugin_install_1');
                if (!class_exists('ZipArchive')) {
                    msg('插件安装失败：你的主机不支持 ZipArchive 类，请返回');
                }
                if (!is_writable(SYSTEM_ROOT . '/plugins')) {
                    msg('插件安装失败：你的主机不支持文件写入，请手动安装插件');
                }
                if (!isset($_FILES['plugin'])) {
                    msg('若要安装插件，请上传插件包');
                }
                $file = $_FILES['plugin'];
                if (!empty($file['error'])) {
                    msg('插件安装失败：在上传文件时发生错误：代码：' . $file['error']);
                }
                if (empty($file['size'])) {
                    msg('插件安装失败：插件包大小无效 ( 0 Byte )，请确认压缩包是否已损坏');
                }
                $z  = new ZipArchive();
                if (!$z->open($file['tmp_name'])) {
                    msg('插件安装失败：无法打开压缩包');
                }
                $pluginId = extractPluginArchive($z, SYSTEM_ROOT . '/plugins');
                if ($pluginId === false) {
                    $z->close();
                    msg('插件安装失败：ZIP 目录结构不合法、入口文件不存在或目标插件已存在');
                }
                $z->close();
                doAction('plugin_install_2');
                msg('插件安装成功（插件标识：' . htmlspecialchars($pluginId, ENT_QUOTES, 'UTF-8') . '），请回到插件列表点击「安装插件」完成注册');

                break;
            default:
                msg('未定义操作');

                break;
        }
        doAction('admin_tools_doing');
        Redirect('index.php?mod=admin:tools&ok');

        break;
    case 'admin:users':
        if (!empty($_GET['control'])) {
            $control_id = (int) $_GET['control'];
            $osq = $m->once_fetch_array("SELECT `id`,`role`,`pw` FROM  `" . DB_NAME . "`.`" . DB_PREFIX . "users` WHERE `id` = '{$control_id}' LIMIT 1");
            empty($osq['pw']) and msg('用户不存在');
            $osq['role'] == 'admin' and msg('无法控制管理员');
            doAction('admin_users_control');
            set_site_cookie('uid', $control_id, time() + auth_cookie_lifetime());
            set_site_cookie('pwd', hash_hmac('sha256', $osq['pw'], $osq['id'] . $osq['pw']), time() + auth_cookie_lifetime());
            set_site_cookie('con_uid', (int) UID);
            set_site_cookie('con_pwd', isset($_COOKIE['pwd']) ? (string) $_COOKIE['pwd'] : '');
            redirect('index.php');
        }
        switch (strip_tags($_POST['do'])) {
            case 'cookie':
                foreach ((array) $_POST['user'] as $value) {
                    $value = (int) $value;
                    $m->query("DELETE FROM `" . DB_NAME . "`.`" . DB_PREFIX . "baiduid` WHERE  `" . DB_PREFIX . "baiduid`.`uid` = " . $value);
                }
                doAction('admin_users_cookie');

                break;
            case 'clean':
                foreach ((array) $_POST['user'] as $value) {
                    CleanUser((int) $value);
                }
                doAction('admin_users_clean');

                break;
            case 'delete':
                MustAdminWarning($_POST['user']);
                foreach ((array) $_POST['user'] as $value) {
                    DeleteUser((int) $value);
                }
                doAction('admin_users_delete');

                break;
            case 'crole':
                // 判断用户组字符串是否合法

                $role = $_POST['crolev'];
                if (!in_array($role, array('user', 'admin', 'vip', 'banned'))) {
                    msg('无效的用户组！');
                }
                // 如果准备将用户组设为admin以外时，先检测是否还有其他admin可用
                // 避免出现所有用户都被设为非admin的问题
                if ($role != 'admin') {
                    MustAdminWarning($_POST['user']);
                }

                foreach ($_POST['user'] as $value) {
                    $value = (int) $value;
                    doAction('admin_users_crole_process', $value, $role);
                    $m->pquery("UPDATE `" . DB_NAME . "`.`" . DB_PREFIX . "users` SET `role` = ? WHERE `id` = ?", array($role, $value));
                }
                doAction('admin_users_crole');

                break;
            case 'cset':
                foreach ($_POST['user'] as $value) {
                    option::udel($value);
                }
                doAction('admin_users_cset');

                break;
            case 'mailblast':
                //向全部留有邮箱的用户群发邮件；misc::mail 成功返回 true、失败返回错误消息字符串
                $mailSubject = trim((string) request_text_value($_POST, 'mail_subject'));
                $mailContent = trim((string) request_scalar_value($_POST, 'mail_content'));
                if ($mailSubject === '' || $mailContent === '') {
                    msg('邮件群发失败：请填写主题与正文');
                }
                $mailRs = $m->query("SELECT `email`, `name` FROM `" . DB_NAME . "`.`" . DB_PREFIX . "users` WHERE `email` != ''");
                $mailSent = 0;
                $mailFailed = 0;
                while ($mailUser = $m->fetch_array($mailRs)) {
                    $mailBody = str_replace(array('{name}', '{site}'), array($mailUser['name'], SYSTEM_NAME), $mailContent);
                    if (misc::mail($mailUser['email'], $mailSubject, nl2br(htmlspecialchars($mailBody, ENT_QUOTES, 'UTF-8'))) === true) {
                        $mailSent++;
                    } else {
                        $mailFailed++;
                    }
                    usleep(300000);
                }
                msg("邮件群发完成：成功 {$mailSent}，失败 {$mailFailed}", 'index.php?mod=admin:users');

                break;
            case 'add':
                $name = isset($_POST['name']) ? strip_tags(trim($_POST['name'])) : '';
                $mail = isset($_POST['mail']) ? strip_tags(trim($_POST['mail'])) : '';
                $pw   = isset($_POST['pwd']) ? $_POST['pwd'] : '';
                $role = isset($_POST['role']) ? strip_tags($_POST['role']) : 'user';
                if (!in_array($role, array('user', 'admin', 'vip', 'banned'), true)) {
                    $role = 'user';
                }
                if (empty($name) || empty($mail) || empty($pw)) {
                    msg('添加用户失败：请正确填写用户名、密码或邮箱');
                }
                //与注册流程一致的白名单校验：用户名字符集与长度、邮箱格式
                if (!preg_match('/^[A-Za-z0-9_\x{4e00}-\x{9fa5}]{1,20}$/u', $name)) {
                    msg('添加用户失败：用户名只允许字母、数字、下划线、中文，长度不超过 20');
                }
                if (!checkMail($mail)) {
                    msg('添加用户失败：邮箱格式不正确');
                }
                $dup = $m->pquery("SELECT COUNT(*) AS total FROM `" . DB_NAME . "`.`" . DB_PREFIX . "users` WHERE `name` = ? OR `email` = ?", array($name, $mail));
                $dupRow = $dup ? $m->fetch_array($dup) : array('total' => 0);
                if ($dupRow['total'] > 0) {
                    msg('添加用户失败：用户名或邮箱已经存在');
                }
                $m->pquery('INSERT INTO `' . DB_NAME . '`.`' . DB_PREFIX . 'users` (`name`, `pw`, `email`, `role`, `t`) VALUES (?, ?, ?, ?, ?)', array($name, EncodePwd($pw), $mail, $role, getfreetable()));
                doAction('admin_users_add');
                Redirect('index.php?mod=admin:users&ok');

                break;
            default:
                msg('未定义操作');

                break;
        }
        Redirect('index.php?mod=admin:users&ok');

        break;
    case 'admin:invite':
        if (ROLE != 'admin') {
            msg('权限不足！');
        }
        invite_ensure_table();
        $inviteAction = isset($_POST['invite_action']) ? (string) $_POST['invite_action'] : '';
        if ($inviteAction === 'multi') {
            option::set('yr_reg_multi', isset($_POST['yr_reg_multi']) && $_POST['yr_reg_multi'] == '1' ? '1' : '0');
        } elseif ($inviteAction === 'generate') {
            $count = min(100, max(1, (int) request_scalar_value($_POST, 'count', 1)));
            $maxUses = min(9999, max(1, (int) request_scalar_value($_POST, 'max_uses', 1)));
            $days = min(3650, max(0, (int) request_scalar_value($_POST, 'days', 0)));
            $note = trim((string) request_text_value($_POST, 'note'));
            $expire = $days > 0 ? time() + $days * 86400 : 0;
            $made = 0;
            for ($ix = 0; $ix < $count; $ix++) {
                try {
                    $code = 'TCS-' . strtoupper(bin2hex(random_bytes(4)));
                } catch (Throwable $e) {
                    $code = 'TCS-' . strtoupper(substr(md5(uniqid('', true) . mt_rand()), 0, 8));
                }
                $m->pquery("INSERT IGNORE INTO `" . DB_PREFIX . "invites` (`code`, `max_uses`, `used_count`, `expire`, `status`, `note`, `created_at`) VALUES (?, ?, 0, ?, 1, ?, ?)", array($code, $maxUses, $expire, $note, time()));
                $made++;
            }
            msg("已生成 {$made} 个邀请码", 'index.php?mod=admin:invite');
        } elseif ($inviteAction === 'toggle') {
            $inviteId = (int) request_scalar_value($_POST, 'id', 0);
            $m->pquery("UPDATE `" . DB_PREFIX . "invites` SET `status` = 1 - `status` WHERE `id` = ?", array($inviteId));
        } elseif ($inviteAction === 'delete') {
            $inviteId = (int) request_scalar_value($_POST, 'id', 0);
            $m->pquery("DELETE FROM `" . DB_PREFIX . "invites` WHERE `id` = ?", array($inviteId));
        }
        Redirect('index.php?mod=admin:invite&ok');

        break;
    case 'admin:totp':
        // V5.13 B5：管理员双因素认证（TOTP）设置
        if (ROLE != 'admin') {
            msg('权限不足!');
        }
        doAction('totp_setting_1');
        require_once SYSTEM_ROOT . '/lib/class.totp.php';
        $action = isset($_GET['setting']) ? (string) $_GET['setting'] : '';
        $i['totp_msg'] = '';
        if ($action === 'gen') {
            option::set('admin_totp_secret', P::encrypt(TOTP::generateSecret()));
            $i['totp_msg'] = '双因素认证密钥已生成，请在认证器应用（Google/Microsoft Authenticator 等）中添加下方密钥并输入验证码完成确认';
        } elseif ($action === 'verify') {
            $code = isset($_POST['code']) ? (string) $_POST['code'] : '';
            if (TOTP::verify(totp_get_secret(), $code)) {
                totp_issue_cookie();
                $i['totp_msg'] = '验证通过，双因素认证已生效';
            } else {
                $i['totp_msg'] = '验证码错误，请重新输入';
            }
        } elseif ($action === 'disable') {
            option::set('admin_totp_secret', '');
            $i['totp_msg'] = '双因素认证已关闭';
        }
        doAction('totp_setting_2');
        $i['totp_enabled'] = totp_enabled();
        if ($i['totp_enabled']) {
            $i['totp_secret'] = totp_get_secret();
            $i['totp_account'] = SYSTEM_NAME !== '' ? 'admin@' . SYSTEM_NAME : 'admin';
            $i['totp_uri'] = TOTP::otpauthUri($i['totp_secret'], $i['totp_account'], SYSTEM_NAME);
        }
        $i['mode'] = array('admin', 'totp');
        template('admin-totp');
        break;
    case 'admin:cron':
        doAction('cron_setting_1');
        if (!empty($_GET['act']) && preg_match('/^[A-Za-z0-9_]+$/', (string) $_GET['act'])) {
            cron::aset($_GET['act'], array('no' => 0, 'enable' => 1));
        } elseif (!empty($_GET['dis']) && preg_match('/^[A-Za-z0-9_]+$/', (string) $_GET['dis'])) {
            cron::aset($_GET['dis'], array('no' => 1, 'enable' => 0));
        } elseif (isset($_GET['uninst']) && preg_match('/^[A-Za-z0-9_]+$/', (string) $_GET['uninst'])) {
            cron::del($_GET['uninst']);
        } elseif (isset($_GET['add'])) {
            $cronName = isset($_POST['name']) ? trim((string) $_POST['name']) : '';
            $cronFile = isset($_POST['file']) ? trim((string) $_POST['file']) : '';
            if (stripos($cronFile, 'do.php') !== false) {
                msg('<h4>请不要将do.php加入到云签的计划任务中来</h4>若需签到，请用云监控监控<br/>' . SYSTEM_URL . 'do.php<br/>即可实现计划任务(cron)的效果<br/><br/>推荐云监控:<a href="http://www.aliyun.com/product/jiankong/" target="_blank">阿里云监控</a> 或 <a href="http://jk.cloud.360.cn/" target="_blank">360网站服务监控</a> 或 <a href="http://ce.baidu.com/" target="_blank">百度云观测</a><br/>如果你的服务器在国外且国内访问较慢，则推荐使用:<a href="http://www.mywebcron.com/" target="_blank">Free Web Cron Service </a>', SYSTEM_URL . 'index.php?mod=admin:cron');
            } elseif (!preg_match('/^[A-Za-z0-9_]+$/', $cronName) || !preg_match('~^(lib/[A-Za-z0-9_\-/]+\.php|plugins/[A-Za-z0-9_\-]+/[A-Za-z0-9_\-/]+\.php)$~', str_replace('\\', '/', $cronFile)) || strpos($cronFile, '..') !== false) {
                msg('计划任务添加失败：任务名称或文件不合法');
            } else {
                $cronSet = array('file' => $cronFile, 'no' => $_POST['no'], 'desc' => $_POST['desc'], 'freq' => $_POST['freq'], 'lastdo' => $_POST['lastdo'], 'log' => $_POST['log']);
                if (isset($_POST['enable'])) { $cronSet['enable'] = ($_POST['enable'] == '0') ? 0 : 1; }
                if (isset($_POST['max_retry'])) { $cronSet['max_retry'] = max(0, min(20, (int) $_POST['max_retry'])); }
                cron::aset($cronName, $cronSet);
            }
        } elseif (isset($_GET['run']) && preg_match('/^[A-Za-z0-9_]+$/', (string) $_GET['run'])) {
            // V5.13：完整执行单个任务（加锁→执行→记录→排程）
            cron::runOnce($_GET['run']);
        } elseif (isset($_GET['retry']) && preg_match('/^[A-Za-z0-9_]+$/', (string) $_GET['retry'])) {
            // V5.13：手动重试，先清零重试计数再立即执行
            cron::aset($_GET['retry'], array('retry' => 0, 'nextrun' => 0));
            cron::runOnce($_GET['retry']);
        } elseif (isset($_GET['xorder'])) {
            foreach ($_POST['order'] as $key => $value) {
                if (!preg_match('/^[A-Za-z0-9_]+$/', (string) $key)) {
                    continue;
                }
                cron::aset($key, array('orde' => (int) $value));
            }
        }
        doAction('cron_setting_2');
        Redirect('index.php?mod=admin:cron&ok');

        break;
    case 'admin:create_lock':
        if (!file_put_contents(SYSTEM_ROOT . '/setup/install.lock', '1')) {
            $msg = '未能放置 install.lock，请手动完成。<br/><br/>';
        } else {
            $msg = '系统成功放置 install.lock，但是如果您的环境为引擎，您必须手工放置<br/><br/>';
        }
        $msg .= '若要手工放置，请在云签到的 setup 目录下建立一个 install.lock 文件';
        msg($msg);

        break;
    case 'baiduid':
        $bdussIn = isset($_POST['bduss']) ? $_POST['bduss'] : (isset($_GET['bduss']) ? $_GET['bduss'] : '');
        $stokenIn = isset($_POST['stoken']) ? $_POST['stoken'] : (isset($_GET['stoken']) ? $_GET['stoken'] : '');
        $delIn = isset($_POST['del']) ? $_POST['del'] : (isset($_GET['del']) ? $_GET['del'] : '');
        if (isset($_POST['delete']) || isset($_GET['delete'])) {
            doAction('baiduid_set_1');
            CleanUser(UID);
            $m->query("DELETE FROM `" . DB_NAME . "`.`" . DB_PREFIX . "baiduid` WHERE `" . DB_PREFIX . "baiduid`.`uid` = " . UID);
        } elseif (!empty($bdussIn) && !empty($stokenIn)) {
            if (option::get('bduss_num') == '-1' && ROLE != 'admin') {
                msg('本站禁止绑定新账号');
            }

            if (option::get('bduss_num') != '0' && ISVIP == false) {
                $count = $m->once_fetch_array("SELECT COUNT(*) AS `c` FROM `" . DB_NAME . "`.`" . DB_PREFIX . "baiduid` WHERE `" . DB_PREFIX . "baiduid`.`uid` = " . UID);
                if (($count['c'] + 1) > option::get('bduss_num')) {
                    msg('您当前绑定的账号数已达到管理员设置的上限<br/><br/>您当前已绑定 ' . $count['c'] . ' 个账号，最多只能绑定 ' . option::get('bduss_num') . ' 个账号');
                }
            }
            // 去除双引号和bduss=
            $bduss = str_replace('"', '', $bdussIn);
            $bduss = str_ireplace('BDUSS=', '', $bduss);
            $bduss = str_replace(' ', '', $bduss);
            $bduss = sqladds($bduss);
            // 同上操作取得stoken
            $stoken = str_replace('"', '', $stokenIn);
            $stoken = str_ireplace('STOKEN=', '', $stoken);
            $stoken = str_replace(' ', '', $stoken);
            $stoken = sqladds($stoken);
            //get user info
            $baiduUserInfo = getBaiduUserInfo($bduss);
            if (empty($baiduUserInfo["portrait"])) {
                msg('您的 Cookie 信息有误，请核验后重新绑定');
            }
            $baidu_name = sqladds($baiduUserInfo["name"]);
            $baidu_name_portrait = sqladds($baiduUserInfo["portrait"]);
            doAction('baiduid_set_2');

            $same_pid = option::get('same_pid');

            if ($same_pid === '3') {
                $checkSame = $m->once_fetch_array("SELECT id, uid FROM `" . DB_NAME . "`.`" . DB_PREFIX . "baiduid` WHERE `portrait` = '{$baidu_name_portrait}' AND `uid`='" . UID . "'");
            } else {
                $checkSame = $m->once_fetch_array("SELECT id, uid FROM `" . DB_NAME . "`.`" . DB_PREFIX . "baiduid` WHERE `portrait` = '{$baidu_name_portrait}'");
            }

            if ($same_pid == '3' && $checkSame['uid'] == UID && !empty($checkSame)) {
                $encBduss = $m->real_escape_string(P::encrypt($bduss));
            $encStoken = $m->real_escape_string(P::encrypt($stoken));
            $m->query("UPDATE `" . DB_NAME . "`.`" . DB_PREFIX . "baiduid` SET `bduss`='{$encBduss}', `stoken`='{$encStoken}' WHERE `id` = '{$checkSame["id"]}';");
            } else {
                $encBduss2 = $m->real_escape_string(P::encrypt($bduss));
            $encStoken2 = $m->real_escape_string(P::encrypt($stoken));
            $m->query("INSERT INTO `" . DB_NAME . "`.`" . DB_PREFIX . "baiduid` (`id`,`uid`,`bduss`,`stoken`,`name`,`portrait`) VALUES  (NULL,'" . UID . "', '{$encBduss2}', '{$encStoken2}', '{$baidu_name}', '{$baidu_name_portrait}')");
            }
        } elseif (!empty($delIn)) {
            $del = (int) $delIn;
            doAction('baiduid_set_3');
            $x = $m->once_fetch_array("SELECT * FROM  `" . DB_NAME . "`.`" . DB_PREFIX . "users` WHERE  `id` = " . UID . " LIMIT 1");
            $m->query("DELETE FROM `" . DB_NAME . "`.`" . DB_PREFIX . "baiduid` WHERE `" . DB_PREFIX . "baiduid`.`uid` = " . UID . " AND `" . DB_PREFIX . "baiduid`.`id` = " . $del);
            $m->query('DELETE FROM `' . DB_NAME . '`.`' . DB_PREFIX . $x['t'] . '` WHERE `' . DB_PREFIX . $x['t'] . '`.`uid` = ' . UID . ' AND `' . DB_PREFIX . $x['t'] . '`.`pid` = ' . $del);
        } elseif (isset($_POST['batch_import'])) {
            //批量导入 BDUSS（可带 STOKEN），每行一个账号
            $batchRaw = (string) (isset($_POST['batch_bduss']) ? $_POST['batch_bduss'] : '');
            $lines = preg_split('/\r?\n/', $batchRaw);
            $lines = array_filter(array_map('trim', $lines), 'strlen');
            if (count($lines) > 50) {
                msg('单次最多导入 50 个账号，请分批操作');
            }
            $quota = option::get('bduss_num');
            $countRow = $m->once_fetch_array("SELECT COUNT(*) AS c FROM `" . DB_NAME . "`.`" . DB_PREFIX . "baiduid` WHERE `uid` = " . UID);
            $current = (int) $countRow['c'];
            $samePid = option::get('same_pid');
            $ok = 0;
            $skip = 0;
            $fail = 0;
            $failDetail = array();
            foreach ($lines as $line) {
                //兼容 BDUSS=xxx / STOKEN=yyy 前缀与常见分隔符
                $line = str_ireplace(array('BDUSS=', 'STOKEN='), array('', ';'), $line);
                $parts = preg_split('/[,;|\s]+|----/', $line);
                $parts = array_values(array_filter(array_map('trim', $parts), 'strlen'));
                $bduss = isset($parts[0]) ? str_replace('"', '', $parts[0]) : '';
                $stoken = isset($parts[1]) ? str_replace('"', '', $parts[1]) : '';
                if (strlen($bduss) < 20) {
                    $fail++;
                    $failDetail[] = '格式无效';
                    continue;
                }
                if ($quota == '-1' && ROLE != 'admin') {
                    $skip++;
                    continue;
                }
                if ($quota != '0' && $quota != '-1' && !ISVIP && ($current + $ok) >= (int) $quota) {
                    $skip++;
                    continue;
                }
                $info = getBaiduUserInfo($bduss);
                if (empty($info['portrait'])) {
                    $fail++;
                    $failDetail[] = 'Cookie 校验失败';
                    continue;
                }
                $portrait = sqladds($info['portrait']);
                $bname = sqladds($info['name']);
                $encB = $m->real_escape_string(P::encrypt($bduss));
                $encS = $stoken !== '' ? ', `' . 'stoken' . '` = \'' . $m->real_escape_string(P::encrypt($stoken)) . '\'' : '';
                if ($samePid === '3') {
                    $dup = $m->once_fetch_array("SELECT `id` FROM `" . DB_NAME . "`.`" . DB_PREFIX . "baiduid` WHERE `portrait` = '{$portrait}' AND `uid` = " . UID . " LIMIT 1");
                } else {
                    $dup = $m->once_fetch_array("SELECT `id`, `uid` FROM `" . DB_NAME . "`.`" . DB_PREFIX . "baiduid` WHERE `portrait` = '{$portrait}' LIMIT 1");
                }
                if (!empty($dup)) {
                    if ($samePid === '3' && $dup['id'] > 0) {
                        $m->query("UPDATE `" . DB_NAME . "`.`" . DB_PREFIX . "baiduid` SET `bduss` = '{$encB}'{$encS} WHERE `id` = " . (int) $dup['id']);
                        $ok++;
                    } elseif (($samePid == '1' || $samePid == '2') && !ISADMIN) {
                        $skip++;
                    } else {
                        $encSIns = $stoken !== '' ? ', \'' . $m->real_escape_string(P::encrypt($stoken)) . '\'' : ', NULL';
                        $m->query("INSERT INTO `" . DB_NAME . "`.`" . DB_PREFIX . "baiduid` (`id`, `uid`, `bduss`, `stoken`, `name`, `portrait`) VALUES (NULL, " . UID . ", '{$encB}'{$encSIns}, '{$bname}', '{$portrait}')");
                        $ok++;
                    }
                } else {
                    $encSIns = $stoken !== '' ? ', \'' . $m->real_escape_string(P::encrypt($stoken)) . '\'' : ', NULL';
                    $m->query("INSERT INTO `" . DB_NAME . "`.`" . DB_PREFIX . "baiduid` (`id`, `uid`, `bduss`, `stoken`, `name`, `portrait`) VALUES (NULL, " . UID . ", '{$encB}'{$encSIns}, '{$bname}', '{$portrait}')");
                    $ok++;
                }
            }
            $summary = '批量导入完成：成功 ' . $ok . ' 个，跳过 ' . $skip . ' 个（配额或重复），失败 ' . $fail . ' 个';
            if (!empty($failDetail)) {
                $summary .= '<br/>失败原因：' . htmlspecialchars(implode('；', array_slice($failDetail, 0, 5)), ENT_QUOTES, 'UTF-8');
            }
            if ($ok > 0) {
                CleanUser(UID);
            }
            msg($summary, 'index.php?mod=baiduid');
        } elseif (isset($_POST['proxy_pid'])) {
            //账号级代理覆盖：留空恢复跟随全局，off 表示该账号禁用代理
            $proxyPid = (int) $_POST['proxy_pid'];
            $proxyOwner = $m->once_fetch_array("SELECT `id` FROM `" . DB_NAME . "`.`" . DB_PREFIX . "baiduid` WHERE `id` = {$proxyPid} AND `uid` = " . UID);
            if (empty($proxyOwner) && !ISADMIN) {
                msg('权限不足，无法修改该账号的代理设置');
            }
            $proxyRaw = trim((string) (isset($_POST['proxy']) ? $_POST['proxy'] : ''));
            if ($proxyRaw !== '' && strcasecmp($proxyRaw, 'off') !== 0 && misc::parseProxyUrl($proxyRaw) === false) {
                msg('代理格式不正确<br/><br/>支持格式：http://host:port 或 socks5://user:pass@host:port，留空跟随全局，off 为禁用');
            }
            $proxySql = "UPDATE `" . DB_NAME . "`.`" . DB_PREFIX . "baiduid` SET `proxy` = ? WHERE `id` = ?";
            $proxyParams = array($proxyRaw, $proxyPid);
            if (!ISADMIN) {
                $proxySql .= ' AND `uid` = ?';
                $proxyParams[] = UID;
            }
            $m->pquery($proxySql, $proxyParams);
            Redirect('index.php?mod=baiduid');
        } elseif (empty($bdussIn)) {
            msg('BDUSS或STOKEN不合法，请核验后重新绑定');
        }
        doAction('baiduid_set');
        Redirect("index.php?mod=baiduid");

        break;
    case 'showtb':
        if (isset($_POST['restore_pid'])) {
            $restorePidRaw = isset($_POST['restore_pid']) ? $_POST['restore_pid'] : '';
            if (!is_scalar($restorePidRaw) || preg_match('/^\d{1,10}$/', (string) $restorePidRaw) !== 1) {
                msg('无效的账号参数');
            }
            $restorePid = (int) $restorePidRaw;
            if (!SignManager::restoreBaiduid($restorePid, ISADMIN ? 0 : UID)) {
                msg('账号不存在或无权恢复');
            }
            Redirect('index.php?mod=showtb&ok');
        } elseif (isset($_GET['set'])) {
            $x = $m->fetch_array($m->query('SELECT * FROM  `' . DB_NAME . '`.`' . DB_PREFIX . TABLE . '` WHERE  `uid` = ' . UID . ' LIMIT 1'));
            $f = $x['tieba'];
            foreach ($_POST['no'] as $k => $x) {
                $id = intval($k);
                if ($x == '0') {
                    $xv = '0';
                } else {
                    $xv = '1';
                }
                $m->query("UPDATE `" . DB_PREFIX . TABLE . "` SET `no` =  '{$xv}' WHERE  `id` = '{$id}' AND `uid` = '" . UID . "' ;");
            }
            Redirect('index.php?mod=showtb&ok');
        } elseif (isset($_GET['refreshTiebaList'])) {
            $r = misc::scanTiebaByUser();
            echo 1;
        } elseif (isset($_GET['clean'])) {
            CleanUser(UID);
            Redirect('index.php?mod=showtb');
        } elseif (isset($_GET['reset'])) {
            $max = $m->fetch_array($m->query("select max(id) as id from `" . DB_NAME . "`.`" . DB_PREFIX . TABLE . "` where `uid`=" . UID));
            $min = $m->fetch_array($m->query("select min(id) as id from `" . DB_NAME . "`.`" . DB_PREFIX . TABLE . "` where `uid`=" . UID));
            $max = $max['id'];
            $min = $min['id'];
            while ($min < $max) {
                $res = $m->fetch_array($m->query('SELECT * FROM `' . DB_NAME . '`.`' . DB_PREFIX . TABLE . '` WHERE `id` =' . $min . ' Limit 1'));
                if ($res['status'] != 0) {
                    $m->query('UPDATE `' . DB_NAME . '`.`' . DB_PREFIX . TABLE . '` SET `latest` = 0,`status` = 0,`last_error` = NULL WHERE `id` =' . $min);
                }
                $min = $min + 1;
            }
            Redirect('index.php?mod=showtb&ok');
        } elseif (isset($_POST['add'])) {
            if (option::get('enable_addtieba') == '1') {
                $v = trim((string) $_POST['add']);
                if ($v === '' || mb_strlen($v, 'UTF-8') > 60) {
                    msg('贴吧名不合法');
                }
                $pid = (int) strip_tags($_POST['pid']);
                $osq = $m->pquery("SELECT * FROM `" . DB_NAME . "`.`" . DB_PREFIX . TABLE . "` WHERE `uid` = ? AND `tieba` = ?", array(UID, $v));
                if ($m->num_rows($osq) == 0) {
                    $table = $m->fetch_array($m->query('select * from `' . DB_NAME . '`.`' . DB_PREFIX . 'users` where `id` = ' . UID));
                    $tb_max = $m->fetch_row($m->query("SELECT COUNT(*) FROM `" . DB_NAME . "`.`" . DB_PREFIX . $table['t'] . "` where `uid` = " . UID));
                    if (ROLE == 'admin' || ROLE == 'vip') {
                        $m->pquery("INSERT INTO `" . DB_NAME . "`.`" . DB_PREFIX . TABLE . "` (`pid`, `uid`, `tieba`, `no`, `latest`) VALUES (?, ?, ?, 0, 0)", array($pid, UID, $v));
                    } else {
                        if ($tb_max[0] < option::get('tb_max')) {
                            $m->pquery("INSERT INTO `" . DB_NAME . "`.`" . DB_PREFIX . TABLE . "` (`pid`, `uid`, `tieba`, `no`, `latest`) VALUES (?, ?, ?, 0, 0)", array($pid, UID, $v));
                        } else {
                            msg('错误：您的贴吧数量超过限制，无法刷新！');
                        }
                    }
                }
            }
            Redirect('index.php?mod=showtb&ok');
        }
        doAction('showtb_set');

        break;
    case 'set':
        //用户自主修改登录密码：验证当前密码后更新，旧会话 cookie 随哈希变化自动失效
        if (isset($_POST['pw_action']) && $_POST['pw_action'] === 'change') {
            $curPw = (string) request_scalar_value($_POST, 'cur_pw');
            $newPw = (string) request_scalar_value($_POST, 'new_pw');
            $newPw2 = (string) request_scalar_value($_POST, 'new_pw2');
            if ($curPw === '' || $newPw === '' || $newPw2 === '') {
                msg('修改密码失败：请填写当前密码与新密码');
            }
            if (strlen($newPw) < 6 || strlen($newPw) > 64) {
                msg('修改密码失败：新密码长度需为 6-64 位');
            }
            if ($newPw !== $newPw2) {
                msg('修改密码失败：两次输入的新密码不一致');
            }
            $pwRow = $m->once_fetch_array("SELECT `pw` FROM `" . DB_NAME . "`.`" . DB_PREFIX . "users` WHERE `id` = " . (int) UID . " LIMIT 1");
            if (empty($pwRow['pw']) || !VerifyPwd($curPw, $pwRow['pw'])) {
                msg('修改密码失败：当前密码不正确');
            }
            $m->pquery("UPDATE `" . DB_PREFIX . "users` SET `pw` = ? WHERE `id` = ?", array(EncodePwd($newPw), UID));
            doAction('set_pw_changed');
            clear_site_cookie('uid');
            clear_site_cookie('pwd');
            msg('密码修改成功，请使用新密码重新登录', 'index.php?mod=login');
        }
        $apiAction = isset($_POST['api_action']) ? (string) $_POST['api_action'] : '';
        if ($apiAction === 'generate') {
            try {
                $plainToken = 'tcs_' . bin2hex(random_bytes(32));
            } catch (Throwable $e) {
                $plainToken = 'tcs_' . hash('sha256', uniqid('', true) . mt_rand());
            }
            $tokenHash = hash('sha256', $plainToken);
            $m->query("UPDATE `" . DB_PREFIX . "users` SET `api_token_hash` = '" . $m->real_escape_string($tokenHash) . "' WHERE `id` = " . (int) UID);
            msg('新的个人 API Token 已生成。此值只显示一次，请立即保存：<br/><br/><code style="word-break:break-all;display:block;padding:12px;background:#f5f5f5">' . htmlspecialchars($plainToken, ENT_QUOTES, 'UTF-8') . '</code><br/>调用时使用 <code>Authorization: Bearer 上述Token</code>。它只能访问当前账号的数据，可在个人设置中随时撤销。', 'index.php?mod=set');
        }
        if ($apiAction === 'revoke') {
            $m->query("UPDATE `" . DB_PREFIX . "users` SET `api_token_hash` = NULL WHERE `id` = " . (int) UID);
            Redirect('index.php?mod=set&ok');
        }
        // 获取头像的url
        // 无法获取无id帐号头像, 不建议使用 *wontfix

        if ($i['post']['face_img'] == 1 && $i['post']['face_baiduid'] != '') {
            $data = getUserInfo($i['post']['face_baiduid']);
            $i['post']['face_url'] = "https://himg.bdimg.com/sys/portrait/item/{$data["data"]["portrait"]}";
            if (empty($i['post']['face_url'])) {
                msg('获取贴吧头像失败，可能是网络问题，请重试');
            }
        }
        /*
        受信任的设置项，如果插件要使用系统的API去储存设置，必须通过set_save1或set_save2挂载点挂载设置名
        具体挂载方法为：
        global $PostArray;
        $PostArray[] = '设置名';
        为了兼容旧版本，可以global以后检查一下是不是空变量，为空则为旧版本
        */
        $PostArray = array(
            'face_img',
            'face_baiduid',
            'face_url'
        );
        doAction('set_save1');
    //更改邮箱
        if ($_POST['mail'] != $i['user']['email'] && !empty($_POST['mail'])) {
            if (checkMail($_POST['mail'])) {
                $mail = trim((string) $_POST['mail']);
                $dup = $m->pquery("SELECT COUNT(*) AS total FROM `" . DB_NAME . "`.`" . DB_PREFIX . "users` WHERE `email` = ?", array($mail));
                $z = $dup ? $m->fetch_array($dup) : array('total' => 0);
                if ($z['total'] > 0) {
                    msg('修改失败：邮箱已经存在');
                }
                $m->pquery("UPDATE `" . DB_PREFIX . "users` SET `email` = ? WHERE `id` = ?", array($mail, UID));
            } else {
                msg('邮箱格式有误，请检查');
            }
        }
        $set = array();
        foreach ($PostArray as $value) {
            if (!isset($i['post'][$value])) {
                $i['post'][$value] = '';
            }
            @option::uset($value, $i['post'][$value]);
        }
        doAction('set_save2');
        Redirect('index.php?mod=set&ok');

        break;
    case 'admin:testnotify':
        $r = Notify::send(SYSTEM_FN . ' V' . SYSTEM_VER . ' 通知测试', "这是一条来自 " . SYSTEM_FN . " 的测试通知，如果你收到了此消息，表示通知系统可以正常工作。\n\n站点地址：" . SYSTEM_URL, 'test');
        if ($r['ok']) {
            Redirect('index.php?mod=admin:set&notifytestok');
        } else {
            msg('通知发送失败：' . $r['detail']);
        }
        break;
    case 'admin:testmail':
        global $i;
        $x = misc::mail($i['user']['email'], SYSTEM_FN . ' V' . SYSTEM_VER . ' - 邮件发送测试', '这是一封关于 ' . SYSTEM_FN . ' 的测试邮件，如果你收到了此邮件，表示邮件系统可以正常工作<br/><br/>站点地址：' . SYSTEM_URL, array('测试附件.txt' => '这是一个测试附件'));
        if ($x === true) {
            Redirect('index.php?mod=admin:set&mailtestok');
        } else {
            msg('邮件发送失败，发件日志：<br/>' . $x);
        }

        break;
}

if (ROLE == 'admin' && $i['mode'][0] == 'plugin') {
    option::pset($i['mode'][1], $_POST);
    Redirect("index.php?mod=admin:setplug&plug={$i['mode'][1]}&ok");
} elseif (ROLE == 'admin' && $i['mode'][0] == 'setplugin') {
    settingPlugin($i['mode'][1]);
}
