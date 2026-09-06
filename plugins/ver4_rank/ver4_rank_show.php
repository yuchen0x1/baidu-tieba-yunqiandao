<?php if (!defined('SYSTEM_ROOT')) {
    die('Insufficient Permissions');
}
loadhead('贴吧名人堂助攻');
global $m;
$uid = UID;
$b = $m->fetch_array($m->query("SELECT count(id) AS `c`FROM `" . DB_NAME . "`.`" . DB_PREFIX . "baiduid` WHERE `uid` = {$uid}"));
$rankOn = !empty(option::uget('ver4_rank_check', $uid));
$rankLogTable = $m->fetch_array($m->query("SHOW TABLES LIKE '" . DB_PREFIX . "ver4_rank_log'"));
$myRankCount = !empty($rankLogTable)
    ? (int) $m->fetch_array($m->query("SELECT count(id) AS `c` FROM `" . DB_NAME . "`.`" . DB_PREFIX . "ver4_rank_log` WHERE `uid` = {$uid}"))['c']
    : 0;
?>
<section class="dashboard-hero">
    <div class="eyebrow hero-eyebrow">EXTENSIONS · 助攻</div>
    <h1>贴吧名人堂助攻</h1>
    <p>开启后每日会对添加的名人自动助攻，助力 TA 冲击贴吧名人堂。</p>
    <div class="dashboard-hero-meta">
        <span class="hero-tag"><?php echo $rankOn ? '已开启' : '未开启'; ?></span>
        <span class="hero-tag">关注名人 <?php echo $myRankCount; ?> 位</span>
    </div>
    <div class="hero-actions">
        <button type="button" class="cs-btn cs-btn-primary cs-btn-sm" data-toggle="modal" data-target="#AddUser">+ 添加名人</button>
        <a href="http://tieba.baidu.com/celebrity/rankHome" target="_blank" class="cs-btn cs-btn-secondary cs-btn-sm">名人堂 ↗</a>
        <button type="button" class="cs-btn cs-btn-danger cs-btn-sm" data-toggle="modal" data-target="#DelUser">清空列表</button>
    </div>
</section>
<?php
if ($b['c'] < 1) {
    echo '<div class="alert alert-warning">您需要先绑定至少一个百度ID才可以使用本功能</div>';
    loadfoot();
    die;
}
if (isset($_GET['save'])) {
    $check = isset($_POST['c']) ? $_POST['c'] : '0';
    if (!empty($check)) {
        option::uset('ver4_rank_check', 1, $uid);
    } else {
        option::uset('ver4_rank_check', 0, $uid);
    }
    redirect('index.php?plugin=ver4_rank&success=' . urlencode('您的设置已成功保存'));
}
if (isset($_GET['duser'])) {
    $id = isset($_GET['id']) ? $_GET['id'] : '';
    if (!empty($id)) {
        global $m;
        $m->pquery("DELETE FROM `" . DB_NAME . "`.`" . DB_PREFIX . "ver4_rank_log` WHERE `id` = ? AND `uid` = ?", array($id, $uid));
        redirect('index.php?plugin=ver4_rank&success=' . urlencode('已成功删除该名人！'));
    } else {
        redirect('index.php?plugin=ver4_rank&error=' . urlencode('ID不合法'));
    }
}
if (isset($_GET['dauser'])) {
    global $m;
    $m->query("DELETE FROM `" . DB_NAME . "`.`" . DB_PREFIX . "ver4_rank_log` WHERE `uid` = {$uid}");
    redirect('index.php?plugin=ver4_rank&success=' . urlencode('名人列表已成功清空！'));
}
if (isset($_GET['newuser'])) {
    $pid = isset($_POST['pid']) ? $_POST['pid'] : '';
    $ck = isset($_POST['check']) ? sqladds($_POST['check']) : '';

    $p = $m->fetch_array($m->pquery("SELECT * FROM `" . DB_NAME . "`.`" . DB_PREFIX . "baiduid` WHERE `id` = ?", array($pid)));
    if ($p['uid'] != UID) {
        redirect('index.php?plugin=ver4_rank&error=' . urlencode('你不能替他人添加名人呦'));
    }

    if (!is_array($ck) || empty($ck)) {
        redirect('index.php?plugin=ver4_rank&error=' . urlencode('数据非法，或者你没有选择名人，提交失败'));
    }
    $list = json_decode(file_get_contents(PLUGIN_ROOT . '/ver4_rank/ver4_rank_list.json'), true);
    foreach ($ck as $v) {
        if (isset($list[$v])) {
            $ux = $m->fetch_array($m->pquery("SELECT * FROM `" . DB_NAME . "`.`" . DB_PREFIX . "ver4_rank_log` WHERE `pid` = ? AND `name` = ?", array($pid, $list[$v]['name'])));
            if (empty($ux['name'])) {
                $m->pquery("INSERT INTO `" . DB_NAME . "`.`" . DB_PREFIX . "ver4_rank_log` (`uid`,`pid`,`fid`,`nid`,`name`,`tieba`,`date`) VALUES (?,?,?,?,?,?,0)", array($uid, $pid, $list[$v]['fid'], $list[$v]['nid'], $list[$v]['name'], $list[$v]['tieba']));
            }
        }
    }
    redirect('index.php?plugin=ver4_rank&success=' . urlencode('名人已成功添加！'));
}

