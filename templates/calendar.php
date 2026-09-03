<?php
if (!defined('SYSTEM_ROOT')) {
    die('Insufficient Permissions');
}
global $m;
require_once SYSTEM_ROOT . '/lib/class.SignManager.php';

// 90 天数据（用于热力图 Tab 1）
$calendarRows = SignManager::calendar(90, UID);
// 365 天数据（用于全年热力图 Tab 3）
$yearRows = SignManager::calendar(365, UID);
$calendarMap = array();
$yearMap = array();
$totalSuccess = 0;
$totalError = 0;
$yearSuccess = 0;
$yearError = 0;
$thisMonthSuccess = 0;
$thisMonthError = 0;
$thisMonth = date('Y-m');
$thisYear = date('Y');
foreach ($calendarRows as $row) {
    $calendarMap[$row['day']] = $row;
    $totalSuccess += $row['success'];
    $totalError += $row['error'];
    if (strpos($row['day'], $thisMonth) === 0) {
        $thisMonthSuccess += $row['success'];
        $thisMonthError += $row['error'];
    }
}
foreach ($yearRows as $row) {
    $yearMap[$row['day']] = $row;
    $yearSuccess += $row['success'];
    $yearError += $row['error'];
}
$start = strtotime('-89 days midnight');
$end = strtotime('today midnight');
$yearStart = strtotime('-364 days midnight');
$maxTotal = 1;
$yearMaxTotal = 1;
foreach ($calendarRows as $row) {
    $maxTotal = max($maxTotal, $row['total']);
}
foreach ($yearRows as $row) {
    $yearMaxTotal = max($yearMaxTotal, $row['total']);
}
$successRate = ($totalSuccess + $totalError) > 0 ? round($totalSuccess / ($totalSuccess + $totalError) * 100) : 0;
$yearRate = ($yearSuccess + $yearError) > 0 ? round($yearSuccess / ($yearSuccess + $yearError) * 100) : 0;
$thisMonthRate = ($thisMonthSuccess + $thisMonthError) > 0 ? round($thisMonthSuccess / ($thisMonthSuccess + $thisMonthError) * 100) : 0;
$streak = SignManager::streak(UID);

// 渲染热力图格子（通用函数）
function render_heatmap_cells($map, $startStamp, $endStamp, $maxTotal) {
    for ($stamp = $startStamp; $stamp <= $endStamp; $stamp += 86400) {
        $day = date('Y-m-d', $stamp);
        $row = isset($map[$day]) ? $map[$day] : array('success' => 0, 'error' => 0, 'total' => 0);
        $ratio = $row['total'] > 0 ? $row['total'] / $maxTotal : 0;
        $level = $ratio <= 0 ? 0 : min(4, max(1, (int) ceil($ratio * 4)));
        $title = $day . '：成功 ' . (int) $row['success'] . '，失败 ' . (int) $row['error'] . '，共 ' . (int) $row['total'] . ' 次';
        $hasError = $row['error'] > 0 ? ' has-error' : '';
        $dataLevel = 'level-' . $level . $hasError;
        echo '<span class="calendar-cell ' . $dataLevel . '" title="' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '" aria-label="' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '" data-level="' . $level . '" data-error="' . ($hasError ? '1' : '0') . '"></span>';
    }
}
?>
<section class="dashboard-hero">
    <div class="eyebrow hero-eyebrow">Personal Signing History / <?php echo $thisYear; ?></div>
    <h1>签到日历</h1>
    <p>展示近 90 天与全年 365 天的实际贴吧签到结果。历史记录从 V5.12 开始积累，旧版记录不会被回填。</p>
    <div class="dashboard-hero-meta">
        <span class="hero-tag">当前连签 <?php echo (int) $streak['current_streak']; ?> 天</span>
        <span class="hero-tag">最长连签 <?php echo (int) $streak['max_streak']; ?> 天</span>
        <span class="hero-tag">近 90 天成功率 <?php echo $successRate; ?>%</span>
        <span class="hero-tag">全年成功率 <?php echo $yearRate; ?>%</span>
    </div>
</section>

<div class="cs-tabs" id="calendar-tabs">
    <ul class="cs-tablist" role="tablist">
        <li><button type="button" class="cs-tab" role="tab" id="tab-90d" aria-controls="panel-90d" aria-selected="true">90 天热力图</button></li>
        <li><button type="button" class="cs-tab" role="tab" id="tab-month" aria-controls="panel-month" aria-selected="false">本月汇总</button></li>
        <li><button type="button" class="cs-tab" role="tab" id="tab-year" aria-controls="panel-year" aria-selected="false">全年热力图</button></li>
    </ul>
</div>

