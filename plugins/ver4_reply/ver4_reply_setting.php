<?php

if (!defined('SYSTEM_ROOT')) {
    die('Insufficient Permissions');
}
global $m;

if (isset($_POST['ver4_reply_submit'])) {
    csrf(true, true);
    option::set('ver4_reply_kws', trim((string) $_POST['ver4_reply_kws']));
    option::set('ver4_reply_contents', trim((string) $_POST['ver4_reply_contents']));
    option::set('ver4_reply_num', max(1, min(20, (int) $_POST['ver4_reply_num'])));
    option::set('ver4_reply_daily', max(1, min(200, (int) $_POST['ver4_reply_daily'])));
    option::set('ver4_reply_sleep', max(0, (int) $_POST['ver4_reply_sleep']));
    echo '<div class="alert alert-success">自动回帖设置已保存</div>';
}

$kws     = htmlspecialchars((string) option::get('ver4_reply_kws'), ENT_QUOTES, 'UTF-8');
$contents = htmlspecialchars((string) option::get('ver4_reply_contents'), ENT_QUOTES, 'UTF-8');
$num     = htmlspecialchars((string) option::get('ver4_reply_num'), ENT_QUOTES, 'UTF-8');
$daily   = htmlspecialchars((string) option::get('ver4_reply_daily'), ENT_QUOTES, 'UTF-8');
$sleep   = htmlspecialchars((string) option::get('ver4_reply_sleep'), ENT_QUOTES, 'UTF-8');
?>

<form method="post" action="index.php?mod=admin:setplug&plug=ver4_reply">
    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">
    <table class="table table-striped">
        <tr>
            <td style="width:35%"><b>目标贴吧</b><br/><br/>每行一个贴吧名（不带"吧"字）<br/>留空表示不执行回帖</td>
            <td><textarea class="form-control" name="ver4_reply_kws" style="height:120px" placeholder="例如：&#10;签到&#10;贴吧云签到"><?php echo $kws; ?></textarea></td>
        </tr>
        <tr>
            <td><b>回帖内容库</b><br/><br/>每行一条，回复时随机选取<br/>建议 10 条以上且内容自然，降低风控风险<br/><span class="text-danger">请勿发布违法违规内容，后果由使用者自负</span></td>
            <td><textarea class="form-control" name="ver4_reply_contents" style="height:160px" placeholder="每行一条回复内容"><?php echo $contents; ?></textarea></td>
        </tr>
        <tr>
            <td><b>每吧每次回帖数</b><br/>按最新主题帖顺序取帖回复</td>
            <td><input type="number" min="1" max="20" step="1" class="form-control" name="ver4_reply_num" value="<?php echo $num; ?>"></td>
        </tr>
        <tr>
            <td><b>每账号每日回帖上限</b><br/>所有目标贴吧合计</td>
            <td><input type="number" min="1" max="200" step="1" class="form-control" name="ver4_reply_daily" value="<?php echo $daily; ?>"></td>
        </tr>
        <tr>
            <td><b>回帖间隔</b><br/>单位为毫秒</td>
            <td><input type="number" min="0" step="500" class="form-control" name="ver4_reply_sleep" value="<?php echo $sleep; ?>"></td>
        </tr>
        <tr>
            <td></td>
            <td><input type="submit" class="btn btn-primary" name="ver4_reply_submit" value="保存设置"></td>
        </tr>
    </table>
</form>
