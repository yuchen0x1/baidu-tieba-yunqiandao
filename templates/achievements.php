<?php
if (!defined('SYSTEM_ROOT')) {
    die('Insufficient Permissions');
}
if (ROLE === 'visitor' || ROLE === 'banned') {
    msg('请先登录');
}
require_once SYSTEM_ROOT . '/lib/class.Achievement.php';

// 管理员控制模式下可查看任意用户；否则只查自己
$viewUid = (ISADMIN && defined('CON_UID') && CON_UID > 0) ? (int) CON_UID : (int) UID;

// 「刷新成就」按钮触发即时检查（仅本人可触发）
$justChecked = false;
$justUnlocked = array();
if (isset($_GET['refresh']) && $viewUid === (int) UID) {
    $justChecked = true;
    $justUnlocked = Achievement::checkAndUnlock($viewUid);
}

$items = Achievement::progress($viewUid);
$summary = Achievement::summary($viewUid);

// 按 tier 排序：platinum > gold > silver > bronze；已解锁在前
$tierOrder = array(
    Achievement::TIER_PLATINUM => 4,
    Achievement::TIER_GOLD     => 3,
    Achievement::TIER_SILVER   => 2,
    Achievement::TIER_BRONZE   => 1,
);
usort($items, function ($a, $b) use ($tierOrder) {
    if ($a['unlocked'] !== $b['unlocked']) {
        return $a['unlocked'] ? -1 : 1;
    }
    $ta = isset($tierOrder[$a['tier']]) ? $tierOrder[$a['tier']] : 0;
    $tb = isset($tierOrder[$b['tier']]) ? $tierOrder[$b['tier']] : 0;
    if ($ta !== $tb) {
        return $tb - $ta;
    }
    return $a['threshold'] <=> $b['threshold'];
});

$tierLabels = array(
    Achievement::TIER_BRONZE   => '青铜',
    Achievement::TIER_SILVER   => '白银',
    Achievement::TIER_GOLD     => '黄金',
    Achievement::TIER_PLATINUM => '铂金',
);

$metricLabels = array(
    'max_streak'         => '最长连签',
    'current_streak'     => '当前连签',
    'total_success'      => '总成功签到',
    'total_signed_days'  => '签到天数',
    'max_streak_30d'     => '近 30 天连签',
);
?>
<section class="dashboard-hero">
    <div class="eyebrow hero-eyebrow">Achievements / <?php echo date('Y-m-d'); ?></div>
    <h1>成就勋章</h1>
    <p>通过持续签到解锁成就。每枚勋章代表一个签到里程碑，达成条件后自动解锁。</p>
    <div class="dashboard-hero-meta">
        <span class="hero-tag">已解锁 <?php echo (int) $summary['unlocked']; ?> / <?php echo (int) $summary['total']; ?></span>
        <span class="hero-tag">完成度 <?php echo (int) $summary['progress_pct']; ?>%</span>
        <?php if (!empty($summary['latest'])) { ?>
            <span class="hero-tag">最近解锁：<?php echo htmlspecialchars($summary['latest']['name'], ENT_QUOTES, 'UTF-8'); ?></span>
        <?php } ?>
    </div>
</section>

<?php if ($justChecked) { ?>
<div class="cs-toast-container" role="status" aria-live="polite">
    <?php if (empty($justUnlocked)) { ?>
        <div class="cs-toast cs-toast-info">已检查，暂无新成就解锁。继续签到即可解锁下一枚勋章。</div>
    <?php } else { ?>
        <div class="cs-toast cs-toast-success">恭喜！本次解锁 <?php echo count($justUnlocked); ?> 枚新成就。</div>
    <?php } ?>
</div>
<?php } ?>

<section class="metric-grid" aria-label="成就总览" style="margin:16px 1%">
    <div class="metric-card">
        <span class="metric-label">总成就</span>
        <strong class="metric-value"><?php echo (int) $summary['total']; ?></strong>
        <span class="metric-note">系统已定义勋章数</span>
    </div>
    <div class="metric-card">
        <span class="metric-label">已解锁</span>
        <strong class="metric-value"><?php echo (int) $summary['unlocked']; ?></strong>
        <span class="metric-note">解锁率 <?php echo (int) $summary['progress_pct']; ?>%</span>
    </div>
    <div class="metric-card">
        <span class="metric-label">待解锁</span>
        <strong class="metric-value"><?php echo (int) $summary['locked']; ?></strong>
        <span class="metric-note">继续签到即可推进</span>
    </div>
</section>

<?php if ($viewUid === (int) UID) { ?>
<form method="get" action="index.php" style="margin:8px 1%">
    <input type="hidden" name="mod" value="achievements">
    <button type="submit" name="refresh" value="1" class="cs-btn cs-btn-primary cs-btn-sm">
        <svg class="icon-inline" aria-hidden="true" style="width:16px;height:16px;vertical-align:-2px;margin-right:4px"><use href="#i-refresh"/></svg>
        刷新成就状态
    </button>
</form>
<?php } ?>

<div class="cs-achievement-grid" style="margin:16px 1%">
<?php foreach ($items as $it) {
    $tierClass = 'tier-' . $it['tier'];
    $stateClass = $it['unlocked'] ? 'cs-unlocked' : 'cs-locked';
    $metricLabel = isset($metricLabels[$it['metric']]) ? $metricLabels[$it['metric']] : $it['metric'];
    ?>
    <article class="cs-achievement-card <?php echo $tierClass; ?> <?php echo $stateClass; ?>">
        <header class="cs-achievement-header">
            <span class="cs-achievement-icon">
                <svg aria-hidden="true"><use href="#<?php echo htmlspecialchars($it['icon'], ENT_QUOTES, 'UTF-8'); ?>"/></svg>
            </span>
            <div class="cs-achievement-meta">
                <span class="cs-achievement-tier <?php echo $tierClass; ?>"><?php echo htmlspecialchars($tierLabels[$it['tier']], ENT_QUOTES, 'UTF-8'); ?></span>
                <?php if ($it['unlocked']) { ?>
                    <span class="cs-achievement-status cs-achievement-status-unlocked">已解锁</span>
                <?php } else { ?>
                    <span class="cs-achievement-status cs-achievement-status-locked">未解锁</span>
                <?php } ?>
            </div>
        </header>
        <h3 class="cs-achievement-title"><?php echo htmlspecialchars($it['name'], ENT_QUOTES, 'UTF-8'); ?></h3>
        <p class="cs-achievement-desc"><?php echo htmlspecialchars($it['desc'], ENT_QUOTES, 'UTF-8'); ?></p>
        <div class="cs-achievement-progress">
            <div class="cs-achievement-progress-bar" style="width:<?php echo (int) $it['progress']; ?>%"></div>
        </div>
        <div class="cs-achievement-progress-text">
            <span><?php echo $metricLabel; ?>：<strong><?php echo (int) $it['current']; ?></strong> / <?php echo (int) $it['threshold']; ?></span>
            <span><?php echo (int) $it['progress']; ?>%</span>
        </div>
        <?php if ($it['unlocked'] && $it['unlocked_at'] > 0) { ?>
            <footer class="cs-achievement-date">解锁于 <?php echo htmlspecialchars(date('Y-m-d H:i', $it['unlocked_at']), ENT_QUOTES, 'UTF-8'); ?></footer>
        <?php } ?>
    </article>
<?php } ?>
</div>
