<?php
if (!defined('SYSTEM_ROOT')) {
    die('Insufficient Permissions');
}
if (ROLE === 'visitor' || ROLE === 'banned') {
    msg('请先登录');
}
global $m;
require_once SYSTEM_ROOT . '/lib/class.SignManager.php';

$viewUid = (ISADMIN && defined('CON_UID') && CON_UID > 0) ? (int) CON_UID : (int) UID;

// 统计区间（天），仅允许 7/30/90/365
$days = isset($_GET['days']) ? (int) $_GET['days'] : 30;
if (!in_array($days, array(7, 30, 90, 365), true)) {
    $days = 30;
}

$overview = SignManager::overview($viewUid, $days);
$trendRows = SignManager::trend($days, $viewUid);
$forumRows = SignManager::tiebaHealth($viewUid, $days);
$failureRows = SignManager::failureBreakdown($viewUid, 10, $days);
$hourRows = SignManager::hourDistribution($viewUid, $days);
$accountRows = SignManager::accountCompare($viewUid, $days);
$healthMap = SignManager::accountHealth($viewUid);
$streak = SignManager::streak($viewUid);
$monitorStats = SignManager::monitorHitStats($viewUid, $days);
$healthTrendRows = SignManager::healthTrend($viewUid, $days);
$hasHealthTrend = false;
foreach ($healthTrendRows as $htr) {
    if ($htr['total'] > 0) { $hasHealthTrend = true; break; }
}

// 状态徽章
$signStatusMap = array(
    'success' => array('cs-badge-success', '成功'),
    'error'   => array('cs-badge-danger', '失败'),
    'skipped' => array('cs-badge-neutral', '跳过'),
);
$healthStatusMap = array(
    'ok'      => array('cs-badge-success', '正常'),
    'invalid' => array('cs-badge-danger', '已失效'),
    'unknown' => array('cs-badge-neutral', '未知'),
);
$fmtTime = function ($ts) {
    return $ts > 0 ? date('Y-m-d H:i', (int) $ts) : '—';
};
?>
<section class="dashboard-hero">
    <div class="eyebrow hero-eyebrow">Deep Insights / Last <?php echo $days; ?> Days</div>
    <h1>签到数据分析</h1>
    <p>基于本地签到历史（V5.12 起积累）与账号健康数据的深度分析，全部来自本站记录，不请求百度接口。</p>
    <div class="dashboard-hero-meta">
        <span class="hero-tag">近 <?php echo $days; ?> 天成功率 <?php echo $overview['rate']; ?>%</span>
        <span class="hero-tag">累计签到 <?php echo (int) $overview['total']; ?> 次</span>
        <span class="hero-tag">当前连签 <?php echo (int) $streak['current_streak']; ?> 天</span>
        <span class="hero-tag">最长连签 <?php echo (int) $streak['max_streak']; ?> 天</span>
    </div>
    <div class="hero-actions" style="flex-wrap:wrap;gap:8px;align-items:center">
        <span style="font-size:12px;color:var(--color-muted)">统计区间：</span>
        <?php foreach (array(7, 30, 90, 365) as $d) { ?>
        <a class="cs-btn cs-btn-ghost cs-btn-sm <?php echo $d === $days ? 'is-active' : ''; ?>" href="index.php?mod=analysis&days=<?php echo $d; ?>"><?php echo $d === 365 ? '近一年' : ('近 ' . $d . ' 天'); ?></a>
        <?php } ?>
        <a class="cs-btn cs-btn-secondary cs-btn-sm" href="index.php?mod=analysis&days=<?php echo $days; ?>&export=csv&type=forum" style="margin-left:auto">导出吧明细 CSV</a>
        <a class="cs-btn cs-btn-secondary cs-btn-sm" href="index.php?mod=analysis&days=<?php echo $days; ?>&export=csv">导出报表 CSV</a>
    </div>
</section>

