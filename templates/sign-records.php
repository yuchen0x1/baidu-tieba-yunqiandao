<?php
if (!defined('SYSTEM_ROOT')) {
    die('Insufficient Permissions');
}
/**
 * 签到记录（合并页）：签到日历 + 签到日志 双标签
 * 由 control.php 的 case 'calendar' / case 'signlogs' 调用；
 * 两个旧路由均渲染本页，默认标签按入口区分（signlogs 进入时定位到日志标签）。
 */
$signRecordsTab = SYSTEM_PAGE === 'signlogs' ? 'logs' : 'calendar';
?>
<div class="cs-tabs" id="sign-records-tabs" style="margin:0 1%">
    <ul class="cs-tablist" role="tablist">
        <li><button type="button" class="cs-tab" role="tab" id="tab-srcalendar" aria-controls="panel-srcalendar" aria-selected="<?php echo $signRecordsTab === 'calendar' ? 'true' : 'false'; ?>">签到日历</button></li>
        <li><button type="button" class="cs-tab" role="tab" id="tab-srlogs" aria-controls="panel-srlogs" aria-selected="<?php echo $signRecordsTab === 'logs' ? 'true' : 'false'; ?>">签到日志</button></li>
    </ul>
</div>
<div class="cs-tabpanel" id="panel-srcalendar" role="tabpanel" aria-labelledby="tab-srcalendar" <?php if ($signRecordsTab !== 'calendar') echo 'hidden'; ?>>
<?php
ob_start();
template('calendar');
echo ob_get_clean();
?>
</div>
<div class="cs-tabpanel" id="panel-srlogs" role="tabpanel" aria-labelledby="tab-srlogs" <?php if ($signRecordsTab !== 'logs') echo 'hidden'; ?>>
<?php
ob_start();
template('sign-logs');
echo ob_get_clean();
?>
</div>
<script>
//标签切换后通知图表重算尺寸（日历页趋势图为 ECharts，隐藏初始化时容器尺寸为 0）
(function () {
    var container = document.getElementById('sign-records-tabs');
    if (!container) { return; }
    var fireResize = function () {
        window.setTimeout(function () {
            try { window.dispatchEvent(new Event('resize')); } catch (e) {}
        }, 60);
    };
    container.addEventListener('click', fireResize);
    if (document.readyState === 'complete') {
        fireResize();
    } else {
        window.addEventListener('load', fireResize);
    }
})();
</script>
