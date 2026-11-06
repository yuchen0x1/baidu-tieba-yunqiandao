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

/** 保存用户账号分组映射（pid=>分组名），存 users_options 的 ver4_account_groups */
function baiduid_groups_save($uid, array $map)
{
    global $m;
    $json = json_encode($map, JSON_UNESCAPED_UNICODE);
    $exists = $m->pquery("SELECT `uid` FROM `" . DB_PREFIX . "users_options` WHERE `uid` = ? AND `name` = 'ver4_account_groups' LIMIT 1", array((int) $uid));
    if ($exists !== false && $exists->fetch_array()) {
        $m->pquery("UPDATE `" . DB_PREFIX . "users_options` SET `value` = ? WHERE `uid` = ? AND `name` = 'ver4_account_groups'", array($json, (int) $uid));
    } else {
        $m->pquery("INSERT INTO `" . DB_PREFIX . "users_options` (`uid`, `name`, `value`) VALUES (?, 'ver4_account_groups', ?)", array((int) $uid, $json));
    }
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
        $signUser = $m->pquery("SELECT
            SUM(`latest` = ? AND `status` != 0) AS err_cnt,
            SUM(`latest` = ? AND `status` = 0) AS signed_cnt,
            SUM(`latest` != ?) AS waiting_cnt
            FROM `" . DB_NAME . "`.`" . DB_PREFIX . TABLE . "` WHERE `uid` = ? AND `no` = '0'", array($today, $today, $today, UID));
        $userRow = $signUser !== false ? $m->fetch_array($signUser) : false;
        if (is_array($userRow)) {
            $count['userError']   = (int) $userRow['err_cnt'];
            $count['userSigned']  = (int) $userRow['signed_cnt'];
            $count['userWaiting'] = (int) $userRow['waiting_cnt'];
        }
        echo "<br/><b>签到状态：</b>已签到 {$count['userSigned']} 个贴吧，{$count['userError']} 个出错， {$count['userWaiting']} 个贴吧等待签到";
        echo '<br/><b>您的签到数据表：</b>' . DB_PREFIX . TABLE;

        if (ROLE == 'admin') {
            //全站统计用 SQL 聚合，不再把每张表全部行拉进 PHP 逐行计数
            foreach ($i['table'] as $value) {
                $signTab = $m->pquery("SELECT
                    SUM(`no` != '0') AS no_cnt,
                    SUM(`no` = '0' AND `latest` = ? AND `status` != '0') AS err_cnt,
                    SUM(`no` = '0' AND `latest` = ? AND `status` = '0') AS signed_cnt,
                    SUM(`no` = '0' AND `latest` != ?) AS waiting_cnt
                    FROM `" . DB_NAME . "`.`" . DB_PREFIX . $value . "`", array($today, $today, $today));
                $tabRow = $signTab !== false ? $m->fetch_array($signTab) : false;
                if (is_array($tabRow)) {
                    $count['allNo']      += (int) $tabRow['no_cnt'];
                    $count['allError']   += (int) $tabRow['err_cnt'];
                    $count['allSigned']  += (int) $tabRow['signed_cnt'];
                    $count['allWaiting'] += (int) $tabRow['waiting_cnt'];
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
            // 历史更新日志：拉取失败时静默跳过，不影响主检查结果
            try {
                $history = ProjectUpdater::fetchReleaseHistory($release['tag_name']);
                $hist = '<div class="panel panel-default" style="background-color: #F9F9F9;"><div class="panel-body"><b>历史更新日志</b>';
                $shown = 0;
                foreach ($history as $h) {
                    if ($h['tag_name'] === $release['tag_name']) {
                        continue; // 最新版本的说明已在上方展示
                    }
                    $pubDate = $h['published_at'] !== '' ? substr($h['published_at'], 0, 10) : '';
                    $hist .= '<details class="cs-collapse"' . ($shown === 0 ? ' open' : '') . '>'
                        . '<summary>V' . htmlspecialchars($h['version'], ENT_QUOTES, 'UTF-8')
                        . ($h['is_prerelease'] ? '　<span class="cs-badge cs-badge-neutral">预发布</span>' : '')
                        . ($pubDate !== '' ? '　<span class="text-muted">' . htmlspecialchars($pubDate, ENT_QUOTES, 'UTF-8') . '</span>' : '')
                        . '</summary>'
                        . '<div class="cs-collapse-body">'
                        . ($h['body'] !== '' ? nl2br(htmlspecialchars($h['body'], ENT_QUOTES, 'UTF-8')) : '（本版本无发布说明）')
                        . ($h['release_url'] !== '' ? '<br/><a href="' . htmlspecialchars($h['release_url'], ENT_QUOTES, 'UTF-8') . '" target="_blank" rel="noopener">查看该版本 Release 页</a>' : '')
                        . '</div></details>';
                    $shown++;
                }
                $hist .= $shown === 0 ? '暂无更多历史版本。' : '';
                $hist .= '</div></div>';
                echo $hist;
            } catch (Throwable $e) {
                error_log('获取历史更新日志失败：' . $e->getMessage());
            }
        } catch (ProjectUpdaterRateLimitException $e) {
            error_log('在线更新检查失败（限流）：' . $e->getMessage());
            $retryAt = $e->getResetAt() > time() ? $e->getResetAt() : time() + max(60, $e->getRetryAfter());
            echo '<div class="alert alert-warning"><b>检查更新失败：GitHub 接口达到匿名请求限额。</b><br/>'
                . 'GitHub 对匿名请求按 IP 限流，服务器出口 IP 当日额度已用尽。建议：'
                . '<ul><li>稍后重试（额度预计 ' . htmlspecialchars(date('H:i', $retryAt), ENT_QUOTES, 'UTF-8') . ' 前后恢复）；</li>'
                . '<li>在部署环境中配置环境变量 <code>UPDATE_GITHUB_TOKEN</code>（GitHub 个人访问令牌，只发送给 api.github.com）可提升限额；</li>'
                . '<li><a href="' . htmlspecialchars(PROJECT_RELEASES_URL, ENT_QUOTES, 'UTF-8') . '" target="_blank" rel="noopener">打开项目 Release 页面手动更新</a>。</li></ul></div>';
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
            //文件已覆盖：跳转根目录一键升级入口（新请求加载新代码，自动备份+迁移+清缓存），实现点一次「立即更新」全自动完成
            msg('文件已更新到 V' . htmlspecialchars($release['version'], ENT_QUOTES, 'UTF-8') . '，正在跳转完成升级……', SYSTEM_URL . 'upgrade.php?autostart=1');
        } catch (ProjectUpdaterRateLimitException $e) {
            error_log('在线更新失败（限流）：' . $e->getMessage());
            msg('更新失败：GitHub 接口达到匿名请求限额，请稍后重试；'
                . '也可配置环境变量 UPDATE_GITHUB_TOKEN 提升限额，或到 <a href="' . htmlspecialchars(PROJECT_RELEASES_URL, ENT_QUOTES, 'UTF-8') . '" target="_blank" rel="noopener">Release 页面</a>手动下载更新包。');
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

    case 'sign:run':
        //聚合操作：立即签到我的账号（pid=0 全部账号；pid>0 指定账号，单次最多处理 15 个贴吧）
        if (!defined('UID')) {
            msg('请先登录');
        }
        @set_time_limit(100);
        global $i;
        require_once SYSTEM_ROOT . '/lib/class.SignManager.php';
        require_once SYSTEM_ROOT . '/lib/security.php';
        $windowBlocked = misc::signWindowBlocked();
        if ($windowBlocked !== false) {
            echo json_encode(array('error' => 2, 'msg' => '当前不在签到时段内：' . $windowBlocked), JSON_UNESCAPED_UNICODE);
            die;
        }
        $signPid = isset($_POST['pid']) ? (int) $_POST['pid'] : 0;
        $done = 0;
        if ($signPid > 0) {
            $ownSign = $m->pquery("SELECT `id` FROM `" . DB_PREFIX . "baiduid` WHERE `id` = ? AND `uid` = ?", array($signPid, UID));
            $ownSignRow = $ownSign !== false ? $ownSign->fetch_array() : false;
            if (empty($ownSignRow)) {
                echo json_encode(array('error' => 1, 'msg' => '账号不存在或无权访问'), JSON_UNESCAPED_UNICODE);
                die;
            }
            $signMode = safe_sign_modes(option::get('sign_mode'));
            $todayD = date('d');
            $remaining = 15;
            foreach ($i['table'] as $sgTable) {
                if ($remaining <= 0) {
                    break;
                }
                $sgQs = $m->pquery("SELECT * FROM `" . DB_PREFIX . $sgTable . "` WHERE `pid` = ? AND `no` = 0 AND `latest` != ? LIMIT " . $remaining, array($signPid, $todayD));
                if ($sgQs === false) {
                    continue;
                }
                while ($sgRow = $sgQs->fetch_array()) {
                    misc::DoSign_All((int) $sgRow['uid'], $sgRow['tieba'], (int) $sgRow['id'], $sgTable, $signMode, (int) $sgRow['pid'], (int) $sgRow['fid']);
                    $done++;
                    $remaining--;
                    if ($remaining <= 0) {
                        break;
                    }
                }
            }
        } else {
            foreach ($i['table'] as $sgTable) {
                misc::DoSign($sgTable, UID);
            }
        }
        //「立即签到」只处理当前用户自己的账号，统计也必须限定本人，避免把全站数字回给普通用户
        $signStat = SignManager::signSummary((int) UID);
        echo json_encode(array(
            'error' => 0,
            'done' => $done,
            'signed' => (int) $signStat['signed'],
            'failed' => (int) $signStat['error'],
            'waiting' => (int) $signStat['waiting'],
        ), JSON_UNESCAPED_UNICODE);
        die;

    case 'baiduid:groups':
        //账号分组：保存当前用户的 pid→分组名 映射（JSON 存 users_options）
        if (!defined('UID')) {
            msg('请先登录');
        }
        $grJson = (string) (isset($_POST['groups']) ? $_POST['groups'] : '');
        $grMap = json_decode($grJson, true);
        if (!is_array($grMap)) {
            echo json_encode(array('error' => 1, 'msg' => '数据格式错误'), JSON_UNESCAPED_UNICODE);
            die;
        }
        $clean = array();
        foreach ($grMap as $grPid => $grName) {
            $grPid = (int) $grPid;
            $grName = mb_substr(trim((string) $grName), 0, 20);
            //仅允许当前用户自己的账号，分组名留空表示移除
            $own = $m->pquery("SELECT `id` FROM `" . DB_PREFIX . "baiduid` WHERE `id` = ? AND `uid` = ?", array($grPid, UID));
            if ($own !== false && $own->fetch_array()) {
                if ($grName !== '') {
                    $clean[$grPid] = $grName;
                }
            }
        }
        baiduid_groups_save(UID, $clean);
        echo json_encode(array('error' => 0, 'updated' => count($clean)), JSON_UNESCAPED_UNICODE);
        die;

    case 'baiduid:proxyall':
        //统一代理：一键应用到我的全部账号（留空恢复跟随全局，off 全部禁用）
        if (!defined('UID')) {
            msg('请先登录');
        }
        $paProxy = trim((string) (isset($_POST['proxy']) ? $_POST['proxy'] : ''));
        if ($paProxy !== '' && strcasecmp($paProxy, 'off') !== 0 && misc::parseProxyUrl($paProxy) === false) {
            echo json_encode(array('error' => 1, 'msg' => '代理格式不正确：支持 http://host:port 或 socks5://user:pass@host:port'), JSON_UNESCAPED_UNICODE);
            die;
        }
        $m->pquery("UPDATE `" . DB_PREFIX . "baiduid` SET `proxy` = ? WHERE `uid` = ?", array($paProxy, UID));
        $paCount = $m->pquery("SELECT COUNT(*) AS c FROM `" . DB_PREFIX . "baiduid` WHERE `uid` = ?", array(UID));
        $paRow = $paCount !== false ? $paCount->fetch_array() : array('c' => 0);
        echo json_encode(array('error' => 0, 'updated' => (int) $paRow['c']), JSON_UNESCAPED_UNICODE);
        die;

    case 'baiduid:moindex':
        //我的贴吧：关注列表 + 每吧等级 + 今日签到状态（mo/q/newmoindex），结果缓存 30 分钟
        global $m;
        if (!defined('UID')) {
            msg('请先登录');
        }
        $moPid = isset($_POST['pid']) ? (int) $_POST['pid'] : 0;
        $moForce = isset($_POST['force']) && $_POST['force'] == '1';
        $own = $m->pquery("SELECT `id`, `bduss` FROM `" . DB_PREFIX . "baiduid` WHERE `id` = ? AND `uid` = ?", array($moPid, UID));
        $ownRow = $own !== false ? $own->fetch_array() : false;
        if (empty($ownRow)) {
            echo json_encode(array('error' => 1, 'msg' => '账号不存在或无权访问'), JSON_UNESCAPED_UNICODE);
            die;
        }
        $cacheKey = 'baiduid_moindex_' . $moPid;
        $cacheTimeKey = 'baiduid_moindex_time_' . $moPid;
        $cached = option::get($cacheKey);
        $cachedTime = (int) option::get($cacheTimeKey);
        if (!$moForce && $cached !== '' && $cached !== null && time() - $cachedTime < 1800) {
            echo $cached;
            die;
        }
        $moBduss = P::decrypt($ownRow['bduss']);
        if (empty($moBduss)) {
            echo json_encode(array('error' => 2, 'msg' => '账号凭证无效'), JSON_UNESCAPED_UNICODE);
            die;
        }
        $moCh = new wcurl('https://tieba.baidu.com/mo/q/newmoindex');
        misc::applyProxy($moCh, $moPid);
        $moCh->addCookie(array('BDUSS' => $moBduss));
        $moRaw = $moCh->get();
        $moJson = json_decode((string) $moRaw, true);
        if (!is_array($moJson) || (isset($moJson['no']) && $moJson['no'] != 0) || empty($moJson['data']['like_forum'])) {
            echo json_encode(array('error' => 3, 'msg' => '贴吧接口暂时不可用，请稍后再试'), JSON_UNESCAPED_UNICODE);
            die;
        }
        $forums = array();
        $signed = 0;
        foreach ($moJson['data']['like_forum'] as $f) {
            $isSign = !empty($f['is_sign']);
            $forums[] = array(
                'name' => (string) $f['forum_name'],
                'level' => (int) (isset($f['user_level']) ? $f['user_level'] : 0),
                'exp' => (int) (isset($f['user_exp']) ? $f['user_exp'] : 0),
                'signed' => $isSign,
            );
            if ($isSign) {
                $signed++;
            }
        }
        usort($forums, function ($a, $b) {
            return $b['level'] === $a['level'] ? $b['exp'] - $a['exp'] : $b['level'] - $a['level'];
        });
        $out = json_encode(array(
            'error' => 0,
            'forums' => $forums,
            'signed' => $signed,
            'total' => count($forums),
            'fetched_at' => time(),
        ), JSON_UNESCAPED_UNICODE);
        option::set($cacheKey, $out);
        option::set($cacheTimeKey, (string) time());
        echo $out;
        die;

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
        $name  = !empty($_POST['bd_name']) ? $_POST['bd_name'] : '';
        $pw    = !empty($_POST['bd_pw'])   ? $_POST['bd_pw']   : '';
        if ($name === '' || $pw === '') {
            //前端期待 JSON；此前裸 die() 会输出空白页让用户无从判断
            echo json_encode(array('error' => '-1', 'msg' => '请填写百度账号和密码'), JSON_UNESCAPED_UNICODE);
            die;
        }
        $vcode = !empty($_POST['vcode'])   ? $_POST['vcode']   : '';
        $vcodestr = !empty($_POST['vcodestr']) ? $_POST['vcodestr'] : '';
        $loginResult = misc::loginBaidu($name, $pw, $vcode, $vcodestr);
    if ($loginResult[0] == -3) {
        echo json_encode(array('error' => '-3', 'msg' => '请输入验证码', 'vcodestr' => $loginResult[1], 'img' => $loginResult[2]), JSON_UNESCAPED_UNICODE);
    } elseif ($loginResult[0] == 0) {
        if ((option::get('same_pid') == '1' || option::get('same_pid') == '2') && !ISADMIN) {
            $checkRs = $m->pquery("SELECT * FROM `" . DB_NAME . "`.`" . DB_PREFIX . "baiduid` WHERE `name` = ?", array((string) $loginResult[2]));
            $checkSame = $checkRs ? $m->fetch_array($checkRs) : false;
            if (!empty($checkSame)) {
                if (option::get('same_pid') == '2') {
                    echo json_encode(array('error' => '-11', 'msg' => '你已经绑定了这个百度账号或者该账号已被其他人绑定，若要重新绑定，请先解绑'), JSON_UNESCAPED_UNICODE);
                } elseif (option::get('same_pid') == '1' && $checkSame['uid'] == UID) {
                    echo json_encode(array('error' => '-10', 'msg' => '你已经绑定了这个百度账号，若要重新绑定，请先解绑'), JSON_UNESCAPED_UNICODE);
                }
                die;
            }
        }
        $m->pquery("INSERT INTO `" . DB_NAME . "`.`" . DB_PREFIX . "baiduid` (`uid`,`bduss`,`name`) VALUES (?, ?, ?)", array(UID, P::encrypt((string) $loginResult[1]), (string) $loginResult[2]));
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
            $baidu_name = (string) $baiduUserInfo["name"];
            $baidu_name_portrait = (string) $baiduUserInfo["portrait"];
            $bdussEnc = P::encrypt((string) $loginResult["bduss"]);
            $stokenEnc = P::encrypt((string) $loginResult["stoken"]);

            $same_pid = option::get('same_pid');

            if ($same_pid === '3') {
                $checkRs = $m->pquery("SELECT id, uid FROM `" . DB_NAME . "`.`" . DB_PREFIX . "baiduid` WHERE `portrait` = ? AND `uid` = ?", array($baidu_name_portrait, UID));
            } else {
                $checkRs = $m->pquery("SELECT id, uid FROM `" . DB_NAME . "`.`" . DB_PREFIX . "baiduid` WHERE `portrait` = ?", array($baidu_name_portrait));
            }
            $checkSame = $checkRs ? $m->fetch_array($checkRs) : false;

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
                    $m->pquery("INSERT INTO `" . DB_NAME . "`.`" . DB_PREFIX . "baiduid` (`id`,`uid`,`bduss`,`stoken`,`name`,`portrait`) VALUES (NULL, ?, ?, ?, ?, ?)", array(UID, $bdussEnc, $stokenEnc, $baidu_name, $baidu_name_portrait));
                    $loginResult["msg"] = "获取BDUSS成功";
                    $loginResult["name"] = "{$baidu_name} [{$baidu_name_portrait}]";
                }
            } else {
                if ($same_pid == '3' && $checkSame['uid'] == UID && !empty($checkSame)) {
                    $m->pquery("UPDATE `" . DB_NAME . "`.`" . DB_PREFIX . "baiduid` SET `bduss` = ?, `stoken` = ? WHERE `id` = ?", array($bdussEnc, $stokenEnc, (int) $checkSame['id']));
                    $loginResult["msg"] = "更新BDUSS成功";
                } else {
                    $m->pquery("INSERT INTO `" . DB_NAME . "`.`" . DB_PREFIX . "baiduid` (`id`,`uid`,`bduss`,`stoken`,`name`,`portrait`) VALUES (NULL, ?, ?, ?, ?, ?)", array(UID, $bdussEnc, $stokenEnc, $baidu_name, $baidu_name_portrait));
                    $loginResult["msg"] = "获取BDUSS成功";
                }
                $loginResult["name"] = "{$baidu_name} [{$baidu_name_portrait}]";
            }
        }
    }
        echo json_encode($loginResult, JSON_UNESCAPED_UNICODE);

        break;
    case 'ver4_ai:testconn':
        // AI 模型连通性检测：优先用提交的地址/Key/模型（允许未保存即测），缺项回退已保存/共享配置
        if (!defined('UID')) {
            msg('请先登录');
        }
        require_once SYSTEM_ROOT . '/plugins/ver4_ai/ver4_ai_client.php';
        header('content-type: application/json; charset=utf-8');
        $url   = trim((string) ($_POST['url'] ?? ''));
        $key   = trim((string) ($_POST['key'] ?? ''));
        $model = trim((string) ($_POST['model'] ?? ''));
        $saved = ver4_ai_user_cfg(UID);
        if ($saved === false) {
            if ($url === '' || $key === '' || $model === '') {
                echo json_encode(array('ok' => false, 'msg' => '请先填写 API 地址、Key 和模型名称（或使用站点共享配置）'), JSON_UNESCAPED_UNICODE);
                die;
            }
            $cfg = array('base' => rtrim($url, '/'), 'key' => $key, 'model' => $model, 'temperature' => 0.1, 'max_tokens' => 256);
        } else {
            $cfg = $saved;
            if ($url !== '') {
                $cfg['base'] = rtrim($url, '/');
            }
            if ($key !== '') {
                $cfg['key'] = $key;
            }
            if ($model !== '') {
                $cfg['model'] = $model;
            }
        }
        $err = null;
        $t0 = microtime(true);
        $reply = ver4_ai_chat($cfg, array(array('role' => 'user', 'content' => '回复两个字：正常')), $err);
        $ms = (int) round((microtime(true) - $t0) * 1000);
        if ($reply === false) {
            echo json_encode(array('ok' => false, 'msg' => (string) $err), JSON_UNESCAPED_UNICODE);
            die;
        }
        echo json_encode(array('ok' => true, 'msg' => mb_substr((string) $reply, 0, 60), 'ms' => $ms), JSON_UNESCAPED_UNICODE);
        die;
    default:
        msg('未定义操作');
        break;
}
?>
