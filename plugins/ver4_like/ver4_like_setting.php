<?php

if (!defined('SYSTEM_ROOT')) {
    die('Insufficient Permissions');
}
global $m;

$plugin_name = 'ver4_like';

if (isset($_POST['ver4_like_submit'])) {
    csrf(true, true);
    option::set('ver4_like_kws', trim((string) $_POST['ver4_like_kws']));
    option::set('ver4_like_num', max(1, (int) $_POST['ver4_like_num']));
    option::set('ver4_like_sleep', max(0, (int) $_POST['ver4_like_sleep']));
    echo '<div class="alert alert-success">云点赞设置已保存</div>';
}

$kws  = htmlspecialchars((string) option::get('ver4_like_kws'), ENT_QUOTES, 'UTF-8');
$num  = htmlspecialchars((string) option::get('ver4_like_num'), ENT_QUOTES, 'UTF-8');
$sleep = htmlspecialchars((string) option::get('ver4_like_sleep'), ENT_QUOTES, 'UTF-8');
?>

<form method="post" action="index.php?mod=admin:setplug&plug=ver4_like">
    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">
    <table class="table table-striped">
        <tr>
            <td style="width:35%"><b>目标贴吧</b><br/><br/>每行一个贴吧名（不带"吧"字）<br/>留空表示不执行点赞</td>
            <td><textarea class="form-control" name="ver4_like_kws" style="height:120px" placeholder="例如：&#10;签到&#10;贴吧云签到"><?php echo $kws; ?></textarea></td>
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
