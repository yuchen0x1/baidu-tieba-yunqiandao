<?php
if (!defined('SYSTEM_ROOT')) {
    die('Insufficient Permissions');
}
if (ROLE === 'visitor' || ROLE === 'banned') {
    msg('请先登录');
}
require_once SYSTEM_ROOT . '/lib/class.MessageCenter.php';

$viewUid = (ISADMIN && defined('CON_UID') && CON_UID > 0) ? (int) CON_UID : (int) UID;
$type = isset($_GET['type']) && in_array($_GET['type'], array('reply', 'at'), true) ? (string) $_GET['type'] : '';
$unreadOnly = isset($_GET['unread']) ? (bool) $_GET['unread'] : false;
$page = isset($_GET['page']) ? max(1, (int) $_GET['page']) : 1;

// 处理操作
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $opAction = isset($_POST['op']) ? (string) $_POST['op'] : '';
    if ($opAction === 'mark_read' && isset($_POST['message_id'])) {
        $messageId = (int) $_POST['message_id'];
        MessageCenter::markRead($messageId, $viewUid);
    } elseif ($opAction === 'mark_all_read') {
        MessageCenter::markAllRead($viewUid);
    }
    $redirectParams = array('mod' => 'messages');
    if ($type !== '') { $redirectParams['type'] = $type; }
    if ($unreadOnly) { $redirectParams['unread'] = '1'; }
    if ($page > 1) { $redirectParams['page'] = $page; }
    Redirect('index.php?' . http_build_query($redirectParams));
}

$result = MessageCenter::items($viewUid, $type, $unreadOnly, $page, 20);
$rows = $result['rows'];
$total = $result['total'];
$pages = $result['pages'];
$currentPage = $result['page'];
$unreadCount = MessageCenter::unreadCount($viewUid);
$typeLabels = array('reply' => '回复', 'at' => '@我');
$typeColors = array('reply' => 'info', 'at' => 'brand');
if (!function_exists('message_relative_time')) {
    /** 把时间戳渲染为「10 分钟前」样式相对时间，超过 7 天回退绝对日期 */
    function message_relative_time($timestamp)
    {
        $diff = time() - (int) $timestamp;
        if ($diff < 60) return '刚刚';
        if ($diff < 3600) return floor($diff / 60) . ' 分钟前';
        if ($diff < 86400) return floor($diff / 3600) . ' 小时前';
        if ($diff < 7 * 86400) return floor($diff / 86400) . ' 天前';
        return date('Y-m-d H:i', (int) $timestamp);
    }
}
?>
<section class="dashboard-hero">
    <div class="eyebrow hero-eyebrow">Messages / <?php echo date('Y-m-d'); ?></div>
    <h1>贴吧消息中心</h1>
    <p>聚合展示已绑定百度账号的「回复我的 / @我的」消息，每小时自动拉取一次。</p>
    <div class="dashboard-hero-meta">
        <span class="hero-tag">未读 <?php echo (int) $unreadCount; ?> 条</span>
        <span class="hero-tag">共 <?php echo (int) $total; ?> 条消息</span>
    </div>
</section>