<div class="cs-tabpanel" id="panel-90d" role="tabpanel" aria-labelledby="tab-90d">
    <section class="metric-grid" aria-label="签到统计" style="margin-bottom:24px;margin-top:16px">
        <div class="metric-card">
            <span class="metric-label">成功签到</span>
            <strong class="metric-value"><?php echo (int) $totalSuccess; ?></strong>
            <span class="metric-note">近 90 天自动签到成功</span>
        </div>
        <div class="metric-card">
            <span class="metric-label">失败次数</span>
            <strong class="metric-value"><?php echo (int) $totalError; ?></strong>
            <span class="metric-note">需要检查 BDUSS 或网络</span>
        </div>
        <div class="metric-card">
            <span class="metric-label">成功率</span>
            <strong class="metric-value"><?php echo $successRate; ?>%</strong>
            <span class="metric-note">越高越好</span>
        </div>
    </section>

    <section class="panel panel-primary">
        <div class="panel-heading"><span class="panel-title">签到活跃度热力图</span></div>
        <div class="panel-body">
            <div class="sign-calendar-legend" aria-label="日历图例">
                <span>无记录</span><i class="calendar-cell level-0" data-filter="0"></i><i class="calendar-cell level-1" data-filter="1"></i><i class="calendar-cell level-2" data-filter="2"></i><i class="calendar-cell level-3" data-filter="3"></i><i class="calendar-cell level-4" data-filter="4"></i><span>签到次数更多</span>
                <span style="margin-left:16px"></span>
                <i class="calendar-cell has-error" style="background:var(--color-surface-soft)" data-filter="error"></i><span>存在失败</span>
                <span style="margin-left:16px;font-size:11px;color:var(--color-muted)">点击图例可切换过滤</span>
            </div>
            <div class="sign-calendar-grid" aria-label="近90天签到日历" id="calendar-grid" data-filter-target>
                <?php render_heatmap_cells($calendarMap, $start, $end, $maxTotal); ?>
            </div>
            <p class="text-muted" style="margin-top:14px;font-size:12px">红色边框表示当天存在签到失败。将鼠标悬停在格子上可查看每日成功、失败与总次数，格子被放大高亮以便快速识别。</p>
        </div>
    </section>
</div>

<div class="cs-tabpanel" id="panel-month" role="tabpanel" aria-labelledby="tab-month" hidden style="margin-top:16px">
    <section class="metric-grid" aria-label="本月汇总">
        <div class="metric-card">
            <span class="metric-label">本月成功</span>
            <strong class="metric-value"><?php echo (int) $thisMonthSuccess; ?></strong>
            <span class="metric-note"><?php echo $thisMonth; ?></span>
        </div>
        <div class="metric-card">
            <span class="metric-label">本月失败</span>
            <strong class="metric-value"><?php echo (int) $thisMonthError; ?></strong>
            <span class="metric-note">需要排查</span>
        </div>
        <div class="metric-card">
            <span class="metric-label">本月成功率</span>
            <strong class="metric-value"><?php echo $thisMonthRate; ?>%</strong>
            <span class="metric-note">环比上月见趋势</span>
        </div>
    </section>

    <div class="cs-chart-card" style="margin-top:24px">
        <div class="cs-chart-card-header"><h3 class="cs-chart-card-title">本月每日签到趋势</h3><span class="cs-chart-card-subtitle">成功 / 失败 双线</span></div>
        <div class="cs-chart-container" id="month-trend-chart" style="min-height:280px"></div>
    </div>
</div>

<div class="cs-tabpanel" id="panel-year" role="tabpanel" aria-labelledby="tab-year" hidden style="margin-top:16px">
    <section class="metric-grid" aria-label="全年统计">
        <div class="metric-card">
            <span class="metric-label">全年成功</span>
            <strong class="metric-value"><?php echo (int) $yearSuccess; ?></strong>
            <span class="metric-note">近 365 天累计</span>
        </div>
        <div class="metric-card">
            <span class="metric-label">全年失败</span>
            <strong class="metric-value"><?php echo (int) $yearError; ?></strong>
            <span class="metric-note">需要复盘</span>
        </div>
        <div class="metric-card">
            <span class="metric-label">全年成功率</span>
            <strong class="metric-value"><?php echo $yearRate; ?>%</strong>
            <span class="metric-note">长期稳定性</span>
        </div>
    </section>

    <section class="panel panel-primary">
        <div class="panel-heading"><span class="panel-title">全年签到活跃度（GitHub 式）</span></div>
        <div class="panel-body">
            <div class="sign-calendar-legend" aria-label="全年日历图例">
                <span>无记录</span><i class="calendar-cell level-0"></i><i class="calendar-cell level-1"></i><i class="calendar-cell level-2"></i><i class="calendar-cell level-3"></i><i class="calendar-cell level-4"></i><span>签到更多</span>
            </div>
            <div class="sign-calendar-grid sign-calendar-grid-year" aria-label="近365天签到日历" id="calendar-grid-year" style="overflow-x:auto">
                <?php render_heatmap_cells($yearMap, $yearStart, $end, $yearMaxTotal); ?>
            </div>
            <p class="text-muted" style="margin-top:14px;font-size:12px">横向滚动可查看更早的日期。每一列代表一周，从周日到周六共 7 行。</p>
        </div>
    </section>

    <div class="cs-chart-card" style="margin-top:24px">
        <div class="cs-chart-card-header"><h3 class="cs-chart-card-title">近 12 个月签到趋势</h3><span class="cs-chart-card-subtitle">按月聚合成功/失败</span></div>
        <div class="cs-chart-container" id="year-trend-chart" style="min-height:280px"></div>
    </div>
