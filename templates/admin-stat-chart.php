<?php if (!defined('SYSTEM_ROOT')) { die('Insufficient Permissions'); } if (ROLE != 'admin') { msg('权限不足!'); }
global $m;
require_once SYSTEM_ROOT . '/lib/class.SignManager.php';
$logTable = DB_PREFIX . 'cron_log_batch';
$since = time() - 86400 * 30;
$rows = $m->query("SELECT DATE(FROM_UNIXTIME(created_at)) AS day, SUM(CASE WHEN status IN ('ok','success','partial') THEN 1 ELSE 0 END) AS ok_count, SUM(CASE WHEN status NOT IN ('ok','success','partial') THEN 1 ELSE 0 END) AS fail_count FROM `{$logTable}` WHERE `created_at` > {$since} AND `action` LIKE 'sign%' GROUP BY day ORDER BY day ASC;");
$days = $oks = $fails = array();
while ($r = $m->fetch_array($rows)) { $days[] = $r['day']; $oks[] = (int) $r['ok_count']; $fails[] = (int) $r['fail_count']; }
$total = array_sum($oks) + array_sum($fails);
$rate = $total > 0 ? round(array_sum($oks) / $total * 100, 1) : 0;
$lastRow = $m->once_fetch_array("SELECT created_at FROM `{$logTable}` WHERE `action` LIKE 'sign%' ORDER BY id DESC LIMIT 1;");
$lastTime = $lastRow ? date('Y-m-d H:i:s', (int) $lastRow['created_at']) : '-';
$calendar90 = SignManager::calendar(90, 0);
$summary = SignManager::signSummary(0);
$rateToday = ($summary['signed'] + $summary['error']) > 0 ? round($summary['signed'] / ($summary['signed'] + $summary['error']) * 100, 1) : 0;
?>
<ul class="cs-tablist" role="tablist">
  <li><a href="index.php?mod=admin:stat:sign">签到</a></li>
  <li><a href="index.php?mod=admin:stat:env">环境</a></li>
  <li class="active"><a href="index.php?mod=admin:stat:chart">趋势报表</a></li>
</ul>

<section class="dashboard-hero" style="margin-top:16px">
    <div class="eyebrow hero-eyebrow">Sign Trend Report / 30 Days</div>
    <h1>签到趋势报表</h1>
    <p>多视图查看签到执行情况：折线趋势、90 天热力图、当日成功率。</p>
</section>

<div class="cs-tabs" id="chart-tabs">
    <ul class="cs-tablist" role="tablist">
        <li><button type="button" class="cs-tab" role="tab" id="tab-trend" aria-controls="panel-trend" aria-selected="true">30 天趋势</button></li>
        <li><button type="button" class="cs-tab" role="tab" id="tab-heat" aria-controls="panel-heat" aria-selected="false">90 天热力图</button></li>
        <li><button type="button" class="cs-tab" role="tab" id="tab-gauge" aria-controls="panel-gauge" aria-selected="false">今日成功率</button></li>
    </ul>
</div>

<div class="cs-tabpanel" id="panel-trend" role="tabpanel" aria-labelledby="tab-trend">
    <div class="cs-chart-card" style="margin-top:16px">
        <div class="cs-chart-card-header"><h3 class="cs-chart-card-title">最近 30 天签到趋势</h3><span class="cs-chart-card-subtitle">基于 cron_log_batch 批次日志</span></div>
        <div class="row">
            <div class="col-sm-4"><div class="metric-card"><div class="metric-value"><?php echo $total; ?></div><div class="metric-label">总执行次数</div></div></div>
            <div class="col-sm-4"><div class="metric-card"><div class="metric-value"><?php echo $rate; ?>%</div><div class="metric-label">成功率</div></div></div>
            <div class="col-sm-4"><div class="metric-card"><div class="metric-value" style="font-size:14px"><?php echo htmlspecialchars($lastTime, ENT_QUOTES, 'UTF-8'); ?></div><div class="metric-label">最近执行</div></div></div>
        </div>
        <div class="cs-chart-container" id="sign-chart-trend" style="min-height:320px;margin-top:16px"></div>
    </div>
</div>

<div class="cs-tabpanel" id="panel-heat" role="tabpanel" aria-labelledby="tab-heat" hidden>
    <div class="cs-chart-card" style="margin-top:16px">
        <div class="cs-chart-card-header"><h3 class="cs-chart-card-title">90 天签到热力图</h3><span class="cs-chart-card-subtitle">每日签到总次数</span></div>
        <div class="cs-chart-container" id="sign-chart-heat" style="min-height:280px;margin-top:8px"></div>
    </div>
