<?php if (!defined('SYSTEM_ROOT')) {
    die('Insufficient Permissions');
}  if (ROLE != 'admin') {
    msg('权限不足！');
}
global $m,$i;
doAction('admin_update_1');
?>
<section class="dashboard-hero" style="margin-top:16px">
    <div class="eyebrow hero-eyebrow">EXTENSIONS · 升级</div>
    <h1>系统更新</h1>
    <p>按 GitHub 正式 Release 检查新版本，检测到更新后会显示版本说明与升级入口。</p>
    <div class="dashboard-hero-meta">
        <span class="hero-tag">当前版本 V<?php echo SYSTEM_VER; ?></span>
    </div>
</section>
<div class="input-group">
   <span class="input-group-addon">更新服务器</span>
   <select id="server" class="form-control">
      <option value="0">GitHub Releases（获取最新正式版本）</option>
  </select>
  <!--<span class="input-group-btn">
     <input id="save_btn" type="button" value="保存并应用" class="cs-btn cs-btn-info" onclick="save_server()">
 </span>-->
</div>
<br/>
<!--<script type="text/javascript">
     <?php
         // $server = option::get('update_server') === null ? 0 : option::get('update_server');
     ?>
     $('#server').val('<?php //echo $server; ?>');
   function save_server() {
        $('#save_btn').val('正在保存').attr("disabled","disabled");
         $.ajax({
          async:true,
         url: 'ajax.php?mod=admin:update:changeServer&server=' + $('#server').val(),
         type: "GET",
        data : {},
          dataType: 'html',
           timeout: 90000,
         success: function(data){
          location.reload();
       },
           error: function(error){
           console.log(error);
             $('#save_btn').val('保存并应用').removeAttr("disabled");
             alert('保存失败！错误信息已记录到控制台，按F12打开控制台查看详细信息');
        }
         });
     }
</script>-->

<?php
//检测服务器是否支持写入
if (is_writable(SYSTEM_ROOT . '/setup')) {
    ?><div id="comsys2">
	<div class="alert alert-info"><span id="upd_info">正在检查更新......</span><br/><br/>
	<div class="progress progress-striped active">
	<div class="progress-bar progress-bar-success progress-bar-striped active" id="upd_prog" role="progressbar" aria-valuemin="0" aria-valuemax="100" style="width: 100%">
	<span class="sr-only">正在检查更新</span></div></div></div>
</div>

<div id="comsys"></div>
<div id="comsys2"></div>
<div id="comsys3"></div>
<br>
<div id="comsys5">
  <div class="panel panel-default" style="background-color: #F9F9F9;">
    <div class="panel-body">
	  <b>Tips:</b><br/>
	  1.系统现在按 GitHub 正式 Release 检查版本，不会把开发中的 commit 当成稳定更新。<br/>
	  2.检测到新版本后会显示版本说明、Release 页面和 Web 部署包下载入口。<br/>
	  3.部分版本变动需要执行升级脚本，请留意版本说明；升级后可前往<a href="./setup/update.php" target="_blank">脚本列表</a>查看。<br>
    </div>
  </div>
</div>
  <?php $writable = "1";
} else {
    echo '<div class="alert alert-danger" role="alert">你的服务器不支持文件写入，请手动更新</div>';
    $writable = "0";
}
?>

<script type="text/javascript">
function waitup() {
    $("#comsys").html('<div class="alert alert-warning">开始下载、校验并安装更新，请不要关闭页面。服务器会在校验通过后才覆盖文件，网络较慢时页面可能等待数分钟。</div>');
    pollUpdateStatus();
}
function update() {
}
function pollUpdateStatus() {
    $.ajax({
        url: 'ajax.php?mod=admin:update:status',
        type: 'GET',
        dataType: 'json',
        timeout: 5000,
        success: function(status) {
            if (!status || !status.state || status.state === 'idle') {
                return;
            }
            var detail = status.message || '更新处理中';
            if (status.percent !== null && status.percent !== undefined) {
                detail += '（' + status.percent + '%）';
            }
            if (status.speed_bytes_per_second > 0) {
                detail += '，速度约 ' + (status.speed_bytes_per_second / 1048576).toFixed(2) + ' MB/s';
            }
            if (status.eta_seconds !== null && status.eta_seconds !== undefined) {
                detail += '，预计剩余 ' + status.eta_seconds + ' 秒';
            }
            $("#upd_info").text(detail);
            if (status.state === 'starting' || status.state === 'downloading' || status.state === 'verified' || status.state === 'installing') {
                window.setTimeout(pollUpdateStatus, 1500);
            }
        }
    });
}
pollUpdateStatus();
if(<?php echo $writable; ?>==1){
    $.ajax({
     async:true,
      url: 'ajax.php?mod=admin:update<?php if (isset($_GET['ok'])) {
            echo '&ok';
                                     } ?>',
    type: "GET",
   data : {},
     dataType: 'html',
      timeout: 90000,
    success: function(data){
       $("#comsys3").html(data);
      $("#upd_info").html('完毕');
      $("#comsys2").delay(1000).slideUp(500);
     },
      error: function(xhr){
      console.log(xhr);
         $("#upd_info").html('检查更新失败！');
      var detail = xhr && xhr.responseText ? xhr.responseText : '<div class="alert alert-danger">检查更新失败：无法连接到更新服务器<br/>错误已经记录到控制台，打开控制台查看详细<br/>你可以尝试手动更新</div><br/>';
      $("#comsys").html(detail);
   }
    });
}
</script>
<?php doAction('admin_update_2'); ?>
