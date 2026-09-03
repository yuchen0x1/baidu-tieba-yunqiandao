<?php
if (!defined('SYSTEM_ROOT')) {
    die('Insufficient Permissions');
}
loadhead();
?>
<div class="auth-layout">
    <aside class="auth-aside">
        <span class="auth-mark">云</span>
        <div class="eyebrow hero-eyebrow">Personal Console</div>
        <h1>把签到任务，交给一个可靠的工作台。</h1>
        <p>登录后即可管理百度账号、同步贴吧列表，并查看每次签到的运行状态。</p>
        <ul class="auth-points">
            <li>账号与贴吧数据集中管理</li>
            <li>任务运行状态随时可查</li>
            <li>为管理员提供独立运维空间</li>
        </ul>
    </aside>
    <section class="auth-form">
        <div class="eyebrow">Welcome Back</div>
        <h2>登录 <?php echo htmlspecialchars(SYSTEM_NAME, ENT_QUOTES, 'UTF-8'); ?></h2>
        <p class="auth-subtitle">输入账号信息，继续使用你的签到工作台。</p>
        <?php doAction('login_page_1'); ?>
        <?php if (isset($_GET['error_msg'])) { ?>
            <div class="alert alert-danger">错误：<?php echo strip_tags($_GET['error_msg']); ?></div>
        <?php } ?>
        <?php if (isset($_GET['msg'])) { ?>
            <div class="alert alert-info"><?php echo strip_tags($_GET['msg']); ?></div>
        <?php } ?>
        <form name="f" method="post" action="index.php?mod=admin:login">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">
            <div class="form-group">
                <label for="login-user"><?php echo I18N::t('login.username'); ?></label>
                <input id="login-user" type="text" class="form-control" name="user" placeholder="输入用户名或邮箱地址" required>
            </div>
            <div class="form-group">
                <label for="login-password"><?php echo I18N::t('login.password'); ?></label>
                <input id="login-password" type="password" class="form-control" name="pw" required>
            </div>
            <?php if (option::get('captcha')) { ?>
            <div class="form-group">
                <label for="captcha_input">验证码</label>
                <div class="captcha-row">
                    <input type="text" class="form-control" name="captcha" id="captcha_input" placeholder="点击右侧图片刷新" required>
                    <img src="index.php?mod=captcha" alt="验证码" class="captcha-image" id="captcha" title="点击刷新验证码">
                </div>
                <script>
                    $(function () {
                        $('#captcha').on('load', function () {
                            $('#captcha_input').prop('disabled', false).val('');
                        }).on('click', function () {
                            $(this).attr('src', 'index.php?mod=captcha&refresh=' + Date.now());
                        });
                    });
                </script>
            </div>
            <?php } ?>
            <label class="checkbox-inline"><input type="checkbox" name="ispersis" value="1"> <?php echo I18N::t('login.remember'); ?></label>
            <?php doAction('login_page_2'); ?>
            <button type="submit" class="cs-btn cs-btn-primary auth-submit">进入工作台</button>
            <?php doAction('login_page_3'); ?>
        </form>
        <?php
        require_once SYSTEM_ROOT . '/lib/class.OAuth.php';
        if (OAuth::githubConfigured()) { ?>
        <div class="auth-oauth" style="margin-top:14px;padding-top:14px;border-top:1px solid var(--color-border, #eee)">
            <a class="cs-btn cs-btn-secondary cs-btn-block" href="index.php?mod=oauth:start&provider=github" rel="noopener">
                <svg class="cs-icon" style="width:16px;height:16px;vertical-align:-3px;margin-right:6px" aria-hidden="true"><use href="#i-link"/></svg>使用 GitHub 登录
            </a>
        </div>
        <?php } ?>
        <div class="auth-footer"><?php echo I18N::t('login.no_account'); ?><a href="index.php?mod=reg">创建一个账号</a>　<a href="index.php?mod=lostpw">忘记密码？</a></div>
    </section>
</div>
<?php loadfoot(); ?>

