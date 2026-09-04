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
    //账号分组映射（pid => 分组名）
    $groupMapRaw = option::uget('ver4_account_groups', UID);
    $groupMap = is_string($groupMapRaw) && $groupMapRaw !== '' ? json_decode($groupMapRaw, true) : array();
    if (!is_array($groupMap)) {
        $groupMap = array();
    }
    ?>
<div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;margin:0 1% 10px">
    <span class="text-muted" style="font-size:12px">分组筛选：</span>
    <button type="button" class="cs-btn cs-btn-secondary cs-btn-sm group-filter is-active" data-group="">全部</button>
    <?php
    $groupNames = array_unique(array_values($groupMap));
    foreach ($groupNames as $gn) {
        echo '<button type="button" class="cs-btn cs-btn-ghost cs-btn-sm group-filter" data-group="' . htmlspecialchars($gn, ENT_QUOTES, 'UTF-8') . '">' . htmlspecialchars($gn, ENT_QUOTES, 'UTF-8') . '</button>';
    }
    ?>
    <details style="margin-left:auto">
        <summary style="cursor:pointer;font-size:12px">设置分组</summary>
        <div style="display:flex;flex-direction:column;gap:6px;margin-top:8px;min-width:260px">
            <?php
            foreach ($i['user']['bduss'] as $gpKey => $gpVal) {
                $gpName = empty($i['user']['baidu'][$gpKey]) ? ('PID ' . $gpKey) : $i['user']['baidu'][$gpKey];
                $gpCur = isset($groupMap[(int) $gpKey]) ? $groupMap[(int) $gpKey] : '';
                echo '<div style="display:flex;gap:6px;align-items:center">'
                    . '<span style="min-width:120px;font-size:12px">' . htmlspecialchars($gpName, ENT_QUOTES, 'UTF-8') . '</span>'
                    . '<input type="text" class="form-control group-input" data-pid="' . (int) $gpKey . '" value="' . htmlspecialchars($gpCur, ENT_QUOTES, 'UTF-8') . '" placeholder="分组名，留空清除" style="width:160px">'
                    . '</div>';
            }
            ?>
            <div><input type="submit" class="cs-btn cs-btn-primary cs-btn-sm" id="group-save" value="保存分组"></div>
        </div>
    </details>
