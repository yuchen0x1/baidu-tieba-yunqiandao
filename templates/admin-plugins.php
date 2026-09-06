<?php if (!defined('SYSTEM_ROOT')) {
    die('Insufficient Permissions');
} if (ROLE != 'admin') {
    msg('权限不足！');
}
if (defined('SYSTEM_NO_PLUGIN') && SYSTEM_NO_PLUGIN) {
    die('<div class="alert alert-danger">您设置了"SYSTEM_NO_PLUGIN"，所以无法使用任何与插件相关的功能</div>');
}
global $i;
if (isset($_GET['ok'])) {
    echo '<div class="alert alert-success">插件操作成功</div>';
}
if (isset($_GET['error_msg'])) {
    echo '<div class="alert alert-danger">' . htmlspecialchars((empty($_GET['error_msg']) ? '未知的异常' : $_GET['error_msg']), ENT_QUOTES, 'UTF-8') . '</div>';
}

$x       = getPlugins();
$plugins = '';
foreach ($x as $key => $val) {
    $pluginfo = '';
    $action   = '';
    if (!empty($val['plugin']['url'])) {
        $pluginfo .= '<b>' . sanitize_html_link($val['plugin']['url'], $val['plugin']['name']) . '</b>';
    } else {
        $pluginfo .= '<b>' . htmlspecialchars($val['plugin']['name'], ENT_QUOTES, 'UTF-8') . '</b>';
    }
    if (!empty($val['plugin']['description'])) {
        $pluginfo .= '<br/>' . htmlspecialchars($val['plugin']['description'], ENT_QUOTES, 'UTF-8');
    } else {
        $pluginfo .= '<br/>';
    }

    if (!empty($val['plugin']['version'])) {
        $pluginfo .= '<br/>程序版本：' . htmlspecialchars($val['plugin']['version'], ENT_QUOTES, 'UTF-8');
        if (!empty($i['plugins']['info'][$val['plugin']['id']]['ver'])) {
            $pluginfo .= ' | 已安装版本：' . htmlspecialchars($i['plugins']['info'][$val['plugin']['id']]['ver'], ENT_QUOTES, 'UTF-8');
        }
    } else {
        $pluginfo .= '<br/>程序版本：1.0';
    }

    if (!empty($val['author']['url'])) {
        $authinfo = sanitize_html_link($val['author']['url'], $val['author']['author']);
    } else {
        $authinfo = htmlspecialchars($val['author']['author'], ENT_QUOTES, 'UTF-8');
    }

    if (!empty($val['plugin']['for'])) {
        if (!is_numeric($val['plugin']['for'])) {
            $for = '';
            $fortc = '<br/>适用版本：不限';
        } elseif ($val['plugin']['for'] > SYSTEM_VER) {
            $for = "&ver=" . htmlspecialchars($val['plugin']['for'], ENT_QUOTES, 'UTF-8');
            $fortc = '<br/>适用版本：<span class="cs-text-danger">V' . htmlspecialchars($val['plugin']['for'], ENT_QUOTES, 'UTF-8') . '+</span>';
        } else {
            $for = '';
            $fortc = '<br/>适用版本：V' . htmlspecialchars($val['plugin']['for'], ENT_QUOTES, 'UTF-8') . '+';
        }
    }
    if (isset($i['plugins']['info'][$val['plugin']['id']]['status'])) {
        $fortc .= '<br/>加载顺序：<span style="cursor:grab" class="cron-drag-handle" title="拖拽调整加载顺序">⋮⋮</span> <input required type="number" class="form-control input-sm cron-order-input" style="width:35%; display:inline" name="' . htmlspecialchars($val['plugin']['id'], ENT_QUOTES, 'UTF-8') . '" value="' . htmlspecialchars($val['plugin']['order'], ENT_QUOTES, 'UTF-8') . '">';
    }
    if (in_array($val['plugin']['id'], $i['plugins']['all'])) {
        if ($i['plugins']['info'][$val['plugin']['id']]['status'] == '1') {
            $status = '<span class="cs-badge cs-badge-success">已激活</span> | <a href="setting.php?mod=admin:plugins&dis=' . htmlspecialchars($val['plugin']['id'], ENT_QUOTES, 'UTF-8') . '">禁用插件</a>';
            if (isset($val['core']) && ($val['core']['setting'] && $val['view']['setting']) || (isset($val['plugin']['old']) && file_exists(SYSTEM_ROOT . '/plugins/' . $val['plugin']['id'] . '/' . $val['plugin']['id'] . '_setting.php'))) {
                $action .= '<a href="index.php?mod=admin:setplug&plug=' . htmlspecialchars($val['plugin']['id'], ENT_QUOTES, 'UTF-8') . '" title="查看设置"><svg class="icon-inline" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg><span style="padding:0 3px">设置</span></a> ';
            }
            if (isset($val['core']) && $val['core']['show'] && $val['view']['show']) {
                $action .= '<a href="index.php?plugin=' . htmlspecialchars($val['plugin']['id'], ENT_QUOTES, 'UTF-8') . '" title="查看页面"><svg class="icon-inline" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg><span style="padding:0 3px">页面</span></a> ';
            }
        } else {
            $status = '<span class="cs-badge cs-badge-neutral">已禁用</span> | <a href="setting.php?mod=admin:plugins&act=' . htmlspecialchars($val['plugin']['id'], ENT_QUOTES, 'UTF-8') . '">激活插件</a>';
        }
        $action .= '<br/><a data-plugin-name="' . htmlspecialchars($val['plugin']['name'], ENT_QUOTES, 'UTF-8') . '" data-plugin-version="' . htmlspecialchars($val['plugin']['version'], ENT_QUOTES, 'UTF-8') . '" onclick="return confirm(\'你想要清除此插件的数据吗？文件会得到保留。\\n\' + this.getAttribute(\'data-plugin-name\') + \' V\' + this.getAttribute(\'data-plugin-version\'));" href="setting.php?mod=admin:plugins&clean=' . htmlspecialchars($val['plugin']['id'], ENT_QUOTES, 'UTF-8') . '" style="color:#FF6A00;" title="清除数据"><svg class="icon-inline" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg><span style="padding:0 3px">重置</span></a> ';
    } else {
        $status = '<span class="cs-badge cs-badge-warning">未安装</span> | <a href="setting.php?mod=admin:plugins&install=' . htmlspecialchars($val['plugin']['id'], ENT_QUOTES, 'UTF-8') . $for . '">安装插件</a>';
    }

    $plugins .= '<tr><td>' . $pluginfo . '</td><td>' . $authinfo . '<br/>' . htmlspecialchars($val['plugin']['id'], ENT_QUOTES, 'UTF-8') . $fortc . '</td><td>' . $status . '<br/>';
    $plugins .= $action . '<a data-plugin-name="' . htmlspecialchars($val['plugin']['name'], ENT_QUOTES, 'UTF-8') . '" data-plugin-version="' . htmlspecialchars($val['plugin']['version'], ENT_QUOTES, 'UTF-8') . '" onclick="return confirm(\'你想要要卸载此插件吗？这将删除相关文件。\\n\' + this.getAttribute(\'data-plugin-name\') + \' V\' + this.getAttribute(\'data-plugin-version\'));" href="setting.php?mod=admin:plugins&uninst=' . htmlspecialchars($val['plugin']['id'], ENT_QUOTES, 'UTF-8') . '" style="color:red;" title="卸载插件"><svg class="icon-inline" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg><span style="padding:0 3px">卸载</span></a></td> ';
    $plugins .= '</tr>';
}

