<?php
if (!defined('SYSTEM_ROOT')) {
    die('Insufficient Permissions');
}
global $i, $m;
$today = date('Y-m-d');
$accountCount = !empty($i['user']['bduss']) ? count($i['user']['bduss']) : 0;
$topicCount = (int) $i['user']['tbnum'];
// 阶段 D：为工作台图表卡片准备数据（复用 SignManager）
require_once SYSTEM_ROOT . '/lib/class.SignManager.php';
$summary = SignManager::signSummary(UID);
$calendar30 = SignManager::calendar(30, UID);
$streak = SignManager::streak(UID);
$failures = SignManager::failureBreakdown(UID, 5);
$successRate = ($summary['signed'] + $summary['error']) > 0
    ? round($summary['signed'] / ($summary['signed'] + $summary['error']) * 100, 1)
    : 0;
// 成就摘要（阶段 N 工作台增强卡片）
$achSummary = null;
$achLatest = null;
if (file_exists(SYSTEM_ROOT . '/lib/class.Achievement.php')) {
    require_once SYSTEM_ROOT . '/lib/class.Achievement.php';
    $achSummary = Achievement::summary(UID);
    $achProgress = Achievement::progress(UID);
    foreach ($achProgress as $it) {
        if ($it['unlocked'] && ($achLatest === null || $it['unlocked_at'] > $achLatest['unlocked_at'])) {
            $achLatest = $it;
        }
    }
}
// 今日失败贴吧数（供快速复签卡片使用）
$todayFailedCount = 0;
if (defined('TABLE') && !empty($i['user']['bduss'])) {
    $todayDay = date('d');
    $fcRow = $m->once_fetch_array("SELECT COUNT(*) AS c FROM `" . DB_NAME . "`.`" . DB_PREFIX . TABLE . "` WHERE `uid` = " . (int) UID . " AND `no` = 0 AND `latest` = '" . $m->real_escape_string($todayDay) . "' AND `status` != 0");
    $todayFailedCount = (int) ($fcRow['c'] ?? 0);
}
// 签到趋势数据（阶段 O：30 天趋势线图）
$trend30 = SignManager::trend(30, UID);
$trendDates = array();
$trendSuccess = array();
$trendError = array();
$trendRate = array();
foreach ($trend30 as $tr) {
    $trendDates[] = $tr['date'];
    $trendSuccess[] = $tr['success'];
    $trendError[] = $tr['error'];
    $trendRate[] = $tr['rate'];
}
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
<div class="cs-alert cs-alert-warning" role="alert">
    <svg class="cs-alert-icon" aria-hidden="true"><use href="#i-info"/></svg>
    <div class="cs-alert-body">
        <strong>当前为控制模式：</strong><?php echo htmlspecialchars(CON_NAME, ENT_QUOTES, 'UTF-8'); ?> 正在查看 <?php echo htmlspecialchars(NAME, ENT_QUOTES, 'UTF-8'); ?> 的账号数据。
        <a href="index.php?mod=usercontrolback">返回自己的账号</a>
    </div>
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
        <strong class="metric-value"><button type="button" class="cs-btn cs-btn-ghost cs-btn-sm" data-view-status>查看状态</button></strong>
        <span class="metric-note">读取最近一次任务结果</span>
    </div>
</section>

