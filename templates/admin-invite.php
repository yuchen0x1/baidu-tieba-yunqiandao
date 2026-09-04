<?php if (!defined('SYSTEM_ROOT')) {
    die('Insufficient Permissions');
}
if (ROLE != 'admin') {
    msg('权限不足！');
}
global $m;
invite_ensure_table();

if (isset($_GET['ok'])) {
    echo '<div class="alert alert-success">操作成功</div>';
}

$multiOn = option::get('yr_reg_multi') == '1';
$total = $m->fetch_row($m->query("SELECT COUNT(*) FROM `" . DB_PREFIX . "invites`"));
$active = $m->fetch_row($m->query("SELECT COUNT(*) FROM `" . DB_PREFIX . "invites` WHERE `status` = 1 AND (`expire` = 0 OR `expire` > " . time() . ") AND `used_count` < `max_uses`"));
?>
<div class="alert alert-info">
    目前共 <?php echo (int) $total[0]; ?> 个邀请码，其中 <?php echo (int) $active[0]; ?> 个当前可用。
    多邀请码模式<?php echo $multiOn ? '<span class="label label-success">已启用</span>' : '<span class="label label-default">未启用</span>' ?>：
    启用后注册须使用下方列表中的邀请码，设置中心的单一邀请码失效。
</div>

<div class="row">
    <div class="col-md-6">
        <h4>启用多邀请码模式</h4>
        <form action="setting.php?mod=admin:invite" method="post">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">
            <input type="hidden" name="invite_action" value="multi">
            <label class="checkbox"><input type="checkbox" name="yr_reg_multi" value="1" <?php if ($multiOn) {
                echo 'checked="checked"';
                          } ?>> 启用多邀请码注册校验</label>
            <input type="submit" class="cs-btn cs-btn-primary" value="保存">
        </form>
    </div>
    <div class="col-md-6">
        <h4>批量生成邀请码</h4>
        <form action="setting.php?mod=admin:invite" method="post">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">
            <input type="hidden" name="invite_action" value="generate">
            <table class="table">
                <tr><td style="width:40%">生成数量（1-100）</td><td><input type="number" name="count" min="1" max="100" value="10" class="form-control" required></td></tr>
                <tr><td>每个可用次数（1-9999）</td><td><input type="number" name="max_uses" min="1" max="9999" value="1" class="form-control" required></td></tr>
                <tr><td>有效天数（0=永不过期）</td><td><input type="number" name="days" min="0" max="3650" value="0" class="form-control"></td></tr>
                <tr><td>备注</td><td><input type="text" name="note" maxlength="255" class="form-control" placeholder="例如：2026 年活动"></td></tr>
            </table>
            <input type="submit" class="cs-btn cs-btn-primary" value="批量生成" onclick="return confirm('确定生成邀请码吗？');">
        </form>
    </div>
</div>

<h4>邀请码列表</h4>
<div class="table-responsive">
<table class="cs-table">
    <thead>
        <tr>
            <th>#</th>
            <th>邀请码</th>
            <th>已用 / 上限</th>
            <th>过期时间</th>
            <th>状态</th>
            <th>备注</th>
            <th>创建时间</th>
            <th>操作</th>
        </tr>
    </thead>
    <tbody>
    <?php
    $rs = $m->query("SELECT * FROM `" . DB_PREFIX . "invites` ORDER BY `id` DESC");
    if ($m->num_rows($rs) == 0) {
        echo '<tr><td colspan="8" class="text-center">暂无邀请码，请先批量生成</td></tr>';
    }
    while ($row = $m->fetch_array($rs)) {
        $expired = (int) $row['expire'] > 0 && (int) $row['expire'] <= time();
        $usedUp = (int) $row['used_count'] >= (int) $row['max_uses'];
        if ((int) $row['status'] !== 1) {
            $state = '<span class="label label-default">已停用</span>';
        } elseif ($expired || $usedUp) {
            $state = '<span class="label label-warning">' . ($expired ? '已过期' : '已用尽') . '</span>';
        } else {
            $state = '<span class="label label-success">可用</span>';
        }
        $expireText = (int) $row['expire'] > 0 ? date('Y-m-d', (int) $row['expire']) : '永不';
        $csrf = htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8');
        $id = (int) $row['id'];
        echo '<tr>'
            . '<td>' . $id . '</td>'
            . '<td><code>' . htmlspecialchars($row['code'], ENT_QUOTES, 'UTF-8') . '</code></td>'
            . '<td>' . (int) $row['used_count'] . ' / ' . (int) $row['max_uses'] . '</td>'
            . '<td>' . $expireText . '</td>'
            . '<td>' . $state . '</td>'
            . '<td>' . htmlspecialchars($row['note'], ENT_QUOTES, 'UTF-8') . '</td>'
            . '<td>' . date('Y-m-d H:i', (int) $row['created_at']) . '</td>'
            . '<td>'
            . '<form style="display:inline" action="setting.php?mod=admin:invite" method="post"><input type="hidden" name="csrf_token" value="' . $csrf . '"><input type="hidden" name="invite_action" value="toggle"><input type="hidden" name="id" value="' . $id . '"><button type="submit" class="cs-btn cs-btn-secondary btn-xs">' . ((int) $row['status'] === 1 ? '停用' : '启用') . '</button></form> '
            . '<form style="display:inline" action="setting.php?mod=admin:invite" method="post" onsubmit="return confirm(\'确定删除该邀请码吗？\');"><input type="hidden" name="csrf_token" value="' . $csrf . '"><input type="hidden" name="invite_action" value="delete"><input type="hidden" name="id" value="' . $id . '"><button type="submit" class="cs-btn cs-btn-danger btn-xs">删除</button></form>'
            . '</td>'
            . '</tr>';
    }
    ?>
    </tbody>
</table>
</div>
