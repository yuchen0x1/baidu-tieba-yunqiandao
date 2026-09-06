<?php if (!defined('SYSTEM_ROOT')) {
    die('Insufficient Permissions');
}  if (ROLE != 'admin') {
    msg('权限不足！');
}
global $m;

if (isset($_GET['ok'])) {
    echo '<div class="alert alert-success">操作用户成功</div>';
}
if (isset($_GET['add'])) {  ?>
<form action="setting.php?mod=admin:users" method="post">
<input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">
<input type="hidden" name="do" value="add">
<div class="table-responsive">
<table class="cs-table">
    <thead>
        <tr>
            <th>参数</th>
            <th>值</th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td>用户名</td>
            <td><input type="text" name="name" class="form-control" required></td>
        </tr>
        <tr>
            <td>密码</td>
            <td><input type="password" name="pwd" class="form-control" required></td>
        </tr>
        <tr>
            <td>邮箱</td>
            <td><input type="email" name="mail" class="form-control" required></td>
        </tr>
        <tr>
            <td>用户组</td>
            <td>
                <label><input type="radio" name="role" value="user" required checked="checked"> 用户&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;</label>
                <label><input type="radio" name="role" value="vip" required> VIP&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;</label>
                <label><input type="radio" name="role" value="admin" required> 管理员&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;</label>
            </td>
        </tr>
    </tbody>
</table>
</div>
<input type="submit" class="cs-btn cs-btn-primary" value="提交更改">
</form>

<?php } else {
    $userc = $m->fetch_row($m->query("SELECT COUNT(*) FROM `" . DB_NAME . "`.`" . DB_PREFIX . "users`"));
    $users = '';
    $s = $m->query('SELECT * FROM  `' . DB_NAME . '`.`' . DB_PREFIX . 'users` ORDER BY `role`,`id`');

    while ($x = $m->fetch_array($s)) {
        $users .= '<tr><td>' . htmlspecialchars($x['id'], ENT_QUOTES, 'UTF-8') . '<br/><input type="checkbox" name="user[]" id="user_' . htmlspecialchars($x['id'], ENT_QUOTES, 'UTF-8') . '" value="' . htmlspecialchars($x['id'], ENT_QUOTES, 'UTF-8') . '"></td><td onclick="$(\'#user_\' + \'' . htmlspecialchars($x['id'], ENT_QUOTES, 'UTF-8') . '\').click()"><a href="setting.php?mod=admin:users&control=' . htmlspecialchars($x['id'], ENT_QUOTES, 'UTF-8') . '">' . htmlspecialchars($x['name'], ENT_QUOTES, 'UTF-8') . '</a><br/>用户组：' . htmlspecialchars(getrole($x['role']), ENT_QUOTES, 'UTF-8') . '</td><td onclick="$(\'#user_\' + \'' . htmlspecialchars($x['id'], ENT_QUOTES, 'UTF-8') . '\').click()">' . htmlspecialchars($x['email'], ENT_QUOTES, 'UTF-8') . '<br/>数据表：' . htmlspecialchars($x['t'], ENT_QUOTES, 'UTF-8') . '</td></tr>';
    }

    ?>
<section class="dashboard-hero" style="margin-top:16px">
    <div class="eyebrow hero-eyebrow">OPERATIONS · 用户</div>
    <h1>用户管理</h1>
    <p>点击用户名进入控制，勾选复选框后可批量执行操作。删除等操作不可逆，请谨慎。</p>
    <div class="dashboard-hero-meta">
        <span class="hero-tag">用户 <?php echo $userc[0]; ?> 名</span>
    </div>
    <div class="hero-actions">
        <a class="cs-btn cs-btn-primary cs-btn-sm" href="index.php?mod=admin:users&add">+ 新建用户</a>
        <a class="cs-btn cs-btn-secondary cs-btn-sm" href="#mailblast">邮件群发</a>
        <input id="user-search" class="form-control" placeholder="搜索用户名 / 邮箱 / UID" style="max-width:240px;display:inline-block;height:28px;padding:2px 10px">
    </div>
</section>
<form action="setting.php?mod=admin:users" method="post" onsubmit="return userAdminSubmit();">
<input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">
<div class="table-responsive">
<table class="cs-table">
    <thead>
        <tr>
            <th>UID</th>
            <th>用户名/用户组</th>
            <th>电子邮箱/数据表</th>
        </tr>
    </thead>
    <tbody>
        <?php echo $users; ?>
    </tbody>
</table>
</div>
<a name="submit_button" id="submit_button"></a>
<div id="user-batch-bar" style="display:none;position:sticky;bottom:0;z-index:20;margin-top:12px;padding:10px 14px;background:var(--color-surface,#fff);border:1px solid var(--color-line);border-radius:8px;box-shadow:0 -4px 12px rgba(0,0,0,.08);flex-wrap:wrap;align-items:center;gap:10px">
    <b style="font-size:12px">已选 <span id="user-sel-count">0</span> 位，选择操作：</b>
    <label><input type="radio" name="do" value="cookie" checked> 清除 Cookie</label>
    <label><input type="radio" name="do" value="clean"> 清除贴吧数据</label>
    <label><input type="radio" name="do" value="delete"> 删除用户</label>
    <label><input type="radio" name="do" value="cset"> 清除设置</label>
    <input type="radio" name="do" id="userdo_crole" value="crole">
    <select class="form-control input-sm" style="display: inline; width: auto" name="crolev" onchange="$('#userdo_crole').attr('checked',true);">
        <option>调整用户组为</option>
        <option value="user">用户</option>
        <option value="vip">VIP</option>
        <option value="admin">管理员</option>
        <option value="banned">禁止访问</option>
    </select>
    <input type="submit" class="cs-btn cs-btn-primary" value="执行操作">
</div>
&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
<button type="button" class="cs-btn cs-btn-secondary" onclick="location = 'index.php?mod=admin:users&add'">添加用户</button>
</form>

<h4 id="mailblast">邮件群发</h4>
<form action="setting.php?mod=admin:users" method="post" onsubmit="return confirm('确定向全部留有邮箱的用户群发邮件吗？');">
<input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">
<input type="hidden" name="do" value="mailblast">
<div class="table-responsive">
<table class="cs-table">
    <tbody>
        <tr>
            <td style="width:20%">邮件主题</td>
            <td><input type="text" name="mail_subject" class="form-control" maxlength="200" required></td>
        </tr>
        <tr>
            <td>邮件正文（HTML）<br/><span class="text-muted">可用变量：{name} 用户名、{site} 站点名</span></td>
            <td><textarea name="mail_content" class="form-control" style="height:140px" required></textarea></td>
        </tr>
    </tbody>
</table>
</div>
<input type="submit" class="cs-btn cs-btn-primary" value="群发邮件">
</form>
<?php } ?>
<script>
    function userAdminSubmit() {
        if($("input[name='do']:checked").val() != 'control') {
            return confirm('此操作不可逆，你确定要执行吗？');
        }
    }
    (function () {
        var boxes = document.querySelectorAll('input[name="user[]"]');
        var bar = document.getElementById('user-batch-bar');
        var countEl = document.getElementById('user-sel-count');
        function refresh() {
            var n = 0;
            boxes.forEach(function (b) { if (b.checked) n++; });
            bar.style.display = n > 0 ? 'flex' : 'none';
            countEl.textContent = n;
        }
        boxes.forEach(function (b) { b.addEventListener('change', refresh); });
        var search = document.getElementById('user-search');
        if (search) {
            search.addEventListener('input', function () {
                var kw = this.value.trim().toLowerCase();
                document.querySelectorAll('form table tbody tr').forEach(function (tr) {
                    tr.style.display = tr.textContent.toLowerCase().indexOf(kw) >= 0 ? '' : 'none';
                });
            });
        }
    })();
</script>
