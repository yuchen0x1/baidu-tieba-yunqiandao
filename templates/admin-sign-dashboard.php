<?php if (!defined('SYSTEM_ROOT')) { die('Insufficient Permissions'); } if (ROLE != 'admin') { msg('权限不足!'); }
global $m;
require_once SYSTEM_ROOT . '/lib/class.SignManager.php';
$action = isset($_GET['action']) ? $_GET['action'] : 'dashboard';
$health_table = DB_PREFIX . SignManager::HEALTH_TABLE;
$log_table = DB_PREFIX . SignManager::LOG_TABLE;
?>
<div class="panel panel-primary" style="margin:1%">
    <div class="panel-heading"><h3 class="panel-title">签到管理 - 健康度与日志</h3></div>
    <div class="panel-body">
        <ul class="nav nav-tabs">
            <li class="<?php echo $action == 'dashboard' ? 'active' : '' ?>"><a href="index.php?mod=admin:sign&action=dashboard">健康看板</a></li>
            <li class="<?php echo $action == 'logs' ? 'active' : '' ?>"><a href="index.php?mod=admin:sign&action=logs">签到日志</a></li>
        </ul>
        <br/>
<?php if ($action == 'dashboard'): ?>
        <div class="alert alert-info">自动检测百度账号 BDUSS 是否失效。健康度检查每天自动执行一次，也可以手动执行。</div>
        <p>
        <form action="do.php?mod=runcron&cron=system_sign_retry" method="post" target="_blank" style="display:inline;"><input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8'); ?>"><input type="hidden" name="pw" value="<?php echo htmlspecialchars(option::get('cron_pw'), ENT_QUOTES, 'UTF-8'); ?>"><button type="submit" class="btn btn-warning btn-sm">立即复签失败贴吧</button></form>
        &nbsp;
        <form action="do.php?mod=runcron&cron=system_sign" method="post" target="_blank" style="display:inline;"><input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8'); ?>"><input type="hidden" name="pw" value="<?php echo htmlspecialchars(option::get('cron_pw'), ENT_QUOTES, 'UTF-8'); ?>"><button type="submit" class="btn btn-success btn-sm">立即执行全部签到</button></form>
        &nbsp;
        </p>
        <p><form action="do.php?mod=runcron&cron=system_health_check" method="post" target="_blank" style="display:inline;"><input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8'); ?>"><input type="hidden" name="pw" value="<?php echo htmlspecialchars(option::get('cron_pw'), ENT_QUOTES, 'UTF-8'); ?>"><button type="submit" class="btn btn-primary btn-sm">手动执行健康度检查</button></form></p>
        <div class="table-responsive">
        <table class="table table-hover table-bordered table-striped">
            <thead><tr><th>账号ID</th><th>名称</th><th>状态</th><th>最后成功</th><th>最后失败</th><th>失败次数</th><th>错误信息</th></tr></thead>
            <tbody>
<?php
$rows = $m->query("SELECT h.*, IFNULL(b.name, h.name) AS display_name FROM `$health_table` h LEFT JOIN `" . DB_PREFIX . "baiduid` b ON h.pid = b.id ORDER BY h.status ASC, h.fail_count DESC;");
if ($m->num_rows($rows) == 0) {
    echo '<tr><td colspan="7" class="text-center">尚未执行健康度检查，请先运行手动检查</td></tr>';
} else {
    while ($r = $m->fetch_array($rows)) {
        $status_label = $r['status'] == 'ok' ? 'label-success' : ($r['status'] == 'invalid' ? 'label-danger' : 'label-default');
        $status_text = $r['status'] == 'ok' ? '正常' : ($r['status'] == 'invalid' ? '已失效' : $r['status']);
        $last_ok = $r['last_ok'] ? date('Y-m-d H:i', $r['last_ok']) : '-';
        $last_fail = $r['last_fail'] ? date('Y-m-d H:i', $r['last_fail']) : '-';
        echo '<tr><td>' . $r['pid'] . '</td><td>' . htmlspecialchars($r['display_name'], ENT_QUOTES, 'UTF-8') . '</td><td><span class="label ' . $status_label . '">' . htmlspecialchars($status_text, ENT_QUOTES, 'UTF-8') . '</span></td><td>' . $last_ok . '</td><td>' . $last_fail . '</td><td>' . intval($r['fail_count']) . '</td><td>' . htmlspecialchars($r['error_msg'], ENT_QUOTES, 'UTF-8') . '</td></tr>';
    }
}
?>
            </tbody>
        </table>
        </div>
<?php elseif ($action == 'logs'): ?>
        <div class="table-responsive">
        <table class="table table-hover table-bordered table-striped">
            <thead><tr><th>ID</th><th>账号</th><th>操作</th><th>状态</th><th>消息</th><th>耗时(ms)</th><th>时间</th></tr></thead>
            <tbody>
<?php
$page = isset($_GET['p']) ? intval($_GET['p']) : 1;
$offset = max(0, ($page - 1) * 50);
$total = $m->once_fetch_array("SELECT COUNT(*) AS c FROM `$log_table`;");
$logs = $m->query("SELECT l.*, IFNULL(b.name, CONCAT('ID:', l.pid)) AS display_name FROM `$log_table` l LEFT JOIN `" . DB_PREFIX . "baiduid` b ON l.pid = b.id ORDER BY l.id DESC LIMIT $offset, 50;");
if ($m->num_rows($logs) == 0) {
    echo '<tr><td colspan="7" class="text-center">暂无签到日志</td></tr>';
} else {
    while ($r = $m->fetch_array($logs)) {
        $status_label = $r['status'] == 'success' || $r['status'] == 'ok' ? 'label-success' : ($r['status'] == 'fail' || $r['status'] == 'error' || $r['status'] == 'invalid' ? 'label-danger' : 'label-info');
        echo '<tr><td>' . $r['id'] . '</td><td>' . htmlspecialchars($r['display_name'], ENT_QUOTES, 'UTF-8') . '</td><td>' . htmlspecialchars($r['action'], ENT_QUOTES, 'UTF-8') . '</td><td><span class="label ' . $status_label . '">' . htmlspecialchars($r['status'], ENT_QUOTES, 'UTF-8') . '</span></td><td>' . htmlspecialchars(substr($r['message'], 0, 80), ENT_QUOTES, 'UTF-8') . '</td><td>' . intval($r['duration_ms']) . '</td><td>' . date('Y-m-d H:i:s', $r['created_at']) . '</td></tr>';
    }
}
?>
            </tbody>
        </table>
        </div>
        <nav><ul class="pagination pagination-sm">
<?php
$total_pages = ceil(intval($total['c']) / 50);
for ($i = 1; $i <= $total_pages && $i <= 20; $i++) {
    $active = $i == $page ? ' class="active"' : '';
    echo '<li' . $active . '><a href="index.php?mod=admin:sign&action=logs&p=' . $i . '">' . $i . '</a></li>';
}
?>
        </ul></nav>
        <p><a href="setting.php?mod=admin:cron" class="btn btn-default btn-sm">返回计划任务管理</a></p>
<?php endif; ?>
    </div>
</div>
