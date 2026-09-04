<?php

if (!defined('SYSTEM_ROOT')) {
    die('Insufficient Permissions');
}
/**
 * 用户侧帖子监控设置页（index.php?plugin=ver4_monitor）
 * 每个用户配置自己的目标贴吧与关键词，系统定时扫描新帖并推送命中提醒。
 */
if (!defined('UID')) {
    ReDirect('index.php?mod=login');
    die;
}
loadhead('帖子监控');
global $m;
$uid = UID;

$bdRow = $m->once_fetch_array("SELECT COUNT(*) AS c FROM `" . DB_PREFIX . "baiduid` WHERE `uid` = {$uid}");
if ((int) $bdRow['c'] < 1) {
    echo '<div class="page-heading"><div><div class="eyebrow">THREAD MONITOR</div><h1>帖子监控</h1></div></div>';
    echo '<div class="alert alert-warning">您还没有绑定百度账号，请先前往 <a href="index.php?mod=baiduid">百度账号</a> 页绑定后使用本功能（扫描需要借助账号身份访问贴吧接口）。</div>';
    loadfoot();
    die;
}

if (isset($_POST['ver4_monitor_submit'])) {
    csrf(true, true);
    ver4_monitor_uopt_set($uid, 'ver4_monitor_enable', $_POST['ver4_monitor_enable'] == '1' ? '1' : '0');
    ver4_monitor_uopt_set($uid, 'ver4_monitor_kws', trim((string) $_POST['ver4_monitor_kws']));
    ver4_monitor_uopt_set($uid, 'ver4_monitor_words', trim((string) $_POST['ver4_monitor_words']));
    ver4_monitor_uopt_set($uid, 'ver4_monitor_exclude', trim((string) $_POST['ver4_monitor_exclude']));
    ver4_monitor_uopt_set($uid, 'ver4_monitor_notify', $_POST['ver4_monitor_notify'] == '1' ? '1' : '0');
    redirect('index.php?plugin=ver4_monitor&success=' . urlencode('帖子监控设置已保存'));
}

$g = function ($key, $default = '') use ($uid) {
    $v = option::uget($key, $uid);
    return ($v === null || $v === '') ? $default : $v;
};
$monEnable = $g('ver4_monitor_enable', '0');
$monKws = htmlspecialchars($g('ver4_monitor_kws'), ENT_QUOTES, 'UTF-8');
$monWords = htmlspecialchars($g('ver4_monitor_words'), ENT_QUOTES, 'UTF-8');
$monExclude = htmlspecialchars($g('ver4_monitor_exclude'), ENT_QUOTES, 'UTF-8');
$monNotify = $g('ver4_monitor_notify', '1');
$notifyEnabled = option::get('notify_enable') == '1';

//最近命中记录
$recentRows = array();
$logRs = $m->query("SELECT `forum`, `kw`, `tid`, `title`, `created_at` FROM `" . DB_PREFIX . "ver4_monitor_hit` WHERE `uid` = {$uid} ORDER BY `id` DESC LIMIT 12");
while ($r = $m->fetch_array($logRs)) {
    $recentRows[] = $r;
}
?>
<div class="page-heading"><div><div class="eyebrow">THREAD MONITOR</div><h1>帖子监控</h1></div></div>
<p class="text-muted">定时扫描目标贴吧的最新主题帖，标题命中关键词立即推送提醒（支持监控全部新帖）。命中记录保留在本页，方便随时回看。</p>
<?php if (isset($_GET['success'])): ?>
<div class="alert alert-success"><?php echo htmlspecialchars((string) $_GET['success'], ENT_QUOTES, 'UTF-8'); ?></div>
<?php endif; ?>
<form method="post" action="index.php?plugin=ver4_monitor">
    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">
    <table class="table table-striped">
        <tr>
            <td style="width:35%"><b>监控开关</b></td>
            <td>
                <select name="ver4_monitor_enable" class="form-control" style="width:140px">
                    <option value="0" <?php echo $monEnable != '1' ? 'selected' : ''; ?>>关闭</option>
                    <option value="1" <?php echo $monEnable == '1' ? 'selected' : ''; ?>>开启</option>
                </select>
            </td>
        </tr>
        <tr>
            <td><b>目标贴吧</b><br/><br/>每行一个贴吧名（不带"吧"字）<br/>建议不超过 5 个，扫描越少越快</td>
            <td><textarea class="form-control" name="ver4_monitor_kws" style="height:110px" placeholder="例如：&#10;来日易在心中"><?php echo $monKws; ?></textarea></td>
        </tr>
        <tr>
            <td><b>监控关键词</b><br/><br/>每行一个，标题包含任一关键词即命中<br/>留空表示监控全部新帖</td>
            <td><textarea class="form-control" name="ver4_monitor_words" style="height:110px" placeholder="例如：&#10;求助&#10;抽奖&#10;福利"><?php echo $monWords; ?></textarea></td>
        </tr>
        <tr>
            <td><b>排除关键词</b><br/><br/>每行一个，标题包含任一排除词则忽略<br/>即使命中了监控关键词也会被过滤</td>
            <td><textarea class="form-control" name="ver4_monitor_exclude" style="height:90px" placeholder="例如：&#10;广告&#10;招聘"><?php echo $monExclude; ?></textarea></td>
        </tr>
        <tr>
            <td><b>通知推送</b><br/>命中后通过站点通知渠道推送（Server酱/TG/邮件等，由站长在设置中心配置）<?php echo $notifyEnabled ? '' : '<br/><span class="text-danger">当前站点通知未启用，命中只记录在本页</span>'; ?></td>
            <td>
                <select name="ver4_monitor_notify" class="form-control" style="width:140px">
                    <option value="1" <?php echo $monNotify == '1' ? 'selected' : ''; ?>>推送</option>
                    <option value="0" <?php echo $monNotify != '1' ? 'selected' : ''; ?>>仅记录</option>
                </select>
            </td>
        </tr>
        <tr>
            <td></td>
            <td><input type="submit" class="btn btn-primary" name="ver4_monitor_submit" value="保存设置"></td>
        </tr>
    </table>
</form>
<h4 style="margin-top:20px;">最近命中</h4>
<?php if (empty($recentRows)): ?>
<div class="text-muted">暂无命中记录。开启监控后，命中的新帖会展示在这里。</div>
<?php else: ?>
<table class="table table-striped">
    <tr><th>时间</th><th>贴吧</th><th>关键词</th><th>帖子标题</th><th>查看</th></tr>
    <?php foreach ($recentRows as $r): ?>
    <tr>
        <td><?php echo htmlspecialchars(date('m-d H:i', (int) $r['created_at']), ENT_QUOTES, 'UTF-8'); ?></td>
        <td><?php echo htmlspecialchars($r['forum'], ENT_QUOTES, 'UTF-8'); ?>吧</td>
        <td><?php echo htmlspecialchars($r['kw'] !== '' ? $r['kw'] : '（全部新帖）', ENT_QUOTES, 'UTF-8'); ?></td>
        <td><?php echo htmlspecialchars(mb_substr((string) $r['title'], 0, 40), ENT_QUOTES, 'UTF-8'); ?></td>
        <td><a class="cs-btn cs-btn-secondary cs-btn-sm" href="https://tieba.baidu.com/p/<?php echo (int) $r['tid']; ?>" target="_blank" rel="noopener">打开</a></td>
    </tr>
    <?php endforeach; ?>
</table>
<?php endif; ?>
<?php loadfoot(); ?>
