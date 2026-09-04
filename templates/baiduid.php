<?php if (!defined('SYSTEM_ROOT')) {
    die('Insufficient Permissions');
}
global $i;
global $m;
$allowBind = (option::get('bduss_num') != '-1' || ISVIP);
?>

<div class="cs-tabs" id="PageTab">
    <ul class="cs-tablist" role="tablist">
        <li><button type="button" class="cs-tab" role="tab" id="tab-adminid" aria-controls="adminid" aria-selected="true">管理账号</button></li>
        <?php if ($allowBind) { ?>
            <li><button type="button" class="cs-tab" role="tab" id="tab-newid" aria-controls="newid" aria-selected="false">扫码绑定</button></li>
            <li><button type="button" class="cs-tab" role="tab" id="tab-newid2" aria-controls="newid2" aria-selected="false">手动绑定</button></li>
        <?php } ?>
    </ul>
</div>

<!-- PAGE1: ADMINID -->
<div class="cs-tabpanel" id="adminid" role="tabpanel" aria-labelledby="tab-adminid">
<?php if (option::get('bduss_num') == '-1' && ISVIP != true) {
    ?>
<div class="cs-alert cs-alert-danger" role="alert">
    <svg class="cs-alert-icon" aria-hidden="true"><use href="#i-alert"/></svg>
    <div class="cs-alert-body">本站禁止绑定百度账号，当前已绑定 <?php echo sizeof($i['user']['bduss']) ?> 个账号，PID 即为 账号ID</div>
</div>
    <?php
} elseif (empty($i['user']['bduss'])) {
    ?>
<div class="cs-alert cs-alert-warning">
    <svg class="cs-alert-icon" aria-hidden="true"><use href="#i-alert"/></svg>
    <div class="cs-alert-body">
        无法显示列表，因为当前还没有绑定任何百度账号<br/>若要绑定账号，请点击「扫码绑定」或「手动绑定」标签
        <?php if (option::get('bduss_num') != '0' && ISVIP != true) {
            echo '，您最多能够绑定 ' . option::get('bduss_num') . ' 个账号';
        } ?>
    </div>
</div>
    <?php
} else {
    ?>
<div class="cs-alert cs-alert-info">
    <svg class="cs-alert-icon" aria-hidden="true"><use href="#i-info"/></svg>
    <div class="cs-alert-body">当前已绑定 <?php echo sizeof($i['user']['bduss']) ?> 个账号，PID 即为 账号ID
    <?php if (option::get('bduss_num') != '0' && ISVIP != true) {
        echo '，您最多能够绑定 ' . option::get('bduss_num') . ' 个账号';
    } ?>。</div>
</div>
    <?php
} if (!empty($i['user']['bduss'])) {
    ?>
<div class="cs-table-wrap">
<table class="cs-table">
  <thead>
    <tr>
      <th>PID</th>
      <th style="width:20%">百度名称</th>
      <th style="width:20%">Portrait</th>
      <th style="width:30%">BDUSS</th>
      <th style="width:20%">STOKEN</th>
      <th>操作</th>
    </tr>
  </thead>
  <tbody>
    <?php
    foreach ($i['user']['bduss'] as $key => $value) {
        echo '<tr><td>' . $key . '</td>';
        $name = empty($i['user']['baidu'][$key]) ? '未记录百度ID' : htmlspecialchars($i['user']['baidu'][$key], ENT_QUOTES, 'UTF-8');
        if ($name == '[E]') {
            $name = '<span class="cs-badge cs-badge-danger">已失效</span>';
        }
        echo '<td>' . $name . '</td>';
        echo '<td><input type="text" class="form-control" readonly value="' . htmlspecialchars($i['user']['baidu_portrait'][$key], ENT_QUOTES, 'UTF-8') . '"></td>';
        echo '<td><input type="text" class="form-control" readonly value="' . htmlspecialchars($value, ENT_QUOTES, 'UTF-8') . '"></td>';
        echo '<td><input type="text" class="form-control" readonly value="' . htmlspecialchars($i['user']['stoken'][$key], ENT_QUOTES, 'UTF-8') . '"></td>';
        echo '<td><div style="display:flex;flex-direction:column;gap:6px">';
        echo '<a class="cs-btn cs-btn-danger cs-btn-sm" href="setting.php?mod=baiduid&del=' . htmlspecialchars($key, ENT_QUOTES, 'UTF-8') . '" onclick="return confirm(\'确定要解绑该账号吗？\');"><svg class="cs-icon" style="width:12px;height:12px" aria-hidden="true"><use href="#i-x"/></svg> 解绑</a>';
        echo '<form method="post" action="setting.php" style="display:flex;flex-direction:column;gap:4px">';
        echo '<input type="hidden" name="mod" value="baiduid">';
        echo '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') . '">';
        echo '<input type="hidden" name="proxy_pid" value="' . htmlspecialchars($key, ENT_QUOTES, 'UTF-8') . '">';
        echo '<input type="text" class="form-control input-sm" name="proxy" placeholder="代理(留空跟随全局)" value="' . htmlspecialchars(isset($i['user']['bduss_proxy'][$key]) ? $i['user']['bduss_proxy'][$key] : '', ENT_QUOTES, 'UTF-8') . '" title="格式：http://host:port 或 socks5://user:pass@host:port，off 为禁用">';
        echo '<button type="submit" class="cs-btn cs-btn-secondary cs-btn-sm">保存代理</button>';
        echo '</form></div></td></tr>';
    }
    ?>
  </tbody>
