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
?><form action="setting.php?mod=set" method="post">
<input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">
<?php doAction('set_1'); ?>
<div class="table-responsive">
<table class="table table-hover">
    <thead>
        <tr>
            <th style="width:40%">参数</th>
            <th>值</th>
        </tr>
    </thead>
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
        <tr>
            <td>个人 API Token<br/>用于 API 自动签到、查询健康状态、日志与签到日历。Token 只可访问当前账号数据，生成后仅展示一次。</td>
            <td>
                <?php
                $tokenRow = $m->once_fetch_array("SELECT `api_token_hash` FROM `" . DB_PREFIX . "users` WHERE `id` = " . (int) UID . " LIMIT 1");
                $hasApiToken = !empty($tokenRow['api_token_hash']);
                ?>
                <span class="label <?php echo $hasApiToken ? 'label-success' : 'label-default'; ?>"><?php echo $hasApiToken ? '已启用' : '未生成'; ?></span>
                <button type="submit" class="btn btn-default btn-sm" name="api_action" value="generate" onclick="return confirm('生成新 Token 会立即使旧 Token 失效，确定继续吗？');"><?php echo $hasApiToken ? '重新生成 Token' : '生成 Token'; ?></button>
                <?php if ($hasApiToken) { ?><button type="submit" class="btn btn-danger btn-sm" name="api_action" value="revoke" onclick="return confirm('确定撤销个人 API Token 吗？');">撤销 Token</button><?php } ?>
                <div class="text-muted" style="margin-top:8px">接口：<code>api.php?action=status</code>、<code>calendar</code>、<code>logs</code>；写操作使用 POST。</div>
            </td>
        </tr>
        <?php doAction('set_2'); ?>
    </tbody>
</table></div><input type="submit" class="btn btn-primary" value="提交更改">
</form>

<h3>修改密码</h3>
<form action="setting.php?mod=set" method="post" onsubmit="return confirm('确定要修改登录密码吗？修改后需要使用新密码重新登录。');">
<input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">
<input type="hidden" name="pw_action" value="change">
<div class="table-responsive">
<table class="table table-hover">
    <thead>
        <tr>
            <th style="width:40%">参数</th>
            <th>值</th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td>当前密码</td>
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
<input type="submit" class="btn btn-primary" value="修改密码">
</form>
<br/><?php echo SYSTEM_FN ?> V<?php echo SYSTEM_VER  . ' ' . SYSTEM_VER_NOTE ?> // 作者: <a href="https://kenvix.com" target="_blank">Kenvix</a>  &amp; <a href="http://www.mokeyjay.com/" target="_blank">mokeyjay</a> &amp;  <a href="http://fyy1999.lofter.com/" target="_blank">FYY</a>

