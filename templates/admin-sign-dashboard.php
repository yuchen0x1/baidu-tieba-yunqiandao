<?php if (!defined('SYSTEM_ROOT')) { die('Insufficient Permissions'); } if (ROLE != 'admin') { msg('权限不足!'); }
global $m;
require_once SYSTEM_ROOT . '/lib/class.SignManager.php';
$action = isset($_GET['action']) ? $_GET['action'] : 'dashboard';
$health_table = DB_PREFIX . SignManager::HEALTH_TABLE;
$log_table = DB_PREFIX . SignManager::LOG_TABLE;

// 健康度统计
$healthStats = array('ok' => 0, 'invalid' => 0, 'unknown' => 0);
$hs = $m->query("SELECT `status`, COUNT(*) AS c FROM `{$health_table}` GROUP BY `status`");
while ($hr = $m->fetch_array($hs)) {
    $st = $hr['status'] ?: 'unknown';
    $healthStats[$st] = (int) $hr['c'];
}
$healthTotal = $healthStats['ok'] + $healthStats['invalid'] + $healthStats['unknown'];
?>
<section class="dashboard-hero" style="margin:16px 1%">
    <div class="eyebrow hero-eyebrow">Sign Management / Health & Logs</div>
    <h1>签到管理 - 健康度与日志</h1>
    <p>自动检测百度账号 BDUSS 是否失效，记录签到批次日志。健康度检查每天自动执行一次，也可手动执行。</p>
    <div class="dashboard-hero-meta">
        <span class="hero-tag">正常 <?php echo (int) $healthStats['ok']; ?></span>
        <span class="hero-tag">失效 <?php echo (int) $healthStats['invalid']; ?></span>
        <span class="hero-tag">未知 <?php echo (int) $healthStats['unknown']; ?></span>
    </div>
    <div class="hero-actions">
        <?php if (isset($_GET['run_ok'])) { ?>
        <span class="cs-badge cs-badge-success" role="status">已触发执行：<?php echo htmlspecialchars((string) $_GET['run_ok'], ENT_QUOTES, 'UTF-8'); ?></span>
        <?php } ?>
        <form action="setting.php?mod=admin:sign&run=system_sign" method="post" style="display:inline;">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">
            <button type="submit" class="cs-btn cs-btn-primary cs-btn-sm"><svg class="cs-icon" style="width:12px;height:12px" aria-hidden="true"><use href="#i-check"/></svg> 立即执行全部签到</button>
        </form>
        <form action="setting.php?mod=admin:sign&run=system_sign_retry" method="post" style="display:inline;">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">
            <button type="submit" class="cs-btn cs-btn-secondary cs-btn-sm"><svg class="cs-icon" style="width:12px;height:12px" aria-hidden="true"><use href="#i-refresh"/></svg> 立即复签失败贴吧</button>
        </form>
        <form action="setting.php?mod=admin:sign&run=system_health_check" method="post" style="display:inline;">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">
            <button type="submit" class="cs-btn cs-btn-secondary cs-btn-sm"><svg class="cs-icon" style="width:12px;height:12px" aria-hidden="true"><use href="#i-pulse"/></svg> 手动健康度检查</button>
        </form>
    </div>
</section>

<div class="cs-tabs" id="sign-dashboard-tabs" style="margin:0 1%">
    <ul class="cs-tablist" role="tablist">
        <li><button type="button" class="cs-tab" role="tab" id="tab-dash" aria-controls="panel-dash" aria-selected="<?php echo $action == 'dashboard' ? 'true' : 'false' ?>">健康看板</button></li>
        <li><button type="button" class="cs-tab" role="tab" id="tab-logs" aria-controls="panel-logs" aria-selected="<?php echo $action == 'logs' ? 'true' : 'false' ?>">签到日志</button></li>
    </ul>
</div>

