<?php if (!defined('SYSTEM_ROOT')) {
    die('Insufficient Permissions');
}
global $m,$i;
$day = date('Y-m-d');
if (isset($_GET['ok'])) {
    echo '<div class="cs-alert cs-alert-success"><svg class="cs-alert-icon" aria-hidden="true"><use href="#i-check"/></svg><div class="cs-alert-body">设置保存成功</div></div>';
}
if (isset($_GET['retry_msg'])) {
    echo '<div class="cs-alert cs-alert-info"><svg class="cs-alert-icon" aria-hidden="true"><use href="#i-refresh"/></svg><div class="cs-alert-body">' . htmlspecialchars((string) $_GET['retry_msg'], ENT_QUOTES, 'UTF-8') . '</div></div>';
}
if (isset($_GET['restore_msg'])) {
    echo '<div class="cs-alert cs-alert-success"><svg class="cs-alert-icon" aria-hidden="true"><use href="#i-check"/></svg><div class="cs-alert-body">' . htmlspecialchars((string) $_GET['restore_msg'], ENT_QUOTES, 'UTF-8') . '</div></div>';
}

// 一键复签失败贴吧表单
$todayDay = date('Y-m-d');
$failedCount = 0;
if (!empty($i['user']['bduss'])) {
    $fcRow = $m->once_fetch_array("SELECT COUNT(*) AS c FROM `" . DB_NAME . "`.`" . DB_PREFIX . TABLE . "` WHERE `uid` = " . (int) UID . " AND `no` = 0 AND `latest` = '" . $m->real_escape_string($todayDay) . "' AND `status` != 0");
    $failedCount = (int) ($fcRow['c'] ?? 0);
}
if ($failedCount > 0) {
    echo '<form method="post" action="setting.php?mod=showtb:retry" style="margin:12px 0" onsubmit="return confirm(\'即将对 ' . $failedCount . ' 个今日失败的贴吧执行复签，继续吗？\');">';
    echo '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') . '">';
    echo '<input type="hidden" name="retry_all" value="1">';
    echo '<button type="submit" class="cs-btn cs-btn-primary cs-btn-sm"><svg class="icon-inline" style="width:14px;height:14px;vertical-align:-2px;margin-right:4px" aria-hidden="true"><use href="#i-refresh"/></svg>一键复签失败贴吧（' . $failedCount . '）</button>';
    echo '</form>';
}
if (!empty($i['user']['bduss'])) {
    $num = 0;
    $count1 = $count2 = 0;
    $f = array();
    // 阶段 P：一次性获取所有贴吧的健康度数据，避免 N+1 查询
    require_once SYSTEM_ROOT . '/lib/class.SignManager.php';
    $healthRows = SignManager::tiebaHealth(UID, 30);
    $healthMap = array();
    foreach ($healthRows as $hr) {
        $healthMap[$hr['tieba']] = $hr;
    }
    $ex = $m->query('SELECT * FROM  `' . DB_NAME . '`.`' . DB_PREFIX . TABLE . '` WHERE  `uid` = ' . UID . ' ORDER BY `pid` ASC');
    $lpid = '';
    $seq = 0;
    while ($x = $m->fetch_array($ex)) {
        $num++;
        if ($x['no'] == '0') {
            $no = '<label style="margin-right:8px;cursor:pointer"><input type="radio" name="no[' . $x['id'] . ']" value="1"> 是</label><label style="cursor:pointer"><input type="radio" name="no[' . $x['id'] . ']" value="0" checked> 否</label>';
        } else {
            $no = '<label style="margin-right:8px;cursor:pointer"><input type="radio" name="no[' . $x['id'] . ']" value="1" checked> 是</label><label style="cursor:pointer"><input type="radio" name="no[' . $x['id'] . ']" value="0"> 否</label>';
        }
        if ($lpid != $x['pid'] || !isset($x['pid'])) {
            $lpid = $x['pid'];
            $f[$x['pid']] = '';
            $seq = 0;
        }
        $seq++;
        $rowStatusClass = '';
        if ($x['status'] != 0) {
            $rowStatusClass = 'status-error';
            $count2++;
            $statusCell = '<span class="cs-badge cs-badge-danger">异常</span> <span class="text-muted">#' . $x['status'] . '</span>';
            $tiebaDesc = '<br/><b>错误:</b>' . htmlspecialchars($x['last_error'], ENT_QUOTES, 'UTF-8');
        } elseif ($x['latest'] != $day) {
            $rowStatusClass = 'status-pending';
            $count2++;
            $statusCell = '<span class="cs-badge cs-badge-warning">待签</span>';
            $tiebaDesc = '';
        } else {
            $rowStatusClass = 'status-success';
            $count1++;
            $statusCell = '<span class="cs-badge cs-badge-success">已签</span>';
            $tiebaDesc = '';
        }
        if ($x['no'] != '0') {
            $rowStatusClass .= ' status-ignored';
        }

        $f[$x['pid']] .= '<tr class="' . $rowStatusClass . '"><td>' . $seq . '</td>';
        $tieba = htmlspecialchars($x['tieba'], ENT_QUOTES, 'UTF-8');
        $tieba_url = rawurlencode($x['tieba']);
        $f[$x['pid']] .= '<td class="wrap"><a title="' . $tieba . '" href="https://tieba.baidu.com/f?ie=utf-8&kw=' . $tieba_url . '" target="_blank">' . htmlspecialchars(mb_substr($x['tieba'], 0, 30, 'UTF-8'), ENT_QUOTES, 'UTF-8') . '</a>' . $tiebaDesc . '</td>';
        $f[$x['pid']] .= '<td>' . $statusCell . '</td>';

        // 阶段 P：健康度与签到成长信息（成长经验 / 排名 / 成功率）
        $hInfo = isset($healthMap[$x['tieba']]) ? $healthMap[$x['tieba']] : null;
        if ($hInfo && $hInfo['total'] > 0) {
            $hr = (int) $hInfo['rate'];
            $hTotal = (int) $hInfo['total'];
            $hSuccess = (int) $hInfo['success'];
            $hError = (int) $hInfo['error'];
            if ($hr >= 90) { $hBadge = 'cs-badge-success'; $hLabel = '优秀'; }
            elseif ($hr >= 70) { $hBadge = 'cs-badge-info'; $hLabel = '良好'; }
            elseif ($hr >= 50) { $hBadge = 'cs-badge-warning'; $hLabel = '一般'; }
            else { $hBadge = 'cs-badge-danger'; $hLabel = '较差'; }

            $extraMetrics = '';
            if (!empty($hInfo['last_exp'])) {
                $extraMetrics .= ' <span class="cs-badge cs-badge-brand" style="margin-left:4px">+' . (int) $hInfo['last_exp'] . '经验</span>';
            }
            if (!empty($hInfo['last_rank'])) {
                $extraMetrics .= ' <span class="text-muted" style="font-size:11px;margin-left:4px">第' . (int) $hInfo['last_rank'] . '签</span>';
            }
            $f[$x['pid']] .= '<td><span class="cs-badge ' . $hBadge . '">' . $hr . '%</span>' . $extraMetrics . ' <span class="text-muted" style="font-size:11px">' . $hSuccess . '/' . $hTotal . '</span></td>';
        } else {
            $f[$x['pid']] .= '<td><span class="text-muted">暂无数据</span></td>';
        }
        $f[$x['pid']] .= '<td>' . $no . '</td></tr>';
    }
    echo '<div class="cs-alert cs-alert-info" id="tb_num"><svg class="cs-alert-icon" aria-hidden="true"><use href="#i-info"/></svg><div class="cs-alert-body">当前已列出 <strong>' . $num . '</strong> 个贴吧。已签到 <strong>' . $count1 . '</strong> 个，还有 <strong>' . $count2 . '</strong> 个等待签到<br/>PID 即为 账号ID';
    if (!ISVIP) {
        echo '，您最多可以添加 ' . option::get('tb_max') . ' 个贴吧';
    }
    echo '，移动设备可能需要左右滑动表格才能显示所有内容<br/>功能：<a href="javascript:;" onclick="refreshTiebaList();" style="cursor:pointer">刷新贴吧列表</a> | <a href="setting.php?mod=showtb&clean" onclick="return confirm(\'你真的要清空所有贴吧吗？\');" style="cursor:pointer">清空列表</a>';
    if (option::get('enable_addtieba') == 1) {
        echo ' | <a href="javascript:;" data-toggle="modal" data-target="#AddTieba" style="cursor:pointer">手动添加贴吧</a>';
    }
    echo ' | <a href="setting.php?mod=showtb&reset" onclick="return confirm(\'要把所有出错的贴吧重置为待签到状态吗？今天已签到成功的不会受影响。\');" style="cursor:pointer">重签出错吧</a>';
    echo ' | <a href="javascript:;" onclick="go(\'submit_button\');" style="cursor:pointer">前往底部</a></div></div>';
    if (!empty($f)) {
        echo '<div style="display:flex;flex-wrap:wrap;gap:10px;align-items:center;margin:14px 1% 0">';
        echo '<label style="margin:0;font-size:12px;color:var(--color-muted)">账号</label>';
        echo '<select id="showtb-acc" class="form-control" style="width:auto" onchange="showtbpanel(this.value);">';
        foreach ($i['user']['baidu_portrait'] as $pkey => $pval) {
            echo '<option value="' . htmlspecialchars($pkey, ENT_QUOTES, 'UTF-8') . '">' . htmlspecialchars(($pval ?: $i['user']['baidu_portrait'][$pkey]), ENT_QUOTES, 'UTF-8') . '</option>';
        }
        echo '</select>';
        echo '<input id="showtb-search" class="form-control" placeholder="搜索当前账号的贴吧…" style="width:200px;height:30px">';
        echo '<div id="showtb-filters" class="cs-filter-group" style="display:inline-flex;gap:4px">';
        echo '<button type="button" class="cs-btn cs-btn-xs cs-btn-primary" data-status-filter="">全部</button>';
        echo '<button type="button" class="cs-btn cs-btn-xs cs-btn-ghost" data-status-filter="status-success">已签</button>';
        echo '<button type="button" class="cs-btn cs-btn-xs cs-btn-ghost" data-status-filter="status-pending">待签</button>';
        echo '<button type="button" class="cs-btn cs-btn-xs cs-btn-ghost" data-status-filter="status-error">异常</button>';
        echo '<button type="button" class="cs-btn cs-btn-xs cs-btn-ghost" data-status-filter="status-ignored">已忽略</button>';
        echo '</div>';
        echo '<span id="showtb-pager" style="display:inline-flex;gap:4px;align-items:center;font-size:12px"></span>';
        echo '</div>';
        echo '<form action="setting.php?mod=showtb&set" method="post" id="showtb-form">';
        echo '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') . '">';
        foreach ($i['user']['baidu_portrait'] as $pkey => $pval) {
            if (isset($f[$pkey])) {
                echo '<div id="tbpidpanel_' . $pkey . '" class="tbpanel" style="display:none;padding-top:16px">';
                echo '<div style="text-align:right;margin-bottom:10px"><form action="setting.php?mod=showtb" method="post" style="display:inline"><input type="hidden" name="csrf_token" value="' . htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') . '"><input type="hidden" name="restore_pid" value="' . (int) $pkey . '"><button type="submit" class="cs-btn cs-btn-secondary cs-btn-sm" title="将该账号下所有贴吧恢复为自动签到"><svg class="cs-icon" style="width:12px;height:12px" aria-hidden="true"><use href="#i-refresh"/></svg> 恢复此账号签到</button></form></div>';
                echo '<div class="cs-table-wrap"><div class="cs-table-scroll"><table class="cs-table"><thead><tr>';
                echo '<th>序号</th>';
                echo '<th>贴吧</th>';
                echo '<th>状态</th>';
                echo '<th>近30天成功率</th>';
                echo '<th>忽略</th>';
                echo '</tr></thead><tbody>';
                echo $f[$pkey] . '</tbody></table></div></div></div>';
            } else {
                echo '<div id="tbpidpanel_' . $pkey . '" class="tbpanel cs-alert cs-alert-warning" style="display:none;margin-top:16px"><svg class="cs-alert-icon" aria-hidden="true"><use href="#i-alert"/></svg><div class="cs-alert-body">还没有添加任何贴吧，点击上方的按钮扫描贴吧。<br/>扫描不到贴吧？请检查百度账号BDUSS是否已经失效（尝试重新绑定）</div></div>';
            }
        }
        echo '<div id="showtb-savebar" class="u-toolbar-sticky" style="margin-top:16px;justify-content:flex-end"><button type="submit" id="submit_button" class="cs-btn cs-btn-primary"><svg class="cs-icon" style="width:16px;height:16px" aria-hidden="true"><use href="#i-check"/></svg> 提交更改</button></div></form>';
    } else {
        echo '<div class="cs-alert cs-alert-warning" role="alert"><svg class="cs-alert-icon" aria-hidden="true"><use href="#i-alert"/></svg><div class="cs-alert-body">你已经成功绑定了一个贴吧账号，但是还没有添加任何贴吧，点击上方的按钮扫描贴吧。<br/>扫描不到贴吧？请检查百度账号BDUSS是否已经失效（尝试重新绑定）</div></div>';
    }
    echo '<div style="text-align:center;margin-top:16px"><a href="javascript:scroll(0,0)" style="color:var(--color-muted);font-size:12px">返回顶部</a></div>';
    ?>
    <script>
    (function () {
        var PAGE_SIZE = 50;
        var panels = document.querySelectorAll('.tbpanel');
        var search = document.getElementById('showtb-search');
        var pager = document.getElementById('showtb-pager');
        var currentPid = null;
        var currentStatusFilter = '';

        function rowsOf(panel) {
            return Array.prototype.slice.call(panel.querySelectorAll('tbody tr'));
        }
        function renderPage(panel, page, keyword) {
            var rows = rowsOf(panel);
            var visible = rows.filter(function (r) {
                var matchKw = !keyword || r.textContent.toLowerCase().indexOf(keyword) >= 0;
                var matchStatus = !currentStatusFilter || r.classList.contains(currentStatusFilter);
                return matchKw && matchStatus;
            });
            var pages = Math.max(1, Math.ceil(visible.length / PAGE_SIZE));
            if (page > pages) { page = pages; }
            rows.forEach(function (r) { r.style.display = 'none'; });
            visible.slice((page - 1) * PAGE_SIZE, page * PAGE_SIZE).forEach(function (r) { r.style.display = ''; });
            if (pager) {
                var html = '';
                if (pages > 1 || keyword || currentStatusFilter) {
                    html += '共 ' + visible.length + ' 条';
                    if (pages > 1) {
                        html += '　';
                        for (var p = 1; p <= pages; p++) {
                            html += '<a href="javascript:;" data-page="' + p + '" style="' + (p === page ? 'font-weight:700;color:var(--color-brand,#6d5ae0)' : 'color:inherit') + ';text-decoration:none">' + p + '</a>';
                        }
                    }
                }
                pager.innerHTML = html;
                pager.querySelectorAll('a[data-page]').forEach(function (a) {
                    a.addEventListener('click', function () {
                        renderPage(panel, parseInt(a.getAttribute('data-page'), 10), keyword);
                    });
                });
            }
        }
        window.showtbSearchApply = function () {
            var panel = document.getElementById('tbpidpanel_' + currentPid);
            if (!panel) { return; }
            renderPage(panel, 1, search && search.value ? search.value.trim().toLowerCase() : '');
        };

        // 绑定状态标签筛选
        var filterBtns = document.querySelectorAll('[data-status-filter]');
        filterBtns.forEach(function (btn) {
            btn.addEventListener('click', function () {
                filterBtns.forEach(function (b) {
                    b.classList.remove('cs-btn-primary');
                    b.classList.add('cs-btn-ghost');
                });
                btn.classList.remove('cs-btn-ghost');
                btn.classList.add('cs-btn-primary');
                currentStatusFilter = btn.getAttribute('data-status-filter') || '';
                window.showtbSearchApply();
            });
        });
        var oldShowtbpanel = null;
        window.addEventListener('load', function () {
            oldShowtbpanel = window.showtbpanel;
            window.showtbpanel = function (pid) {
                currentPid = pid;
                if (oldShowtbpanel) { oldShowtbpanel(pid); }
                var sel = document.getElementById('showtb-acc');
                if (sel && sel.value !== pid) { sel.value = pid; }
                window.showtbSearchApply();
            };
        });
        if (search) { search.addEventListener('input', window.showtbSearchApply); }
        //初始化：默认展示第一个账号
        var first = document.querySelector('.tbpanel');
        if (first) {
            var pid = first.id.replace('tbpidpanel_', '');
            var sel = document.getElementById('showtb-acc');
            if (sel) { sel.value = pid; }
            currentPid = pid;
            window.showtbSearchApply();
        }
    })();
    </script>
