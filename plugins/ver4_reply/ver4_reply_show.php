<?php if (!defined('SYSTEM_ROOT')) {
    die('Insufficient Permissions');
}
/**
 * 用户侧自动回帖/发帖设置页（index.php?plugin=ver4_reply）
 * 每个用户配置自己的目标贴吧与内容库，仅对自己绑定的百度账号生效。
 */
if (!defined('UID')) {
    ReDirect('index.php?mod=login');
    die;
}
loadhead('自动回帖/发帖');
global $m;
$uid = UID;

$bdRow = $m->once_fetch_array("SELECT COUNT(*) AS c FROM `" . DB_PREFIX . "baiduid` WHERE `uid` = {$uid}");
$replyKwCount = count(array_filter(preg_split('/\r\n|\r|\n/', (string) option::uget('ver4_reply_kws', $uid))));
$contentCount = count(array_filter(preg_split('/\r\n|\r|\n/', (string) option::uget('ver4_reply_contents', $uid))));
$threadKwCount = count(array_filter(preg_split('/\r\n|\r|\n/', (string) option::uget('ver4_reply_thread_kws', $uid))));
if ((int) $bdRow['c'] < 1) {
    echo '<section class="dashboard-hero"><div class="eyebrow hero-eyebrow">EXTENSIONS · 内容</div><h1>自动回帖/发帖</h1>'
        . '<p>让系统定时用你绑定的百度账号对目标贴吧回帖与发主题帖。</p>'
        . '<div class="dashboard-hero-meta"><span class="hero-tag" style="color:#ffd7d7">未绑定百度账号</span></div></section>';
    echo '<div class="alert alert-warning">您还没有绑定百度账号，请先前往 <a href="index.php?mod=baiduid">百度账号</a> 页绑定后使用本功能。</div>';
    loadfoot();
    die;
}

if (isset($_POST['ver4_reply_submit'])) {
    csrf(true, true);
    //回帖
    ver4_reply_uopt_set($uid, 'ver4_reply_kws', trim((string) $_POST['ver4_reply_kws']));
    ver4_reply_uopt_set($uid, 'ver4_reply_contents', trim((string) $_POST['ver4_reply_contents']));
    ver4_reply_uopt_set($uid, 'ver4_reply_num', (string) max(1, min(20, (int) $_POST['ver4_reply_num'])));
    ver4_reply_uopt_set($uid, 'ver4_reply_daily', (string) max(1, min(200, (int) $_POST['ver4_reply_daily'])));
    ver4_reply_uopt_set($uid, 'ver4_reply_sleep', (string) max(0, (int) $_POST['ver4_reply_sleep']));
    ver4_reply_uopt_set($uid, 'ver4_reply_floor_rate', (string) min(100, max(0, (int) $_POST['ver4_reply_floor_rate'])));
    //发帖
    ver4_reply_uopt_set($uid, 'ver4_reply_thread_kws', trim((string) $_POST['ver4_reply_thread_kws']));
    ver4_reply_uopt_set($uid, 'ver4_reply_thread_titles', trim((string) $_POST['ver4_reply_thread_titles']));
    ver4_reply_uopt_set($uid, 'ver4_reply_thread_contents', trim((string) $_POST['ver4_reply_thread_contents']));
    ver4_reply_uopt_set($uid, 'ver4_reply_thread_num', (string) max(1, min(10, (int) $_POST['ver4_reply_thread_num'])));
    ver4_reply_uopt_set($uid, 'ver4_reply_thread_daily', (string) max(1, min(50, (int) $_POST['ver4_reply_thread_daily'])));
    redirect('index.php?plugin=ver4_reply&success=' . urlencode('自动回帖/发帖设置已保存'));
}

$g = function ($key, $default = '') use ($uid) {
    $v = option::uget($key, $uid);
    return ($v === null || $v === '') ? $default : $v;
};
$kws = htmlspecialchars($g('ver4_reply_kws'), ENT_QUOTES, 'UTF-8');
$contents = htmlspecialchars($g('ver4_reply_contents'), ENT_QUOTES, 'UTF-8');
$num = htmlspecialchars($g('ver4_reply_num', '3'), ENT_QUOTES, 'UTF-8');
$daily = htmlspecialchars($g('ver4_reply_daily', '10'), ENT_QUOTES, 'UTF-8');
$sleep = htmlspecialchars($g('ver4_reply_sleep', '3000'), ENT_QUOTES, 'UTF-8');
$floorRate = htmlspecialchars($g('ver4_reply_floor_rate', '0'), ENT_QUOTES, 'UTF-8');
$tKws = htmlspecialchars($g('ver4_reply_thread_kws'), ENT_QUOTES, 'UTF-8');
$tTitles = htmlspecialchars($g('ver4_reply_thread_titles'), ENT_QUOTES, 'UTF-8');
$tContents = htmlspecialchars($g('ver4_reply_thread_contents'), ENT_QUOTES, 'UTF-8');
$tNum = htmlspecialchars($g('ver4_reply_thread_num', '1'), ENT_QUOTES, 'UTF-8');
$tDaily = htmlspecialchars($g('ver4_reply_thread_daily', '3'), ENT_QUOTES, 'UTF-8');