doAction('admin_plugins');
?>
<section class="dashboard-hero" style="margin-top:16px">
    <div class="eyebrow hero-eyebrow">EXTENSIONS · 插件</div>
    <h1>插件管理</h1>
    <p>插件手工安装：解包插件并上传到 /plugins/ 目录。列表支持拖拽调整加载顺序，卸载会删除相关文件。</p>
    <div class="dashboard-hero-meta">
        <span class="hero-tag">已安装 <?php echo count($i['plugins']['all']); ?></span>
        <span class="hero-tag">已激活 <?php echo count($i['plugins']['actived']); ?></span>
        <span class="hero-tag">共 <?php echo count($x); ?> 个</span>
    </div>
    <div class="hero-actions">
        <button type="button" class="cs-btn cs-btn-primary cs-btn-sm" data-toggle="modal" data-target="#InstallPlugin">上传安装插件</button>
        <button type="button" class="cs-btn cs-btn-secondary cs-btn-sm" data-toggle="modal" data-target="#InstallPluginOnline">从 Gitee 在线安装</button>
        <a href="<?php echo htmlspecialchars(PROJECT_WIKI_URL, ENT_QUOTES, 'UTF-8'); ?>/%E8%B4%B4%E5%90%A7%E4%BA%91%E7%AD%BE%E5%88%B0%E6%8F%92%E4%BB%B6%E5%BA%93" target="_blank" class="cs-btn cs-btn-secondary cs-btn-sm">插件库 ↗</a>
    </div>