</div>
<div class="cs-table-wrap">
<table class="cs-table" id="baiduid-table">
  <thead>
    <tr>
      <th>PID</th>
      <th style="width:20%">百度名称</th>
      <th style="width:90px">健康度</th>
      <th style="width:20%">Portrait</th>
      <th style="width:30%">BDUSS</th>
      <th style="width:20%">STOKEN</th>
      <th>操作</th>
    </tr>
  </thead>
  <tbody>
    <?php
    //每个账号今日签到进度（按 pid 统计所有分表）
    $signProgress = array();
    if (!empty($i['table'])) {
        $todayD = date('d');
        foreach ($i['table'] as $spTable) {
            $spQs = $m->query("SELECT `pid`, COUNT(*) AS total, SUM(`latest` = '{$todayD}') AS signed FROM `" . DB_NAME . "`.`" . DB_PREFIX . $spTable . "` WHERE `uid` = " . (int) UID . " GROUP BY `pid`");
            if ($spQs === false) {
                continue;
            }
            while ($spRow = $m->fetch_array($spQs)) {
                $spPid = (int) $spRow['pid'];
                if (!isset($signProgress[$spPid])) {
                    $signProgress[$spPid] = array('total' => 0, 'signed' => 0);
                }
                $signProgress[$spPid]['total'] += (int) $spRow['total'];
                $signProgress[$spPid]['signed'] += (int) $spRow['signed'];
            }
        }
    }
    //账号健康度评分：BDUSS 健康状态 50 + 近 7 天签到成功率 30 + 代理 10 + 今日完成度 10
    require_once SYSTEM_ROOT . '/lib/class.SignManager.php';
    $healthMap = SignManager::accountHealth((int) UID);
    $rateMap = array();
    $rateQs = $m->pquery("SELECT `pid`, SUM(`status` = 'success') AS s, COUNT(*) AS t FROM `" . DB_PREFIX . SignManager::HISTORY_TABLE . "` WHERE `uid` = ? AND `created_at` >= ? GROUP BY `pid`", array((int) UID, strtotime('-7 days midnight')));
    if ($rateQs !== false) {
        while ($rRow = $rateQs->fetch_array()) {
            $rateMap[(int) $rRow['pid']] = array('s' => (int) $rRow['s'], 't' => (int) $rRow['t']);
        }
    }
    foreach ($i['user']['bduss'] as $key => $value) {
        $groupAttr = isset($groupMap[(int) $key]) ? htmlspecialchars($groupMap[(int) $key], ENT_QUOTES, 'UTF-8') : '';
        echo '<tr data-group="' . $groupAttr . '"><td>' . $key . '</td>';
        $name = empty($i['user']['baidu'][$key]) ? '未记录百度ID' : htmlspecialchars($i['user']['baidu'][$key], ENT_QUOTES, 'UTF-8');
        if ($name == '[E]') {
            $name = '<span class="cs-badge cs-badge-danger">已失效</span>';
        }
        //今日签到进度条
        $progressHtml = '';
        if (isset($signProgress[(int) $key]) && $signProgress[(int) $key]['total'] > 0) {
            $sp = $signProgress[(int) $key];
            $pct = min(100, round($sp['signed'] / $sp['total'] * 100));
            $barColor = $pct >= 100 ? '#3cb45a' : '#5b3aff';
            $progressHtml = '<div style="margin-top:5px;display:flex;align-items:center;gap:6px">'
                . '<div style="flex:1;height:6px;background:rgba(128,128,128,.2);border-radius:3px;overflow:hidden;min-width:60px">'
                . '<div style="height:100%;width:' . $pct . '%;background:' . $barColor . ';border-radius:3px"></div></div>'
                . '<span style="font-size:11px;color:#888;white-space:nowrap">已签 ' . (int) $sp['signed'] . '/' . (int) $sp['total'] . '</span></div>';
        }
        //已缓存的我的贴吧概要徽章（点击下方「查看关注与等级」后生成）
        $moBadge = '';
        $moCacheRaw = option::get('baiduid_moindex_' . $key);
        if ($moCacheRaw !== '' && $moCacheRaw !== null) {
            $moCache = json_decode((string) $moCacheRaw, true);
            if (is_array($moCache) && isset($moCache['error'], $moCache['total'], $moCache['signed'], $moCache['forums']) && $moCache['error'] === 0) {
                $allSigned = (int) $moCache['signed'] >= (int) $moCache['total'];
                $moBadge = '<br/><span class="cs-badge" style="font-size:11px;margin-top:4px;display:inline-block;' . ($allSigned ? 'background:rgba(60,180,90,.15);color:#3cb45a' : 'background:rgba(255,160,0,.15);color:#ff9800') . '">关注 ' . (int) $moCache['total'] . ' 吧 · 已签 ' . (int) $moCache['signed'] . '</span>';
                $topForums = array_slice($moCache['forums'], 0, 3);
                if (!empty($topForums)) {
                    $topText = array();
                    foreach ($topForums as $tf) {
                        $topText[] = htmlspecialchars($tf['name'], ENT_QUOTES, 'UTF-8') . ' Lv.' . (int) $tf['level'];
                    }
                    $moBadge .= '<br/><span style="font-size:11px;color:#888">最高：' . implode(' · ', $topText) . '</span>';
                }
            }
        }
        echo '<td>' . $name . $progressHtml . $moBadge . '</td>';
        //健康度评分
        $hScore = 0;
        $hParts = array();
        $hStatus = isset($healthMap[(int) $key]['status']) ? $healthMap[(int) $key]['status'] : 'unknown';
        if ($hStatus === 'normal') { $hScore += 50; $hParts[] = '凭证正常 50'; }
        elseif ($hStatus === 'invalid') { $hParts[] = '凭证失效 0'; }
        else { $hScore += 25; $hParts[] = '凭证未检测 25'; }
        $hRate = isset($rateMap[(int) $key]) && $rateMap[(int) $key]['t'] > 0 ? $rateMap[(int) $key]['s'] / $rateMap[(int) $key]['t'] : null;
        if ($hRate !== null) { $hScore += (int) round($hRate * 30); $hParts[] = '近7天成功率 ' . (int) round($hRate * 30); }
        else { $hScore += 15; $hParts[] = '暂无签到数据 15'; }
        $hProxy = isset($i['user']['bduss_proxy'][$key]) ? trim((string) $i['user']['bduss_proxy'][$key]) : '';
        if ($hProxy !== '' && strcasecmp($hProxy, 'off') !== 0) { $hScore += 10; $hParts[] = '已配代理 10'; }
        elseif (strcasecmp($hProxy, 'off') === 0) { $hScore += 5; $hParts[] = '代理禁用 5'; }
        else { $hScore += 0; $hParts[] = '跟随全局 0'; }
        if (isset($signProgress[(int) $key]) && $signProgress[(int) $key]['total'] > 0) {
            $sp = $signProgress[(int) $key];
            $hScore += (int) round($sp['signed'] / $sp['total'] * 10);
            $hParts[] = '今日完成 ' . (int) round($sp['signed'] / $sp['total'] * 10);
        } else { $hScore += 5; $hParts[] = '今日无任务 5'; }
        $hScore = min(100, $hScore);
        $hColor = $hScore >= 80 ? '#3cb45a' : ($hScore >= 60 ? '#ff9800' : '#e53935');
        echo '<td style="white-space:nowrap"><span class="cs-badge" style="font-size:12px;white-space:nowrap;background:' . $hColor . '22;color:' . $hColor . '" title="' . htmlspecialchars(implode('，', $hParts), ENT_QUOTES, 'UTF-8') . '">健康度 ' . $hScore . '</span></td>';
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
<div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;margin:0 0 8px">
    <b>统一代理</b>
    <input type="text" class="form-control" id="proxy-input" style="width:300px" placeholder="http://host:port 或 socks5://user:pass@host:port">
    <button type="button" class="cs-btn cs-btn-secondary cs-btn-sm" id="proxy-apply">应用到我的全部账号</button>
    <span class="text-muted" style="font-size:12px">留空并应用 = 全部恢复跟随全局；填 off = 全部禁用代理</span>
</div>
<div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;margin:0 0 16px">
    <b>聚合操作</b>
    <button type="button" class="cs-btn cs-btn-warning cs-btn-sm" id="sign-run-all">⚡ 立即签到（我的全部账号）</button>
    <button type="button" class="cs-btn cs-btn-secondary cs-btn-sm" id="sign-run-one">立即签到（选中账号）</button>
    <button type="button" class="cs-btn cs-btn-secondary cs-btn-sm" id="bulk-refresh-tieba">刷新贴吧列表（全部账号）</button>
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
        <button type="button" class="cs-btn cs-btn-secondary cs-btn-sm" id="mo-export" style="display:none">导出 CSV</button>
        <span class="text-muted" style="font-size:12px">数据缓存 30 分钟，来自手机端贴吧接口</span>
    </div>
    <div id="mo-summary" class="text-muted" style="margin-bottom:8px"></div>
    <div id="mo-forums" style="display:flex;flex-wrap:wrap;gap:6px"></div>
</div>
<script type="text/javascript">
    (function () {
        var moLoading = false;
        var moLastData = null;
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
                    moLastData = x;
                    var box = $('#mo-forums');
                    box.empty();
                    $.each(x.forums, function (i, f) {
                        var badge = $('<span class="cs-badge" style="font-size:12px;padding:4px 8px"></span>')
                            .text(f.name + ' Lv.' + f.level + (f.signed ? ' · 已签' : ' · 未签'))
                            .css(f.signed ? { 'background': 'rgba(60,180,90,.15)', 'color': '#3cb45a' } : { 'background': 'rgba(255,160,0,.15)', 'color': '#ff9800' });
                        box.append(badge);
                    });
                    $('#mo-summary').text('共关注 ' + x.total + ' 个吧，今日已签 ' + x.signed + ' / ' + x.total + '（更新于 ' + new Date(x.fetched_at * 1000).toLocaleTimeString() + '）');
                    $('#mo-export').css('display', '');
                },
                error: function () {
                    moLoading = false;
                    $('#mo-summary').text('加载失败，请稍后重试');
                }
            });
        }
        $('#mo-view').on('click', function () { moLoad(false); });

        //账号分组：筛选 + 保存
        $('.group-filter').on('click', function () {
            $('.group-filter').removeClass('is-active');
            $(this).addClass('is-active');
            var g = $(this).attr('data-group') || '';
            $('#baiduid-table tbody tr').each(function () {
                var rowGroup = $(this).attr('data-group') || '';
                $(this).toggle(g === '' || rowGroup === g);
            });
        });
        $('#group-save').on('click', function () {
            var map = {};
            $('.group-input').each(function () {
                var pid = parseInt($(this).attr('data-pid'), 10);
                var name = $.trim($(this).val());
                if (pid > 0 && name !== '') { map[pid] = name; }
            });
            $.ajax({
                url: 'ajax.php?mod=baiduid:groups',
                dataType: 'json',
                type: 'POST',
                data: { 'groups': JSON.stringify(map), 'csrf_token': $('input[name="csrf_token"]').val() },
                success: function (x) {
                    if (x.error === 0) {
                        if (window.CS && CS.toast) { CS.toast('分组已保存', { type: 'success', body: '已保存 ' + x.updated + ' 个分组' }); }
                        setTimeout(function () { window.location.reload(); }, 700);
                    } else {
                        alert(x.msg || '保存失败');
                    }
                },
                error: function () { alert('请求失败，请稍后重试'); }
            });
        });
        $('#mo-refresh').on('click', function () { moLoad(true); });
        $('#mo-export').on('click', function () {
            if (!moLastData || !moLastData.forums) { alert('请先加载我的贴吧数据'); return; }
            var rows = [['序号', '贴吧名', '我的等级', '经验', '今日签到']];
            $.each(moLastData.forums, function (i, f) {
                rows.push([i + 1, f.name, 'Lv.' + f.level, f.exp, f.signed ? '已签' : '未签']);
            });
            var csv = '\uFEFF' + rows.map(function (r) {
                return r.map(function (c) { return '"' + String(c).replace(/"/g, '""') + '"'; }).join(',');
            }).join('\r\n');
            var blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
            var a = document.createElement('a');
            a.href = URL.createObjectURL(blob);
            a.download = '我的贴吧_' + new Date().toISOString().slice(0, 10) + '.csv';
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
        });

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

        $('#bulk-refresh-tieba').on('click', function () {
            if (!window.confirm('刷新全部账号的关注贴吧列表？扫描需要一些时间，期间请勿关闭页面。')) { return; }
            $('#bulk-refresh-tieba').prop('disabled', true);
            $('#sign-summary').text('正在刷新贴吧列表，可能需要较长时间…');
            $.ajax({
                url: 'setting.php?mod=showtb&refreshTiebaList',
                type: 'GET',
                success: function () {
                    $('#bulk-refresh-tieba').prop('disabled', false);
                    $('#sign-summary').text('贴吧列表已刷新');
                    if (window.CS && CS.toast) { CS.toast('刷新完成', { type: 'success', body: '全部账号的关注贴吧列表已同步' }); }
                },
                error: function () {
                    $('#bulk-refresh-tieba').prop('disabled', false);
                    $('#sign-summary').text('刷新失败，请稍后重试');
                }
            });
        });

        $('#proxy-apply').on('click', function () {
            var val = $('#proxy-input').val();
            var tip = val === '' ? '确定清空并让全部账号恢复跟随全局代理吗？' : (val.toLowerCase() === 'off' ? '确定禁用全部账号的代理吗？' : '确定将该代理应用到我的全部账号吗？');
            if (!window.confirm(tip)) { return; }
            $('#proxy-apply').prop('disabled', true);
            $.ajax({
                url: 'ajax.php?mod=baiduid:proxyall',
                dataType: 'json',
                type: 'POST',
                data: { 'proxy': val, 'csrf_token': $('input[name="csrf_token"]').val() },
                success: function (x) {
                    $('#proxy-apply').prop('disabled', false);
                    if (x.error !== 0) {
                        if (window.CS && CS.toast) { CS.toast('设置失败', { type: 'warning', body: x.msg || '' }); }
                        else { alert(x.msg || '设置失败'); }
                        return;
                    }
                    if (window.CS && CS.toast) { CS.toast('统一代理已生效', { type: 'success', body: '已更新 ' + x.updated + ' 个账号的代理设置' }); }
                    setTimeout(function () { window.location.reload(); }, 800);
                },
                error: function () {
                    $('#proxy-apply').prop('disabled', false);
                    alert('请求失败，请稍后重试');
                }
            });
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
