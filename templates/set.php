<?php if (!defined('SYSTEM_ROOT')) {
    die('Insufficient Permissions');
}
global $m,$i;

if (isset($_GET['ok'])) {
    echo '<div class="alert alert-success">设置保存成功</div>';
}

function addset($name, $type, $x, $other = '', $text = '')
{
    if ($type == 'checkbox') {
        if (option::uget($x) == 1) {
            $other .= ' checked="checked"';
        }
        $value = '1';
    } else {
        $value = option::uget($x);
    }
    echo '<tr><td>' . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . '</td><td><input type="' . htmlspecialchars($type, ENT_QUOTES, 'UTF-8') . '" name="' . htmlspecialchars($x, ENT_QUOTES, 'UTF-8') . '" value="' . htmlspecialchars($value, ENT_QUOTES, 'UTF-8') . '" ' . $other . '>' . htmlspecialchars($text, ENT_QUOTES, 'UTF-8') . '</td>';
}
$tokenRow0 = $m->once_fetch_array("SELECT `api_token_hash` FROM `" . DB_PREFIX . "users` WHERE `id` = " . (int) UID . " LIMIT 1");
$hasApiTokenHero = !empty($tokenRow0['api_token_hash']);
?>
<section class="dashboard-hero">
    <div class="eyebrow hero-eyebrow">Workspace · Settings</div>
    <h1>个人设置</h1>
    <p>基本信息、签到策略、通知报告与 API 安全设置集中在此，修改后统一提交。</p>
    <div class="dashboard-hero-meta">
        <span class="hero-tag">API Token：<?php echo $hasApiTokenHero ? '已启用' : '未生成'; ?></span>
    </div>
</section>
<form action="setting.php?mod=set" method="post" class="personal-settings-form">
<input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">
<?php doAction('set_1'); ?>
<div style="margin:16px 1%;display:grid;grid-template-columns:repeat(auto-fit,minmax(min(100%,320px),1fr));gap:16px;align-items:start">
<div style="display:flex;flex-direction:column;gap:16px">
<div class="panel panel-primary">
    <div class="panel-heading"><span class="panel-title">① 基本信息</span></div>
    <div class="table-responsive">
    <table class="cs-table">
        <tbody>
        <?php doAction('set_3'); ?>
        <tr>
            <td>头像设置<br/>使用Gravatar头像或贴吧头像</td>
            <td>
                <input type="radio" name="face_img" value="0" <?php if (option::uget('face_img') == '0') {
                    echo 'checked';
                                                              } ?>> 使用Gravatar头像（推荐）<br/>
                <input type="radio" name="face_img" value="1" <?php if (option::uget('face_img') == '1') {
                    echo 'checked';
                                                              } ?>> 使用贴吧头像 贴吧用户名：
                <input type="text" name="face_baiduid" value="<?php if (option::uget('face_baiduid')) {
                    echo htmlspecialchars(option::uget('face_baiduid'), ENT_QUOTES, 'UTF-8');
                                                              } ?>" class="form-control" >
            </td>
        </tr>
        <tr>
            <td>邮箱设置<br/>更改你在本站设置的邮箱地址</td>
            <td>
                <input type="text" name="mail" value="<?php echo htmlspecialchars($i['user']['email'], ENT_QUOTES, 'UTF-8') ?>" class="form-control" required>
            </td>
        </tr>
    </tbody>
    </table></div>
