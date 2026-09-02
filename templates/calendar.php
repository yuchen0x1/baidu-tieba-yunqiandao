<?php
if (!defined('SYSTEM_ROOT')) {
    die('Insufficient Permissions');
}
global $m;
require_once SYSTEM_ROOT . '/lib/class.SignManager.php';

$calendarRows = SignManager::calendar(90, UID);
$calendarMap = array();
$totalSuccess = 0;
$totalError = 0;
foreach ($calendarRows as $row) {
    $calendarMap[$row['day']] = $row;
    $totalSuccess += $row['success'];
    $totalError += $row['error'];
}
$start = strtotime('-89 days midnight');
$end = strtotime('today midnight');
$maxTotal = 1;
foreach ($calendarRows as $row) {
    $maxTotal = max($maxTotal, $row['total']);
}
?>
<section class="dashboard-hero">
    <div class="eyebrow hero-eyebrow">Personal Signing History</div>
    <h1>签到日历</h1>
    <p>展示近 90 天的实际贴吧签到结果。历史记录从 V5.12 开始积累，旧版记录不会被回填。</p>
    <div class="dashboard-hero-meta">
        <span class="hero-tag">成功 <?php echo (int) $totalSuccess; ?> 次</span>
        <span class="hero-tag">失败 <?php echo (int) $totalError; ?> 次</span>
        <span class="hero-tag">统计范围：近 90 天</span>
    </div>
</section>

<section class="panel panel-primary">
    <div class="panel-heading"><span class="panel-title">签到活跃度</span></div>
    <div class="panel-body">
        <div class="sign-calendar-legend" aria-label="日历图例">
            <span>无记录</span><i class="calendar-cell level-0"></i><i class="calendar-cell level-1"></i><i class="calendar-cell level-2"></i><i class="calendar-cell level-3"></i><i class="calendar-cell level-4"></i><span>签到次数更多</span>
        </div>
        <div class="sign-calendar-grid" aria-label="近90天签到日历">
            <?php for ($stamp = $start; $stamp <= $end; $stamp += 86400) {
                $day = date('Y-m-d', $stamp);
                $row = isset($calendarMap[$day]) ? $calendarMap[$day] : array('success' => 0, 'error' => 0, 'total' => 0);
                $ratio = $row['total'] > 0 ? $row['total'] / $maxTotal : 0;
                $level = $ratio <= 0 ? 0 : min(4, max(1, (int) ceil($ratio * 4)));
                $title = $day . '：成功 ' . (int) $row['success'] . '，失败 ' . (int) $row['error'] . '，共 ' . (int) $row['total'] . ' 次';
                ?>
                <span class="calendar-cell level-<?php echo $level; ?><?php echo $row['error'] > 0 ? ' has-error' : ''; ?>" title="<?php echo htmlspecialchars($title, ENT_QUOTES, 'UTF-8'); ?>" aria-label="<?php echo htmlspecialchars($title, ENT_QUOTES, 'UTF-8'); ?>"></span>
            <?php } ?>
        </div>
        <p class="text-muted" style="margin-top:14px">红色边框表示当天存在签到失败。将鼠标悬停在格子上可查看每日成功、失败与总次数。</p>
    </div>
</section>

<style>
.sign-calendar-grid { display:grid; grid-template-columns:repeat(18, 14px); gap:5px; align-items:center; overflow-x:auto; padding:8px 0; }
.calendar-cell { display:inline-block; width:14px; height:14px; border-radius:2px; background:#e8edf0; border:1px solid rgba(0,0,0,.04); }
.calendar-cell.level-1 { background:#b7dfc8; }.calendar-cell.level-2 { background:#73c69a; }.calendar-cell.level-3 { background:#34a873; }.calendar-cell.level-4 { background:#16784e; }
.calendar-cell.has-error { border-color:#e05252; box-shadow:0 0 0 1px rgba(224,82,82,.25); }
.sign-calendar-legend { display:flex; gap:6px; align-items:center; flex-wrap:wrap; font-size:12px; color:#68737d; }.sign-calendar-legend .calendar-cell { width:12px; height:12px; }
</style>
