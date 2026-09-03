<?php
if (!defined('SYSTEM_ROOT')) {
    die('Insufficient Permissions');
}
global $m;
require_once SYSTEM_ROOT . '/lib/class.SignManager.php';
SignManager::ensureTables();

$summary = SignManager::signSummary(UID);
$day = date('d');
$accounts = array();
$accRes = $m->query("SELECT b.`id`, b.`name`, b.`portrait`, b.`proxy`,
    IFNULL(h.`status`, 'unknown') AS hstatus, IFNULL(h.`fail_count`, 0) AS hfail,
    h.`last_ok`, h.`error_msg` AS herr
    FROM `" . DB_NAME . "`.`" . DB_PREFIX . "baiduid` b
    LEFT JOIN `" . DB_PREFIX . "baiduid_health` h ON h.`pid` = b.`id`
    WHERE b.`uid` = " . UID . " ORDER BY b.`id` ASC");
while ($row = $m->fetch_array($accRes)) {
    $pid = (int) $row['id'];
    $tb = $m->once_fetch_array("SELECT
        SUM(`no` = 0 AND `latest` = '" . $m->real_escape_string($day) . "' AND `status` = 0) AS signed_cnt,
        SUM(`no` = 0 AND `latest` = '" . $m->real_escape_string($day) . "' AND `status` != 0) AS error_cnt,
        SUM(`no` = 1) AS off_cnt,
        COUNT(*) AS total_cnt
        FROM `" . DB_NAME . "`.`" . DB_PREFIX . TABLE . "` WHERE `pid` = {$pid}");
    $row['signed'] = (int) $tb['signed_cnt'];
    $row['error'] = (int) $tb['error_cnt'];
    $row['off'] = (int) $tb['off_cnt'];
    $row['total'] = (int) $tb['total_cnt'];
    $accounts[] = $row;
}
$week = SignManager::calendar(7, UID);
?>
<section class="dashboard-hero">
    <div class="eyebrow hero-eyebrow">Account Center</div>
    <h1>账号中心</h1>
    <p>集中查看已绑定百度账号的健康状态、贴吧数量与当日签到结果，并支持一键恢复被停用的账号。</p>
    <div class="dashboard-hero-meta">
        <span class="hero-tag">绑定账号 <?php echo count($accounts); ?> 个</span>
        <span class="hero-tag">今日已签 <?php echo (int) $summary['signed']; ?> 个贴吧</span>
        <span class="hero-tag">出错 <?php echo (int) $summary['error']; ?> 个</span>
        <span class="hero-tag">等待 <?php echo (int) $summary['waiting']; ?> 个</span>
    </div>
</section>

<section class="panel panel-primary">
    <div class="panel-heading"><span class="panel-title">账号概览</span></div>
    <?php if (empty($accounts)) { ?>
    <div class="panel-body">
        <div class="alert alert-warning" role="alert">还没有绑定百度账号，请先前往 <a href="index.php?mod=baiduid">百度账号</a> 页绑定。</div>
    </div>
    <?php } else { ?>
    <div class="table-responsive">
        <table class="table table-hover table-bordered table-striped">
            <thead><tr>
                <th>账号</th><th>PID</th><th>健康状态</th><th>连续失败</th>
                <th>贴吧数</th><th>今日已签</th><th>今日出错</th><th>已忽略</th><th>代理</th><th>操作</th>
            </tr></thead>
            <tbody>
            <?php foreach ($accounts as $acc) {
                $hLabel = $acc['hstatus'] == 'ok' ? 'label-success' : ($acc['hstatus'] == 'invalid' ? 'label-danger' : 'label-default');
                $hText = $acc['hstatus'] == 'ok' ? '正常' : ($acc['hstatus'] == 'invalid' ? '已失效' : '未知');
                $portrait = htmlspecialchars($acc['portrait'], ENT_QUOTES, 'UTF-8');
                $name = $acc['name'] !== '' ? htmlspecialchars($acc['name'], ENT_QUOTES, 'UTF-8') : '<span class="text-muted">未记录</span>';
                $proxy = $acc['proxy'] !== '' ? htmlspecialchars($acc['proxy'], ENT_QUOTES, 'UTF-8') : '<span class="text-muted">跟随全局</span>';
                $restore = $acc['hstatus'] == 'invalid' ? '<form action="setting.php?mod=showtb" method="post" style="display:inline"><input type="hidden" name="csrf_token" value="' . htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') . '"><input type="hidden" name="restore_pid" value="' . $acc['id'] . '"><button type="submit" class="btn btn-default btn-xs">恢复签到</button></form>' : '-';
                echo '<tr>'
                    . '<td>' . ($portrait !== '' ? '<img src="https://himg.bdimg.com/sys/portrait/item/' . $portrait . '" width="24" height="24" style="border-radius:50%;vertical-align:middle" alt=""> ' : '') . $name . '</td>'
                    . '<td>' . $acc['id'] . '</td>'
                    . '<td><span class="label ' . $hLabel . '">' . $hText . '</span></td>'
                    . '<td>' . (int) $acc['hfail'] . '</td>'
                    . '<td>' . $acc['total'] . '</td>'
                    . '<td><font color="green">' . $acc['signed'] . '</font></td>'
                    . '<td>' . ($acc['error'] > 0 ? '<font color="red">' . $acc['error'] . '</font>' : '0') . '</td>'
                    . '<td>' . $acc['off'] . '</td>'
                    . '<td style="max-width:180px;word-break:break-all">' . $proxy . '</td>'
                    . '<td>' . $restore . '</td>'
                    . '</tr>';
            } ?>
            </tbody>
        </table>
    </div>
    <div class="panel-body text-muted">
        需要绑定新账号请前往 <a href="index.php?mod=baiduid">百度账号</a>；健康检查每天自动执行一次，失效账号会在达到阈值后自动停用（可在<a href="index.php?mod=admin:set:sign">签到设置</a>配置）。
    </div>
    <?php } ?>
</section>

<section class="panel panel-info">
    <div class="panel-heading"><span class="panel-title">近 7 天签到结果（按天）</span></div>
    <?php if (empty($week)) { ?>
    <div class="panel-body text-muted">暂无历史记录。签到历史自 V5.12 起开始积累，也可在<a href="index.php?mod=calendar">签到日历</a>查看完整视图。</div>
    <?php } else { ?>
    <ul class="list-group">
        <?php foreach ($week as $w) { ?>
        <li class="list-group-item">
            <?php echo htmlspecialchars($w['day'], ENT_QUOTES, 'UTF-8'); ?> ——
            成功 <strong><?php echo (int) $w['success']; ?></strong>，
            失败 <strong style="color:<?php echo $w['error'] > 0 ? '#e05252' : 'inherit'; ?>"><?php echo (int) $w['error']; ?></strong>，
            共 <?php echo (int) $w['total']; ?> 次
        </li>
        <?php } ?>
    </ul>
    <?php } ?>
</section>