<section class="dashboard-grid dashboard-charts" aria-label="签到数据可视化">
    <div class="cs-chart-card">
        <div class="cs-chart-card-header">
            <div>
                <h3 class="cs-chart-card-title">今日签到完成度</h3>
                <span class="cs-chart-card-subtitle">已签 <?php echo (int) $summary['signed']; ?> / 待签 <?php echo (int) $summary['waiting']; ?> / 出错 <?php echo (int) $summary['error']; ?></span>
            </div>
            <span class="cs-stat-pill cs-stat-pill-brand"><span class="cs-stat-pill-value"><?php echo $successRate; ?>%</span><span class="cs-stat-pill-label">成功率</span></span>
        </div>
        <div class="cs-chart-container" id="chart-today-gauge"></div>
    </div>

    <div class="cs-chart-card">
        <div class="cs-chart-card-header">
            <div>
                <h3 class="cs-chart-card-title">近 30 天签到趋势</h3>
                <span class="cs-chart-card-subtitle">每日成功次数</span>
            </div>
        </div>
        <div class="cs-chart-container" id="chart-30d-trend" style="min-height:180px"></div>
    </div>

    <div class="cs-chart-card">
        <div class="cs-chart-card-header">
            <div>
                <h3 class="cs-chart-card-title">近 30 天成功率走势</h3>
                <span class="cs-chart-card-subtitle">每日签到成功率（%）</span>
            </div>
        </div>
        <div class="cs-chart-container" id="chart-30d-rate" style="min-height:180px"></div>
    </div>

    <div class="cs-chart-card">
        <div class="cs-chart-card-header">
            <div>
                <h3 class="cs-chart-card-title">连签天数</h3>
                <span class="cs-chart-card-subtitle">基于签到历史记录计算</span>
            </div>
        </div>
        <div style="display:flex;flex-direction:column;gap:10px;padding:8px 0">
            <span class="cs-stat-pill cs-stat-pill-success" style="align-self:flex-start"><span class="cs-stat-pill-value"><?php echo (int) $streak['current_streak']; ?></span><span class="cs-stat-pill-label">当前连签 / 天</span></span>
            <span class="cs-stat-pill cs-stat-pill-brand" style="align-self:flex-start"><span class="cs-stat-pill-value"><?php echo (int) $streak['max_streak']; ?></span><span class="cs-stat-pill-label">最长连签 / 天</span></span>
            <span class="cs-stat-pill" style="align-self:flex-start"><span class="cs-stat-pill-value"><?php echo $streak['last_sign_day'] ? htmlspecialchars(date('Y-m-d', strtotime($streak['last_sign_day'])), ENT_QUOTES, 'UTF-8') : '—'; ?></span><span class="cs-stat-pill-label">上次签到</span></span>
        </div>
    </div>

    <div class="cs-chart-card">
        <div class="cs-chart-card-header">
            <div>
                <h3 class="cs-chart-card-title">失败原因 Top 5</h3>
                <span class="cs-chart-card-subtitle">近 30 天</span>
            </div>
        </div>
        <div class="cs-chart-container" id="chart-failure-pie" style="min-height:180px"></div>
    </div>

    <?php if ($achSummary !== null) { ?>
    <div class="cs-chart-card">
        <div class="cs-chart-card-header">
            <div>
                <h3 class="cs-chart-card-title">成就进度</h3>
                <span class="cs-chart-card-subtitle">已解锁 <?php echo (int) $achSummary['unlocked']; ?> / <?php echo (int) $achSummary['total']; ?></span>
            </div>
            <span class="cs-stat-pill cs-stat-pill-brand"><span class="cs-stat-pill-value"><?php echo (int) $achSummary['progress_pct']; ?>%</span><span class="cs-stat-pill-label">完成度</span></span>
        </div>
        <div style="padding:8px 0;display:flex;flex-direction:column;gap:10px">
            <div style="height:8px;background:var(--color-surface-soft);border-radius:var(--radius-pill);overflow:hidden">
                <div style="height:100%;width:<?php echo (int) $achSummary['progress_pct']; ?>%;background:linear-gradient(90deg, var(--color-brand), var(--color-brand-deep));transition:width .6s var(--ease-productive)"></div>
            </div>
            <?php if ($achLatest) { ?>
            <span class="cs-stat-pill cs-stat-pill-success" style="align-self:flex-start"><span class="cs-stat-pill-value"><?php echo htmlspecialchars($achLatest['name'], ENT_QUOTES, 'UTF-8'); ?></span><span class="cs-stat-pill-label">最近解锁</span></span>
            <?php } else { ?>
            <span class="cs-stat-pill" style="align-self:flex-start"><span class="cs-stat-pill-value">暂无</span><span class="cs-stat-pill-label">继续签到解锁</span></span>
            <?php } ?>
            <a class="cs-btn cs-btn-ghost cs-btn-sm" href="index.php?mod=achievements" style="align-self:flex-start">查看全部成就</a>
        </div>
    </div>
    <?php } ?>

    <div class="cs-chart-card">
        <div class="cs-chart-card-header">
            <div>
                <h3 class="cs-chart-card-title">今日失败贴吧</h3>
                <span class="cs-chart-card-subtitle"><?php echo (int) $todayFailedCount; ?> 个需要复签</span>
            </div>
            <span class="cs-stat-pill <?php echo $todayFailedCount > 0 ? 'cs-stat-pill-warning' : 'cs-stat-pill-success'; ?>"><span class="cs-stat-pill-value"><?php echo (int) $todayFailedCount; ?></span><span class="cs-stat-pill-label">待复签</span></span>
        </div>
        <div style="padding:8px 0;display:flex;flex-direction:column;gap:10px">
            <?php if ($todayFailedCount > 0) { ?>
            <p class="text-muted" style="margin:0">有 <?php echo (int) $todayFailedCount; ?> 个贴吧今日签到失败，可一键触发复签。</p>
            <form method="post" action="setting.php?mod=showtb:retry" onsubmit="return confirm('即将对 <?php echo (int) $todayFailedCount; ?> 个今日失败的贴吧执行复签，继续吗？');">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">
                <input type="hidden" name="retry_all" value="1">
                <button type="submit" class="cs-btn cs-btn-primary cs-btn-sm"><svg class="icon-inline" style="width:14px;height:14px;vertical-align:-2px;margin-right:4px" aria-hidden="true"><use href="#i-refresh"/></svg>一键复签</button>
            </form>
            <?php } else { ?>
            <p class="text-muted" style="margin:0">今日无失败贴吧，或签到任务尚未执行。</p>
            <a class="cs-btn cs-btn-ghost cs-btn-sm" href="index.php?mod=showtb">前往签到设置</a>
            <?php } ?>
        </div>
    </div>
