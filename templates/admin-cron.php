<?php if (!defined('SYSTEM_ROOT')) {
    die('Insufficient Permissions');
}  if (ROLE != 'admin') {
    msg('权限不足!');
}
global $m,$i;
$cron  = '';
foreach ($i['cron'] as $cs) {
    if ($cs['freq'] == '-1') {
        $freq = '一次性任务';
    } elseif ($cs['freq'] == '0') {
        $freq = '始终运行的任务';
    } else {
        $freq = '执行间隔：' . htmlspecialchars($cs['freq'], ENT_QUOTES, 'UTF-8') . ' 秒';
    }

    if (!empty($cs['lastdo'])) {
        $lastdo = date('Y-m-d H:i:s', $cs['lastdo']);
    } else {
        $lastdo = '从未运行';
    }

    // V5.13：启用状态（兼容旧 no 与新 enable；未迁移库无 enable 列时视为启用）
    $enabled = ((int) $cs['no'] !== 1 && (!isset($cs['enable']) || (int) $cs['enable'] !== 0));
    if ($enabled) {
        $status = '<font color="green">启用</font>';
        $status .= ' | <a href="setting.php?mod=admin:cron&dis=' . htmlspecialchars($cs['name'], ENT_QUOTES, 'UTF-8') . '">禁用</a>';
    } else {
        $status = '<font color="gray">已禁用</font> | <a href="setting.php?mod=admin:cron&act=' . htmlspecialchars($cs['name'], ENT_QUOTES, 'UTF-8') . '">启用</a>';
    }
    // V5.13：最近执行状态徽标
    $ls = isset($cs['last_status']) ? $cs['last_status'] : '';
    $badge = '';
    if ($ls === 'success') {
        $badge = '<font color="green">[上次成功]</font> ';
    } elseif ($ls === 'error') {
        $badge = '<font color="red">[上次失败]</font> ';
    } elseif ($ls === 'running') {
        $badge = '<font color="blue">[运行中]</font> ';
    }
    // V5.13：下次执行时间
    $nextrun = isset($cs['nextrun']) ? (int) $cs['nextrun'] : 0;
    if ($cs['freq'] == '-1') {
        $nextText = '一次性，执行后删除';
    } elseif ($cs['freq'] == '0' || $nextrun === 0) {
        $nextText = '尽快（始终执行）';
    } else {
        $nextText = date('Y-m-d H:i:s', $nextrun);
    }
    // V5.13：上次耗时
    $dur = isset($cs['last_duration']) ? (int) $cs['last_duration'] : 0;
    $durText = $dur > 0 ? ($dur >= 1000 ? number_format($dur / 1000, 2) . ' 秒' : $dur . ' 毫秒') : '—';

    $status .= '<br/><a href="setting.php?mod=admin:cron&run=' . htmlspecialchars($cs['name'], ENT_QUOTES, 'UTF-8') . '">运行</a>';
    if ($ls === 'error' || (isset($cs['retry']) && (int) $cs['retry'] > 0)) {
        $status .= ' | <a href="setting.php?mod=admin:cron&retry=' . htmlspecialchars($cs['name'], ENT_QUOTES, 'UTF-8') . '">重试</a>';
    }
    $status .= ' | <a href="index.php?mod=admin:editcron&edit=' . htmlspecialchars($cs['name'], ENT_QUOTES, 'UTF-8') . '">编辑</a>';
    $status .= ' | <a href="setting.php?mod=admin:cron&uninst=' . htmlspecialchars($cs['name'], ENT_QUOTES, 'UTF-8') . '" style="color:red; " onclick="return confirm(\'你想要卸载此计划任务吗？\');">卸载</a>';
    if (empty($cs['log'])) {
        $status .= '<br/>没有日志可查看';
    } else {
        $status .= '<br/><a href="javascript:;" data-log="' . htmlspecialchars($cs['log'], ENT_QUOTES, 'UTF-8') . '" onclick="alert(this.getAttribute(\'data-log\'));">点击查看此任务的日志</a>';
    }
    $status .= '<br/>运行顺序：<input required class="form-control input-sm" style="width:30%; display:inline" type="number" name="order[' . htmlspecialchars($cs['name'], ENT_QUOTES, 'UTF-8') . ']" value="' . htmlspecialchars($cs['orde'], ENT_QUOTES, 'UTF-8') . '">';
    $cron .= '<input type="hidden" value="' . htmlspecialchars($cs['name'], ENT_QUOTES, 'UTF-8') . '" name="ids[]"><tr><td style="width:30%"><b>' . htmlspecialchars($cs['name'], ENT_QUOTES, 'UTF-8') . '</b><br/>' . htmlspecialchars($cs['file'], ENT_QUOTES, 'UTF-8') . '<br/>' . str_replace(array("\n", "\r\n"), '<br/>', htmlspecialchars($cs['desc'] ? $cs['desc'] : '', ENT_QUOTES, 'UTF-8')) . '</td><td style="width:30%">' . $badge . $freq . '<br/>上次执行：' . $lastdo . '<br/>下次执行：' . $nextText . '<br/>上次耗时：' . $durText . '</td><td style="width:40%">' . $status . '</td></tr>';
}