</table>
</div>
<div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;margin:0 0 16px">
    <b>聚合操作</b>
    <button type="button" class="cs-btn cs-btn-warning cs-btn-sm" id="sign-run-all">⚡ 立即签到（我的全部账号）</button>
    <button type="button" class="cs-btn cs-btn-secondary cs-btn-sm" id="sign-run-one">立即签到（选中账号）</button>
    <span class="text-muted" style="font-size:12px" id="sign-summary">单次立即签到最多处理 15 个贴吧，其余由计划任务继续完成</span>
</div>
<div style="margin-top:18px">
    <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;margin-bottom:10px">
        <b>我的贴吧 · 关注与等级</b>
        <select id="mo-pid" class="form-control" style="width:auto">
            <?php
            foreach ($i['user']['bduss'] as $moKey => $moVal) {
                $moName = empty($i['user']['baidu'][$moKey]) ? ('PID ' . $moKey) : $i['user']['baidu'][$moKey];
                echo '<option value="' . (int) $moKey . '">' . htmlspecialchars($moName, ENT_QUOTES, 'UTF-8') . '（ID ' . (int) $moKey . '）</option>';
            }
            ?>
        </select>
        <button type="button" class="cs-btn cs-btn-primary cs-btn-sm" id="mo-view">查看关注与等级</button>
        <button type="button" class="cs-btn cs-btn-secondary cs-btn-sm" id="mo-refresh">强制刷新</button>
        <span class="text-muted" style="font-size:12px">数据缓存 30 分钟，来自手机端贴吧接口</span>
    </div>
    <div id="mo-summary" class="text-muted" style="margin-bottom:8px"></div>
    <div id="mo-forums" style="display:flex;flex-wrap:wrap;gap:6px"></div>
</div>
<script type="text/javascript">
    (function () {
        var moLoading = false;
        function moLoad(force) {
            if (moLoading) { return; }
            var pid = $('#mo-pid').val();
            if (!pid) { return; }
            moLoading = true;
            $('#mo-summary').text(force ? '正在强制刷新…' : '正在加载…');
            $.ajax({
                url: 'ajax.php?mod=baiduid:moindex',
                dataType: 'json',
                type: 'POST',
                data: { 'pid': pid, 'force': force ? '1' : '0', 'csrf_token': $('input[name="csrf_token"]').val() },
                success: function (x) {
                    moLoading = false;
                    if (x.error !== 0) { $('#mo-summary').text(x.msg || '加载失败'); return; }
                    var box = $('#mo-forums');
                    box.empty();
                    $.each(x.forums, function (i, f) {
                        var badge = $('<span class="cs-badge" style="font-size:12px;padding:4px 8px"></span>')
                            .text(f.name + ' Lv.' + f.level + (f.signed ? ' · 已签' : ' · 未签'))
                            .css(f.signed ? { 'background': 'rgba(60,180,90,.15)', 'color': '#3cb45a' } : { 'background': 'rgba(255,160,0,.15)', 'color': '#ff9800' });
                        box.append(badge);
                    });
                    $('#mo-summary').text('共关注 ' + x.total + ' 个吧，今日已签 ' + x.signed + ' / ' + x.total + '（更新于 ' + new Date(x.fetched_at * 1000).toLocaleTimeString() + '）');
                },
                error: function () {
                    moLoading = false;
                    $('#mo-summary').text('加载失败，请稍后重试');
                }
            });
        }
        $('#mo-view').on('click', function () { moLoad(false); });
        $('#mo-refresh').on('click', function () { moLoad(true); });

        function signRun(pid) {
            var $btn = pid > 0 ? $('#sign-run-one') : $('#sign-run-all');
            if ($btn.prop('disabled')) { return; }
            $btn.prop('disabled', true);
            $('#sign-summary').text('正在签到，请稍候…');
            $.ajax({
                url: 'ajax.php?mod=sign:run',
                dataType: 'json',
                type: 'POST',
                data: { 'pid': pid, 'csrf_token': $('input[name="csrf_token"]').val() },
                success: function (x) {
                    $btn.prop('disabled', false);
                    if (x.error !== 0) {
                        $('#sign-summary').text(x.msg || '签到失败');
                        if (window.CS && CS.toast) { CS.toast('签到未执行', { type: 'warning', body: x.msg || '' }); }
                        return;
                    }
                    var msg = '今日已签 ' + x.signed + ' · 失败 ' + x.failed + ' · 等待 ' + x.waiting + (pid > 0 && x.done > 0 ? '（本次该账号处理 ' + x.done + ' 个吧）' : '');
                    $('#sign-summary').text(msg);
                    if (window.CS && CS.toast) { CS.toast('签到完成', { type: 'success', body: msg }); }
                },
                error: function () {
                    $btn.prop('disabled', false);
                    $('#sign-summary').text('签到请求失败，请稍后重试');
                }
            });
        }
        $('#sign-run-all').on('click', function () {
            if (!window.confirm('立即为我的全部账号执行签到？未到时段或超过单次上限的部分由计划任务继续完成。')) { return; }
            signRun(0);
        });
        $('#sign-run-one').on('click', function () {
            var pid = $('#mo-pid').val();
            if (!pid) { return; }
            signRun(parseInt(pid, 10));
        });
    })();
