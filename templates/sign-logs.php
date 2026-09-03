<?php
if (!defined('SYSTEM_ROOT')) {
    die('Insufficient Permissions');
}
if (ROLE === 'visitor' || ROLE === 'banned') {
    msg('请先登录');
}
require_once SYSTEM_ROOT . '/lib/class.SignManager.php';

// 读取筛选参数
$filters = array(
    'uid'       => ISADMIN && defined('CON_UID') ? (int) CON_UID : (int) UID,
    'date_from' => isset($_GET['date_from']) ? trim((string) $_GET['date_from']) : '',
    'date_to'   => isset($_GET['date_to'])   ? trim((string) $_GET['date_to'])   : '',
    'tieba'     => isset($_GET['tieba'])     ? trim((string) $_GET['tieba'])     : '',
    'status'    => isset($_GET['status'])    ? trim((string) $_GET['status'])    : '',
);
$page     = isset($_GET['page']) ? max(1, (int) $_GET['page']) : 1;
$pageSize = 30;

// 控制模式下管理员可查看任意用户
if (ISADMIN && defined('CON_UID') && CON_UID > 0) {
    $filters['uid'] = (int) CON_UID;
}

$result = SignManager::logs($filters, $page, $pageSize);
$rows   = $result['rows'];
$total  = $result['total'];
$page   = $result['page'];
$pages  = $result['pages'];

$statusOptions = array(
    ''         => '全部',
    'success'  => '成功',
    'error'    => '失败',
    'skipped'  => '跳过',
);

function build_query($override = array()) {
    $params = array_merge($_GET, $override);
    return http_build_query($params);
}
?>
<section class="dashboard-hero" style="margin:16px 1%">
    <div class="eyebrow hero-eyebrow">Sign Logs / <?php echo date('Y-m-d'); ?></div>
    <h1>签到日志详情</h1>
    <p>按日期、贴吧名、签到状态筛选历史记录，每页 <?php echo $pageSize; ?> 条，共 <?php echo $total; ?> 条。</p>
</section>

<form class="cs-form cs-card" method="get" action="index.php" style="margin:0 1% 16px;padding:16px;display:grid;gap:12px;grid-template-columns:repeat(auto-fit, minmax(180px, 1fr));align-items:end">
    <input type="hidden" name="mod" value="signlogs">
    <div>
        <label class="cs-form-label" for="f-date-from">起始日期</label>
        <input class="cs-form-control" type="date" id="f-date-from" name="date_from" value="<?php echo htmlspecialchars($filters['date_from'], ENT_QUOTES, 'UTF-8'); ?>">
    </div>
    <div>
        <label class="cs-form-label" for="f-date-to">结束日期</label>
        <input class="cs-form-control" type="date" id="f-date-to" name="date_to" value="<?php echo htmlspecialchars($filters['date_to'], ENT_QUOTES, 'UTF-8'); ?>">
    </div>
    <div>
        <label class="cs-form-label" for="f-tieba">贴吧名</label>
        <input class="cs-form-control" type="text" id="f-tieba" name="tieba" value="<?php echo htmlspecialchars($filters['tieba'], ENT_QUOTES, 'UTF-8'); ?>" placeholder="支持模糊匹配">
    </div>
    <div>
        <label class="cs-form-label" for="f-status">状态</label>
        <select class="cs-form-control" id="f-status" name="status">
            <?php foreach ($statusOptions as $value => $label) { ?>
            <option value="<?php echo htmlspecialchars($value, ENT_QUOTES, 'UTF-8'); ?>" <?php if ($filters['status'] === $value) echo 'selected'; ?>><?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?></option>
            <?php } ?>
        </select>
    </div>
    <div style="display:flex;gap:8px">
        <button type="submit" class="cs-btn cs-btn-primary cs-btn-sm">筛选</button>
        <a class="cs-btn cs-btn-ghost cs-btn-sm" href="index.php?mod=signlogs">重置</a>
        <a class="cs-btn cs-btn-secondary cs-btn-sm" id="btn-export-csv" href="index.php?mod=signlogs_export&<?php echo htmlspecialchars(build_query(), ENT_QUOTES, 'UTF-8'); ?>" title="将当前筛选结果导出为 CSV（最多 5000 条）" download onclick="return confirm('将根据当前筛选条件导出 CSV（最多 5000 条）。继续？');">导出 CSV</a>
    </div>