//我的贴吧列表（供目标贴吧勾选）
require_once SYSTEM_ROOT . '/lib/forum_picker.php';
$myForums = forum_picker_forums();
?>
<section class="dashboard-hero">
    <div class="eyebrow hero-eyebrow">EXTENSIONS · 内容</div>
    <h1>自动回帖/发帖</h1>
    <p>让系统定时用你绑定的百度账号，对目标贴吧的最新主题帖回帖（内容从你的内容库随机选取），还可自动发布主题帖。支持楼中楼、按天去重与频率控制。</p>
    <div class="dashboard-hero-meta">
        <span class="hero-tag">回帖目标吧 <?php echo $replyKwCount; ?> 个</span>
        <span class="hero-tag">内容库 <?php echo $contentCount; ?> 条</span>
        <span class="hero-tag">发帖目标吧 <?php echo $threadKwCount; ?> 个</span>
    </div>
</section>
<?php if (isset($_GET['success'])): ?>
<div class="alert alert-success"><?php echo htmlspecialchars((string) $_GET['success'], ENT_QUOTES, 'UTF-8'); ?></div>
<?php endif; ?>
<form method="post" action="index.php?plugin=ver4_reply">
    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">
    <table class="table table-striped">
        <tr><th colspan="2"><h4>① 自动回帖</h4></th></tr>
        <tr>
            <td style="width:35%"><b>目标贴吧</b><br/><br/>每行一个贴吧名（不带"吧"字）<br/>留空表示不执行回帖</td>
            <td>
                <?php forum_picker_render('ver4_reply_kws', $myForums); ?>
                <textarea class="form-control" name="ver4_reply_kws" style="height:110px" placeholder="例如：&#10;来日易在心中"><?php echo $kws; ?></textarea>
            </td>
        </tr>
        <tr>
            <td><b>回帖内容库</b><br/><br/>每行一条，回复时随机选取<br/>建议 10 条以上且内容自然，降低风控风险<br/><span class="text-danger">请勿发布违法违规内容，后果由使用者自负</span></td>
            <td><textarea class="form-control" name="ver4_reply_contents" style="height:150px" placeholder="每行一条回复内容"><?php echo $contents; ?></textarea></td>
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
            <td><b>楼中楼回复比例</b><br/>0-100 的百分比。回复时有该概率随机选一栋楼层进行楼中楼回复，设为 0 表示始终回复主题帖</td>
            <td><input type="number" min="0" max="100" step="5" class="form-control" name="ver4_reply_floor_rate" value="<?php echo $floorRate; ?>"></td>
        </tr>
        <tr>
            <td><b>回帖间隔</b><br/>单位为毫秒，发主题帖共用此间隔</td>
            <td><input type="number" min="0" step="500" class="form-control" name="ver4_reply_sleep" value="<?php echo $sleep; ?>"></td>
        </tr>
        <tr><th colspan="2"><h4>② 自动发主题帖</h4></th></tr>
        <tr>
            <td><b>目标贴吧</b><br/><br/>每行一个贴吧名（不带"吧"字）<br/>留空表示不执行发主题帖</td>
            <td>
                <?php forum_picker_render('ver4_reply_thread_kws', $myForums); ?>
                <textarea class="form-control" name="ver4_reply_thread_kws" style="height:100px" placeholder="例如：&#10;来日易在心中"><?php echo $tKws; ?></textarea>
            </td>
        </tr>
        <tr>
            <td><b>标题库</b><br/><br/>每行一条，发帖时随机选取</td>
            <td><textarea class="form-control" name="ver4_reply_thread_titles" style="height:100px" placeholder="每行一条主题帖标题"><?php echo $tTitles; ?></textarea></td>
        </tr>
        <tr>
            <td><b>内容库</b><br/><br/>每行一条，发帖时随机选取<br/><span class="text-danger">请勿发布违法违规内容，后果由使用者自负</span></td>
            <td><textarea class="form-control" name="ver4_reply_thread_contents" style="height:110px" placeholder="每行一条主题帖内容"><?php echo $tContents; ?></textarea></td>
        </tr>
        <tr>
            <td><b>每吧每次发帖数</b></td>
            <td><input type="number" min="1" max="10" step="1" class="form-control" name="ver4_reply_thread_num" value="<?php echo $tNum; ?>"></td>
        </tr>
        <tr>
            <td><b>每账号每日发帖上限</b><br/>所有目标贴吧合计</td>
            <td><input type="number" min="1" max="50" step="1" class="form-control" name="ver4_reply_thread_daily" value="<?php echo $tDaily; ?>"></td>
        </tr>
        <tr>
            <td></td>
            <td><input type="submit" class="btn btn-primary" name="ver4_reply_submit" value="保存设置"></td>
        </tr>
    </table>
</form>
<div class="alert alert-warning" style="margin-top:15px;">
    <b>温馨提示：</b>同一主题帖每天只回一次；触发百度频率限制时会自动终止本轮。若内容发布成功但很快消失，多为百度风控（数据中心 IP 秒删），可为账号配置住宅代理或降低频率。
</div>
<?php forum_picker_script(); ?>
<?php loadfoot(); ?>
