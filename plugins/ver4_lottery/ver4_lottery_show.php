<?php if (!defined('SYSTEM_ROOT')) {
    die('Insufficient Permissions');
}
loadhead('知道商城抽奖');
global $m;
$uid = UID;
$b = $m->fetch_array($m->query("SELECT count(id) AS `c`FROM `" . DB_NAME . "`.`" . DB_PREFIX . "baiduid` WHERE `uid` = {$uid}"));
$lotteryOn = !empty(option::uget('ver4_lottery_check', $uid));
if (isset($_GET['save'])) {
    $check = isset($_POST['c']) ? $_POST['c'] : '0';
    if (!empty($check)) {
        option::uset('ver4_lottery_check', 1, $uid);
    } else {
        option::uset('ver4_lottery_check', 0, $uid);
    }
    redirect('index.php?plugin=ver4_lottery&success=' . urlencode('您的设置已成功保存'));
}
?>
<section class="dashboard-hero">
    <div class="eyebrow hero-eyebrow">EXTENSIONS · 福利</div>
    <h1>知道商城抽奖</h1>
    <p>开启后每日 12 时自动使用绑定的百度账号进行两次知道商城抽奖。</p>
    <div class="dashboard-hero-meta">
        <span class="hero-tag"><?php echo $lotteryOn ? '已开启' : '未开启'; ?></span>
    </div>
    <div class="hero-actions">
        <a href="https://zhidao.baidu.com/ihome/myitem/index" target="_blank" class="cs-btn cs-btn-secondary cs-btn-sm">查看奖品 ↗</a>
    </div>
</section>
<?php
if ($b['c'] < 1) {
    echo '<div class="alert alert-warning">您需要先绑定至少一个百度ID才可以使用本功能，请前往 <a href="index.php?mod=baiduid">百度账号</a> 页绑定。</div>';
    loadfoot();
    die;
}
if (isset($_GET['success'])) {
    echo '<div class="alert alert-success">' . htmlspecialchars($_GET['success']) . '</div>';
}
if (isset($_GET['error'])) {
    echo '<div class="alert alert-danger">' . htmlspecialchars($_GET['error']) . '</div>';
}
?>
<form action="index.php?plugin=ver4_lottery&save" method="post">
    <table class="table table-hover">
        <tbody>
        <tr>
            <td style="width:35%">
                <b>开启抽奖</b><br>
                开启后每日12时开始进行两次抽奖
            </td>
            <td>
                <select name="c" class="form-control" style="width:140px">
                    <option value="1" <?php echo $lotteryOn ? 'selected' : ''; ?>>开启</option>
                    <option value="0" <?php echo $lotteryOn ? '' : 'selected'; ?>>关闭</option>
                </select>
            </td>
        </tr>
        <tr>
            <td><input type="submit" class="btn btn-primary" value="保存设置"></td>
            <td></td>
        </tr>
        </tbody>
    </table>
</form>
<br>
<h4>抽奖日志</h4>
<br>
<div class="bs-example bs-example-tabs" data-example-id="togglable-tabs">
    <?php
    $a = 0;
    $bid = $m->query("SELECT * FROM `" . DB_NAME . "`.`" . DB_PREFIX . "baiduid` WHERE `uid` = {$uid}");
    ?>
    <div style="display:flex;align-items:center;gap:8px;margin-bottom:8px">
        <label style="margin:0;font-size:12px;color:var(--color-muted)">账号</label>
        <select id="lottery-acc" class="form-control" style="width:auto"></select>
    </div>
    <ul id="myTabs" class="nav nav-tabs" role="tablist" style="display:none">
        <?php
        while ($x = $m->fetch_array($bid)) {
            ?>
            <li role="presentation" class="<?= empty($a) ? 'active' : '' ?>"><a href="#b<?= $x['id'] ?>" role="tab" data-toggle="tab"><?= $x['name'] ?></a>
            </li>
            <?php
            $a++;
        }
        ?>
    </ul>
    <script type="text/javascript">
    (function () {
        var sel = document.getElementById('lottery-acc');
        var tabs = document.querySelectorAll('#myTabs a[data-toggle="tab"]');
        tabs.forEach(function (a, i) {
            var opt = document.createElement('option');
            opt.value = i;
            opt.textContent = a.textContent;
            sel.appendChild(opt);
        });
        sel.addEventListener('change', function () {
            tabs[parseInt(this.value, 10)].click();
        });
    })();
    </script>
    <div id="myTabContent" class="tab-content">
        <?php
        $b = 0;
        $bid = $m->query("SELECT * FROM `" . DB_NAME . "`.`" . DB_PREFIX . "baiduid` WHERE `uid` = {$uid}");
        while ($r = $m->fetch_array($bid)) {
            ?>
            <div role="tabpanel" class="tab-pane fade <?= empty($b) ? 'active in' : '' ?>" id="b<?= $r['id'] ?>">
                <table class="table table-striped">
                    <thead>
                    <tr>
                        <td>序号</td>
                        <td>结果</td>
                        <td>奖品</td>
                        <td>时间</td>
                    </tr>
                    </thead>
                    <tbody>
                    <?php
                    $a = 0;
                    $lr = $m->query("SELECT * FROM `" . DB_NAME . "`.`" . DB_PREFIX . "ver4_lottery_log` WHERE `pid` = {$r['id']} ORDER BY `id` DESC");
                    while ($x = $m->fetch_array($lr)) {
                        $a++;
                        $date = date('Y-m-d H:i:s', $x['date']);
                        echo "<tr><td>{$x['id']}</td><td>{$x['result']}</td><td>{$x['prize']}</td><td>{$date}</td></tr>";
                    }
                    if (empty($a)) {
                        echo "<tr><td>暂无抽奖记录</td><td></td><td></td><td></td></tr>";
                    } ?>
                    </tbody>
                </table>
            </div>
            <?php
            $b++;
        }
        ?>
    </div>
</div>
