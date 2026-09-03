<?php
if (!defined('SYSTEM_ROOT')) {
    die('Insufficient Permissions');
}
loadhead();
?>
<div class="auth-layout">
    <aside class="auth-aside">
        <span class="auth-mark">云</span>
        <div class="eyebrow hero-eyebrow">Getting Started</div>
        <h1>第一次使用？先创建管理员账号。</h1>
        <p>系统检测到当前还没有任何管理员账号。设置管理员账号密码后，即可进入工作台开始使用。</p>
        <ul class="auth-points">
            <li>管理员拥有站点全部管理权限</li>
            <li>初始化完成后其他用户只能注册普通账号</li>
            <li>请妥善保管管理员账号与密码</li>
        </ul>
    </aside>
    <section class="auth-form">
        <div class="eyebrow">Initialization</div>
        <h2>初始化管理员账号</h2>
        <p class="auth-subtitle">首次使用本系统，请先设置管理员账号与密码。</p>
        <?php doAction('init_page_2'); ?>
        <?php if (isset($_GET['error_msg'])) { ?>
            <div class="alert alert-danger">错误：<?php echo htmlspecialchars((string) $_GET['error_msg'], ENT_QUOTES, 'UTF-8'); ?></div>
        <?php } ?>
        <form name="f" method="post" action="index.php?mod=init:do">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">
            <div class="form-group">
                <label for="init-user">管理员用户名</label>
                <input id="init-user" type="text" class="form-control" name="user" placeholder="字母、数字、下划线或中文，不超过 20 位" required>
            </div>
            <div class="form-group">
                <label for="init-mail">管理员邮箱</label>
                <input id="init-mail" type="email" class="form-control" name="mail" placeholder="用于找回密码与系统通知" required>
            </div>
            <div class="form-group">
                <label for="init-password">管理员密码</label>
                <input id="init-password" type="password" class="form-control" name="pw" minlength="8" placeholder="至少 8 位" required>
            </div>
            <div class="form-group">
                <label for="init-rpassword">确认密码</label>
                <input id="init-rpassword" type="password" class="form-control" name="rpw" required>
            </div>
            <?php doAction('init_page_3'); ?>
            <button type="submit" class="cs-btn cs-btn-primary auth-submit">完成初始化并进入</button>
        </form>
        <div class="auth-footer">完成初始化后将自动登录并进入工作台。</div>
    </section>
</div>
<?php loadfoot(); ?>
