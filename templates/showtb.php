<?php if (!defined('SYSTEM_ROOT')) {
    die('Insufficient Permissions');
}
global $m,$i;
$day = date('d');
if (isset($_GET['ok'])) {
    echo '<div class="cs-alert cs-alert-success"><svg class="cs-alert-icon" aria-hidden="true"><use href="#i-check"/></svg><div class="cs-alert-body">设置保存成功</div></div>';
}
if (isset($_GET['retry_msg'])) {
    echo '<div class="cs-alert cs-alert-info"><svg class="cs-alert-icon" aria-hidden="true"><use href="#i-refresh"/></svg><div class="cs-alert-body">' . htmlspecialchars((string) $_GET['retry_msg'], ENT_QUOTES, 'UTF-8') . '</div></div>';
}

// 一键复签失败贴吧表单
$todayDay = date('d');
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
        }
        $f[$x['pid']] .= '<tr><td>' . $x['id'] . '</td>';
        $tieba = htmlspecialchars($x['tieba'], ENT_QUOTES, 'UTF-8');
        $tieba_url = rawurlencode($x['tieba']);
        $f[$x['pid']] .= '<td class="wrap"><a title="' . $tieba . '" href="https://tieba.baidu.com/f?ie=utf-8&kw=' . $tieba_url . '" target="_blank">' . htmlspecialchars(mb_substr($x['tieba'], 0, 30, 'UTF-8'), ENT_QUOTES, 'UTF-8') . '</a>';
        if ($x['status'] != 0) {
            $count2++;
            $f[$x['pid']] .= '<br/><b>错误:</b>' . htmlspecialchars($x['last_error'], ENT_QUOTES, 'UTF-8') . '</td>';
            $f[$x['pid']] .= '<td><span class="cs-badge cs-badge-danger">异常</span> <span class="text-muted">#' . $x['status'] . '</span>';
        } elseif ($x['latest'] != $day) {
            $count2++;
            $f[$x['pid']] .= '</td><td><span class="cs-badge cs-badge-warning">待签</span>';
        } else {
            $count1++;
            $f[$x['pid']] .= '</td><td><span class="cs-badge cs-badge-success">正常</span>';
        }
        // 阶段 P：健康度列（近 30 天成功率）
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
            $f[$x['pid']] .= '</td><td><span class="cs-badge ' . $hBadge . '">' . $hr . '%</span> <span class="text-muted" style="font-size:11px">' . $hSuccess . '/' . $hTotal . '（失败 ' . $hError . '）</span>';
        } else {
            $f[$x['pid']] .= '</td><td><span class="text-muted">暂无数据</span>';
        }
        $f[$x['pid']] .= '</td><td>' . $no . '</td></tr>';
    }
    echo '<div class="cs-alert cs-alert-info" id="tb_num"><svg class="cs-alert-icon" aria-hidden="true"><use href="#i-info"/></svg><div class="cs-alert-body">当前已列出 <strong>' . $num . '</strong> 个贴吧。已签到 <strong>' . $count1 . '</strong> 个，还有 <strong>' . $count2 . '</strong> 个等待签到<br/>PID 即为 账号ID';
    if (!ISVIP) {
        echo '，您最多可以添加 ' . option::get('tb_max') . ' 个贴吧';
    }
    echo '，移动设备可能需要左右滑动表格才能显示所有内容<br/>功能：<a href="javascript:;" onclick="refreshTiebaList();" style="cursor:pointer">刷新贴吧列表</a> | <a href="setting.php?mod=showtb&clean" onclick="return confirm(\'你真的要清空所有贴吧吗？\');" style="cursor:pointer">清空列表</a>';
    if (option::get('enable_addtieba') == 1) {
        echo ' | <a href="javascript:;" data-toggle="modal" data-target="#AddTieba" style="cursor:pointer">手动添加贴吧</a>';
    }
    echo ' | <a href="setting.php?mod=showtb&reset" style="cursor:pointer">重签出错吧</a>';
    echo ' | <a href="javascript:;" onclick="go(\'submit_button\');" style="cursor:pointer">前往底部</a></div></div>';
    if (!empty($f)) {
        echo '<div class="cs-tabs"><ul class="cs-tablist" role="tablist">';
        foreach ($i['user']['baidu'] as $pkey => $pval) {
            echo '<li><button type="button" class="cs-tab pidlist" role="tab" onclick="showtbpanel(\'' . htmlspecialchars($pkey, ENT_QUOTES, 'UTF-8') . '\');">' . htmlspecialchars(($pval ?: $i['user']['baidu_portrait'][$pkey]), ENT_QUOTES, 'UTF-8') . '</button></li>';
        }
        echo '</ul></div>';
        echo '<form action="setting.php?mod=showtb&set" method="post">';
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
        echo '<div style="margin-top:16px"><button type="submit" id="submit_button" class="cs-btn cs-btn-primary cs-btn-lg cs-btn-block"><svg class="cs-icon" style="width:16px;height:16px" aria-hidden="true"><use href="#i-check"/></svg> 提交更改</button></div></form>';
    } else {
        echo '<div class="cs-alert cs-alert-warning" role="alert"><svg class="cs-alert-icon" aria-hidden="true"><use href="#i-alert"/></svg><div class="cs-alert-body">你已经成功绑定了一个贴吧账号，但是还没有添加任何贴吧，点击上方的按钮扫描贴吧。<br/>扫描不到贴吧？请检查百度账号BDUSS是否已经失效（尝试重新绑定）</div></div>';
    }
    echo '<div style="text-align:center;margin-top:16px"><a href="javascript:scroll(0,0)" style="color:var(--color-muted);font-size:12px">返回顶部</a></div>';
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

<div style="text-align:center;color:var(--color-muted);font-size:12px;padding:24px 0 16px"><?php echo SYSTEM_FN ?> V<?php echo SYSTEM_VER  . ' ' . SYSTEM_VER_NOTE ?> // 作者: <a href="https://kenvix.com" target="_blank">Kenvix</a>  &amp; <a href="http://www.mokeyjay.com/" target="_blank">mokeyjay</a>
