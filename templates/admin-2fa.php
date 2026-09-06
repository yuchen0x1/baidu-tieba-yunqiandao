<?php if (!defined('SYSTEM_ROOT')) {
    die('Insufficient Permissions');
}
global $i;
loadhead('双因素认证'); ?>
<div class="panel panel-primary">
    <div class="panel-heading"><b>双因素认证</b></div>
    <div class="panel-body">
        <p>账号已通过密码验证。请再输入认证器应用（Google / Microsoft Authenticator 等）中的 6 位动态验证码完成登录。</p>
        <form method="post" action="index.php?mod=admin:2fa" class="form-inline">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">
            <div class="form-group">
                <input type="text" name="code" class="form-control" maxlength="6" pattern="[0-9]{6}" placeholder="6 位验证码" autocomplete="one-time-code" style="width:180px;" required>
            </div>
            <button type="submit" class="cs-btn cs-btn-primary">验证并登录</button>
        </form>
        <p style="margin-top:12px;color:var(--color-muted)">提示：若无法获取验证码，请联系站点管理员重置双因素安全设置。</p>
    </div>
</div>
<?php loadfoot(); ?>
