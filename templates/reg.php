<?php
if (!defined('SYSTEM_ROOT')) {
    die('Insufficient Permissions');
}
if (option::get('enable_reg') != 1) {
    $text = option::get('stop_reg');
    if (!empty($text)) {
        msg($text);
    } else {
        msg('该站点已关闭注册');
    }
}
loadhead();
?>
<div class="auth-layout">
    <aside class="auth-aside">
        <span class="auth-mark">云</span>
        <div class="eyebrow hero-eyebrow">Create Workspace</div>
        <h1>建立你的个人签到空间。</h1>
        <p>注册完成后，绑定自己的百度账号并同步需要签到的贴吧即可。</p>
        <ul class="auth-points">
            <li>每个账号拥有独立的数据空间</li>
            <li>密码采用安全哈希保存</li>
            <li>随时可以调整个人设置</li>
        </ul>
    </aside>
    <section class="auth-form">
        <div class="eyebrow">New Workspace</div>
        <h2>创建账号</h2>
        <p class="auth-subtitle">填写下面的信息，开始你的云签到工作台。</p>
        <?php doAction('reg_page_1'); ?>
        <?php if (isset($_GET['error_msg'])) { ?>
            <div class="alert alert-danger">错误：<?php echo strip_tags($_GET['error_msg']); ?></div>
        <?php } ?>
        <?php if (isset($_GET['msg'])) { ?>
            <div class="alert alert-info"><?php echo strip_tags($_GET['msg']); ?></div>
        <?php } ?>
        <form name="f" method="post" action="index.php?mod=admin:reg" data-password-match-form>
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">
            <div class="form-group">
                <label for="reg-user">用户名</label>
                <input id="reg-user" type="text" class="form-control" name="user" required>
            </div>
            <div class="form-group">
                <label for="pw">密码</label>
                <input type="password" class="form-control" name="pw" id="pw" required>
            </div>
            <div class="form-group">
                <label for="rpw">再次输入密码</label>
                <input type="password" class="form-control" name="rpw" id="rpw" required>
            </div>
            <div class="form-group">
                <label for="mail">邮箱地址</label>
                <input type="email" class="form-control" name="mail" id="mail" required>
            </div>
            <?php $yr_reg = option::get('yr_reg'); if (!empty($yr_reg)) { ?>
            <div class="form-group">
                <label for="yr">邀请码</label>
                <input type="text" class="form-control" name="yr" id="yr" required>
            </div>
            <?php } ?>
            <?php if (option::get('captcha')) { ?>
            <div class="form-group">
                <label for="captcha_input">验证码</label>
                <div class="captcha-row">
                    <input type="text" class="form-control" name="captcha" id="captcha_input" required>
                    <img src="index.php?mod=captcha" alt="验证码" class="captcha-image" id="captcha" title="点击刷新验证码">
                </div>
                <script>
                    $(function () {
                        $('#captcha').on('click', function () {
                            $(this).attr('src', 'index.php?mod=captcha&refresh=' + Date.now());
                        });
                    });
                </script>
            </div>
            <?php } ?>
            <?php doAction('reg_page_2'); ?>
            <button type="submit" class="cs-btn cs-btn-primary auth-submit">创建我的账号</button>
            <?php doAction('reg_page_3'); ?>
        </form>
        <div class="auth-footer">已经有账号？<a href="index.php?mod=login">返回登录</a></div>
    </section>
</div>
<?php loadfoot(); ?>