</form>

<div style="margin:0 1%;overflow-x:auto">
    <table class="cs-table">
        <thead>
            <tr>
                <th style="width:140px">时间</th>
                <th>贴吧</th>
                <th style="width:80px">状态</th>
                <th style="width:80px">错误码</th>
                <th>信息</th>
                <th style="width:80px">操作</th>
            </tr>
        </thead>
        <tbody>
        <?php if (empty($rows)) { ?>
            <tr><td colspan="6" style="text-align:center;padding:24px;color:var(--color-ink-soft)">没有符合条件的记录</td></tr>
        <?php } else { foreach ($rows as $row) {
            $statusBadge = $row['status'] === 'success' ? 'cs-badge-success' : ($row['status'] === 'error' ? 'cs-badge-error' : 'cs-badge-neutral');
        ?>
            <tr>
                <td><?php echo htmlspecialchars(date('Y-m-d H:i', $row['created_at']), ENT_QUOTES, 'UTF-8'); ?></td>
                <td><?php echo htmlspecialchars($row['tieba'], ENT_QUOTES, 'UTF-8'); ?></td>
                <td><span class="cs-badge <?php echo $statusBadge; ?>"><?php echo htmlspecialchars($row['status'], ENT_QUOTES, 'UTF-8'); ?></span></td>
                <td><?php echo $row['error_code'] ? (int) $row['error_code'] : '-'; ?></td>
                <td><?php echo htmlspecialchars($row['message'] ?: '-', ENT_QUOTES, 'UTF-8'); ?></td>
                <td>
                    <?php if ($row['status'] === 'error' && $row['pid'] > 0) { ?>
                    <form method="post" action="setting.php?mod=showtb:retry" style="display:inline" target="_blank">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">
                        <input type="hidden" name="pid" value="<?php echo (int) $row['pid']; ?>">
                        <input type="hidden" name="tieba" value="<?php echo htmlspecialchars($row['tieba'], ENT_QUOTES, 'UTF-8'); ?>">
                        <button type="submit" class="cs-btn cs-btn-secondary cs-btn-sm" title="对该贴吧执行一次复签">复签</button>
                    </form>
                    <?php } else { ?>
                    <span style="color:var(--color-ink-soft)">—</span>
                    <?php } ?>
                </td>
            </tr>
        <?php } } ?>
        </tbody>
    </table>
</div>

<?php if ($pages > 1) { ?>
<nav class="cs-pagination" aria-label="分页" style="margin:16px 1%;display:flex;gap:6px;flex-wrap:wrap;align-items:center">
    <?php
    $renderPage = function ($p, $label = null, $disabled = false) use ($page) {
        $label = $label !== null ? $label : $p;
        if ($disabled) {
            echo '<span class="cs-page cs-page-disabled" aria-disabled="true">' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</span>';
        } elseif ($p == $page) {
            echo '<span class="cs-page cs-page-current" aria-current="page">' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</span>';
        } else {
            $url = 'index.php?' . build_query(array('page' => $p));
            echo '<a class="cs-page" href="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '">' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</a>';
        }
    };
    $renderPage(max(1, $page - 1), '上一页', $page <= 1);
    $start = max(1, $page - 4);
    $end = min($pages, $page + 4);
    if ($start > 1) { $renderPage(1, '1'); if ($start > 2) echo '<span class="cs-page-ellipsis">…</span>'; }
    for ($p = $start; $p <= $end; $p++) { $renderPage($p); }
    if ($end < $pages) { if ($end < $pages - 1) echo '<span class="cs-page-ellipsis">…</span>'; $renderPage($pages, (string) $pages); }
    $renderPage(min($pages, $page + 1), '下一页', $page >= $pages);
    ?>
    <span style="margin-left:auto;color:var(--color-ink-soft);font-size:13px">第 <?php echo $page; ?> / <?php echo $pages; ?> 页</span>
</nav>
<?php } ?>
