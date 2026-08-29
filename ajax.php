<?php
require dirname(__FILE__) . '/init.php';

$requestMethod = isset($_SERVER['REQUEST_METHOD']) ? strtoupper((string) $_SERVER['REQUEST_METHOD']) : 'GET';
$updateAction = SYSTEM_PAGE === 'admin:update:updnow';
if ($updateAction && $requestMethod !== 'POST') {
    msg('更新操作必须使用 POST 请求');
}
$stateChangingRequest = $updateAction || in_array($requestMethod, array('POST', 'PUT', 'PATCH', 'DELETE'), true);
csrf($stateChangingRequest, $stateChangingRequest);
if (ROLE != 'admin' && strpos(SYSTEM_PAGE, 'admin:') === 0) {
    msg('权限不足');
}

switch (SYSTEM_PAGE) {
    case 'ajax:status':
        global $m,$i;
        $today = date('d');
        $count = array(
            'userSigned'  => 0,
            'userWaiting' => 0,
            'userError'   => 0,
            'allSigned'   => 0,
            'allWaiting'  => 0,
            'allNo'       => 0,
            'allError'    => 0
        );
        $signUser = $m->query("SELECT `latest`,`status` FROM `" . DB_NAME . "`.`" . DB_PREFIX . TABLE . "` WHERE `uid` = " . UID . " AND `no` = '0'");
        while ($countUser = $m->fetch_array($signUser)) {
            if ($countUser['latest'] == $today) {
                if ($countUser['status'] != '0') {
                    $count['userError']++;
                } else {
                    $count['userSigned']++;
                }
            } else {
                $count['userWaiting']++;
            }
        }
        echo "<br/><b>签到状态：</b>已签到 {$count['userSigned']} 个贴吧，{$count['userError']} 个出错， {$count['userWaiting']} 个贴吧等待签到";
        echo '<br/><b>您的签到数据表：</b>' . DB_PREFIX . TABLE;

        if (ROLE == 'admin') {
            foreach ($i['table'] as $value) {
                $signTab = $m->query("SELECT `latest`,`status`,`no` FROM `" . DB_NAME . "`.`" . DB_PREFIX . $value . "`");
                while ($countTab = $m->fetch_array($signTab)) {
                    if ($countTab['no'] != '0') {
                        $count['allNo']++;
                    } elseif ($countTab['latest'] == $today) {
                        if ($countTab['status'] != '0') {
                            $count['allError']++;
                        } else {
                            $count['allSigned']++;
                        }
                    } else {
                        $count['allWaiting']++;
                    }
                }
            }
            echo "<br/><b>签到状态[总体]：</b>已签到 {$count['allSigned']} 个贴吧，还有 {$count['allWaiting']} 个贴吧等待签到";
            echo "<br/><b>贴吧状态[总体]：</b>有 {$count['allError']} 个贴吧签到出错，{$count['allNo']} 个贴吧已被设定为忽略";
            echo '<br/><b>用户注册/添加用户首选表：</b>' . DB_PREFIX . option::get('freetable');
        }
        break;

    case 'admin:server':
        ?>
        <li class="list-group-item">
            <b>PHP 版本：</b><?php echo htmlspecialchars(PHP_VERSION, ENT_QUOTES, 'UTF-8') ?>
            （SAPI：<?php echo htmlspecialchars(php_sapi_name(), ENT_QUOTES, 'UTF-8') ?>）
        </li>
        <li class="list-group-item">
            <b>MySQL 版本：</b><?php echo htmlspecialchars($m->getMysqlVersion(), ENT_QUOTES, 'UTF-8') ?>
        </li>
        <li class="list-group-item">
            <b>程序最大运行时间：</b><?php echo htmlspecialchars(ini_get('max_execution_time'), ENT_QUOTES, 'UTF-8') ?>s
        </li>
        <li class="list-group-item">
            <b>POST许可：</b><?php echo htmlspecialchars(ini_get('post_max_size'), ENT_QUOTES, 'UTF-8'); ?>
        </li>
        <li class="list-group-item">
            <b>文件上传许可：</b><?php echo htmlspecialchars(ini_get('upload_max_filesize'), ENT_QUOTES, 'UTF-8'); ?>
        </li>
        <?php
        break;

    case 'admin:update':
        try {
            $release = ProjectUpdater::fetchLatestRelease();
            $status = ProjectUpdater::buildStatus(SYSTEM_VER, $release);
            $tip = isset($_GET['ok']) ? '<b>更新成功，下面是最新版本信息。</b><hr/>' : '';
            $tip .= '<div><b>当前版本：</b>V' . htmlspecialchars($status['current_version'], ENT_QUOTES, 'UTF-8') . '　<b>最新正式版本：</b>V' . htmlspecialchars($status['latest_version'], ENT_QUOTES, 'UTF-8') . '</div>';
            $tip .= '<div class="text-right"><a href="' . htmlspecialchars($release['release_url'], ENT_QUOTES, 'UTF-8') . '" target="_blank" class="btn btn-default">查看 Release</a> ';
            if ($release['asset_download_url'] !== '') {
                $tip .= '<a href="' . htmlspecialchars($release['asset_download_url'], ENT_QUOTES, 'UTF-8') . '" target="_blank" class="btn btn-default">下载 Windows 包</a> ';
            }
            if ($status['update_available']) {
                $tip .= '<form action="ajax.php?mod=admin:update:updnow" method="post" style="display:inline;"><input type="hidden" name="csrf_token" value="' . htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') . '"><input type="hidden" name="tag" value="' . htmlspecialchars($release['tag_name'], ENT_QUOTES, 'UTF-8') . '"><button type="submit" class="btn btn-primary" onclick="waitup();">立即更新到 V' . htmlspecialchars($release['version'], ENT_QUOTES, 'UTF-8') . '</button></form>';
            } else {
                $tip .= '<span class="label label-success" style="display:inline-block;padding:8px;">当前已是最新版本</span>';
            }
            $tip .= '</div>';
            if ($release['body'] !== '') {
                $tip .= '<hr/><div><b>版本说明：</b><br/>' . nl2br(htmlspecialchars($release['body'], ENT_QUOTES, 'UTF-8')) . '</div>';
            }
            if ($release['asset_digest'] !== '') {
                $tip .= '<hr/><small>Windows 包校验值：' . htmlspecialchars($release['asset_digest'], ENT_QUOTES, 'UTF-8') . '</small>';
            }
            echo '<div class="panel panel-default" style="background-color: #F9F9F9;"><div class="panel-body">' . $tip . '</div></div>';
        } catch (Throwable $e) {
            error_log('在线更新检查失败：' . $e->getMessage());
            echo '<div class="alert alert-danger"><b>检查更新失败：</b>暂时无法连接更新服务器，请稍后重试或打开项目 Release 页面手动更新。</div>';
        }
        break;

    case 'admin:update:updnow':
        try {
            $release = ProjectUpdater::fetchLatestRelease();
            $requestedTag = isset($_POST['tag']) ? trim((string) $_POST['tag']) : '';
            if ($requestedTag !== '' && !hash_equals($requestedTag, $release['tag_name'])) {
                msg('更新版本已变化，请返回页面重新检查更新。');
            }
            $status = ProjectUpdater::buildStatus(SYSTEM_VER, $release);
            if (!$status['update_available']) {
                msg('当前已经是最新版本，无需更新。');
            }
            ProjectUpdater::installSourceRelease($release);
            msg('恭喜您，已更新到 V' . htmlspecialchars($release['version'], ENT_QUOTES, 'UTF-8') . '！', SYSTEM_URL . 'index.php?mod=admin:update&ok');
        } catch (Throwable $e) {
            error_log('在线更新失败：' . $e->getMessage());
            msg('更新失败：' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8'));
        }
        break;

    // case 'admin:update:changeServer':
    //     if (isset($_GET['server'])) {
    //         option::set('update_server', $_GET['server']);
    //     }
    //     break;

    case 'baiduid:getverify':
        global $m;
        if (option::get('bduss_num') == '-1' && ROLE != 'admin') {
            msg('本站禁止绑定新账号');
        }
        if (option::get('bduss_num') != '0' && ISVIP == false) {
            $count = $m->once_fetch_array("SELECT COUNT(*) AS `c` FROM `" . DB_NAME . "`.`" . DB_PREFIX . "baiduid` WHERE `uid` = " . UID);
            if (($count['c'] + 1) > option::get('bduss_num')) {
                msg('您当前绑定的账号数已达到管理员设置的上限<br/><br/>您当前已绑定 ' . $count['c'] . ' 个账号，最多只能绑定 ' . option::get('bduss_num') . ' 个账号');
            }
        }
        $name  = !empty($_POST['bd_name']) ? $_POST['bd_name'] :
        die();
        $pw    = !empty($_POST['bd_pw'])   ? $_POST['bd_pw']   : die();
        $vcode = !empty($_POST['vcode'])   ? $_POST['vcode']   : '';
        $vcodestr = !empty($_POST['vcodestr']) ? $_POST['vcodestr'] : '';
        $loginResult = misc::loginBaidu($name, $pw, $vcode, $vcodestr);
    if ($loginResult[0] == -3) {
        echo json_encode(array('error' => '-3', 'msg' => '请输入验证码', 'vcodestr' => $loginResult[1], 'img' => $loginResult[2]), JSON_UNESCAPED_UNICODE);
    } elseif ($loginResult[0] == 0) {
        $bdussSafe = $m->escape_string((string) $loginResult[1]);
        $nameSafe = $m->escape_string((string) $loginResult[2]);
        if ((option::get('same_pid') == '1' || option::get('same_pid') == '2') && !ISADMIN) {
            $checkSame = $m->once_fetch_array("SELECT * FROM `" . DB_NAME . "`.`" . DB_PREFIX . "baiduid` WHERE `name` = '{$nameSafe}'");
            if (!empty($checkSame)) {
                if (option::get('same_pid') == '2') {
                    echo json_encode(array('error' => '-11', 'msg' => '你已经绑定了这个百度账号或者该账号已被其他人绑定，若要重新绑定，请先解绑'), JSON_UNESCAPED_UNICODE);
                } elseif (option::get('same_pid') == '1' && $checkSame['uid'] == UID) {
                    echo json_encode(array('error' => '-10', 'msg' => '你已经绑定了这个百度账号，若要重新绑定，请先解绑'), JSON_UNESCAPED_UNICODE);
                }
                die;
            }
        }
        $bdussEnc = $m->escape_string(P::encrypt((string)$loginResult[1]));
        $m->query("INSERT INTO `" . DB_NAME . "`.`" . DB_PREFIX . "baiduid` (`uid`,`bduss`,`name`) VALUES  (" . UID . ", '{$bdussEnc}', '{$nameSafe}')");
        echo json_encode(array('error' => '0', 'msg' => '获取BDUSS成功', 'bduss' => $loginResult[1], 'name' => $loginResult[2]), JSON_UNESCAPED_UNICODE);
    } else {
        echo json_encode(array('error' => (string) $loginResult[0], 'msg' => (string) $loginResult[1]), JSON_UNESCAPED_UNICODE);
    }
        break;
    case "baiduid:qrlogin":
        global $m;
        if (option::get('bduss_num') == '-1' && ROLE != 'admin') {
            msg('本站禁止绑定新账号');
        }
        if (option::get('bduss_num') != '0' && ISVIP == false) {
            $count = $m->once_fetch_array("SELECT COUNT(*) AS `c` FROM `" . DB_NAME . "`.`" . DB_PREFIX . "baiduid` WHERE `uid` = " . UID);
            if (($count['c'] + 1) > option::get('bduss_num')) {
                msg('您当前绑定的账号数已达到管理员设置的上限<br/><br/>您当前已绑定 ' . $count['c'] . ' 个账号，最多只能绑定 ' . option::get('bduss_num') . ' 个账号');
            }
        }
        $sign = !empty($_POST['sign']) ? $_POST['sign'] :
        die();
        $loginResult = misc::get_real_bduss($sign);
    if ($loginResult["error"] == 0) {
        $baiduUserInfo = getBaiduUserInfo($loginResult["bduss"]);
        if (!empty($baiduUserInfo["portrait"])) {
            $baidu_name = $baiduUserInfo["name"];
            $baidu_name_portrait = sqladds($baiduUserInfo["portrait"]);
            $baidu_name_esc = $m->escape_string((string) $baidu_name);
            $bduss_esc = $m->escape_string(P::encrypt((string) $loginResult["bduss"]));
            $stoken_esc = $m->escape_string(P::encrypt((string) $loginResult["stoken"]));

            $same_pid = option::get('same_pid');

            if ($same_pid === '3') {
                $checkSame = $m->once_fetch_array("SELECT id, uid FROM `" . DB_NAME . "`.`" . DB_PREFIX . "baiduid` WHERE `portrait` = '{$baidu_name_portrait}' AND `uid`='" . UID . "'");
            } else {
                $checkSame = $m->once_fetch_array("SELECT id, uid FROM `" . DB_NAME . "`.`" . DB_PREFIX . "baiduid` WHERE `portrait` = '{$baidu_name_portrait}'");
            }
            
            if ($same_pid != '3' && ($same_pid == '1' || $same_pid == '2') && !ISADMIN) {
                if (!empty($checkSame)) {
                    if ($same_pid == '2') {
                        $loginResult["error"] = -11;
                        $loginResult["msg"] = "你已经绑定了这个百度账号或者该账号已被其他人绑定，若要重新绑定，请先解绑";
                    } elseif ($same_pid == '1' && $checkSame['uid'] == UID) {
                        $loginResult["error"] = -10;
                        $loginResult["msg"] = "你已经绑定了这个百度账号，若要重新绑定，请先解绑";
                    }
                    $loginResult["bduss"] = "";
                } else {
                    $m->query("INSERT INTO `" . DB_NAME . "`.`" . DB_PREFIX . "baiduid` (`id`,`uid`,`bduss`,`stoken`,`name`,`portrait`) VALUES  (NULL,'" . UID . "', '{$bduss_esc}', '{$stoken_esc}', '{$baidu_name_esc}', '{$baidu_name_portrait}')");
                    $loginResult["msg"] = "获取BDUSS成功";
                    $loginResult["name"] = "{$baidu_name} [{$baidu_name_portrait}]";
                }
            } else {
                if ($same_pid == '3' && $checkSame['uid'] == UID && !empty($checkSame)) {
                    $m->query("UPDATE `" . DB_NAME . "`.`" . DB_PREFIX . "baiduid` SET `bduss`='{$bduss_esc}', `stoken`='{$stoken_esc}' WHERE `id` = '{$checkSame["id"]}';");
                    $loginResult["msg"] = "更新BDUSS成功";
                } else {
                    $m->query("INSERT INTO `" . DB_NAME . "`.`" . DB_PREFIX . "baiduid` (`id`,`uid`,`bduss`,`stoken`,`name`,`portrait`) VALUES  (NULL,'" . UID . "', '{$bduss_esc}', '{$stoken_esc}', '{$baidu_name_esc}', '{$baidu_name_portrait}')");
                    $loginResult["msg"] = "获取BDUSS成功";
                }
                $loginResult["name"] = "{$baidu_name} [{$baidu_name_portrait}]";
            }
        }
    }
        echo json_encode($loginResult, JSON_UNESCAPED_UNICODE);

        break;
    default:
        msg('未定义操作');
        break;
}
?>
