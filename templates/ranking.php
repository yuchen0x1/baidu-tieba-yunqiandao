<?php
if (!defined('SYSTEM_ROOT')) {
    die('Insufficient Permissions');
}
if (ROLE === 'visitor' || ROLE === 'banned') {
    msg('请先登录');
}
require_once SYSTEM_ROOT . '/lib/class.SignManager.php';

$limit = 20;
// 多取 2 倍以便过滤掉隐藏昵称用户后仍能凑够 $limit
$rawLimit = $limit * 2;
$rawRows = SignManager::ranking($rawLimit);

// 过滤隐藏昵称用户：默认显示真实昵称，勾选 hide_from_ranking 后显示脱敏
$rankRows = array();
foreach ($rawRows as $row) {
    if ($row['uid'] <= 0) continue;
    $hidden = option::uget($row['uid'], 'hide_from_ranking') == 1;
    $rankRows[] = array(
        'uid'           => $row['uid'],
        'name'          => $hidden ? ('用户#' . $row['uid']) : $row['name'],
        'is_hidden'     => $hidden,
        'success_count' => $row['success_count'],
        'total_count'   => $row['total_count'],
        'rate'          => $row['total_count'] > 0 ? round($row['success_count'] / $row['total_count'] * 100, 1) : 0,
    );
    if (count($rankRows) >= $limit) break;
}

// 顶部统计
$totalUsers = 0;
$avgRate = 0;
$sumRate = 0;
$rateCount = 0;
foreach ($rankRows as $r) {
    if ($r['total_count'] > 0) {
        $sumRate += $r['rate'];
        $rateCount++;
    }
}
$totalUsers = count($rankRows);
$avgRate = $rateCount > 0 ? round($sumRate / $rateCount, 1) : 0;

// 全局今日完成度（用 signSummary 聚合所有用户较贵，改为查表）
global $m;
$todayStart = strtotime(date('Y-m-d') . ' 00:00:00');
$todaySql = "SELECT SUM(`status` = 'success') AS s, SUM(`status` = 'error') AS e, COUNT(*) AS t
    FROM `" . DB_PREFIX . SignManager::HISTORY_TABLE . "`
    WHERE `created_at` >= " . (int) $todayStart;
$todayRow = $m->once_fetch_array($todaySql);
$todaySuccess = (int) ($todayRow['s'] ?? 0);
$todayError   = (int) ($todayRow['e'] ?? 0);
$todayTotal   = (int) ($todayRow['t'] ?? 0);
$todayRate    = $todayTotal > 0 ? round($todaySuccess / $todayTotal * 100, 1) : 0;
?>
<section class="dashboard-hero" style="margin:16px 1%">
    <div class="eyebrow hero-eyebrow">User Ranking / <?php echo date('Y-m-d'); ?></div>
    <h1>签到排行榜</h1>
    <p>近 30 天签到活跃度排名。默认显示用户昵称，可在「个人设置」中隐藏。仅统计有签到记录的用户。</p>
</section>

<section class="metric-grid" aria-label="排行统计" style="margin:16px 1%">
    <div class="metric-card">
        <span class="metric-label">上榜用户</span>
        <strong class="metric-value"><?php echo $totalUsers; ?></strong>
        <span class="metric-note">近 30 天活跃账号</span>
    </div>
    <div class="metric-card">
        <span class="metric-label">平均成功率</span>
        <strong class="metric-value"><?php echo $avgRate; ?>%</strong>
        <span class="metric-note">上榜用户平均</span>
    </div>
    <div class="metric-card">
        <span class="metric-label">今日签到</span>
        <strong class="metric-value"><?php echo $todayTotal; ?></strong>
        <span class="metric-note">成功 <?php echo $todaySuccess; ?> / 失败 <?php echo $todayError; ?> · 成功率 <?php echo $todayRate; ?>%</span>
    </div>
</section>

<?php if (empty($rankRows)) { ?>
<div class="cs-empty" style="margin:24px 1%">
    <svg class="cs-empty-icon" aria-hidden="true" style="width:48px;height:48px"><use href="#i-trophy"/></svg>
    <h2 class="cs-empty-title">还没有签到数据</h2>
    <p class="cs-empty-desc">系统会随自动签到累积记录。请稍后再来查看排行榜。</p>
</div>
<?php } else { ?>

<div class="cs-chart-card" style="margin:16px 1%">
    <div class="cs-chart-card-header">
        <div>
            <h3 class="cs-chart-card-title">Top <?php echo count($rankRows); ?> 用户签到次数</h3>
            <span class="cs-chart-card-subtitle">基于近 30 天成功签到数</span>
        </div>
    </div>
    <div class="cs-chart-container" id="chart-ranking-bar" style="min-height:360px"></div>
</div>

<div style="margin:16px 1%;overflow-x:auto">
    <table class="cs-table cs-ranking-table">
        <thead>
            <tr>
                <th style="width:60px">排名</th>
                <th>用户</th>
                <th style="width:120px">30 天成功</th>
                <th style="width:120px">30 天总数</th>
                <th style="width:120px">成功率</th>
                <th style="width:120px">操作</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($rankRows as $idx => $r) {
            $rank = $idx + 1;
            $positionClass = $rank <= 3 ? 'rank-' . $rank : '';
        ?>
            <tr>
                <td><span class="cs-rank-position <?php echo $positionClass; ?>"><?php echo $rank; ?></span></td>
                <td><?php echo htmlspecialchars($r['name'], ENT_QUOTES, 'UTF-8'); ?><?php if ($r['is_hidden']) { ?> <span class="cs-badge cs-badge-neutral" style="margin-left:6px">已隐藏</span><?php } ?></td>
                <td><?php echo (int) $r['success_count']; ?></td>
                <td><?php echo (int) $r['total_count']; ?></td>
                <td>
                    <span class="cs-badge <?php echo $r['rate'] >= 95 ? 'cs-badge-success' : ($r['rate'] >= 80 ? 'cs-badge-warning' : 'cs-badge-error'); ?>"><?php echo $r['rate']; ?>%</span>
                </td>
                <td>
                    <?php if ($r['uid'] == UID) { ?>
                    <span class="cs-badge cs-badge-brand">我</span>
                    <?php } ?>
                </td>
            </tr>
        <?php } ?>
        </tbody>
    </table>
</div>
<?php } ?>

<?php loadChartAssets(); ?>
<script>
// components.js 为 defer 加载，CS 在解析期内联脚本执行时尚不可用，必须等 DOMContentLoaded
function initRankingCharts(){
    if (typeof echarts === 'undefined' || !window.CS || !CS.chart) return;
    var rows = <?php echo json_encode($rankRows, JSON_UNESCAPED_UNICODE); ?>;
    if (!rows.length) return;

    var el = document.getElementById('chart-ranking-bar');
    if (!el) return;
    CS.chart.create(el, {
        grid: { left: '3%', right: '4%', bottom: '3%', containLabel: true },
        tooltip: { trigger: 'axis', axisPointer: { type: 'shadow' } },
        xAxis: { type: 'value', minInterval: 1 },
        yAxis: {
            type: 'category',
            data: rows.map(function(r){ return r.name; }).reverse(),
            axisLabel: { interval: 0, fontSize: 11 }
        },
        series: [{
            name: '30 天成功签到',
            type: 'bar',
            data: rows.map(function(r){ return r.success_count; }).reverse(),
            itemStyle: { color: '#5b3aff', borderRadius: [0, 4, 4, 0] },
            barMaxWidth: 24,
            label: { show: true, position: 'right', fontSize: 11 }
        }]
    });
}
if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', initRankingCharts); } else { initRankingCharts(); }
</script>
