<?php
if (!defined('SYSTEM_ROOT')) {
    die('Insufficient Permissions');
}
if (ROLE === 'visitor' || ROLE === 'banned') {
    msg('请先登录');
}
require_once SYSTEM_ROOT . '/lib/class.Notice.php';

$viewUid = (ISADMIN && defined('CON_UID') && CON_UID > 0) ? (int) CON_UID : (int) UID;
$unreadOnly = isset($_GET['unread']) ? (bool) $_GET['unread'] : false;
$page = isset($_GET['page']) ? max(1, (int) $_GET['page']) : 1;

// 处理操作
$actionResult = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $opAction = isset($_POST['op']) ? (string) $_POST['op'] : '';
    $noticeId = isset($_POST['notice_id']) ? (int) $_POST['notice_id'] : 0;
    if ($opAction === 'mark_read' && $noticeId > 0) {
        Notice::markRead($noticeId, $viewUid);
    } elseif ($opAction === 'mark_all_read') {
        Notice::markAllRead($viewUid);
    } elseif ($opAction === 'delete' && $noticeId > 0) {
        Notice::delete($noticeId, $viewUid);
    }
    // 操作后重定向，避免重复提交
    $redirectParams = array('mod' => 'notices');
    if ($unreadOnly) { $redirectParams['unread'] = '1'; }
    if ($page > 1) { $redirectParams['page'] = $page; }
    Redirect('index.php?' . http_build_query($redirectParams));
}

$result = Notice::items($viewUid, $unreadOnly, $page, 20);
$rows = $result['rows'];
$total = $result['total'];
$pages = $result['pages'];
$currentPage = $result['page'];
$unreadCount = Notice::unreadCount($viewUid);
?>
<section class="dashboard-hero">
    <div class="eyebrow hero-eyebrow">Notifications / <?php echo date('Y-m-d'); ?></div>
    <h1>站内通知</h1>
    <p>这里集中展示签到异常、成就解锁、账号失效等系统通知。未读通知会在导航栏显示徽章。</p>
    <div class="dashboard-hero-meta">
        <span class="hero-tag">未读 <?php echo (int) $unreadCount; ?> 条</span>
        <span class="hero-tag">共 <?php echo (int) $total; ?> 条通知</span>
    </div>
</section>

<div style="margin:8px 1%;display:flex;gap:8px;flex-wrap:wrap;align-items:center">
    <a class="cs-btn cs-btn-ghost cs-btn-sm <?php echo !$unreadOnly ? 'is-active' : ''; ?>" href="index.php?mod=notices">全部通知</a>
    <a class="cs-btn cs-btn-ghost cs-btn-sm <?php echo $unreadOnly ? 'is-active' : ''; ?>" href="index.php?mod=notices&unread=1">仅未读（<?php echo (int) $unreadCount; ?>）</a>
    <?php if ($unreadCount > 0) { ?>
    <form method="post" action="index.php?mod=notices" style="display:inline">
        <input type="hidden" name="op" value="mark_all_read">
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">
        <button type="submit" class="cs-btn cs-btn-secondary cs-btn-sm">全部标为已读</button>
    </form>
    <?php } ?>
</div>

<div class="cs-table-wrap" style="margin:16px 1%">
    <div class="cs-table-scroll">
        <table class="cs-table">
            <thead>
                <tr>
                    <th style="width:40px"></th>
                    <th style="width:90px">类型</th>
                    <th>标题</th>
                    <th style="width:140px">时间</th>
                    <th style="width:120px">操作</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($rows)) { ?>
                <tr><td colspan="5" style="text-align:center;padding:24px;color:var(--color-muted)">
                    <?php echo $unreadOnly ? '暂无未读通知' : '暂无通知'; ?>
                </td></tr>
            <?php } else { foreach ($rows as $row) {
                $meta = Notice::typeMeta($row['type']);
                $isUnread = $row['is_read'] == 0;
            ?>
                <tr class="<?php echo $isUnread ? 'cs-row-highlight' : ''; ?>">
                    <td style="text-align:center">
                        <?php if ($isUnread) { ?>
                        <span class="cs-badge cs-badge-brand" style="padding:2px 6px;font-size:10px">新</span>
                        <?php } ?>
                    </td>
                    <td>
                        <span class="cs-badge cs-badge-<?php echo htmlspecialchars($meta['color'], ENT_QUOTES, 'UTF-8'); ?>">
                            <svg class="icon-inline" style="width:12px;height:12px;vertical-align:-1px;margin-right:2px" aria-hidden="true"><use href="#<?php echo htmlspecialchars($meta['icon'], ENT_QUOTES, 'UTF-8'); ?>"/></svg>
                            <?php echo htmlspecialchars($meta['label'], ENT_QUOTES, 'UTF-8'); ?>
                        </span>
                    </td>
                    <td>
                        <?php if ($isUnread) { ?><strong><?php } ?>
                        <?php echo htmlspecialchars($row['title'], ENT_QUOTES, 'UTF-8'); ?>
                        <?php if ($isUnread) { ?></strong><?php } ?>
                        <?php if ($row['content']) { ?>
                            <div style="font-size:12px;color:var(--color-muted);margin-top:2px"><?php echo htmlspecialchars(mb_substr($row['content'], 0, 200, 'UTF-8'), ENT_QUOTES, 'UTF-8'); ?></div>
                        <?php } ?>
                        <?php if ($row['link']) { ?>
                            <div style="margin-top:4px"><a href="<?php echo htmlspecialchars($row['link'], ENT_QUOTES, 'UTF-8'); ?>" style="font-size:12px">查看详情 &rarr;</a></div>
                        <?php } ?>
                    </td>
                    <td style="font-size:12px;color:var(--color-muted)">
                        <?php echo htmlspecialchars(date('Y-m-d H:i', $row['created_at']), ENT_QUOTES, 'UTF-8'); ?>
                    </td>
                    <td>
                        <form method="post" action="index.php?mod=notices" style="display:inline">
                            <input type="hidden" name="op" value="<?php echo $isUnread ? 'mark_read' : 'delete'; ?>">
                            <input type="hidden" name="notice_id" value="<?php echo (int) $row['id']; ?>">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">
                            <?php if ($isUnread) { ?>
                            <button type="submit" class="cs-btn cs-btn-ghost cs-btn-sm" title="标记为已读">已读</button>
                            <?php } else { ?>
                            <button type="submit" class="cs-btn cs-btn-ghost cs-btn-sm" title="删除此通知" onclick="return confirm('确认删除？')"><svg style="width:12px;height:12px" aria-hidden="true"><use href="#i-close"/></svg></button>
                            <?php } ?>
                        </form>
                    </td>
                </tr>
            <?php } } ?>
            </tbody>
        </table>
    </div>
</div>

<?php if ($pages > 1) { ?>
<nav class="cs-pagination" aria-label="通知分页" style="margin:16px 1%">
    <?php for ($i = 1; $i <= $pages; $i++) {
        $url = 'index.php?mod=notices&page=' . $i . ($unreadOnly ? '&unread=1' : '');
        $cls = $i === $currentPage ? 'cs-page-item is-active' : 'cs-page-item';
        ?>
        <a class="<?php echo $cls; ?>" href="<?php echo $url; ?>"><?php echo $i; ?></a>
    <?php } ?>
</nav>
<?php } ?>