</script>
    <?php
} ?>
</div>

<!-- PAGE2: NEWID -->
<div class="cs-tabpanel" id="newid" role="tabpanel" aria-labelledby="tab-newid" hidden>
  <script type="text/javascript">
      $(document).ready(function(){
          $("#addbdid_form").submit(function(e){
              $('#addbdid_submit').attr('disabled',true);
              $('#addbdid_prog').css({"display":""});
              $('.addbdis_text').html('正在拉取验证信息...');
              $('#addbdid_pb').css({"width":"25%"});
              $.ajax({
                  url:"ajax.php?mod=baiduid:qrlogin",
                  async:true,
                  dataType:"json",
                  type:'POST',
                  data: {
                      'sign': $('#sign').val(),
                      'csrf_token': $('input[name="csrf_token"]').val()
                  },
                  complete: function(x,y) {
                      $('#addbdid_submit').removeAttr('disabled');
                  },
                  success: function(x) {
                      if(x.error == 0) {
                          $('#addbdid_msg').text('成功绑定百度账号：' + x.name);
                          $('#addbdid_pb').css({"width":"100%"});
                          $('#addbdid_prog').fadeOut(500);
                          if (window.CS && CS.toast) { CS.toast('绑定成功', { type: 'success', body: x.name }); }
                      } else {
                          $('#addbdid_msg').text(x.msg);
                      }
                  },
                  error: function(x) {
                      $('#addbdid_prog').fadeOut(500);
                      $('#addbdid_msg').text('操作失败，未知错误。这可能是网络原因所致，请刷新重试#2');
                  }
              });
          });
      });
  </script>
<div id="addbdid_prog" style="display:none">
  <b><span class="addbdis_text">正在拉取验证信息...</span></b><br/><br/>
  <div class="progress">
    <div class="progress-bar progress-bar-striped active" id="addbdid_pb" role="progressbar" aria-valuemin="0" aria-valuemax="100" style="width: 25%">
    </div>
  </div>
</div>
<div class="cs-alert cs-alert-warning" id="addbdid_msg">
    <svg class="cs-alert-icon" aria-hidden="true"><use href="#i-info"/></svg>
    <div class="cs-alert-body">如果您多次尝试绑定失败，不妨试试 <a href="https://bduss.nest.moe" target="_blank">手动获取</a> 吧！</div>
</div>
<form method="post" id="addbdid_form" onsubmit="return false;">
  <?php
  $login_info = misc::get_login_qrcode();
  $qr_imgurl = preg_match('#^(https?://|//)#i', (string) $login_info['imgurl']) ? $login_info['imgurl'] : '';
  $qr_sign = htmlspecialchars((string) $login_info['sign'], ENT_QUOTES, 'UTF-8');
  ?>
  <?php if ($qr_imgurl !== '') { ?>
  <img src="<?php echo htmlspecialchars($qr_imgurl, ENT_QUOTES, 'UTF-8'); ?>" alt="qrcode" class="thumbnail center-block">
  <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">
  <div id="addbdid_ver" style="display: none">
    <input type="hidden" id="sign" value="<?php echo $qr_sign; ?>">
  </div>
  <div style="display:flex;flex-direction:column;gap:8px;margin-top:12px">
      <a href="https://wappass.baidu.com/wp/?qrlogin=&sign=<?php echo $qr_sign; ?>" class="cs-btn cs-btn-secondary cs-btn-block" target="_blank"><svg class="cs-icon" style="width:14px;height:14px" aria-hidden="true"><use href="#i-log-in"/></svg> 网页授权</a>
      <input type="submit" id="addbdid_submit" class="cs-btn cs-btn-primary cs-btn-block" value="点击绑定">
  </div>
  <?php } else { ?>
  <div class="cs-alert cs-alert-danger" role="alert">
      <svg class="cs-alert-icon" aria-hidden="true"><use href="#i-alert"/></svg>
      <div class="cs-alert-body">
        <strong>二维码获取失败</strong>：服务器当前无法连接百度的二维码接口（网络原因或接口临时不可用）。<br/>
        <br/>你可以：
        <br/>1. 稍后刷新本页重试；
        <br/>2. 切换到「手动绑定」标签，直接粘贴 BDUSS 绑定（页面内有图文教程）。
      </div>
  </div>
  <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">
  <?php } ?>
