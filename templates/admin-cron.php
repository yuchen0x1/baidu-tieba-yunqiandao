<?php
if (!defined('SYSTEM_ROOT')) { die('Insufficient Permissions'); }
if (ROLE !== 'admin') { msg('权限不足!'); }
global $i;
$cronEscape = static function ($v) { return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8'); };
$cronTasks = $i['cron'] ?? array();
$cronNow = time();
$cronEnabled = count(array_filter($cronTasks, array('cron', 'isEnabled')));
$cronFailed = count(array_filter($cronTasks, static function ($row) { return ($row['last_status'] ?? '') === 'error'; }));
$historyName = substr(request_scalar_value($_GET, 'task'), 0, 40);
$historyStatus = request_scalar_value($_GET, 'status');
if (!in_array($historyStatus, array('success', 'error', 'skipped', 'running'), true)) { $historyStatus = ''; }
$historyDays = (int) request_scalar_value($_GET, 'days', '7');
if (!in_array($historyDays, array(0, 1, 7, 30), true)) { $historyDays = 7; }
$history = cron::runHistory(array('name' => $historyName, 'status' => $historyStatus, 'since' => $historyDays ? $cronNow - $historyDays * 86400 : 0), (int) request_scalar_value($_GET, 'page', '1'));
$historyLink = static function ($page) use ($historyName, $historyStatus, $historyDays) {
    return 'index.php?' . http_build_query(array('mod' => 'admin:cron', 'task' => $historyName, 'status' => $historyStatus, 'days' => $historyDays, 'page' => $page)) . '#cron-history';
};
$cronLabels = array('success' => '成功', 'error' => '失败', 'skipped' => '跳过', 'running' => '开始执行');
$cronClasses = array('success' => 'success', 'error' => 'danger', 'skipped' => 'neutral', 'running' => 'info');
$cronDuration = static function ($ms) { return (int) $ms >= 1000 ? number_format($ms / 1000, 2) . ' 秒' : (int) $ms . ' 毫秒'; };
?>
<link rel="stylesheet" href="source/css/cron.css?v=<?php echo SYSTEM_VER; ?>">
<section class="dashboard-hero">
    <h2>调度概览</h2>
    <p>查看自动签到与维护任务的执行状态，定位失败并安排重试。需每分钟触发 do.php，或启动常驻调度。</p>
    <div class="dashboard-hero-meta"><span class="hero-tag">共 <?php echo count($cronTasks); ?> 项</span><span class="hero-tag">已启用 <?php echo $cronEnabled; ?> 项</span><span class="hero-tag">最近失败 <?php echo $cronFailed; ?> 项</span></div>
    <div class="hero-actions">
        <a href="index.php?mod=admin:editcron" class="cs-btn cs-btn-primary cs-btn-sm">添加任务</a>
        <button type="submit" form="cron-action-form" formaction="setting.php?mod=admin:cron&amp;runall" class="cs-btn cs-btn-secondary cs-btn-sm">运行到期任务</button>
        <a href="index.php?mod=admin:cron" class="cs-btn cs-btn-secondary cs-btn-sm">刷新状态</a>
    </div>
</section>
<?php if (isset($_GET['run_failed'])) { ?><div class="alert alert-warning" role="alert">任务未执行成功，可能正在运行或发生异常。请查看运行历史；重试不会中断正在执行的任务。</div>
<?php } elseif (isset($_GET['ok'])) { ?><div class="alert alert-success" role="status">计划任务操作已完成。</div><?php } ?>
<form id="cron-action-form" action="setting.php?mod=admin:cron" method="post"><input type="hidden" name="csrf_token" value="<?php echo $cronEscape(csrf_token()); ?>"></form>
<div class="cron-toolbar">
    <label for="cron-search">查找任务<input id="cron-search" type="search" class="form-control" placeholder="任务名、描述或文件"></label>
    <label for="cron-filter">任务状态<select id="cron-filter" class="form-control"><option value="all">全部任务</option><option value="enabled">已启用</option><option value="disabled">已禁用</option><option value="error">最近失败</option><option value="running">运行中</option><option value="due">已到期</option></select></label>
    <span id="cron-result-count" role="status" aria-live="polite"><?php echo count($cronTasks); ?> 项任务</span>
</div>
<form action="setting.php?mod=admin:cron&amp;xorder" method="post" id="cron-order-form">
<input type="hidden" name="csrf_token" value="<?php echo $cronEscape(csrf_token()); ?>">
<div class="table-responsive"><table class="cs-table cron-table">
<thead><tr><th scope="col">任务与说明</th><th scope="col">执行情况</th><th scope="col">操作与顺序</th></tr></thead>
<tbody id="cron-task-list">
<?php foreach ($cronTasks as $cs) {
    $enabled = cron::isEnabled($cs); $running = !empty($cs['running']); $lastStatus = $cs['last_status'] ?? '';
    $due = $enabled && ((int) $cs['freq'] === -1 || (int) ($cs['nextrun'] ?? 0) <= $cronNow);
    $taskUrl = 'setting.php?mod=admin:cron&';
?>
<tr data-cron-task data-enabled="<?php echo (int) $enabled; ?>" data-running="<?php echo (int) $running; ?>" data-status="<?php echo $cronEscape($lastStatus); ?>" data-due="<?php echo (int) $due; ?>">
<td class="cron-description"><strong><?php echo $cronEscape($cs['name']); ?></strong><div class="cron-file"><?php echo $cronEscape($cs['file']); ?></div><p><?php echo nl2br($cronEscape($cs['desc'] ?? '')); ?></p></td>
<td><span class="cs-badge cs-badge-<?php echo $enabled ? 'success' : 'neutral'; ?>"><?php echo $enabled ? '已启用' : '已禁用'; ?></span>
<?php if ($running) { ?><span class="cs-badge cs-badge-info">运行中</span><?php } elseif ($lastStatus) { ?><span class="cs-badge cs-badge-<?php echo $cronClasses[$lastStatus] ?? 'neutral'; ?>">上次<?php echo $cronLabels[$lastStatus] ?? $cronEscape($lastStatus); ?></span><?php } ?>
<dl class="cron-details">
<dt>执行间隔</dt><dd><?php echo (int) $cs['freq'] === -1 ? '一次性任务' : ((int) $cs['freq'] === 0 ? '每轮调度' : (int) $cs['freq'] . ' 秒'); ?></dd>
<dt>上次执行</dt><dd><?php echo empty($cs['lastdo']) ? '从未运行' : date('Y-m-d H:i:s', (int) $cs['lastdo']); ?></dd>
<dt>下次执行</dt><dd><?php echo !$enabled ? '已暂停' : ($running ? '等待当前执行结束' : ($due ? '下一轮调度' : date('Y-m-d H:i:s', (int) $cs['nextrun']))); ?></dd>
<dt>上次耗时</dt><dd><?php echo $cronDuration($cs['last_duration'] ?? 0); ?></dd>
<dt>重试进度</dt><dd><?php echo (int) ($cs['retry'] ?? 0); ?> / <?php echo (int) ($cs['max_retry'] ?? option::get('cron_max_retry')); ?></dd>
</dl></td>
<td><div class="cron-actions">
<button type="submit" form="cron-action-form" formaction="<?php echo $cronEscape($taskUrl . 'run=' . urlencode($cs['name'])); ?>" class="cs-btn cs-btn-secondary cs-btn-sm" <?php echo $running ? 'disabled' : ''; ?> aria-label="<?php echo $cronEscape('运行 ' . $cs['name']); ?>">运行</button>
<?php if ($lastStatus === 'error' || !empty($cs['retry'])) { ?><button type="submit" form="cron-action-form" formaction="<?php echo $cronEscape($taskUrl . 'retry=' . urlencode($cs['name'])); ?>" class="cs-btn cs-btn-secondary cs-btn-sm" <?php echo $running ? 'disabled' : ''; ?>>重试</button><?php } ?>
<button type="submit" form="cron-action-form" formaction="<?php echo $cronEscape($taskUrl . ($enabled ? 'dis=' : 'act=') . urlencode($cs['name'])); ?>" class="cs-btn cs-btn-ghost cs-btn-sm"><?php echo $enabled ? '禁用' : '启用'; ?></button>
<a class="cs-btn cs-btn-ghost cs-btn-sm" href="index.php?mod=admin:editcron&amp;edit=<?php echo urlencode($cs['name']); ?>">编辑</a>
<button type="submit" form="cron-action-form" formaction="<?php echo $cronEscape($taskUrl . 'uninst=' . urlencode($cs['name'])); ?>" class="cs-btn cs-btn-ghost cs-btn-sm cs-text-danger" data-confirm="卸载后此任务将停止调度，已有运行历史会保留。确认卸载？" <?php echo $running ? 'disabled' : ''; ?>>卸载</button>
<a class="cs-btn cs-btn-ghost cs-btn-sm" href="<?php echo $cronEscape('index.php?mod=admin:cron&task=' . urlencode($cs['name']) . '#cron-history'); ?>">运行历史</a>
</div><label class="cron-order-label">运行顺序 <input type="number" name="order[<?php echo $cronEscape($cs['name']); ?>]" value="<?php echo (int) $cs['orde']; ?>" class="form-control cron-order-input" required></label>
<?php if (!empty($cs['log'])) { ?><details class="cron-log"><summary>最近日志</summary><pre><?php echo $cronEscape($cs['log']); ?></pre></details><?php } ?></td></tr>
<?php } ?>
</tbody></table></div>
<p id="cron-empty" class="cron-empty" <?php echo $cronTasks ? 'hidden' : ''; ?>>没有符合条件的任务。可调整搜索条件，或添加新任务。</p>
<?php if ($cronTasks) { ?><button type="submit" class="cs-btn cs-btn-primary cs-btn-sm">保存运行顺序</button><span class="cron-hint">可拖动任务行，或输入数字后保存。</span><?php } ?>
</form>
<section id="cron-history" class="cron-history" aria-labelledby="cron-history-title">
<h2 id="cron-history-title">运行历史</h2><p class="cron-hint">“开始执行”是启动事件，结束结果另记一条。当前是否运行请以上方列表为准。</p>
<form method="get" action="index.php#cron-history" class="cron-toolbar">
<input type="hidden" name="mod" value="admin:cron">
<label for="history-task">任务名<input id="history-task" name="task" class="form-control" value="<?php echo $cronEscape($historyName); ?>" maxlength="40" placeholder="全部任务" list="cron-task-names"></label>
<datalist id="cron-task-names"><?php foreach ($cronTasks as $cs) { ?><option value="<?php echo $cronEscape($cs['name']); ?>"><?php } ?></datalist>
<label for="history-status">结果<select id="history-status" name="status" class="form-control"><option value="">全部结果</option><?php foreach ($cronLabels as $value => $label) { ?><option value="<?php echo $value; ?>" <?php echo $historyStatus === $value ? 'selected' : ''; ?>><?php echo $label; ?></option><?php } ?></select></label>
<label for="history-days">时间<select id="history-days" name="days" class="form-control"><?php foreach (array(1 => '最近一天', 7 => '最近七天', 30 => '最近三十天', 0 => '全部时间') as $value => $label) { ?><option value="<?php echo $value; ?>" <?php echo $historyDays === $value ? 'selected' : ''; ?>><?php echo $label; ?></option><?php } ?></select></label>
<button type="submit" class="cs-btn cs-btn-secondary cs-btn-sm">查询历史</button></form>
<p class="cron-mobile-hint">左右滑动表格可查看完整时间与日志。</p>
<div class="table-responsive" role="region" aria-label="任务运行历史表格，可横向滚动" tabindex="0"><table class="cs-table cron-history-table"><thead><tr><th scope="col">任务名</th><th scope="col">结果</th><th scope="col">开始 / 结束</th><th scope="col">耗时</th><th scope="col">日志</th></tr></thead><tbody>
<?php foreach ($history['rows'] as $run) { ?><tr><td><?php echo $cronEscape($run['name']); ?></td><td><span class="cs-badge cs-badge-<?php echo $cronClasses[$run['status']] ?? 'neutral'; ?>"><?php echo $cronLabels[$run['status']] ?? $cronEscape($run['status']); ?></span></td>
<td><?php echo empty($run['started_at']) ? '旧记录未保存开始时间' : date('Y-m-d H:i:s', (int) $run['started_at']); ?><br><span class="cron-hint"><?php echo date('Y-m-d H:i:s', (int) $run['finished_at']); ?></span></td><td><?php echo $cronDuration($run['duration_ms']); ?></td><td class="cron-history-message"><?php echo $cronEscape($run['message']); ?></td></tr><?php } ?>
<?php if (!$history['rows']) { ?><tr><td colspan="5" class="cron-empty">暂无符合条件的记录。可扩大时间范围或清空任务名后重试。</td></tr><?php } ?>
</tbody></table></div>
<nav class="cron-pagination" aria-label="运行历史分页"><span role="status">共 <?php echo $history['total']; ?> 条 · 第 <?php echo $history['page']; ?> / <?php echo $history['pages']; ?> 页</span>
<?php if ($history['page'] > 1) { ?><a class="cs-btn cs-btn-secondary cs-btn-sm" href="<?php echo $cronEscape($historyLink($history['page'] - 1)); ?>">上一页</a><?php } ?>
<?php if ($history['page'] < $history['pages']) { ?><a class="cs-btn cs-btn-secondary cs-btn-sm" href="<?php echo $cronEscape($historyLink($history['page'] + 1)); ?>">下一页</a><?php } ?>
</nav></section>
<script src="source/js/cron.js?v=<?php echo SYSTEM_VER; ?>" defer></script>