<div style="margin:8px 1%;display:flex;gap:8px;flex-wrap:wrap;align-items:center">
    <a class="cs-btn cs-btn-ghost cs-btn-sm <?php echo $type === '' ? 'is-active' : ''; ?>" href="index.php?mod=messages">全部</a>
    <a class="cs-btn cs-btn-ghost cs-btn-sm <?php echo $type === 'reply' ? 'is-active' : ''; ?>" href="index.php?mod=messages&type=reply">回复我的</a>
    <a class="cs-btn cs-btn-ghost cs-btn-sm <?php echo $type === 'at' ? 'is-active' : ''; ?>" href="index.php?mod=messages&type=at">@我的</a>
    <a class="cs-btn cs-btn-ghost cs-btn-sm <?php echo $unreadOnly ? 'is-active' : ''; ?>" href="index.php?mod=messages<?php echo $type !== '' ? '&type=' . $type : ''; ?>&unread=1">仅未读</a>
    <?php if ($unreadCount > 0) { ?>
    <form method="post" action="index.php?mod=messages" style="display:inline">
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
                    <th style="width:80px">类型</th>
                    <th>内容</th>
                    <th style="width:100px">发送者</th>
                    <th style="width:120px">时间</th>
                    <th style="width:150px">操作</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($rows)) { ?>
                <tr><td colspan="6" style="text-align:center;padding:24px;color:var(--color-muted)">
                    暂无消息。绑定并保持百度账号有效后，系统会每小时自动拉取。
                </td></tr>
            <?php } else { foreach ($rows as $row) {
                $isUnread = $row['is_read'] == 0;
                $rowType = isset($typeLabels[$row['type']]) ? $row['type'] : 'reply';
            ?>
                <tr class="<?php echo $isUnread ? 'cs-row-highlight' : ''; ?>">
                    <td style="text-align:center">
                        <?php if ($isUnread) { ?>
                        <span class="cs-badge cs-badge-brand" style="padding:2px 6px;font-size:10px">新</span>
                        <?php } ?>
                    </td>
                    <td>
                        <span class="cs-badge cs-badge-<?php echo htmlspecialchars($typeColors[$rowType], ENT_QUOTES, 'UTF-8'); ?>"><?php echo $typeLabels[$rowType]; ?></span>
                    </td>
                    <td>
                        <?php if ($isUnread) { ?><strong><?php } ?>
                        <?php echo htmlspecialchars($row['title'] !== '' ? $row['title'] : '（无标题）', ENT_QUOTES, 'UTF-8'); ?>
                        <?php if ($isUnread) { ?></strong><?php } ?>
                        <?php if ($row['content']) { ?>
                            <div style="font-size:12px;color:var(--color-muted);margin-top:2px"><?php echo htmlspecialchars(mb_substr($row['content'], 0, 200, 'UTF-8'), ENT_QUOTES, 'UTF-8'); ?></div>
                        <?php } ?>
                        <?php if ($row['forum']) { ?>
                            <div style="font-size:12px;color:var(--color-muted);margin-top:2px">来自贴吧：<?php echo htmlspecialchars($row['forum'], ENT_QUOTES, 'UTF-8'); ?></div>
                        <?php } ?>
                    </td>
                    <td style="font-size:12px"><?php echo htmlspecialchars($row['sender_name'], ENT_QUOTES, 'UTF-8'); ?></td>
                    <td style="font-size:12px;color:var(--color-muted)" title="<?php echo htmlspecialchars(date('Y-m-d H:i', $row['created_at']), ENT_QUOTES, 'UTF-8'); ?>">
                        <?php echo htmlspecialchars(message_relative_time($row['created_at']), ENT_QUOTES, 'UTF-8'); ?>
                    </td>
                    <td>
                        <?php if ($row['url']) { ?>
                        <a class="cs-btn cs-btn-ghost cs-btn-sm" href="<?php echo htmlspecialchars($row['url'], ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener">查看</a>
                        <?php } ?>
                        <?php if ($isUnread) { ?>
                        <form method="post" action="index.php?mod=messages" style="display:inline">
                            <input type="hidden" name="op" value="mark_read">
                            <input type="hidden" name="message_id" value="<?php echo (int) $row['id']; ?>">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">
                            <button type="submit" class="cs-btn cs-btn-ghost cs-btn-sm">已读</button>
                        </form>
                        <?php } ?>
                    </td>
                </tr>
            <?php } } ?>
            </tbody>
        </table>
    </div>
</div>

<?php if ($pages > 1) { ?>
<div style="margin:8px 1%;display:flex;gap:6px;justify-content:center">
    <?php for ($p = 1; $p <= $pages; $p++) { ?>
        <a class="cs-btn cs-btn-ghost cs-btn-sm <?php echo $p == $currentPage ? 'is-active' : ''; ?>"
           href="index.php?mod=messages<?php echo $type !== '' ? '&type=' . $type : ''; ?><?php echo $unreadOnly ? '&unread=1' : ''; ?>&page=<?php echo $p; ?>"><?php echo $p; ?></a>
    <?php } ?>
</div>
<?php } ?>