<?php if ($overview['total'] === 0) { ?>
<div class="cs-alert cs-alert-info" style="margin:16px 1%">
    <svg class="cs-alert-icon" aria-hidden="true"><use href="#i-info"/></svg>
    <div class="cs-alert-body">近 <?php echo $days; ?> 天暂无签到记录。签到历史从 V5.12 开始积累，保持云签到运行后这里会自动生成趋势、贴吧对比与失败原因分析。</div>
</div>
<?php } else { ?>

<section class="metric-grid metric-grid--four" aria-label="分析概览" style="margin:16px 1%">
    <div class="metric-card">
        <span class="metric-label">总签到</span>
        <strong class="metric-value"><?php echo (int) $overview['total']; ?></strong>
        <span class="metric-note">近 <?php echo $days; ?> 天累计，日均 <?php echo $overview['avg_per_day']; ?> 次</span>
    </div>
    <div class="metric-card">
        <span class="metric-label">成功 / 失败</span>
        <strong class="metric-value"><?php echo (int) $overview['success']; ?> <span style="font-size:14px;color:var(--color-muted)">/</span> <?php echo (int) $overview['error']; ?></strong>
        <span class="metric-note">另有跳过 <?php echo (int) $overview['skipped']; ?> 次</span>
    </div>
    <div class="metric-card">
        <span class="metric-label">成功率</span>
        <strong class="metric-value"><?php echo $overview['rate']; ?>%</strong>
        <span class="metric-note"><?php if ($overview['rate'] >= 90) { ?>运行优秀<?php } elseif ($overview['rate'] >= 70) { ?>运行良好<?php } elseif ($overview['rate'] >= 50) { ?>有待改善<?php } else { ?>需要排查<?php } ?></span>
    </div>
    <div class="metric-card">
        <span class="metric-label">活跃贴吧</span>
        <strong class="metric-value"><?php echo (int) $overview['forums']; ?></strong>
        <span class="metric-note">有效签到天数 <?php echo (int) $overview['active_days']; ?> 天</span>
    </div>
</section>

<div class="cs-chart-card" style="margin:8px 1% 24px">
    <div class="cs-chart-card-header"><h3 class="cs-chart-card-title">成功率趋势</h3><span class="cs-chart-card-subtitle">每日成功/失败次数与成功率</span></div>
    <div class="cs-chart-container" id="analysis-trend-chart" style="min-height:300px"></div>
</div>

<div class="cs-chart-card" style="margin:0 1% 24px">
    <div class="cs-chart-card-header"><h3 class="cs-chart-card-title">吧维度对比</h3><span class="cs-chart-card-subtitle">按签到次数排序（Top 15）</span></div>
    <div class="cs-chart-container" id="analysis-forum-chart" style="min-height:300px"></div>
</div>

<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(340px,1fr));gap:16px;align-items:start;margin:0 1% 24px">