<?php
} else {
    echo '<div class="cs-alert cs-alert-danger"><svg class="cs-alert-icon" aria-hidden="true"><use href="#i-alert"/></svg><div class="cs-alert-body">无法列出贴吧列表，因为当前没有绑定百度账号（请在「百度账号管理」页面绑定）</div></div>';
}

?>
<div class="modal fade" id="AddTieba" tabindex="-1" role="dialog" aria-labelledby="AddTiebaLabel" aria-hidden="true">
 <div class="modal-dialog">
     <div class="modal-content">
            <div class="modal-header">
             <button type="button" class="close" data-dismiss="modal"><span aria-hidden="true">&times;</span><span class="sr-only">Close</span></button>
                <h4 class="modal-title" id="AddTiebaLabel">手动添加贴吧</h4>
            </div>
         <div class="modal-body">
               <form action="setting.php?mod=showtb" method="post">
                   <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">
                   <div class="input-group">
                      <span class="input-group-addon">请选择对应账号</span>
                     <select name="pid" required class="form-control">
                            <?php foreach ($i['user']['bduss'] as $key => $value) {
                                $name = empty($i['user']['baidu'][$key]) ? ' [' . $i['user']['baidu_portrait'][$key] . ']' : (' (' . $i['user']['baidu'][$key] . ' [' . $i['user']['baidu_portrait'][$key] . ']' . ')');
                                echo '<option value="' . htmlspecialchars($key, ENT_QUOTES, 'UTF-8') . '">' . htmlspecialchars($key . $name, ENT_QUOTES, 'UTF-8') . '</option>';
                            }
                            ?>
                        </select>
                  </div>
                 <br/>
                  <div class="input-group">
                      <span class="input-group-addon">请输入贴吧名称</span>
                     <input type="text" class="form-control" name="add" required>
                   </div>
         </div>
         <div class="modal-footer">
             <button type="button" class="btn btn-default" data-dismiss="modal">取消</button>
             <button type="submit" class="btn btn-primary">提交贴吧</button>
            </div></form>
      </div><!-- /.modal-content -->
 </div><!-- /.modal-dialog -->
</div><!-- /.modal -->

<script>
function showtbpanel(pid) {
    $('.tbpanel').css('display', 'none');
    $("#tbpidpanel_" + pid).fadeIn(200);
    $('.pidlist').attr('aria-selected', 'false');
    $('.pidlist').filter(function() { return this.onclick && this.onclick.toString().indexOf(pid) > -1; }).attr('aria-selected', 'true');
}
$(document).ready(function() {
    $(".pidlist:first").attr('aria-selected', 'true');
    $(".tbpanel:first").css('display', '');
});

function refreshTiebaList() {
    var ds = $('#tb_num');
    ds.html('<svg class="cs-alert-icon" aria-hidden="true"><use href="#i-refresh"/></svg><div class="cs-alert-body">正在刷新贴吧列表，可能需要较长时间，请耐心等待...</div>');
    $.ajax({
        type: "GET",
        url: 'setting.php?mod=showtb&refreshTiebaList',
        dataType: "text",
        beforeSend: function() {},
        success: function(data) {
            if (data == "1") self.location.reload();
        },
        complete: function(XMLHttpRequest, textStatus) {},
        error: function() {
            self.location.reload();
        }
    });
}
</script>