</div>

<?php loadChartAssets(); ?>
<script>
(function(){
    if (window.CS && CS.tabs) {
        var t = document.getElementById('calendar-tabs');
        if (t) CS.tabs.init(t);
    }
    // 图例点击过滤（同时作用于 90 天与全年网格）
    var legend = document.querySelector('.sign-calendar-legend');
    var cells = document.querySelectorAll('#calendar-grid .calendar-cell, #calendar-grid-year .calendar-cell');
    if (legend) {
        legend.addEventListener('click', function(e){
            var item = e.target.closest('[data-filter]');
            if (!item) return;
            var filter = item.getAttribute('data-filter');
            cells.forEach(function(c){
                var show = true;
                if (filter === 'error') {
                    show = c.getAttribute('data-error') === '1';
                } else if (filter === '0') {
                    show = c.getAttribute('data-level') === '0';
                } else {
                    show = c.getAttribute('data-level') === filter;
                }
                c.style.opacity = show ? '1' : '0.15';
            });
        });
    }

    if (typeof echarts === 'undefined' || !window.CS || !CS.chart) return;
    var cal = <?php echo json_encode($calendarRows, JSON_UNESCAPED_UNICODE); ?>;
    var yearRows = <?php echo json_encode($yearRows, JSON_UNESCAPED_UNICODE); ?>;
    var thisMonth = '<?php echo $thisMonth; ?>';
    var monthData = cal.filter(function(r){ return r.day.indexOf(thisMonth) === 0; });
    var trendEl = document.getElementById('month-trend-chart');
    if (trendEl && monthData.length) {
        CS.chart.create(trendEl, {
            tooltip: { trigger: 'axis' }, legend: { data: ['成功','失败'], top: 0 },
            grid: { left: '3%', right: '4%', bottom: '8%', containLabel: true },
            xAxis: { type: 'category', data: monthData.map(function(r){ return r.day.substring(8); }) },
            yAxis: { type: 'value', minInterval: 1 },
            series: [
                { name: '成功', type: 'bar', stack: 'sign', data: monthData.map(function(r){ return r.success; }), itemStyle: { color: '#10b981' } },
                { name: '失败', type: 'bar', stack: 'sign', data: monthData.map(function(r){ return r.error; }), itemStyle: { color: '#c0554f' } }
            ]
        });
    }

    // 近 12 个月趋势（聚合 yearRows 按月）
    var monthBuckets = {};
    yearRows.forEach(function(r){
        var m = r.day.substring(0, 7); // YYYY-MM
        if (!monthBuckets[m]) monthBuckets[m] = { success: 0, error: 0 };
        monthBuckets[m].success += r.success;
        monthBuckets[m].error += r.error;
    });
    var monthKeys = Object.keys(monthBuckets).sort();
    var yearTrendEl = document.getElementById('year-trend-chart');
    if (yearTrendEl && monthKeys.length) {
        CS.chart.create(yearTrendEl, {
            tooltip: { trigger: 'axis' }, legend: { data: ['成功','失败'], top: 0 },
            grid: { left: '3%', right: '4%', bottom: '8%', containLabel: true },
            xAxis: { type: 'category', data: monthKeys },
            yAxis: { type: 'value', minInterval: 1 },
            series: [
                { name: '成功', type: 'bar', stack: 'sign', data: monthKeys.map(function(k){ return monthBuckets[k].success; }), itemStyle: { color: '#10b981' } },
                { name: '失败', type: 'bar', stack: 'sign', data: monthKeys.map(function(k){ return monthBuckets[k].error; }), itemStyle: { color: '#c0554f' } }
            ]
        });
    }
    // 主题切换由 components.js 全局监听并调用 CS.chart.refreshTheme()
})();
</script>