</section>

<div class="dashboard-grid">
    <div class="dashboard-stack">
        <section class="panel panel-primary">
            <div class="panel-heading"><span class="panel-title">账号与签到资源</span></div>
            <div class="panel-body">
                <?php if (!empty($i['opt']['ann'])) { ?>
                <div class="cs-alert cs-alert-info">
                    <svg class="cs-alert-icon" aria-hidden="true"><use href="#i-info"/></svg>
                    <div class="cs-alert-body"><strong>公告：</strong><?php echo function_exists('sanitize_footer_html') ? sanitize_footer_html($i['opt']['ann']) : htmlspecialchars($i['opt']['ann'], ENT_QUOTES, 'UTF-8'); ?></div>
                </div>
                <?php } ?>
                <div class="resource-row">
                    <div>
                        <strong>百度账号</strong>
                        <?php if ($accountCount === 0) { ?>
                            <p class="text-muted">还没有绑定百度账号，暂时无法使用签到功能。</p>
                            <a class="cs-btn cs-btn-primary cs-btn-sm" href="index.php?mod=baiduid"><svg class="cs-icon" style="width:14px;height:14px" aria-hidden="true"><use href="#i-link"/></svg> 前往绑定</a>
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
                            <a class="cs-btn cs-btn-secondary cs-btn-sm" href="index.php?mod=showtb"><svg class="cs-icon" style="width:14px;height:14px" aria-hidden="true"><use href="#i-refresh"/></svg> 刷新贴吧列表</a>
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
            <ul class="cs-list">
                <li class="cs-list-item"><strong>账号权限</strong><span class="text-muted"><?php echo ISVIP ? '当前账号拥有增强绑定和贴吧数量权限。' : '当前为标准用户权限。'; ?></span></li>
                <li class="cs-list-item"><strong>下一步</strong><span class="text-muted">先绑定账号，再同步贴吧列表，最后查看签到状态。</span></li>
            </ul>
        </section>
        <?php doAction('index_p_1'); ?>
    </aside>
</div>

<?php if (ROLE == 'admin') { ?>
<div class="dashboard-grid dashboard-grid--spaced">
    <section class="panel panel-warning">
        <div class="panel-heading"><span class="panel-title">系统健康</span></div>
        <ul class="cs-list">
            <li class="cs-list-item">
                <strong>计划任务</strong>
                <?php
                if (empty($i['opt']['cron_last_do_time'])) {
                    echo '<span class="text-danger">从未运行</span>';
                } else {
                    echo '<span class="text-muted">上次运行：' . htmlspecialchars($i['opt']['cron_last_do_time'], ENT_QUOTES, 'UTF-8') . '</span>';
                    if (time() - strtotime($i['opt']['cron_last_do_time']) > 86400) {
                        echo '<span class="text-warning" style="margin-top:4px;display:block">今天尚未运行，请检查主机计划任务。</span>';
                    }
                }
                ?>
            </li>
            <?php if (defined('ANTI_CSRF') && !ANTI_CSRF) { ?><li class="cs-list-item"><span class="text-danger">安全提醒：</span>站点的 CSRF 防御已关闭。</li><?php } ?>
            <?php if (version_compare(PHP_VERSION, '5.4.0') < 0) { ?><li class="cs-list-item"><span class="text-danger">运行环境提醒：</span>当前 PHP 版本较低。</li><?php } ?>
            <?php doAction('index_p_3'); ?>
        </ul>
    </section>
    <section class="panel panel-danger">
        <div class="panel-heading"><span class="panel-title">服务器信息</span></div>
        <ul class="cs-list" id="server">
            <li class="cs-list-item">
                <div class="cs-skeleton cs-skeleton-text" style="width:60%"></div>
                <div class="cs-skeleton cs-skeleton-text" style="width:40%"></div>
                <div class="cs-skeleton cs-skeleton-text" style="width:50%"></div>
            </li>
        </ul>
    </section>
</div>
<?php } ?>

<?php doAction('index_3'); doAction('index_2'); ?>

<?php loadChartAssets(); ?>
<script type="text/javascript">
    <?php if (ISADMIN) { ?>
    $.ajax({
        async: true,
        url: 'ajax.php?mod=admin:server',
        type: 'GET',
        data: {},
        dataType: 'HTML',
        timeout: 90000,
        success: function (data) {
            $('#server').html(data);
            // 兼容旧 list-group-item 样式 → 自动补 cs-list-item 类
            $('#server').find('.list-group-item').addClass('cs-list-item');
        },
        error: function () {
            $('#server').html('<li class="cs-list-item"><span class="text-danger">服务器信息读取失败。</span></li>');
        }
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

    // 阶段 D：工作台 ECharts 图表初始化
    (function () {
        if (typeof echarts === 'undefined' || !window.CS || !CS.chart) return;
        var summary = <?php echo json_encode($summary, JSON_UNESCAPED_UNICODE); ?>;
        var rate = <?php echo $successRate; ?>;
        var cal = <?php echo json_encode($calendar30, JSON_UNESCAPED_UNICODE); ?>;
        var failures = <?php echo json_encode($failures, JSON_UNESCAPED_UNICODE); ?>;
        var trendDates = <?php echo json_encode($trendDates, JSON_UNESCAPED_UNICODE); ?>;
        var trendRate = <?php echo json_encode($trendRate, JSON_UNESCAPED_UNICODE); ?>;

        // 1. 今日签到完成度 gauge
        var gaugeEl = document.getElementById('chart-today-gauge');
        if (gaugeEl) {
            CS.chart.create(gaugeEl, {
                series: [{
                    type: 'gauge', radius: '90%', startAngle: 200, endAngle: -20,
                    progress: { show: true, width: 14, roundCap: true },
                    axisLine: { lineStyle: { width: 14, color: [[1, '#5b3aff']] } },
                    pointer: { show: false },
                    axisTick: { show: false }, axisLabel: { show: false }, splitLine: { show: false },
                    detail: { formatter: '{value}%', fontSize: 28, fontWeight: 'bold', offsetCenter: [0, '5%'], color: '#5b3aff' },
                    title: { show: true, offsetCenter: [0, '40%'], fontSize: 12, color: '#64748b' },
                    data: [{ value: rate, name: '成功率' }]
                }]
            });
        }

        // 2. 近 30 天趋势 sparkline
        var trendEl = document.getElementById('chart-30d-trend');
        if (trendEl && cal.length) {
            var days = cal.map(function (r) { return r.day; });
            var values = cal.map(function (r) { return r.success; });
            CS.chart.create(trendEl, {
                tooltip: { trigger: 'axis', formatter: function (p) { return p[0].name + '<br/>签到成功 ' + p[0].value + ' 次'; } },
                grid: { left: '3%', right: '4%', top: 8, bottom: 20, containLabel: true },
                xAxis: { type: 'category', data: days, axisLabel: { fontSize: 10, color: '#64748b' } },
                yAxis: { type: 'value', minInterval: 1, axisLabel: { fontSize: 10, color: '#64748b' } },
                series: [{
                    type: 'line', smooth: true, showSymbol: false, data: values,
                    lineStyle: { width: 2, color: '#10b981' },
                    areaStyle: { opacity: .15, color: '#10b981' }
                }]
            });
        }

        // 2b. 近 30 天成功率走势（阶段 O）
        var rateEl = document.getElementById('chart-30d-rate');
        if (rateEl && trendDates.length) {
            CS.chart.create(rateEl, {
                tooltip: { trigger: 'axis', formatter: function (p) { return p[0].name + '<br/>成功率 ' + p[0].value + '%'; } },
                grid: { left: '3%', right: '4%', top: 8, bottom: 20, containLabel: true },
                xAxis: { type: 'category', data: trendDates, axisLabel: { fontSize: 10, color: '#64748b' } },
                yAxis: { type: 'value', min: 0, max: 100, axisLabel: { fontSize: 10, color: '#64748b', formatter: '{value}%' } },
                series: [{
                    type: 'line', smooth: true, showSymbol: false, data: trendRate,
                    lineStyle: { width: 2, color: '#5b3aff' },
                    areaStyle: { opacity: .12, color: '#5b3aff' },
                    markLine: { silent: true, symbol: 'none', data: [{ yAxis: 100, lineStyle: { color: '#22c55e', type: 'dashed' } }, { yAxis: 50, lineStyle: { color: '#f59e0b', type: 'dashed' } }] }
                }]
            });
        }

        // 3. 失败原因 Top 5 饼图
        var pieEl = document.getElementById('chart-failure-pie');
        if (pieEl) {
            var pieData = failures.map(function (r) {
                return { name: r.reason.length > 30 ? r.reason.substring(0, 30) + '…' : r.reason, value: r.count };
            });
            if (!pieData.length) {
                pieEl.innerHTML = '<div style="display:flex;align-items:center;justify-content:center;height:180px;color:#94a3b8;font-size:13px">近 30 天无失败记录</div>';
            } else {
                CS.chart.create(pieEl, {
                    tooltip: { trigger: 'item', formatter: '{b}<br/>{c} 次 ({d}%)' },
                    legend: { bottom: 0, type: 'scroll', textStyle: { fontSize: 11 } },
                    series: [{
                        type: 'pie', radius: ['35%', '60%'], center: ['50%', '42%'],
                        avoidLabelOverlap: true,
                        itemStyle: { borderRadius: 6, borderColor: '#fff', borderWidth: 2 },
                        label: { show: false },
                        data: pieData
                    }]
                });
            }
        }

        // 主题切换由 components.js 全局监听并调用 CS.chart.refreshTheme()
    })();
</script>