if (isset($_GET['ok'])) {
    echo '<div class="alert alert-success">计划任务操作成功</div>';
}

$count = $m->once_fetch_array("SELECT COUNT(*) AS c FROM `" . DB_NAME . "`.`" . DB_PREFIX . "cron`");

// V5.13：最近任务运行历史
$runs = cron::recentRuns(20);
$runRows = '';
foreach ($runs as $r) {
    $rs = $r['status'];
    $color = 'gray';
    if ($rs === 'success') {
        $color = 'green';
    } elseif ($rs === 'error' || $rs === 'skipped') {
        $color = 'red';
    } elseif ($rs === 'running') {
        $color = 'blue';
    }
    $durText2 = (int) $r['duration_ms'] > 0 ? ((int) $r['duration_ms'] >= 1000 ? number_format($r['duration_ms'] / 1000, 2) . ' 秒' : $r['duration_ms'] . ' 毫秒') : '—';
    $timeText = !empty($r['started_at']) ? date('Y-m-d H:i:s', $r['started_at']) : '—';
    $runRows .= '<tr><td>' . htmlspecialchars($r['name'], ENT_QUOTES, 'UTF-8') . '</td><td><font color="' . $color . '">' . htmlspecialchars($rs, ENT_QUOTES, 'UTF-8') . '</font></td><td>' . htmlspecialchars($timeText, ENT_QUOTES, 'UTF-8') . '</td><td>' . $durText2 . '</td><td style="max-width:380px;word-break:break-all;">' . htmlspecialchars($r['message'], ENT_QUOTES, 'UTF-8') . '</td></tr>';
}
?>
<div class="alert alert-info" id="tb_num">当前共有 <?php echo $count['c'] ?> 个计划任务，您需要添加根目录下 do.php 到您主机的计划任务后，下面的任务才能被执行<br/><a href="index.php?mod=admin:editcron">添加新计划任务</a> | <form action="do.php" method="post" style="display:inline;"><input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8'); ?>"><input type="hidden" name="pw" value="<?php echo htmlspecialchars(option::get('cron_pw'), ENT_QUOTES, 'UTF-8'); ?>"><button type="submit" class="btn btn-link" style="padding:0;border:0;">运行全部计划任务</button></form></div>
<form action="setting.php?mod=admin:cron&xorder" method="post">
<input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">
<div class="table-responsive">
<table class="table table-hover">
   <thead>
        <tr>
           <th>任务描述/文件</th>
           <th>其他信息</th>
          <th>状态/操作</th>
     </tr>
  </thead>
   <tbody>
        <?php echo $cron ?>
    </tbody>
</table>
</div>
<input type="submit" class="btn btn-primary" value="提交更改">
</form>
<br/>
<h4>最近任务运行历史（V5.13）</h4>
<div class="table-responsive">
<table class="table table-hover">
   <thead>
        <tr>
           <th>任务名</th>
           <th>状态</th>
           <th>开始时间</th>
           <th>耗时</th>
           <th>日志</th>
     </tr>
  </thead>
   <tbody>
        <?php echo $runRows ? $runRows : '<tr><td colspan="5">暂无运行记录</td></tr>' ?>
    </tbody>
</table>
</div>
<br/>
<br/><br/><?php echo SYSTEM_FN ?> V<?php echo SYSTEM_VER  . ' ' . SYSTEM_VER_NOTE ?> // 作者: <a href="https://kenvix.com" target="_blank">Kenvix</a>  &amp; <a href="http://www.mokeyjay.com/" target="_blank">mokeyjay</a> &amp;  <a href="http://fyy1999.lofter.com/" target="_blank">FYY</a>