</form>

<details class="cs-collapse">
    <summary>提示</summary>
    <div class="cs-collapse-body">
      <ul>
        <li>可直接点击 "网页授权" 并在新打开页授权，无需客户端扫码，非移动设备的UA可能会导致无法打开</li>
        <li>若二维码失效请刷新本页以刷新二维码</li>
      </ul>
    </div>
</details>
</div>

<!-- PAGE3: NEWID2 -->
<div class="cs-tabpanel" id="newid2" role="tabpanel" aria-labelledby="tab-newid2" hidden>
<form action="setting.php" method="post">
<input type="hidden" name="mod" value="baiduid">
<input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">
<div class="cs-input-group">
  <span class="cs-input-addon">输入BDUSS</span>
  <input type="text" class="form-control" name="bduss" id="bduss_input">
</div>
<div class="cs-input-group">
  <span class="cs-input-addon">输入STOKEN</span>
  <input type="text" class="form-control" name="stoken" id="stoken_input">
</div>
<button type="submit" class="cs-btn cs-btn-primary"><svg class="cs-icon" style="width:14px;height:14px" aria-hidden="true"><use href="#i-check"/></svg> 点击提交</button>
</form>

<details class="cs-collapse">
    <summary>批量导入（多账号粘贴）</summary>
    <div class="cs-collapse-body">
        <form action="setting.php" method="post">
            <input type="hidden" name="mod" value="baiduid">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">
            <input type="hidden" name="batch_import" value="1">
            <textarea class="form-control" style="height:150px" name="batch_bduss" placeholder="每行一个账号，支持以下格式：&#10;BDUSS值&#10;BDUSS值----STOKEN值&#10;BDUSS值,STOKEN值&#10;BDUSS=BDUSS值;STOKEN=STOKEN值"></textarea>
            <div style="margin-top:10px;display:flex;align-items:center;gap:10px;flex-wrap:wrap">
                <button type="submit" class="cs-btn cs-btn-primary">开始导入</button>
                <span class="text-muted" style="font-size:12px">单次最多 50 行；系统会自动校验每个 Cookie 并识别账号名，无效行将被跳过</span>
            </div>
        </form>
    </div>
</details>

<details class="cs-collapse">
    <summary>贴吧账号手动绑定教程（Chrome）</summary>
    <div class="cs-collapse-body">
        <b>以下是贴吧账号手动绑定教程：</b><br/><br/>
        1.使用 Chrome 或 Chromium 内核的浏览器
        <br/><br/>2.打开百度首页 <a href="http://www.baidu.com" target="_blank">http://www.baidu.com/</a>
           <br/><br/>3.右键，点击 <b>查看网页信息</b>
        <br/><br/>4.确保已经登录百度，然后点击 <b>显示 Cookie 和网站数据</b>
        <br/><br/>5.如图，依次展开 <b>passport.baidu.com</b> -> <b>Cookie</b> -> <b>BDUSS</b>，stoken获取方式同理
        <br/><br/><picture><source srcset="source/doc/baiduid.webp" type="image/webp"><img src="source/doc/baiduid.png" alt="BDUSS 获取教程" style="max-width:100%"></picture>
        <br/><br/>6.按下 Ctrl+A 全选，然后复制并输入到上面的表单即可
        <br/><br/>请注意，一旦退出登录，可能导致 BDUSS 失效，因此建议在隐身模式下登录
    </div>
</details>
</div>

<?php doAction('baiduid'); ?>
<script type="text/javascript">
// v4: 初始化 baiduid tabs（CS.tabs 自动初始化 .cs-tabs，此处兜底）
if (window.CS && CS.tabs) { CS.tabs.init(document.getElementById('PageTab')); }
</script>
<div style="text-align:center;color:var(--color-muted);font-size:12px;padding:24px 0 16px"><?php echo SYSTEM_FN ?> V<?php echo SYSTEM_VER  . ' ' . SYSTEM_VER_NOTE ?> // 作者: <a href="https://kenvix.com" target="_blank">Kenvix</a> &amp; <a href="http://www.mokeyjay.com/" target="_blank">mokeyjay</a> &amp; <a href="http://fyy1999.lofter.com/" target="_blank">FYY</a></div>