</div>
<div class="panel panel-info">
    <div class="panel-heading"><span class="panel-title">③ 通知与报告</span></div>
    <div class="panel-body reminder-delivery">
        <h3>最近推送结果</h3>
        <p>服务接受后才记录已发送；失败保留重试机会。此处不代表手机或邮箱已经读到消息。</p>
        <dl class="reminder-delivery-list">
        <?php foreach (array('sign_fail' => '失败提醒', 'daily_summary' => '每日摘要', 'weekreport' => '每周报告') as $reminderEvent => $reminderLabel) {
            $reminderResult = json_decode((string) option::uget('remind_result_' . $reminderEvent), true);
            $reminderStatus = is_array($reminderResult) ? ($reminderResult['status'] ?? '') : '';
            $reminderLabels = array('accepted' => '服务已接受', 'failed' => '发送失败', 'skipped' => '未发送');
            $reminderText = $reminderLabels[$reminderStatus] ?? '尚无记录';
            $reminderTone = $reminderStatus === 'accepted' ? 'success' : ($reminderStatus === 'failed' ? 'danger' : 'neutral');
        ?>
            <div>
                <dt><?php echo $reminderLabel; ?></dt>
                <dd><span class="cs-badge cs-badge-<?php echo $reminderTone; ?>"><?php echo $reminderText; ?></span>
                <?php if (!empty($reminderResult['at'])) { ?><time datetime="<?php echo date('c', (int) $reminderResult['at']); ?>"><?php echo date('m-d H:i', (int) $reminderResult['at']); ?></time><?php } ?></dd>
            </div>
        <?php } ?>
        </dl>
        <p>失败提醒使用个人邮箱；每日摘要优先使用<?php if (in_array('ver4_monitor', $i['plugins']['actived'] ?? array(), true)) { ?><a href="index.php?plugin=ver4_monitor">帖子监控</a><?php } else { ?>帖子监控插件<?php } ?>中配置的个人渠道，未发送时回退邮箱。</p>
    </div>
    <div class="table-responsive">
    <table class="cs-table">
        <tbody>
        <tr>
            <td>签到排行榜隐私<br/>默认在「签到排行」页面公开展示你的昵称</td>
            <td>
                <label style="display:inline-flex;align-items:center;gap:8px;cursor:pointer">
                    <input type="checkbox" name="hide_from_ranking" value="1" <?php if (option::uget('hide_from_ranking') == 1) echo 'checked'; ?>>
                    <span>在排行榜中隐藏我的昵称（显示为「用户#<?php echo (int) UID; ?>」）</span>
                </label>
            </td>
        </tr>
        <tr>
            <td>签到失败提醒<br/>当今日有贴吧签到失败时，向本站邮箱推送提醒邮件</td>
            <td>
                <label style="display:inline-flex;align-items:center;gap:8px;cursor:pointer">
                    <input type="checkbox" name="remind_sign_fail" value="1" <?php if (option::uget('remind_sign_fail') == 1) echo 'checked'; ?>>
                    <span>启用签到失败提醒</span>
                </label>
                <div style="margin-top:6px;display:flex;align-items:center;gap:6px">
                    <label for="remind_fail_threshold" style="margin:0">失败次数达到</label>
                    <input type="number" id="remind_fail_threshold" name="remind_sign_fail_threshold" min="1" max="20" value="<?php $t = (int) option::uget('remind_sign_fail_threshold'); echo $t > 0 ? $t : 1; ?>" style="width:60px">
                    <span>次时提醒（1-20）</span>
                </div>
            </td>
        </tr>
        <tr>
            <td>每日签到摘要<br/>到站点设定时间或签到完成后发送当日尝试次数汇总，失败自动留待下轮重试</td>
            <td>
                <label style="display:inline-flex;align-items:center;gap:8px;cursor:pointer">
                    <input type="checkbox" name="remind_daily_summary" value="1" <?php if (option::uget('remind_daily_summary') == 1) echo 'checked'; ?>>
                    <span>启用每日签到摘要</span>
                </label>
            </td>
        </tr>
        <tr>
            <td>每周签到报告<br/>每周到点通过你的个人通知渠道（帖子监控页配置，未配置走站点渠道）推送近 7 天签到汇总、成功率与失败原因 Top</td>
            <td>
                <label style="display:inline-flex;align-items:center;gap:8px;cursor:pointer">
                    <input type="checkbox" name="weekly_report_enable" value="1" <?php if (option::uget('weekly_report_enable') == 1) echo 'checked'; ?>>
                    <span>启用每周签到报告</span>
                </label>
                <div style="margin-top:6px;display:flex;align-items:center;gap:6px">
                    <label for="weekly_report_day" style="margin:0">推送日</label>
                    <select id="weekly_report_day" name="weekly_report_day" class="cs-form-control" style="width:auto">
                        <?php $wrDay = option::uget('weekly_report_day');
                        $wrDay = ($wrDay === null || $wrDay === '') ? 1 : (int) $wrDay;
                        foreach (array(1 => '周一', 2 => '周二', 3 => '周三', 4 => '周四', 5 => '周五', 6 => '周六', 0 => '周日') as $wdVal => $wdLabel) { ?>
                        <option value="<?php echo $wdVal; ?>" <?php if ($wrDay === $wdVal) echo 'selected'; ?>><?php echo $wdLabel; ?></option>
                        <?php } ?>
                    </select>
                </div>
            </td>
        </tr>
    </tbody>
    </table></div>
