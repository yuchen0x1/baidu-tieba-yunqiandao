<?php if (!defined('SYSTEM_ROOT')) { die('Insufficient Permissions'); } if (ROLE != 'admin') { msg('权限不足!'); }
global $m;
$logTable = DB_PREFIX . 'cron_log_batch';
$since = time() - 86400 * 30;
$rows = $m->query("SELECT DATE(FROM_UNIXTIME(created_at)) AS day, SUM(CASE WHEN status IN ('ok','success','partial') THEN 1 ELSE 0 END) AS ok_count, SUM(CASE WHEN status NOT IN ('ok','success','partial') THEN 1 ELSE 0 END) AS fail_count FROM `{$logTable}` WHERE `created_at` > {$since} AND `action` LIKE 'sign%' GROUP BY day ORDER BY day ASC;");
$days = $oks = $fails = array();
while ($r = $m->fetch_array($rows)) { $days[] = $r['day']; $oks[] = (int) $r['ok_count']; $fails[] = (int) $r['fail_count']; }
$total = array_sum($oks) + array_sum($fails);
$rate = $total > 0 ? round(array_sum($oks) / $total * 100, 1) : 0;
$lastRow = $m->once_fetch_array("SELECT created_at FROM `{$logTable}` WHERE `action` LIKE 'sign%' ORDER BY id DESC LIMIT 1;");
$lastTime = $lastRow ? date('Y-m-d H:i:s', (int) $lastRow['created_at']) : '-';
?>
<div class="panel panel-primary" style="margin:1%">
    <div class="panel-heading"><h3 class="panel-title">签到报表 - 最近30天趋势</h3></div>
    <div class="panel-body">
        <div class="row">
            <div class="col-sm-4"><div class="metric-card"><div class="metric-value"><?php echo $total; ?></div><div class="metric-label">总执行次数</div></div></div>
            <div class="col-sm-4"><div class="metric-card"><div class="metric-value"><?php echo $rate; ?>%</div><div class="metric-label">成功率</div></div></div>
            <div class="col-sm-4"><div class="metric-card"><div class="metric-value"><?php echo htmlspecialchars($lastTime, ENT_QUOTES, 'UTF-8'); ?></div><div class="metric-label">最近执行</div></div></div>
        </div>
        <div id="sign-chart" style="width:100%;height:320px;margin-top:16px;"></div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/echarts@5/dist/echarts.min.js"></script>
<script>
(function(){
    var chart = echarts.init(document.getElementById('sign-chart'));
    chart.setOption({
        tooltip:{trigger:'axis'},
        legend:{data:['签到成功','签到失败']},
        xAxis:{type:'category',data:<?php echo json_encode($days); ?>},
        yAxis:{type:'value',minInterval:1},
        series:[
            {name:'签到成功',type:'line',smooth:true,data:<?php echo json_encode($oks); ?>,itemStyle:{color:'#10b981'}},
            {name:'签到失败',type:'line',smooth:true,data:<?php echo json_encode($fails); ?>,itemStyle:{color:'#ef4444'}}
        ]
    });
    window.addEventListener('resize',function(){chart.resize();});
})();
</script>