?>
<section class="dashboard-hero">
    <div class="eyebrow hero-eyebrow">EXTENSIONS · 助攻</div>
    <h1>贴吧名人堂助攻</h1>
    <p>开启后每日会对添加的名人自动助攻，助力 TA 冲击贴吧名人堂。</p>
    <div class="dashboard-hero-meta">
        <span class="hero-tag"><?php echo $rankOn ? '已开启' : '未开启'; ?></span>
        <span class="hero-tag">关注名人 <?php echo $myRankCount; ?> 位</span>
    </div>
    <div class="hero-actions">
        <button type="button" class="cs-btn cs-btn-primary cs-btn-sm" data-toggle="modal" data-target="#AddUser">+ 添加名人</button>
        <a href="http://tieba.baidu.com/celebrity/rankHome" target="_blank" class="cs-btn cs-btn-secondary cs-btn-sm">名人堂 ↗</a>
        <button type="button" class="cs-btn cs-btn-danger cs-btn-sm" data-toggle="modal" data-target="#DelUser">清空列表</button>
    </div>
</section>
<?php
if (isset($_GET['success'])) {
    echo '<div class="alert alert-success">' . htmlspecialchars($_GET['success']) . '</div>';
}
if (isset($_GET['error'])) {
    echo '<div class="alert alert-danger">' . htmlspecialchars($_GET['error']) . '</div>';
}
?>
<form action="index.php?plugin=ver4_rank&save" method="post">
    <table class="table table-hover">
        <tbody>
        <tr>
            <td style="width:35%">
                <b>开启助攻</b><br>
                开启后每日会对设置的名人进行助攻
            </td>
            <td>
                <select name="c" class="form-control" style="width:140px">
                    <option value="1" <?php echo $rankOn ? 'selected' : ''; ?>>开启</option>
                    <option value="0" <?php echo $rankOn ? '' : 'selected'; ?>>关闭</option>
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
<h4>助攻日志</h4>
<br>
<div class="bs-example bs-example-tabs" data-example-id="togglable-tabs">
    <?php
    $a = 0;
    $bid = $m->query("SELECT * FROM `" . DB_NAME . "`.`" . DB_PREFIX . "baiduid` WHERE `uid` = {$uid}");
    ?>
    <div style="display:flex;align-items:center;gap:8px;margin-bottom:8px">
        <label style="margin:0;font-size:12px;color:var(--color-muted)">账号</label>
        <select id="rank-acc" class="form-control" style="width:auto"></select>
    </div>
    <ul id="myTabs" class="nav nav-tabs" role="tablist" style="display:none">
        <?php
        while ($x = $m->fetch_array($bid)) {
            ?>
            <li role="presentation" class="<?= empty($a) ? 'active' : '' ?>"><a href="#b<?= $x['id'] ?>" role="tab"
                                                                                data-toggle="tab"><?= $x['name'] ?></a>
            </li>
            <?php
            $a++;
        }
        ?>
    </ul>
    <script type="text/javascript">
    (function () {
        var sel = document.getElementById('rank-acc');
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
                        <td>贴吧</td>
                        <td>名人</td>
                        <td>时间</td>
                        <td>日志</td>
                        <td>操作</td>
                    </tr>
                    </thead>
                    <tbody>
                    <?php
                    $a = 0;
                    $lr = $m->query("SELECT * FROM `" . DB_NAME . "`.`" . DB_PREFIX . "ver4_rank_log` WHERE `pid` = {$r['id']} ORDER BY `id` DESC");
                    while ($x = $m->fetch_array($lr)) {
                        $a++;
                        $date = date('Y-m-d H:i:s', $x['date']); ?>
                        <tr>
                            <td><?= $x['id'] ?></td>
                            <td><a href="http://tieba.baidu.com/f?kw=<?= $x['tieba'] ?>"
                                   target="_blank"><?= $x['tieba'] ?></a></td>
                            <td><?= $x['name'] ?></td>
                            <td><?= $date ?></td>
                            <td>
                                <a class="btn btn-info" href="javascript:;" data-toggle="modal"
                                   data-target="#LogUser<?= $x['id'] ?>">查看</a>
                            </td>
                            <td>
                                <a class="btn btn-danger" href="javascript:;" data-toggle="modal"
                                   data-target="#DelUser<?= $x['id'] ?>">删除</a>
                            </td>
                        </tr>
                        <div class="modal fade" id="LogUser<?= $x['id'] ?>" tabindex="-1" role="dialog"
                             aria-hidden="true">
                            <div class="modal-dialog">
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <button type="button" class="close" data-dismiss="modal"><span
                                                aria-hidden="true">&times;</span><span
                                                class="sr-only">Close</span></button>
                                        <h4 class="modal-title">日志详情</h4>
                                    </div>
                                    <div class="modal-body">
                                        <div class="input-group">
                                                    <?= empty($x['log']) ? '暂无日志' : $x['log'] ?>
                                        </div>
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-default" data-dismiss="modal">取消</button>
                                    </div>
                                </div><!-- /.modal-content -->
                            </div><!-- /.modal-dialog -->
                        </div><!-- /.modal -->
                        <div class="modal fade" id="DelUser<?= $x['id'] ?>" tabindex="-1" role="dialog"
                             aria-hidden="true">
                            <div class="modal-dialog">
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <button type="button" class="close" data-dismiss="modal"><span
                                                aria-hidden="true">&times;</span><span
                                                class="sr-only">Close</span></button>
                                        <h4 class="modal-title">温馨提示</h4>
                                    </div>
                                    <div class="modal-body">
                                        <form action="index.php?plugin=ver4_rank&duser&id=<?= $x['id'] ?>"
                                              method="post">
                                            <div class="input-group">
                                                您确定要删除这个名人嘛(删除后无法恢复)？
                                            </div>
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-default" data-dismiss="modal">取消</button>
                                        <button type="submit" class="btn btn-primary">确定</button>
                                    </div>
                                    </form>
                                </div><!-- /.modal-content -->
                            </div><!-- /.modal-dialog -->
                        </div><!-- /.modal -->
                                <?php
                    }
                    if (empty($a)) {
                        echo "<tr><td>暂无助攻记录</td><td></td><td></td><td></td><td></td><td></td></tr>";
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


<div class="modal fade" id="AddUser" tabindex="-1" role="dialog" aria-labelledby="AddUser" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="index.php?plugin=ver4_rank&newuser" method="post">
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal"><span
                            aria-hidden="true">&times;</span><span
                            class="sr-only">Close</span></button>
                    <h4 class="modal-title">选择名人（支持多选）</h4>
                </div>
                <div class="modal-body">
                    <div class="input-group">
                        <span class="input-group-addon">请选择对应账号</span>
                        <select name="pid" required="" class="form-control">
                            <?php
                            global $m;
                            $b = $m->query("SELECT * FROM `" . DB_NAME . "`.`" . DB_PREFIX . "baiduid` WHERE `uid` = {$uid}");
                            while ($x = $m->fetch_array($b)) {
                                echo '<option value="' . $x['id'] . '">' . $x['name'] . '</option>';
                            }
                            ?>
                        </select>
                    </div>
                    <br>
                    <table class="table table-striped">
                        <thead>
                        <tr>
                            <td>序号</td>
                            <td>贴吧</td>
                            <td>名人</td>
                            <td>操作</td>
                        </tr>
                        </thead>
                        <tbody>
                        <?php
                        $r = json_decode(file_get_contents(PLUGIN_ROOT . '/ver4_rank/ver4_rank_list.json'), true);
                        foreach ($r as $id => $x) {
                            ?>
                            <tr>
                                <td><?= $id + 1 ?></td>
                                <td><a href="http://tieba.baidu.com/f?kw=<?= $x['tieba'] ?>"
                                       target="_blank"><?= $x['tieba'] ?></a></td>
                                <td><?= $x['name'] ?></td>
                                <td>
                                    <input type="checkbox" name="check[]" value="<?= $id ?>">
                                </td>
                            </tr>
                            <?php
                        }
                        ?>
                        </tbody>
                    </table>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-default" data-dismiss="modal">取消</button>
                    <button type="submit" class="btn btn-primary">提交</button>
                </div>
            </form>
        </div><!-- /.modal-content -->
    </div><!-- /.modal-dialog -->
</div><!-- /.modal -->

<div class="modal fade" id="DelUser" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="index.php?plugin=ver4_rank&dauser" method="post">
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal"><span
                            aria-hidden="true">&times;</span><span
                            class="sr-only">Close</span></button>
                    <h4 class="modal-title">温馨提示</h4>
                </div>
                <div class="modal-body">
                    
                    <div class="input-group">
                        您确定要清空列表（该执行后无法恢复）？
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-default" data-dismiss="modal">取消</button>
                    <button type="submit" class="btn btn-primary">确定</button>
                </div>
            </form>
        </div><!-- /.modal-content -->
    </div><!-- /.modal-dialog -->
</div><!-- /.modal -->