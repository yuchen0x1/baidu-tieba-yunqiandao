<?php
if (!defined('SYSTEM_ROOT')) {
    die('Insufficient Permissions');
}
global $i, $m;
$today = date('Y-m-d');
$accountCount = !empty($i['user']['bduss']) ? count($i['user']['bduss']) : 0;
$topicCount = (int) $i['user']['tbnum'];
doAction('index_1');
?>

<section class="dashboard-hero">
    <div class="eyebrow hero-eyebrow">Cloud Sign Studio / <?php echo $today; ?></div>
    <h1>把签到状态，集中在一个工作台。</h1>
    <p><?php echo htmlspecialchars(SYSTEM_NAME, ENT_QUOTES, 'UTF-8'); ?> 正在运行。这里可以快速查看账号、贴吧数量、签到状态和系统健康情况。</p>
    <div class="dashboard-hero-meta">
        <span class="hero-tag">版本 V<?php echo SYSTEM_VER . (SYSTEM_REV != '' ? '.' . SYSTEM_REV : ''); ?></span>
        <span class="hero-tag">用户组：<?php echo htmlspecialchars(getrole(ROLE), ENT_QUOTES, 'UTF-8'); ?></span>
        <?php if (ISVIP) { ?><span class="hero-tag">增强权限已启用</span><?php } ?>
    </div>
</section>

<?php if (defined('CON_NAME')) { ?>
<div class="alert alert-warning" role="alert">
    <strong>当前为控制模式：</strong><?php echo htmlspecialchars(CON_NAME, ENT_QUOTES, 'UTF-8'); ?> 正在查看 <?php echo htmlspecialchars(NAME, ENT_QUOTES, 'UTF-8'); ?> 的账号数据。
    <a href="index.php?mod=usercontrolback">返回自己的账号</a>
</div>
<?php } ?>

<section class="metric-grid" aria-label="账号概览">
    <div class="metric-card">
        <span class="metric-label">已绑定百度账号</span>
        <strong class="metric-value" id="baiduid_used"><?php echo $accountCount; ?></strong>
        <span class="metric-note">可在“百度账号”页面维护</span>
    </div>
    <div class="metric-card">
        <span class="metric-label">已同步贴吧</span>
        <strong class="metric-value" id="tb_used"><?php echo $topicCount; ?></strong>
        <span class="metric-note">同步后将进入自动签到队列</span>
    </div>
    <div class="metric-card">
        <span class="metric-label">签到状态</span>
        <strong class="metric-value" id="stat"><button type="button" class="metric-status-link" data-view-status>查看</button></strong>
        <span class="metric-note">读取最近一次任务结果</span>
    </div>
</section>

<div class="dashboard-grid">
    <div class="dashboard-stack">
        <section class="panel panel-primary">
            <div class="panel-heading"><span class="panel-title">账号与签到资源</span></div>
            <div class="panel-body">
                <?php if (!empty($i['opt']['ann'])) { ?>
                <div class="alert alert-info"><strong>公告：</strong><?php echo function_exists('sanitize_footer_html') ? sanitize_footer_html($i['opt']['ann']) : htmlspecialchars($i['opt']['ann'], ENT_QUOTES, 'UTF-8'); ?></div>
                <?php } ?>
                <div class="resource-row">
                    <div>
                        <strong>百度账号</strong>
                        <?php if ($accountCount === 0) { ?>
                            <p class="text-muted">还没有绑定百度账号，暂时无法使用签到功能。</p>
                            <a class="btn btn-primary" href="index.php?mod=baiduid">前往绑定</a>
                        <?php } else { ?>
                            <p class="text-muted">当前使用 <?php echo $accountCount; ?> 个账号，配额为
                                <span id="baiduid_limit"><?php
                                if ($i['opt']['bduss_num'] == '-1') {
                                    echo '禁止绑定';
                                } elseif ($i['opt']['bduss_num'] == '0' || ISVIP) {
                                    echo '无限';
                                } else {
                                    echo htmlspecialchars($i['opt']['bduss_num'], ENT_QUOTES, 'UTF-8');
                                }
                                ?></span>。
                            </p>
                            <div class="progress" aria-label="百度账号使用进度"><div class="progress-bar" id="baiduid_prog" role="progressbar"></div></div>
                        <?php } ?>
                    </div>
                </div>
                <hr>
                <div class="resource-row">
                    <div>
                        <strong>贴吧列表</strong>
                        <?php if ($topicCount === 0) { ?>
                            <p class="text-muted">还没有同步贴吧列表，自动签到不会有任务可执行。</p>
                            <a class="btn btn-default" href="index.php?mod=showtb">刷新贴吧列表</a>
                        <?php } else { ?>
                            <p class="text-muted">当前同步 <?php echo $topicCount; ?> 个贴吧，配额为
                                <span id="tb_limit"><?php
                                if ($i['opt']['tb_max'] == '-1') {
                                    echo '禁止刷新';
                                } elseif ($i['opt']['tb_max'] == '0' || ISVIP) {
                                    echo '无限';
                                } else {
                                    echo htmlspecialchars($i['opt']['tb_max'], ENT_QUOTES, 'UTF-8');
                                }
                                ?></span>。
                            </p>
                            <div class="progress" aria-label="贴吧使用进度"><div class="progress-bar" id="tb_prog" role="progressbar"></div></div>
                        <?php } ?>
                    </div>
                </div>
                <?php doAction('index_p_2'); ?>
            </div>
        </section>
    </div>

    <aside class="dashboard-stack">
        <section class="panel panel-info">
            <div class="panel-heading"><span class="panel-title">运行提示</span></div>
            <ul class="list-group">
                <li class="list-group-item"><strong>账号权限</strong><br><span class="text-muted"><?php echo ISVIP ? '当前账号拥有增强绑定和贴吧数量权限。' : '当前为标准用户权限。'; ?></span></li>
                <li class="list-group-item"><strong>下一步</strong><br><span class="text-muted">先绑定账号，再同步贴吧列表，最后查看签到状态。</span></li>
            </ul>
        </section>
        <?php doAction('index_p_1'); ?>
    </aside>