<?php if (!empty($failureRows)) { ?>
<div class="cs-chart-card" style="margin:0">
    <div class="cs-chart-card-header"><h3 class="cs-chart-card-title">失败原因 TOP</h3><span class="cs-chart-card-subtitle">按出现次数排序（Top 10）</span></div>
    <div class="cs-chart-container" id="analysis-failure-chart" style="min-height:280px"></div>
    <div class="panel-body">
        <div class="cs-table-wrap"><div class="cs-table-scroll"><table class="cs-table">
            <thead><tr><th>原因</th><th>次数</th><th>占比</th></tr></thead>
            <tbody>
            <?php $failureTotal = 0;
            foreach ($failureRows as $frw) { $failureTotal += $frw['count']; }
            foreach ($failureRows as $frw) { ?>
                <tr>
                    <td class="wrap"><?php echo htmlspecialchars($frw['reason'], ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><?php echo (int) $frw['count']; ?></td>
                    <td style="font-size:12px;color:var(--color-muted)"><?php echo $failureTotal > 0 ? round($frw['count'] / $failureTotal * 100, 1) : 0; ?>%</td>
                </tr>
            <?php } ?>
            </tbody>
        </table></div></div>
    </div>
</div>
<?php } ?>

<div class="cs-chart-card" style="margin:0">
    <div class="cs-chart-card-header"><h3 class="cs-chart-card-title">签到时段分布</h3><span class="cs-chart-card-subtitle">近 <?php echo $days; ?> 天按小时聚合（站点时区）</span></div>
    <div class="cs-chart-container" id="analysis-hour-chart" style="min-height:280px"></div>
</div>

<?php if ($monitorStats['available'] && $monitorStats['total'] > 0) { ?>
<div class="cs-chart-card" style="margin:0">
    <div class="cs-chart-card-header"><h3 class="cs-chart-card-title">监控命中趋势</h3><span class="cs-chart-card-subtitle">帖子监控近 <?php echo $days; ?> 天每日命中数，累计 <?php echo (int) $monitorStats['total']; ?> 条</span></div>
    <div class="cs-chart-container" id="analysis-monitor-chart" style="min-height:260px"></div>
    <div class="panel-body">
        <div class="cs-table-wrap"><div class="cs-table-scroll"><table class="cs-table">
            <thead><tr><th>目标贴吧</th><th>命中数</th><th>已联动回帖</th></tr></thead>
            <tbody>
            <?php foreach ($monitorStats['top'] as $mt) { ?>
                <tr>
                    <td><?php echo htmlspecialchars($mt['forum'], ENT_QUOTES, 'UTF-8'); ?>吧</td>
                    <td><?php echo (int) $mt['hits']; ?></td>
                    <td><?php echo (int) $mt['replied']; ?></td>
                </tr>
            <?php } ?>
            </tbody>
        </table></div></div>
    </div>
</div>
<?php } ?>

<?php if ($hasHealthTrend) { ?>
<div class="cs-chart-card" style="margin:0">
    <div class="cs-chart-card-header"><h3 class="cs-chart-card-title">账号健康度趋势</h3><span class="cs-chart-card-subtitle">来自每日健康检查快照（一天取最后一次结果）</span></div>
    <div class="cs-chart-container" id="analysis-health-chart" style="min-height:260px"></div>
</div>
<?php } ?>
</div>

<section class="panel panel-primary" style="margin:0 1% 24px">
    <div class="panel-heading"><span class="panel-title">贴吧明细（近 <?php echo $days; ?> 天）</span></div>
    <div class="panel-body">
        <div class="cs-table-wrap"><div class="cs-table-scroll"><table class="cs-table">
            <thead><tr><th>贴吧</th><th>成功</th><th>失败</th><th>总计</th><th>成功率</th><th>最近状态</th><th>最近签到时间</th></tr></thead>
            <tbody>
            <?php foreach ($forumRows as $fr) {
                $rate = (float) $fr['rate'];
                if ($rate >= 90) { $rateBadge = 'cs-badge-success'; }
                elseif ($rate >= 70) { $rateBadge = 'cs-badge-info'; }
                elseif ($rate >= 50) { $rateBadge = 'cs-badge-warning'; }
                else { $rateBadge = 'cs-badge-danger'; }
                $ls = isset($signStatusMap[$fr['last_status']]) ? $signStatusMap[$fr['last_status']] : array('cs-badge-neutral', '未知');
            ?>
                <tr>
                    <td class="wrap"><?php echo htmlspecialchars($fr['tieba'], ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><?php echo (int) $fr['success']; ?></td>
                    <td><?php echo (int) $fr['error']; ?></td>
                    <td><?php echo (int) $fr['total']; ?></td>
                    <td><span class="cs-badge <?php echo $rateBadge; ?>"><?php echo $rate; ?>%</span></td>
                    <td><span class="cs-badge <?php echo $ls[0]; ?>"><?php echo $ls[1]; ?></span></td>
                    <td style="font-size:12px;color:var(--color-muted)"><?php echo $fmtTime($fr['last_time']); ?></td>
                </tr>
            <?php } ?>
            </tbody>
        </table></div></div>
    </div>
</section>

<section class="panel panel-primary" style="margin:0 1% 24px">
    <div class="panel-heading"><span class="panel-title">账号对比与健康状态</span></div>
    <div class="panel-body">
        <div class="cs-table-wrap"><div class="cs-table-scroll"><table class="cs-table">
            <thead><tr><th>账号（PID）</th><th>签到</th><th>成功 / 失败</th><th>成功率</th><th>BDUSS 状态</th><th>最近成功</th><th>最近失败</th></tr></thead>
            <tbody>
            <?php if (empty($accountRows)) { ?>
                <tr><td colspan="7" style="text-align:center;padding:20px;color:var(--color-muted)">近 <?php echo $days; ?> 天没有账号产生签到记录</td></tr>
            <?php } else { foreach ($accountRows as $ar) {
                $hv = isset($healthMap[$ar['pid']]) ? $healthMap[$ar['pid']] : null;
                $hs = $hv && isset($healthStatusMap[$hv['status']]) ? $healthStatusMap[$hv['status']] : array('cs-badge-neutral', '无数据');
            ?>
                <tr>
                    <td><?php echo htmlspecialchars($ar['name'] !== '' ? $ar['name'] . '（' . $ar['pid'] . '）' : 'PID ' . $ar['pid'], ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><?php echo (int) $ar['total']; ?></td>
                    <td><?php echo (int) $ar['success']; ?> / <?php echo (int) $ar['error']; ?></td>
                    <td><?php echo $ar['rate']; ?>%</td>
                    <td>
                        <span class="cs-badge <?php echo $hs[0]; ?>"><?php echo $hs[1]; ?></span>
                        <?php if ($hv && $hv['fail_count'] > 0) { ?><span class="text-muted" style="font-size:11px">连续失败 <?php echo (int) $hv['fail_count']; ?> 次</span><?php } ?>
                        <?php if ($hv && $hv['error_msg'] !== '') { ?><div style="font-size:11px;color:var(--color-muted)"><?php echo htmlspecialchars($hv['error_msg'], ENT_QUOTES, 'UTF-8'); ?></div><?php } ?>
                    </td>
                    <td style="font-size:12px;color:var(--color-muted)"><?php echo $hv ? $fmtTime($hv['last_ok']) : '—'; ?></td>
                    <td style="font-size:12px;color:var(--color-muted)"><?php echo $hv ? $fmtTime($hv['last_fail']) : '—'; ?></td>
                </tr>
            <?php } } ?>
            </tbody>
        </table></div></div>
    </div>
</section>

<?php loadChartAssets(); ?>
<script>
// components.js 为 defer 加载，CS 在解析期内联脚本执行时尚不可用，必须等 DOMContentLoaded
function initAnalysisCharts(){
    if (typeof echarts === 'undefined' || !window.CS || !CS.chart) return;

    var trendRows = <?php echo json_encode($trendRows, JSON_UNESCAPED_UNICODE); ?>;
    var forumRows = <?php echo json_encode($forumRows, JSON_UNESCAPED_UNICODE); ?>;
    var failureRows = <?php echo json_encode($failureRows, JSON_UNESCAPED_UNICODE); ?>;
    var hourRows = <?php echo json_encode($hourRows, JSON_UNESCAPED_UNICODE); ?>;
    var successColor = '#10b981', errorColor = '#c0554f', rateColor = '#3b82f6';

    // 成功率趋势：柱状（成功/失败）+ 折线（成功率，右轴）
    var trendEl = document.getElementById('analysis-trend-chart');
    if (trendEl && trendRows.length) {
        CS.chart.create(trendEl, {
            tooltip: { trigger: 'axis' },
            legend: { data: ['成功', '失败', '成功率'], top: 0 },
            grid: { left: '3%', right: '4%', bottom: '8%', containLabel: true },
            xAxis: { type: 'category', data: trendRows.map(function(r){ return r.date.substring(5); }) },
            yAxis: [
                { type: 'value', minInterval: 1 },
                { type: 'value', min: 0, max: 100, axisLabel: { formatter: '{value}%' }, splitLine: { show: false } }
            ],
            series: [
                { name: '成功', type: 'bar', stack: 'sign', data: trendRows.map(function(r){ return r.success; }), itemStyle: { color: successColor } },
                { name: '失败', type: 'bar', stack: 'sign', data: trendRows.map(function(r){ return r.error; }), itemStyle: { color: errorColor } },
                { name: '成功率', type: 'line', yAxisIndex: 1, smooth: true, symbol: 'none', data: trendRows.map(function(r){ return r.rate; }), itemStyle: { color: rateColor } }
            ]
        });
    }

    // 吧维度对比：横向堆叠条形（Top 15）
    var forumEl = document.getElementById('analysis-forum-chart');
    var forumTop = forumRows.slice(0, 15).reverse();
    if (forumEl && forumTop.length) {
        CS.chart.create(forumEl, {
            tooltip: { trigger: 'axis', axisPointer: { type: 'shadow' } },
            legend: { data: ['成功', '失败'], top: 0 },
            grid: { left: '3%', right: '4%', bottom: '8%', containLabel: true },
            xAxis: { type: 'value', minInterval: 1 },
            yAxis: {
                type: 'category',
                data: forumTop.map(function(r){ return r.tieba; }),
                axisLabel: { width: 90, overflow: 'truncate' }
            },
            series: [
                { name: '成功', type: 'bar', stack: 'forum', data: forumTop.map(function(r){ return r.success; }), itemStyle: { color: successColor } },
                { name: '失败', type: 'bar', stack: 'forum', data: forumTop.map(function(r){ return r.error; }), itemStyle: { color: errorColor } }
            ]
        });
    }

    // 失败原因 TOP：横向条形
    var failureEl = document.getElementById('analysis-failure-chart');
    var failureTop = failureRows.slice(0, 10).reverse();
    if (failureEl && failureTop.length) {
        CS.chart.create(failureEl, {
            tooltip: { trigger: 'axis', axisPointer: { type: 'shadow' } },
            grid: { left: '3%', right: '6%', bottom: '8%', containLabel: true },
            xAxis: { type: 'value', minInterval: 1 },
            yAxis: {
                type: 'category',
                data: failureTop.map(function(r){ return r.reason.length > 14 ? r.reason.substring(0, 14) + '…' : r.reason; }),
                axisLabel: { width: 150, overflow: 'truncate' }
            },
            series: [
                { name: '次数', type: 'bar', data: failureTop.map(function(r){ return r.count; }), itemStyle: { color: errorColor } }
            ]
        });
    }

    // 签到时段分布：24 小时堆叠柱状
    var hourEl = document.getElementById('analysis-hour-chart');
    if (hourEl && hourRows.length) {
        CS.chart.create(hourEl, {
            tooltip: { trigger: 'axis' },
            legend: { data: ['成功', '失败'], top: 0 },
            grid: { left: '3%', right: '4%', bottom: '8%', containLabel: true },
            xAxis: { type: 'category', data: hourRows.map(function(r){ return r.hour + '时'; }) },
            yAxis: { type: 'value', minInterval: 1 },
            series: [
                { name: '成功', type: 'bar', stack: 'hour', data: hourRows.map(function(r){ return r.success; }), itemStyle: { color: successColor } },
                { name: '失败', type: 'bar', stack: 'hour', data: hourRows.map(function(r){ return r.error; }), itemStyle: { color: errorColor } }
            ]
        });
    }

    // 监控命中趋势：每日命中数面积折线
    var monitorEl = document.getElementById('analysis-monitor-chart');
    var monitorData = <?php echo json_encode($monitorStats['trend'], JSON_UNESCAPED_UNICODE); ?>;
    if (monitorEl && monitorData.length) {
        CS.chart.create(monitorEl, {
            tooltip: { trigger: 'axis' },
            grid: { left: '3%', right: '4%', bottom: '8%', containLabel: true },
            xAxis: { type: 'category', data: monitorData.map(function(r){ return r.date.substring(5); }) },
            yAxis: { type: 'value', minInterval: 1 },
            series: [
                { name: '命中数', type: 'line', smooth: true, areaStyle: { opacity: 0.15 }, data: monitorData.map(function(r){ return r.hits; }), itemStyle: { color: rateColor } }
            ]
        });
    }

    // 账号健康度趋势：正常账号数柱状 + 正常率折线（右轴）
    var healthEl = document.getElementById('analysis-health-chart');
    var healthData = <?php echo json_encode($healthTrendRows, JSON_UNESCAPED_UNICODE); ?>;
    if (healthEl && healthData.length) {
        CS.chart.create(healthEl, {
            tooltip: { trigger: 'axis' },
            legend: { data: ['正常账号', '正常率'], top: 0 },
            grid: { left: '3%', right: '4%', bottom: '8%', containLabel: true },
            xAxis: { type: 'category', data: healthData.map(function(r){ return r.date.substring(5); }) },
            yAxis: [
                { type: 'value', minInterval: 1 },
                { type: 'value', min: 0, max: 100, axisLabel: { formatter: '{value}%' }, splitLine: { show: false } }
            ],
            series: [
                { name: '正常账号', type: 'bar', stack: 'health', data: healthData.map(function(r){ return r.ok; }), itemStyle: { color: successColor } },
                { name: '正常率', type: 'line', yAxisIndex: 1, smooth: true, symbol: 'none', data: healthData.map(function(r){ return r.rate; }), itemStyle: { color: rateColor } }
            ]
        });
    }
    // 主题切换由 components.js 全局监听并调用 CS.chart.refreshTheme()
}
if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', initAnalysisCharts); } else { initAnalysisCharts(); }
</script>
<?php } ?>
