<?php if (!defined('SYSTEM_ROOT')) {
    die('Insufficient Permissions');
}
if (ROLE !== 'admin') {
    msg('权限不足!');
    die;
}

$limit = option::get('weltolk_sign_qq_limit');

switch ($_GET['act']) {
    case 'ok'://成功回显
        echo '<div class="alert alert-success alert-dismissable"><button type="button" class="close" data-dismiss="alert" aria-hidden="true">&times;</button>插件设置成功</div>';
        break;
    case 'store'://保存设置
        option::set('weltolk_sign_qq_limit', $_POST['limit']);
        ReDirect(SYSTEM_URL . 'index.php?mod=admin:setplug&plug=weltolk_sign_qq&act=ok');
        die;
    default:
        break;
}


?>
<section class="dashboard-hero" style="margin-top:16px">
    <div class="eyebrow hero-eyebrow">EXTENSIONS · 推送</div>
    <h1>签到 QQ 推送 · 站点设置</h1>
    <p>本页只包含站点级参数。推送列表与推送地址由每个用户在自己的「每日签到QQ推送」页面自行管理。</p>
    <div class="dashboard-hero-meta">
        <span class="hero-tag">管理端参数 1 项</span>
    </div>
</section>
<form action="index.php?mod=admin:setplug&plug=weltolk_sign_qq&act=store" method="post">
    <table class="table table-striped">
        <thead>
        <tr>
            <th style="width:45%">参数</th>
            <th style="width:55%">值</th>
        </tr>
        </thead>
        <tbody>
        <tr>
            <td>单次计划任务连续推送次数<br/>越小效率越低，但太大也可能导致超时</td>
            <td><input type="number" min="1" step="1" name="limit" value="<?php echo htmlspecialchars($limit, ENT_QUOTES, 'UTF-8') ?>"
                       class="form-control" required></td>
        </tr>
        </tbody>
    </table>

    <button type="submit" class="btn btn-info">保存设置</button>
</form>