<div class="cs-tabpanel" id="panel-dash" role="tabpanel" aria-labelledby="tab-dash" <?php if ($action != 'dashboard') echo 'hidden' ?>>
    <div class="cs-alert cs-alert-info" role="alert" style="margin:16px 1% 0">
        <svg class="cs-alert-icon" aria-hidden="true"><use href="#i-info"/></svg>
        <div class="cs-alert-body">自动检测百度账号 BDUSS 是否失效。需要立即执行的任务已收在页头右上角。</div>
    </div>

    <section class="dashboard-grid" style="margin:0 1% 24px">
        <div class="cs-chart-card">
            <div class="cs-chart-card-header"><h3 class="cs-chart-card-title">百度账号健康度</h3><span class="cs-chart-card-subtitle">共 <?php echo $healthTotal; ?> 个账号</span></div>
            <div class="cs-chart-container" id="health-pie" style="min-height:240px"></div>
        </div>
        <div class="cs-chart-card">
            <div class="cs-chart-card-header"><h3 class="cs-chart-card-title">健康度摘要</h3></div>
            <div style="display:flex;flex-direction:column;gap:12px;padding:8px 0">
                <span class="cs-stat-pill cs-stat-pill-success"><span class="cs-stat-pill-value"><?php echo $healthStats['ok']; ?></span><span class="cs-stat-pill-label">正常 / OK</span></span>
                <span class="cs-stat-pill cs-stat-pill-danger"><span class="cs-stat-pill-value"><?php echo $healthStats['invalid']; ?></span><span class="cs-stat-pill-label">已失效 / Invalid</span></span>
                <span class="cs-stat-pill"><span class="cs-stat-pill-value"><?php echo $healthStats['unknown']; ?></span><span class="cs-stat-pill-label">未知 / Unknown</span></span>
            </div>
        </div>
    </section>

    <div class="cs-table-wrap" style="margin:0 1%">
        <table class="cs-table">
            <thead><tr><th>账号ID</th><th>名称</th><th>状态</th><th>最后成功</th><th>最后失败</th><th>失败次数</th><th>错误信息</th><th>操作</th></tr></thead>
            <tbody>
<?php
$rows = $m->query("SELECT h.*, IFNULL(b.name, h.name) AS display_name FROM `$health_table` h LEFT JOIN `" . DB_PREFIX . "baiduid` b ON h.pid = b.id ORDER BY h.status ASC, h.fail_count DESC;");
if ($m->num_rows($rows) == 0) {
    echo '<tr><td colspan="8" style="text-align:center;padding:32px;color:var(--color-muted)">尚未执行健康度检查，请先运行手动检查</td></tr>';
} else {
    while ($r = $m->fetch_array($rows)) {
        $status_class = $r['status'] == 'ok' ? 'cs-badge-success' : ($r['status'] == 'invalid' ? 'cs-badge-danger' : '');
        $status_text = $r['status'] == 'ok' ? '正常' : ($r['status'] == 'invalid' ? '已失效' : htmlspecialchars($r['status'], ENT_QUOTES, 'UTF-8'));
        $last_ok = $r['last_ok'] ? date('Y-m-d H:i', $r['last_ok']) : '-';
        $last_fail = $r['last_fail'] ? date('Y-m-d H:i', $r['last_fail']) : '-';
        $restore = $r['status'] == 'invalid' ? '<form action="setting.php?mod=showtb" method="post" style="display:inline"><input type="hidden" name="csrf_token" value="' . htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') . '"><input type="hidden" name="restore_pid" value="' . (int) $r['pid'] . '"><button type="submit" class="cs-btn cs-btn-secondary cs-btn-sm">恢复签到</button></form>' : '-';
        echo '<tr><td>' . $r['pid'] . '</td><td>' . htmlspecialchars($r['display_name'], ENT_QUOTES, 'UTF-8') . '</td><td><span class="cs-badge ' . $status_class . '">' . $status_text . '</span></td><td>' . $last_ok . '</td><td>' . $last_fail . '</td><td>' . intval($r['fail_count']) . '</td><td>' . htmlspecialchars($r['error_msg'], ENT_QUOTES, 'UTF-8') . '</td><td>' . $restore . '</td></tr>';
    }
}
?>
            </tbody>
        </table>
    </div>
</div>

<div class="cs-tabpanel" id="panel-logs" role="tabpanel" aria-labelledby="tab-logs" <?php if ($action != 'logs') echo 'hidden' ?>>
    <div class="cs-table-wrap" style="margin:16px 1%">
        <table class="cs-table" id="sign-log-table">
            <thead><tr><th>ID</th><th>账号</th><th>操作</th><th>状态</th><th>消息</th><th>耗时(ms)</th><th>时间</th></tr></thead>
            <tbody>