</section>
<form action="setting.php?mod=admin:plugins&xorder" method="post">
<input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">
<div class="table-responsive">
<table class="cs-table">
    <thead>
        <tr>
           <th>插件信息</th>
          <th>作者/标识符</th>
            <th style="width:20%">状态/操作</th>
       </tr>
  </thead>
   <tbody>
        <?php echo $plugins; ?>
    </tbody>
</table>
</div><input type="submit" class="cs-btn cs-btn-primary" value="提交更改">
</form>
<script>
(function () {
    var tbody = document.querySelector('form[action*="xorder"] table tbody');
    if (!tbody) { return; }
    var rows = Array.prototype.slice.call(tbody.querySelectorAll('tr'));
    rows.forEach(function (row) { row.setAttribute('draggable', 'true'); });
    var dragRow = null;
    tbody.addEventListener('dragstart', function (e) {
        dragRow = e.target.closest('tr');
        if (dragRow) { e.dataTransfer.effectAllowed = 'move'; }
    });
    tbody.addEventListener('dragover', function (e) {
        e.preventDefault();
        e.dataTransfer.dropEffect = 'move';
    });
    tbody.addEventListener('drop', function (e) {
        e.preventDefault();
        var target = e.target.closest('tr');
        if (!dragRow || !target || dragRow === target) { return; }
        var rect = target.getBoundingClientRect();
        var before = e.clientY < rect.top + rect.height / 2;
        tbody.insertBefore(dragRow, before ? target : target.nextSibling);
        dragRow = null;
        Array.prototype.slice.call(tbody.querySelectorAll('tr')).forEach(function (row, i) {
            var input = row.querySelector('input.cron-order-input');
            if (input) { input.value = i + 1; }
        });
    });
})();
</script>

<div class="modal fade" id="InstallPlugin" tabindex="-1" role="dialog" aria-labelledby="InstallPluginLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <button type="button" class="close" data-dismiss="modal" aria-hidden="true">&times;</button>
        <h4 class="modal-title" id="myModalLabel">安装插件包</h4>
      </div>
      <form action="<?php echo SYSTEM_URL ?>setting.php?mod=admin:tools&setting=install_plugin" onsubmit="$('#installplugin_button').attr('disabled',true);" method="post" enctype="multipart/form-data">
<input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">
      <div class="modal-body">
        请浏览插件包：( ZIP格式 )
        <br/><br/><input type="file" name="plugin" required accept="application/zip" style="width:100%">
        <br/><br/>您的主机必须支持写入才能安装插件，若不支持，请手工安装
      </div>
      <div class="modal-footer">
        <button type="button" class="cs-btn cs-btn-secondary" data-dismiss="modal">取消</button>
        <button type="submit" class="cs-btn cs-btn-primary" id="installplugin_button">上传插件</button>
      </div>
      </form>
    </div><!-- /.modal-content -->
  </div><!-- /.modal-dialog -->
</div><!-- /.modal -->

<div class="modal fade" id="InstallPluginOnline" tabindex="-1" role="dialog" aria-labelledby="InstallPluginOnlineLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <button type="button" class="close" data-dismiss="modal" aria-hidden="true">&times;</button>
        <h4 class="modal-title" id="InstallPluginOnlineLabel">从 Gitee 在线安装插件</h4>
      </div>
      <form action="<?php echo SYSTEM_URL ?>setting.php?mod=admin:tools&setting=install_plugin_online" onsubmit="$('#installplugin_online_button').attr('disabled',true);" method="post">
<input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">
      <div class="modal-body">
        插件包下载地址（仅允许 https://gitee.com 的 ZIP 下载链接）：
        <br/><br/><input type="url" name="plugin_gitee_url" class="form-control" required placeholder="https://gitee.com/xxx/xxx/repository/archive/main.zip" style="width:100%">
        <br/><br/>下载后自动解压到 /plugins/，随后请回到插件列表点击「安装插件」完成注册
      </div>
      <div class="modal-footer">
        <button type="button" class="cs-btn cs-btn-secondary" data-dismiss="modal">取消</button>
        <button type="submit" class="cs-btn cs-btn-primary" id="installplugin_online_button">下载并安装</button>
      </div>
      </form>
    </div><!-- /.modal-content -->
  </div><!-- /.modal-dialog -->
</div><!-- /.modal -->