</div>

<div class="cs-tabpanel" id="panel-gauge" role="tabpanel" aria-labelledby="tab-gauge" hidden>
    <div class="cs-chart-card" style="margin-top:16px">
        <div class="cs-chart-card-header"><h3 class="cs-chart-card-title">今日签到成功率</h3><span class="cs-chart-card-subtitle">已签 <?php echo (int) $summary['signed']; ?> / 待签 <?php echo (int) $summary['waiting']; ?> / 出错 <?php echo (int) $summary['error']; ?></span></div>
        <div class="cs-chart-container" id="sign-chart-gauge" style="min-height:280px"></div>
    </div>
</div>

<?php loadChartAssets(); ?>
<script>
// components.js 为 defer 加载，CS 在解析期内联脚本执行时尚不可用，必须等 DOMContentLoaded
function initAdminChartPage(){
    if (typeof echarts === 'undefined' || !window.CS || !CS.chart) return;
    if (window.CS && CS.tabs) { CS.tabs.init(document.getElementById('chart-tabs')); }
    var days = <?php echo json_encode($days, JSON_UNESCAPED_UNICODE); ?>;
    var oks = <?php echo json_encode($oks, JSON_UNESCAPED_UNICODE); ?>;
    var fails = <?php echo json_encode($fails, JSON_UNESCAPED_UNICODE); ?>;
    var cal90 = <?php echo json_encode($calendar90, JSON_UNESCAPED_UNICODE); ?>;
    var rateToday = <?php echo $rateToday; ?>;

    var trendEl = document.getElementById('sign-chart-trend');
    if (trendEl) {
        CS.chart.create(trendEl, {
            tooltip: { trigger: 'axis' }, legend: { data: ['签到成功','签到失败'], top: 0 },
            xAxis: { type: 'category', data: days },
            yAxis: { type: 'value', minInterval: 1 },
            series: [
                { name: '签到成功', type: 'line', smooth: true, data: oks, itemStyle: { color: '#10b981' } },
                { name: '签到失败', type: 'line', smooth: true, data: fails, itemStyle: { color: '#c0554f' } }
            ]
        });
    }

    var heatEl = document.getElementById('sign-chart-heat');
    if (heatEl && cal90.length) {
        var heatData = cal90.map(function(r){ return [r.day, r.total]; });
        CS.chart.create(heatEl, {
            tooltip: { position: 'top', formatter: function(p){ return p.value[0] + '<br/>共 ' + p.value[1] + ' 次（成功 ' + cal90[p.dataIndex].success + '，失败 ' + cal90[p.dataIndex].error + '）'; } },
            visualMap: { min: 0, max: Math.max(1, Math.max.apply(null, cal90.map(function(r){return r.total;}))), calculable: true, orient: 'horizontal', left: 'center', bottom: 0, inRange: { color: ['#e0e7ff', '#5b3aff', '#3b1fb0'] } },
            calendar: { top: 30, left: 40, right: 40, cellSize: ['auto', 14], range: [cal90[0].day, cal90[cal90.length-1].day], itemStyle: { borderWidth: 1, borderColor: '#fff' }, splitLine: { show: false }, yearLabel: { show: false }, dayLabel: { color: '#64748b' }, monthLabel: { color: '#64748b' } },
            series: [{ type: 'heatmap', coordinateSystem: 'calendar', data: heatData }]
        });
    }

    var gaugeEl = document.getElementById('sign-chart-gauge');
    if (gaugeEl) {
        CS.chart.create(gaugeEl, {
            series: [{
                type: 'gauge', radius: '85%', startAngle: 200, endAngle: -20,
                progress: { show: true, width: 18, roundCap: true },
                axisLine: { lineStyle: { width: 18, color: [[1, '#5b3aff']] } },
                pointer: { show: false },
                axisTick: { show: false }, axisLabel: { show: false }, splitLine: { show: false },
                detail: { formatter: '{value}%', fontSize: 36, fontWeight: 'bold', offsetCenter: [0, '5%'], color: '#5b3aff' },
                title: { show: true, offsetCenter: [0, '40%'], fontSize: 13, color: '#64748b' },
                data: [{ value: rateToday, name: '今日成功率' }]
            }]
        });
    }

    // 主题切换由 components.js 全局监听并调用 CS.chart.refreshTheme()
}
if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', initAdminChartPage); } else { initAdminChartPage(); }
</script>