<?php
$page = isset($_GET['p']) ? intval($_GET['p']) : 1;
$offset = max(0, ($page - 1) * 50);
$total = $m->once_fetch_array("SELECT COUNT(*) AS c FROM `$log_table`;");
$logs = $m->query("SELECT l.*, IFNULL(b.name, CONCAT('ID:', l.pid)) AS display_name FROM `$log_table` l LEFT JOIN `" . DB_PREFIX . "baiduid` b ON l.pid = b.id ORDER BY l.id DESC LIMIT $offset, 50;");
if ($m->num_rows($logs) == 0) {
    echo '<tr><td colspan="7" style="text-align:center;padding:32px;color:var(--color-muted)">暂无签到日志</td></tr>';
} else {
    while ($r = $m->fetch_array($logs)) {
        $status_class = ($r['status'] == 'success' || $r['status'] == 'ok') ? 'cs-badge-success' : (($r['status'] == 'fail' || $r['status'] == 'error' || $r['status'] == 'invalid') ? 'cs-badge-danger' : '');
        echo '<tr><td>' . $r['id'] . '</td><td>' . htmlspecialchars($r['display_name'], ENT_QUOTES, 'UTF-8') . '</td><td>' . htmlspecialchars($r['action'], ENT_QUOTES, 'UTF-8') . '</td><td><span class="cs-badge ' . $status_class . '">' . htmlspecialchars($r['status'], ENT_QUOTES, 'UTF-8') . '</span></td><td>' . htmlspecialchars(substr($r['message'], 0, 80), ENT_QUOTES, 'UTF-8') . '</td><td>' . intval($r['duration_ms']) . '</td><td>' . date('Y-m-d H:i:s', $r['created_at']) . '</td></tr>';
    }
}
?>
            </tbody>
        </table>
    </div>
    <nav style="margin:16px 1%"><ul class="pagination pagination-sm">
<?php
$total_pages = ceil(intval($total['c']) / 50);
for ($i = 1; $i <= $total_pages && $i <= 20; $i++) {
    $active = $i == $page ? ' class="active"' : '';
    echo '<li' . $active . '><a href="index.php?mod=admin:sign&action=logs&p=' . $i . '">' . $i . '</a></li>';
}
?>
        </ul></nav>
    <p style="margin:0 1%"><a href="index.php?mod=admin:cron" class="cs-btn cs-btn-secondary cs-btn-sm">返回计划任务管理</a></p>
</div>

<?php loadChartAssets(); ?>
<script>
// components.js 为 defer 加载，CS 在解析期内联脚本执行时尚不可用，必须等 DOMContentLoaded
function initSignDashboardUI(){
    if (window.CS && CS.tabs) {
        var tabs = document.getElementById('sign-dashboard-tabs');
        if (tabs) CS.tabs.init(tabs);
    }
    if (typeof echarts === 'undefined' || !window.CS || !CS.chart) return;
    var stats = <?php echo json_encode($healthStats, JSON_UNESCAPED_UNICODE); ?>;
    var pieEl = document.getElementById('health-pie');
    if (pieEl) {
        var data = [
            { name: '正常', value: stats.ok, itemStyle: { color: '#10b981' } },
            { name: '已失效', value: stats.invalid, itemStyle: { color: '#c0554f' } },
            { name: '未知', value: stats.unknown, itemStyle: { color: '#94a3b8' } }
        ].filter(function(d){ return d.value > 0; });
        if (!data.length) {
            pieEl.innerHTML = '<div style="display:flex;align-items:center;justify-content:center;height:240px;color:#94a3b8">暂无健康度数据</div>';
        } else {
            CS.chart.create(pieEl, {
                tooltip: { trigger: 'item', formatter: '{b}<br/>{c} 个 ({d}%)' },
                legend: { bottom: 0, textStyle: { fontSize: 12 } },
                series: [{
                    type: 'pie', radius: ['38%','62%'], center: ['50%','45%'],
                    label: { show: true, formatter: '{b}\n{c}', fontSize: 11 },
                    data: data,
                    itemStyle: { borderRadius: 6, borderColor: '#fff', borderWidth: 2 }
                }]
            });
        }
    }
    // 主题切换由 components.js 全局监听并调用 CS.chart.refreshTheme()
}
if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', initSignDashboardUI); } else { initSignDashboardUI(); }
</script>