</div>
</div>
<div style="display:flex;flex-direction:column;gap:16px">
<div class="panel panel-info">
    <div class="panel-heading"><span class="panel-title">② 签到策略</span></div>
    <div class="table-responsive">
    <table class="cs-table">
        <tbody>
        <?php
        // 阶段 R：签到策略配置（用户级 + 全局只读信息）
        $globalSignHour = (int) option::get('sign_hour');
        $globalRetryMax = (int) option::get('retry_max');
        $globalCronLimit = (int) option::get('cron_limit');
        $userRetryMax = (int) option::uget('sign_retry_max');
        $userSignDelay = (int) option::uget('sign_delay_minutes');
        $userShuffle = option::uget('sign_shuffle') == 1;
        ?>
        <tr>
            <td>自动重试<br/>签到失败后自动重试的最大次数（用户级，覆盖全局设置）</td>
            <td>
                <select name="sign_retry_max" class="cs-form-control" style="width:auto;display:inline-block">
                    <option value="0" <?php if ($userRetryMax == 0) echo 'selected'; ?>>跟随全局（<?php echo $globalRetryMax > 0 ? $globalRetryMax . ' 次' : '未启用'; ?>）</option>
                    <?php for ($retryOption = 1; $retryOption <= 5; $retryOption++) { ?>
                    <option value="<?php echo $retryOption; ?>" <?php if ($userRetryMax == $retryOption) echo 'selected'; ?>>重试 <?php echo $retryOption; ?> 次</option>
                    <?php } ?>
                </select>
                <div style="font-size:12px;color:var(--color-muted);margin-top:4px">
                    全局签到时间：<?php echo $globalSignHour; ?>:00 后开始，单次批次限制 <?php echo $globalCronLimit > 0 ? $globalCronLimit : '不限' ?> 个贴吧
                </div>
            </td>
        </tr>
        <tr>
            <td>随机延迟<br/>签到开始前随机延迟 0-N 分钟，避免同时刻集中请求被封</td>
            <td>
                <div style="display:flex;align-items:center;gap:6px">
                    <input type="number" name="sign_delay_minutes" min="0" max="60" value="<?php echo $userSignDelay > 0 ? $userSignDelay : 0; ?>" style="width:60px">
                    <span>分钟（0 表示不延迟，建议 5-15）</span>
                </div>
            </td>
        </tr>
        <tr>
            <td>随机排序<br/>打乱贴吧签到顺序，避免每次以固定顺序请求</td>
            <td>
                <label style="display:inline-flex;align-items:center;gap:8px;cursor:pointer">
                    <input type="checkbox" name="sign_shuffle" value="1" <?php if ($userShuffle) echo 'checked'; ?>>
                    <span>启用随机排序</span>
                </label>
            </td>
        </tr>
    </tbody>
    </table></div>
</div>
<div class="panel panel-primary">
    <div class="panel-heading"><span class="panel-title">④ API 与安全</span></div>
    <div class="table-responsive">
    <table class="cs-table">
        <tbody>
        <tr>
            <td>个人 API Token<br/>用于 API 自动签到、查询健康状态、日志与签到日历。Token 只可访问当前账号数据，生成后仅展示一次。</td>
            <td>
                <?php
                $tokenRow = $m->once_fetch_array("SELECT `api_token_hash` FROM `" . DB_PREFIX . "users` WHERE `id` = " . (int) UID . " LIMIT 1");
                $hasApiToken = !empty($tokenRow['api_token_hash']);
                ?>
                <span class="label <?php echo $hasApiToken ? 'label-success' : 'label-default'; ?>"><?php echo $hasApiToken ? '已启用' : '未生成'; ?></span>
                <button type="submit" class="cs-btn cs-btn-secondary btn-sm" name="api_action" value="generate" onclick="return confirm('生成新 Token 会立即使旧 Token 失效，确定继续吗？');"><?php echo $hasApiToken ? '重新生成 Token' : '生成 Token'; ?></button>
                <?php if ($hasApiToken) { ?><button type="submit" class="cs-btn cs-btn-danger btn-sm" name="api_action" value="revoke" onclick="return confirm('确定撤销个人 API Token 吗？');">撤销 Token</button><?php } ?>
                <div class="text-muted" style="margin-top:8px">接口：<code>api.php?action=status</code>、<code>calendar</code>、<code>logs</code>；写操作使用 POST。</div>
            </td>
        </tr>
        <?php doAction('set_2'); ?>
    </tbody>
    </table></div>
</div>
</div>
</div>
<div style="text-align:right;margin:0 1%"><input type="submit" class="cs-btn cs-btn-primary" value="提交更改"></div>
</form>

<div class="panel panel-danger" style="margin:0 1%">
    <div class="panel-heading"><span class="panel-title">修改密码</span></div>
    <div class="panel-body">
    <form action="setting.php?mod=set" method="post" onsubmit="return confirm('确定要修改登录密码吗？修改后需要使用新密码重新登录。');">
    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">
    <input type="hidden" name="pw_action" value="change">
    <div class="table-responsive">
    <table class="cs-table">
        <tbody>
            <tr>
                <td style="width:40%">当前密码</td>
                <td><input type="password" name="cur_pw" class="form-control" required autocomplete="current-password"></td>
            </tr>
            <tr>
                <td>新密码<br/><span class="text-muted">长度 6-64 位</span></td>
                <td><input type="password" name="new_pw" class="form-control" required minlength="6" maxlength="64" autocomplete="new-password"></td>
            </tr>
            <tr>
                <td>确认新密码</td>
                <td><input type="password" name="new_pw2" class="form-control" required minlength="6" maxlength="64" autocomplete="new-password"></td>
            </tr>
        </tbody>
    </table></div>
    <input type="submit" class="cs-btn cs-btn-primary" value="修改密码">
    </form>
    </div>
</div>