</div>

<?php if (ROLE == 'admin') { ?>
<div class="dashboard-grid dashboard-grid--spaced">
    <section class="panel panel-warning">
        <div class="panel-heading"><span class="panel-title">系统健康</span></div>
        <ul class="list-group">
            <li class="list-group-item">
                <strong>计划任务</strong><br>
                <?php
                if (empty($i['opt']['cron_last_do_time'])) {
                    echo '<span class="text-danger">从未运行</span>';
                } else {
                    echo '<span class="text-muted">上次运行：' . htmlspecialchars($i['opt']['cron_last_do_time'], ENT_QUOTES, 'UTF-8') . '</span>';
                    if (time() - strtotime($i['opt']['cron_last_do_time']) > 86400) {
                        echo '<br><span class="text-warning">今天尚未运行，请检查主机计划任务。</span>';
                    }
                }
                ?>
            </li>
            <?php if (defined('ANTI_CSRF') && !ANTI_CSRF) { ?><li class="list-group-item"><span class="text-danger">安全提醒：</span>站点的 CSRF 防御已关闭。</li><?php } ?>
            <?php if (version_compare(PHP_VERSION, '5.4.0') < 0) { ?><li class="list-group-item"><span class="text-danger">运行环境提醒：</span>当前 PHP 版本较低。</li><?php } ?>
            <?php doAction('index_p_3'); ?>
        </ul>
    </section>
    <section class="panel panel-danger">
        <div class="panel-heading"><span class="panel-title">服务器信息</span></div>
        <ul class="list-group" id="server"><li class="list-group-item text-muted">读取中...</li></ul>
    </section>
</div>
<?php } ?>

<?php doAction('index_3'); doAction('index_2'); ?>

<script type="text/javascript">
    <?php if (ISADMIN) { ?>
    $.ajax({
        async: true,
        url: 'ajax.php?mod=admin:server',
        type: 'GET',
        data: {},
        dataType: 'HTML',
        timeout: 90000,
        success: function (data) { $('#server').html(data); },
        error: function () { $('#server').html('<li class="list-group-item text-danger">服务器信息读取失败。</li>'); }
    });
    <?php } ?>
    <?php if ($i['opt']['bduss_num'] != '0' && $i['opt']['bduss_num'] != '-1' && ISVIP == false && $accountCount > 0) { ?>
    var baiduid = Math.min(100, Math.round($('#baiduid_used').html() / $('#baiduid_limit').html() * 100));
    $('#baiduid_prog').css('width', baiduid + '%').attr('aria-valuenow', baiduid);
    <?php } ?>
    <?php if ($i['opt']['tb_max'] != '0' && $i['opt']['tb_max'] != '-1' && ISVIP == false && $topicCount > 0) { ?>
    var tb = Math.min(100, Math.round($('#tb_used').html() / $('#tb_limit').html() * 100));
    $('#tb_prog').css('width', tb + '%').attr('aria-valuenow', tb);
    <?php } ?>
</script>

