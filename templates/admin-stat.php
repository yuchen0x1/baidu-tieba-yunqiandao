<?php if (!defined('SYSTEM_ROOT')) {
    die('Insufficient Permissions');
}  if (ROLE != 'admin') {
    msg('权限不足！');
}
global $m,$i;
$day = date('Y-m-d');
if (isset($_GET['ok'])) {
    echo '<div class="cs-alert cs-alert-success"><svg class="cs-alert-icon" aria-hidden="true"><use href="#i-check"/></svg><div class="cs-alert-body">设置保存成功</div></div>';
}

if (!isset($i['mode'][2])) {
    $i['mode'][2] = 'sign';
}

require_once SYSTEM_ROOT . '/lib/class.SignManager.php';

switch ($i['mode'][2]) {
    case 'sign':
        ?>
<ul class="cs-tablist" role="tablist">
  <li class="active"><a href="index.php?mod=admin:stat:sign">签到</a></li>
  <li><a href="index.php?mod=admin:stat:env">环境</a></li>
  <li><a href="index.php?mod=admin:stat:chart">趋势报表</a></li>
          <?php doAction('stat_navi'); ?>
</ul>

<section class="dashboard-hero" style="margin-top:16px">
    <div class="eyebrow hero-eyebrow">Sign Statistics / All Users</div>
    <h1>签到信息统计</h1>
    <p>查看所有用户的签到数据汇总与趋势。</p>
</section>

<?php
// 全局聚合统计
$uxsCount = $m->once_fetch_array("SELECT COUNT(*) AS c FROM `" . DB_PREFIX . "users`");
$baiduidCount = $m->once_fetch_array("SELECT COUNT(*) AS c FROM `" . DB_PREFIX . "baiduid`");
$summary = SignManager::signSummary(0);
$streak = SignManager::streak(0);
$calendar30 = SignManager::calendar(30, 0);
$failures = SignManager::failureBreakdown(0, 8);
$ranking = SignManager::ranking(10, 0);
$globalRate = ($summary['signed'] + $summary['error']) > 0
    ? round($summary['signed'] / ($summary['signed'] + $summary['error']) * 100, 1) : 0;
?>

<section class="metric-grid metric-grid--four" style="margin:16px 0">
    <div class="metric-card"><span class="metric-label">用户总数</span><strong class="metric-value"><?php echo (int) $uxsCount['c']; ?></strong><span class="metric-note">已注册</span></div>
    <div class="metric-card"><span class="metric-label">百度账号</span><strong class="metric-value"><?php echo (int) $baiduidCount['c']; ?></strong><span class="metric-note">全站绑定</span></div>
    <div class="metric-card"><span class="metric-label">今日成功</span><strong class="metric-value"><?php echo (int) $summary['signed']; ?></strong><span class="metric-note">待签 <?php echo (int) $summary['waiting']; ?> · 出错 <?php echo (int) $summary['error']; ?></span></div>
    <div class="metric-card"><span class="metric-label">成功率</span><strong class="metric-value"><?php echo $globalRate; ?>%</strong><span class="metric-note">最长连签 <?php echo (int) $streak['max_streak']; ?> 天</span></div>
</section>

<section class="dashboard-grid" style="margin-bottom:24px">
    <div class="cs-chart-card">
        <div class="cs-chart-card-header"><h3 class="cs-chart-card-title">近 30 天签到趋势</h3><span class="cs-chart-card-subtitle">全站每日成功/失败</span></div>
        <div class="cs-chart-container" id="stat-30d-trend" style="min-height:240px"></div>
    </div>
    <div class="cs-chart-card">
        <div class="cs-chart-card-header"><h3 class="cs-chart-card-title">失败原因 Top 8</h3><span class="cs-chart-card-subtitle">近 30 天全站</span></div>
        <div class="cs-chart-container" id="stat-failure-pie" style="min-height:240px"></div>
    </div>
</section>

<div class="cs-chart-card" style="margin-bottom:24px">
    <div class="cs-chart-card-header"><h3 class="cs-chart-card-title">用户签到排行 Top 10</h3><span class="cs-chart-card-subtitle">近 30 天成功次数</span></div>
    <div class="cs-chart-container" id="stat-ranking-bar" style="min-height:260px"></div>
</div>

<div class="cs-chart-card">
    <div class="cs-chart-card-header"><h3 class="cs-chart-card-title">用户签到明细</h3><span class="cs-chart-card-subtitle">所有用户与贴吧数量</span></div>
<div class="cs-table-wrap" style="margin-top:8px">
<table class="cs-table">
    <thead>
        <tr>
        <th>UID</th>
       <th>用户名</th>
       <th>已绑定数</th>
      <th>等待签到数</th>
     <th>签到成功数</th>
     <th>签到出错数</th>
     <th>签到忽略数</th>
     <th>贴吧总数</th>
  </tr>
  </thead>
   <tbody>
            <?php
            $uxsv = $m->query("SELECT * FROM `" . DB_PREFIX . "users` ORDER BY `id`");
            $uxsg = $m->once_fetch_array("SELECT COUNT(*) AS `c` FROM `" . DB_PREFIX . "baiduid`");
            $alls = $alle = $alln = $allm = $allw = 0;
            while ($uxs = $m->fetch_array($uxsv)) {
                $uxsc = $m->once_fetch_array("SELECT COUNT(*) AS `c` FROM `" . DB_PREFIX . "baiduid` WHERE `uid` = " . $uxs['id']);
                $list = $m->query("SELECT id,no,status,latest FROM `" . DB_PREFIX . $uxs['t'] . "` WHERE `uid` = " . $uxs['id']);
                $success = $error = $no = $all = $waiting = 0;
                $num = $m->num_rows($list);
                while ($x = $m->fetch_array($list)) {
                    if ($x['no'] == '1') {
                        $no++;
                    } elseif ($x['latest'] != $day) {
                        $waiting++;
                    } elseif ($x['status'] == '0') {
                        $success++;
                    } elseif ($x['status'] != '0') {
                        $error++;
                    }
                }
                    $allw = $allw + $waiting;
                $alls = $alls + $success;
                $alln = $alln + $no;
                $allm = $allm + $num;
                $alle = $alle + $error;
                echo '<tr><td>' . htmlspecialchars($uxs['id'], ENT_QUOTES, 'UTF-8') . '</td><td>' . htmlspecialchars($uxs['name'], ENT_QUOTES, 'UTF-8') . '</td><td>' . $uxsc['c'] . '</td><td>' . $waiting . '</td><td>' . $success . '</td><td>' . $error . '</td><td>' . $no . '</td><td>' . $num . '</td>';
            }
            echo '<tr><td colspan="2"><strong>总计数据</strong></td><td>' . $uxsg['c'] . '</td><td>' . $allw . '</td><td>' . $alls . '</td><td>' . $alle . '</td><td>' . $alln . '</td><td>' . $allm . '</td>';

            ?>
 </tbody>
</table>
</div>
</div>
<?php loadChartAssets(); ?>
<script>
// components.js 为 defer 加载，CS 在解析期内联脚本执行时尚不可用，必须等 DOMContentLoaded
function initAdminStatCharts(){
    if (typeof echarts === 'undefined' || !window.CS || !CS.chart) return;
    var cal = <?php echo json_encode($calendar30, JSON_UNESCAPED_UNICODE); ?>;
    var failures = <?php echo json_encode($failures, JSON_UNESCAPED_UNICODE); ?>;
    var ranking = <?php echo json_encode($ranking, JSON_UNESCAPED_UNICODE); ?>;

    // 30 天趋势（成功 + 失败 双线）
    var trendEl = document.getElementById('stat-30d-trend');
    if (trendEl && cal.length) {
        CS.chart.create(trendEl, {
            tooltip: { trigger: 'axis' }, legend: { data: ['成功', '失败'], top: 0 },
            grid: { left: '3%', right: '4%', bottom: '8%', containLabel: true },
            xAxis: { type: 'category', data: cal.map(function(r){return r.day;}) },
            yAxis: { type: 'value', minInterval: 1 },
            series: [
                { name: '成功', type: 'line', smooth: true, showSymbol: false, data: cal.map(function(r){return r.success;}), itemStyle: { color: '#10b981' } },
                { name: '失败', type: 'line', smooth: true, showSymbol: false, data: cal.map(function(r){return r.error;}), itemStyle: { color: '#c0554f' } }
            ]
        });
    }
    // 失败原因饼图
    var pieEl = document.getElementById('stat-failure-pie');
    if (pieEl) {
        var pieData = failures.map(function(r){ return { name: r.reason.length>20?r.reason.substring(0,20)+'…':r.reason, value: r.count }; });
        if (!pieData.length) {
            pieEl.innerHTML = '<div style="display:flex;align-items:center;justify-content:center;height:240px;color:#94a3b8">近 30 天无失败记录</div>';
        } else {
            CS.chart.create(pieEl, {
                tooltip: { trigger: 'item', formatter: '{b}<br/>{c} 次 ({d}%)' },
                legend: { bottom: 0, type: 'scroll', textStyle: { fontSize: 11 } },
                series: [{ type: 'pie', radius: ['35%','60%'], center: ['50%','45%'], data: pieData, itemStyle: { borderRadius: 6, borderColor: '#fff', borderWidth: 2 }, label: { show: false } }]
            });
        }
    }
    // 用户排行条形图
    var barEl = document.getElementById('stat-ranking-bar');
    if (barEl && ranking.length) {
        CS.chart.create(barEl, {
            tooltip: { trigger: 'axis', axisPointer: { type: 'shadow' }, formatter: function(p){ return p[0].name + '<br/>成功 ' + p[0].value + ' 次 / 共 ' + ranking[p[0].dataIndex].total_count + ' 次'; } },
            grid: { left: '3%', right: '4%', bottom: '8%', containLabel: true },
            xAxis: { type: 'category', data: ranking.map(function(r){return r.name;}), axisLabel: { interval: 0, rotate: 30, fontSize: 11 } },
            yAxis: { type: 'value', minInterval: 1 },
            series: [{ type: 'bar', data: ranking.map(function(r){return r.success_count;}), itemStyle: { color: '#5b3aff', borderRadius: [4,4,0,0] }, barMaxWidth: 40 }]
        });
    }
    // 主题切换由 components.js 全局监听并调用 CS.chart.refreshTheme()
}
if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', initAdminStatCharts); } else { initAdminStatCharts(); }
</script>
        <?php
        break; case 'env':
        ?>
<ul class="cs-tablist" role="tablist">
  <li><a href="index.php?mod=admin:stat:sign">签到</a></li>
  <li class="active"><a href="index.php?mod=admin:stat:env">环境</a></li>
  <li><a href="index.php?mod=admin:stat:chart">趋势报表</a></li>
        <?php doAction('stat_navi'); ?>
</ul>
<h3>当前服务器软件环境</h3>
        <?php
        define('DO_NOT_LOAD_UI', true);
        require SYSTEM_ROOT . '/setup/check.php';
        ?>
        <?php
                   break; default:
            ?>
<ul class="cs-tablist" role="tablist">
  <li><a href="index.php?mod=admin:stat:sign">签到</a></li>
  <li><a href="index.php?mod=admin:stat:env">环境</a></li>
  <li><a href="index.php?mod=admin:stat:chart">趋势报表</a></li>
            <?php doAction('stat_navi'); ?>
</ul>
            <?php
                              break;
} ?>
