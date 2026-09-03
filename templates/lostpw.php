<?php
if (!defined('SYSTEM_ROOT')) {
    die('Insufficient Permissions');
}
loadhead();
$token = isset($_GET['token']) ? trim((string) $_GET['token']) : '';
?>
<div class="auth-layout">
    <aside class="auth-aside">
        <span class="auth-mark">云</span>
        <div class="eyebrow hero-eyebrow">Account Recovery</div>
        <h1>忘记密码？几分钟就能找回。</h1>
        <p>输入注册时的邮箱地址，我们会发送一封包含重置链接的邮件。链接有效期为 30 分钟。</p>
        <ul class="auth-points">
            <li>重置链接一次性使用，更安全</li>
            <li>未注册的邮箱不会收到邮件</li>
            <li>若收不到邮件，请检查垃圾邮件箱</li>
        </ul>
    </aside>
    <section class="auth-form">
        <?php if ($token !== '') { ?>
        <div class="eyebrow">Set New Password</div>
        <h2>设置新密码</h2>
        <p class="auth-subtitle">为您的账号设置一个新的登录密码。</p>
        <form method="post" action="index.php?mod=lostpw:do">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">
            <input type="hidden" name="action" value="reset">
            <input type="hidden" name="token" value="<?php echo htmlspecialchars($token, ENT_QUOTES, 'UTF-8'); ?>">
            <div class="form-group">
                <label for="lostpw-new">新密码</label>
                <input id="lostpw-new" type="password" class="form-control" name="pw" required>
            </div>
            <div class="form-group">
                <label for="lostpw-repeat">确认新密码</label>
                <input id="lostpw-repeat" type="password" class="form-control" name="rpw" required>
            </div>
            <button type="submit" class="cs-btn cs-btn-primary auth-submit">重置密码</button>
        </form>
        <?php } else { ?>
        <div class="eyebrow">Forgot Password</div>
        <h2>找回密码</h2>
        <p class="auth-subtitle">输入注册邮箱，我们将发送密码重置链接。</p>
        <form method="post" action="index.php?mod=lostpw:do">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">
            <input type="hidden" name="action" value="request">
            <div class="form-group">
                <label for="lostpw-mail">注册邮箱</label>
                <input id="lostpw-mail" type="email" class="form-control" name="mail" placeholder="注册时填写的邮箱地址" required>
            </div>
            <button type="submit" class="cs-btn cs-btn-primary auth-submit">发送重置邮件</button>
        </form>
        <?php } ?>
        <div class="auth-footer">想起密码了？<a href="index.php?mod=login">返回登录</a></div>
    </section>
</div>
<?php loadfoot(); ?>
