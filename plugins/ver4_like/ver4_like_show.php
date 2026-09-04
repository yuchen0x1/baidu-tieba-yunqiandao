<?php if (!defined('SYSTEM_ROOT')) {
    die('Insufficient Permissions');
}
/**
 * 用户侧云点赞设置页（index.php?plugin=ver4_like）
 * 每个用户配置自己的目标贴吧，仅对自己绑定的百度账号生效。
 */
if (!defined('UID')) {
    ReDirect('index.php?mod=login');
    die;
}
loadhead('云点赞');
global $m;
$uid = UID;

$bdRow = $m->once_fetch_array("SELECT COUNT(*) AS c FROM `" . DB_PREFIX . "baiduid` WHERE `uid` = {$uid}");
if ((int) $bdRow['c'] < 1) {
    echo '<div class="page-heading"><div><div class="eyebrow">CLOUD LIKES</div><h1>云点赞</h1></div></div>';
    echo '<div class="alert alert-warning">您还没有绑定百度账号，请先前往 <a href="index.php?mod=baiduid">百度账号</a> 页绑定后使用本功能。</div>';
    loadfoot();
    die;
}

if (isset($_POST['ver4_like_submit'])) {
    csrf(true, true);
    ver4_like_uopt_set($uid, 'ver4_like_kws', trim((string) $_POST['ver4_like_kws']));
    ver4_like_uopt_set($uid, 'ver4_like_num', (string) max(1, min(50, (int) $_POST['ver4_like_num'])));
    ver4_like_uopt_set($uid, 'ver4_like_sleep', (string) max(0, (int) $_POST['ver4_like_sleep']));
    redirect('index.php?plugin=ver4_like&success=' . urlencode('云点赞设置已保存'));
}

$g = function ($key, $default = '') use ($uid) {
    $v = option::uget($key, $uid);
    return ($v === null || $v === '') ? $default : $v;
};
$kws = htmlspecialchars($g('ver4_like_kws'), ENT_QUOTES, 'UTF-8');
$num = htmlspecialchars($g('ver4_like_num', '5'), ENT_QUOTES, 'UTF-8');
$sleep = htmlspecialchars($g('ver4_like_sleep', '1500'), ENT_QUOTES, 'UTF-8');
?>
<div class="page-heading"><div><div class="eyebrow">CLOUD LIKES</div><h1>云点赞</h1></div></div>
<p class="text-muted">让系统定时为你绑定的百度账号在目标贴吧点赞最新帖子，提升账号活跃与帖子曝光。点赞任务由系统计划任务自动执行。</p>
<?php if (isset($_GET['success'])): ?>
<div class="alert alert-success"><?php echo htmlspecialchars((string) $_GET['success'], ENT_QUOTES, 'UTF-8'); ?></div>
<?php endif; ?>
<form method="post" action="index.php?plugin=ver4_like">
    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">
    <table class="table table-striped">
        <tr>
            <td style="width:35%"><b>目标贴吧</b><br/><br/>每行一个贴吧名（不带"吧"字）<br/>留空表示不执行点赞</td>
            <td><textarea class="form-control" name="ver4_like_kws" style="height:120px" placeholder="例如：&#10;来日易在心中"><?php echo $kws; ?></textarea></td>
        </tr>
        <tr>
            <td><b>每个贴吧点赞数量</b><br/>按最新帖子顺序，从第一页取帖点赞</td>
            <td><input type="number" min="1" max="50" step="1" class="form-control" name="ver4_like_num" value="<?php echo $num; ?>"></td>
        </tr>
        <tr>
            <td><b>点赞间隔</b><br/>单位为毫秒，降低风控风险</td>
            <td><input type="number" min="0" step="100" class="form-control" name="ver4_like_sleep" value="<?php echo $sleep; ?>"></td>
        </tr>
        <tr>
            <td></td>
            <td><input type="submit" class="btn btn-primary" name="ver4_like_submit" value="保存设置"></td>
        </tr>
    </table>
</form>
<div class="alert alert-warning" style="margin-top:15px;">
    <b>温馨提示：</b>点赞频率不宜过高，间隔建议 1500 毫秒以上；若点赞后不见效果，多为百度风控（数据中心 IP），可为账号配置住宅代理。
</div>
<?php loadfoot(); ?>
