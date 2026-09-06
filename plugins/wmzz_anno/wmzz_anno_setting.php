<?php
if (!defined('SYSTEM_ROOT')) {
    die('Insufficient Permissions');
}
if (ROLE != 'admin') {
    msg('权限不足!');
}

if (isset($_GET['ok'])) {
    echo '<div class="alert alert-success alert-dismissable"><button type="button" class="close" data-dismiss="alert" aria-hidden="true">&times;</button>插件设置成功</div>';
}

require_once SYSTEM_ROOT . '/lib/security.php';
$doa = safe_announcement_hooks(option::get('wmzz_anno_doa'));
$annoHookLabels = array(
    'index_1' => '工作台（顶部）', 'index_2' => '工作台（中部）', 'index_3' => '工作台（底部）',
    'navi_7' => '导航栏', 'navi_8' => '导航栏（扩展）', 'navi_9' => '导航栏（扩展 2）',
    'login_page_1' => '登录页（上）', 'login_page_2' => '登录页（中）', 'login_page_3' => '登录页（下）',
    'reg_page_1' => '注册页（上）', 'reg_page_2' => '注册页（中）', 'reg_page_3' => '注册页（下）',
    'header' => '全站页头', 'footer' => '全站页脚',
);
$annoSetContent = htmlspecialchars(option::get('wmzz_anno_set'));
$annoTplContent = htmlspecialchars(option::get('wmzz_anno_tpl'));
$annoCount = count(array_filter(preg_split('/\r\n|\r|\n/', (string) $annoSetContent)));
?>
<section class="dashboard-hero" style="margin-top:16px">
    <div class="eyebrow hero-eyebrow">EXTENSIONS · 内容</div>
    <h1>公告栏管理</h1>
    <p>配置全站公告内容、展示模板与挂载位置。公告支持 HTML，输出时请确保内容可信。</p>
    <div class="dashboard-hero-meta">
        <span class="hero-tag">公告 <?php echo $annoCount; ?> 条</span>
        <span class="hero-tag">挂载点 <?php echo count($doa); ?> 处</span>
    </div>
</section>

<form action="index.php?plugin=wmzz_anno" method="post">
<div class="panel panel-info" style="margin:0 1%">
    <div class="panel-heading"><span class="panel-title">① 公告内容</span></div>
    <div class="panel-body">
        <b>请输入您的公告： [ 每行一条，支持 HTML ]</b><br/><br/>
        <textarea class="form-control" name="wmzz_anno_set" style="height:220px;"><?php echo $annoSetContent ?></textarea>
    </div>
</div>

<br/>
<div class="panel panel-info" style="margin:0 1%">
    <div class="panel-heading"><span class="panel-title">② 展示模板与挂载位置</span></div>
    <div class="panel-body">
        <b>公告栏模板： [ 使用 {$anno} 表示公告 ]</b><br/><br/>
        <textarea class="form-control" name="wmzz_anno_tpl" style="height:120px;"><?php echo $annoTplContent ?></textarea>
        <br/><br/>
        <b>公告栏挂载点：</b>（可多选）<br/><br/>
        <div style="display:flex;flex-wrap:wrap;gap:8px">
        <?php foreach ($annoHookLabels as $hookKey => $hookLabel) { ?>
            <label style="display:inline-flex;align-items:center;gap:6px;border:1px solid var(--color-line,#ddd);border-radius:999px;padding:4px 12px;cursor:pointer;<?php echo in_array($hookKey, $doa, true) ? 'background:var(--color-brand-soft,#eef);' : '' ?>">
                <input type="checkbox" name="wmzz_anno_doa[]" value="<?php echo htmlspecialchars($hookKey, ENT_QUOTES, 'UTF-8'); ?>" <?php echo in_array($hookKey, $doa, true) ? 'checked' : '' ?>>
                <span><?php echo htmlspecialchars($hookLabel, ENT_QUOTES, 'UTF-8'); ?></span>
            </label>
        <?php } ?>
        </div>
        <br/>
        <button type="submit" class="cs-btn cs-btn-primary">提交更改</button>
        <button type="button" class="cs-btn cs-btn-secondary" onclick="wmzzAnnoPreview()">预览效果</button>
    </div>
</div>
</form>
<br/><br/>公告栏 V1.5 // 作者: <a href="http://zhizhe8.net" target="_blank">无名智者</a>
<script>
function wmzzAnnoPreview() {
    var tpl = document.querySelector('textarea[name="wmzz_anno_tpl"]').value;
    var anno = document.querySelector('textarea[name="wmzz_anno_set"]').value;
    var html = tpl.split('{$anno}').join(anno) || '(模板为空)';
    var w = window.open('', '_blank');
    if (!w) { alert('浏览器拦截了新窗口，请允许弹窗后重试'); return; }
    w.document.write('<!DOCTYPE html><html><head><meta charset="utf-8"><title>公告预览</title></head><body>' + html + '</body></html>');
    w.document.close();
}
</script>
