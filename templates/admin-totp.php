<?php if (!defined('SYSTEM_ROOT')) {
    die('Insufficient Permissions');
}
global $i;
?>
<?php if (!empty($i['totp_msg'])) {
    echo '<div class="alert alert-info">' . htmlspecialchars((string) $i['totp_msg'], ENT_QUOTES, 'UTF-8') . '</div>';
} ?>
<div class="panel panel-<?php echo !empty($i['totp_enabled']) ? 'success' : 'warning'; ?>">
    <div class="panel-heading"><b>管理员双因素认证（TOTP）</b></div>
    <div class="panel-body">
        <?php if (!empty($i['totp_enabled'])): ?>
            <p>当前状态：<font color="green"><b>已开启</b></font></p>
            <p><b>账户标识：</b><?php echo htmlspecialchars((string) $i['totp_account'], ENT_QUOTES, 'UTF-8'); ?></p>
            <p><b>密钥（Secret）：</b><code><?php echo htmlspecialchars((string) $i['totp_secret'], ENT_QUOTES, 'UTF-8'); ?></code></p>
            <p><b>otpauth 链接（用于二维码 / 手动添加）：</b><br/><code style="word-break:break-all;"><?php echo htmlspecialchars((string) $i['totp_uri'], ENT_QUOTES, 'UTF-8'); ?></code></p>
            <p style="color:#999;">在认证器应用中添加上述密钥后，输入应用生成的 6 位验证码完成一次校验：</p>
            <form method="post" action="setting.php?mod=admin:totp&setting=verify" class="form-inline">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">
                <div class="form-group">
                    <input type="text" name="code" class="form-control" maxlength="6" pattern="[0-9]{6}" placeholder="6 位验证码" style="width:180px;" required>
                </div>
                <button type="submit" class="cs-btn cs-btn-primary">校验并启用</button>
            </form>
            <hr/>
            <form method="post" action="setting.php?mod=admin:totp&setting=disable" style="display:inline;">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">
                <button type="submit" class="cs-btn cs-btn-danger" onclick="return confirm('确定关闭双因素认证吗？');">关闭双因素认证</button>
            </form>
        <?php else: ?>
            <p>当前状态：<font color="gray"><b>未开启</b></font></p>
            <p>开启后，管理员登录需在密码之外再输入认证器应用的 6 位动态验证码，可显著提升后台安全性。</p>
            <form method="post" action="setting.php?mod=admin:totp&setting=gen">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">
                <button type="submit" class="cs-btn cs-btn-primary">生成密钥并启用</button>
            </form>
        <?php endif; ?>
    </div>
</div>
