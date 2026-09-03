<?php
if (!defined('SYSTEM_ROOT')) {
    die('Insufficient Permissions');
}
global $i;

$pageLabels = array(
    'default' => '工作台',
    'set' => '个人设置',
    'baiduid' => '百度账号',
    'showtb' => '贴吧与签到',
    'account' => '账号中心',
    'calendar' => '签到日历',
    'signlogs' => '签到日志',
    'messages' => '贴吧消息中心',
    'ranking' => '签到排行',
    'achievements' => '成就勋章',
    'admin:tools' => '工具箱',
    'admin:set' => '设置中心',
    'admin:users' => '用户管理',
    'admin:invite' => '邀请码管理',
    'admin:stat' => '统计信息',
    'admin:sign' => '签到看板',
    'admin:cron' => '计划任务',
    'admin:totp' => '双因素认证',
    'admin:update' => '系统更新',
    'admin:plugins' => '插件管理',
    'thanks' => '开源致谢',
    'login' => '登录',
    'reg' => '注册'
);
$currentPage = defined('SYSTEM_PAGE') ? SYSTEM_PAGE : 'default';
$currentLabel = isset($pageLabels[$currentPage]) ? $pageLabels[$currentPage] : '控制台';
$isMember = defined('ROLE') && ROLE != 'visitor' && ROLE != 'banned';
$userName = $isMember && defined('NAME') ? NAME : '访客';
$userInitial = function_exists('mb_substr') ? mb_substr($userName, 0, 1, 'UTF-8') : substr($userName, 0, 1);
?>
<div class="app-shell" id="app-shell">
    <aside class="app-sidebar" id="app-sidebar" aria-label="主导航">
        <a class="app-brand" href="index.php">
            <span class="brand-mark">云</span>
            <span class="brand-copy">
                <span class="brand-name"><?php echo htmlspecialchars(SYSTEM_NAME, ENT_QUOTES, 'UTF-8'); ?></span>
                <span class="brand-subtitle">SIGNING CONTROL STUDIO</span>
            </span>
        </a>

        <?php if ($isMember) { ?>
        <section class="sidebar-section">
            <div class="sidebar-label">Workspace</div>
            <nav class="sidebar-nav">
                <ul>
                    <li class="<?php checkIfActive('default'); ?>"><a href="index.php"><svg class="sidebar-icon" aria-hidden="true"><use href="#i-home"/></svg><span>工作台</span></a></li>
                    <li class="<?php checkIfActive('baiduid'); ?>"><a href="index.php?mod=baiduid"><svg class="sidebar-icon" aria-hidden="true"><use href="#i-link"/></svg><span>百度账号</span></a></li>
                    <li class="<?php checkIfActive('account'); ?>"><a href="index.php?mod=account"><svg class="sidebar-icon" aria-hidden="true"><use href="#i-user"/></svg><span>账号中心</span></a></li>
                    <li class="<?php checkIfActive('showtb'); ?>"><a href="index.php?mod=showtb"><svg class="sidebar-icon" aria-hidden="true"><use href="#i-calendar-grid"/></svg><span>贴吧与签到</span></a></li>
                    <li class="<?php checkIfActive('calendar'); ?>"><a href="index.php?mod=calendar"><svg class="sidebar-icon" aria-hidden="true"><use href="#i-calendar"/></svg><span>签到日历</span></a></li>
                    <li class="<?php checkIfActive('signlogs'); ?>"><a href="index.php?mod=signlogs"><svg class="sidebar-icon" aria-hidden="true"><use href="#i-list"/></svg><span>签到日志</span></a></li>
                    <li class="<?php checkIfActive('messages'); ?>"><a href="index.php?mod=messages" id="nav-messages-link"><svg class="sidebar-icon" aria-hidden="true"><use href="#i-mail"/></svg><span>消息中心</span><?php
                        // 服务端渲染消息未读徽章
                        $messageUnread = 0;
                        if (file_exists(SYSTEM_ROOT . '/lib/class.MessageCenter.php') && defined('UID') && UID > 0) {
                            require_once SYSTEM_ROOT . '/lib/class.MessageCenter.php';
                            $messageUnread = MessageCenter::unreadCount(UID);
                        }
                        if ($messageUnread > 0) {
                            echo '<span class="nav-badge nav-badge-show">' . ($messageUnread > 99 ? '99+' : (int) $messageUnread) . '</span>';
                        }
                    ?></a></li>
                    <li class="<?php checkIfActive('ranking'); ?>"><a href="index.php?mod=ranking"><svg class="sidebar-icon" aria-hidden="true"><use href="#i-trophy"/></svg><span>签到排行</span></a></li>
                    <li class="<?php checkIfActive('achievements'); ?>"><a href="index.php?mod=achievements"><svg class="sidebar-icon" aria-hidden="true"><use href="#i-award"/></svg><span>成就勋章</span></a></li>
                    <li class="<?php checkIfActive('notices'); ?>"><a href="index.php?mod=notices" id="nav-notices-link"><svg class="sidebar-icon" aria-hidden="true"><use href="#i-bell"/></svg><span>站内通知</span><?php
                        // 服务端渲染未读徽章
                        $noticeUnread = 0;
                        if (file_exists(SYSTEM_ROOT . '/lib/class.Notice.php') && defined('UID') && UID > 0) {
                            require_once SYSTEM_ROOT . '/lib/class.Notice.php';
                            $noticeUnread = Notice::unreadCount(UID);
                        }
                        if ($noticeUnread > 0) {
                            echo '<span class="nav-badge nav-badge-show">' . ($noticeUnread > 99 ? '99+' : (int) $noticeUnread) . '</span>';
                        }
                    ?></a></li>
                    <li class="<?php checkIfActive('set'); ?>"><a href="index.php?mod=set"><svg class="sidebar-icon" aria-hidden="true"><use href="#i-settings"/></svg><span>个人设置</span></a></li>
                    <li class="<?php checkIfActive('thanks'); ?>"><a href="index.php?mod=thanks"><svg class="sidebar-icon" aria-hidden="true"><use href="#i-heart"/></svg><span>开源致谢</span></a></li>
                    <?php doAction('navi_1'); ?>
                </ul>
            </nav>
        </section>

        <?php if (ROLE == 'admin') { ?>
        <section class="sidebar-section">
            <div class="sidebar-label">Operations</div>
            <nav class="sidebar-nav">
                <ul>
                    <li class="<?php checkIfActive('admin:stat'); ?>"><a href="index.php?mod=admin:stat"><svg class="sidebar-icon" aria-hidden="true"><use href="#i-bar-chart"/></svg><span>统计信息</span></a></li>
                    <li class="<?php checkIfActive('admin:sign'); ?>"><a href="index.php?mod=admin:sign"><svg class="sidebar-icon" aria-hidden="true"><use href="#i-pulse"/></svg><span>签到看板</span></a></li>
                    <li class="<?php checkIfActive('admin:users'); ?>"><a href="index.php?mod=admin:users"><svg class="sidebar-icon" aria-hidden="true"><use href="#i-users"/></svg><span>用户管理</span></a></li>
                    <li class="<?php checkIfActive('admin:invite'); ?>"><a href="index.php?mod=admin:invite"><svg class="sidebar-icon" aria-hidden="true"><use href="#i-mail"/></svg><span>邀请码管理</span></a></li>
                    <li class="<?php checkIfActive('admin:cron'); ?>"><a href="index.php?mod=admin:cron"><svg class="sidebar-icon" aria-hidden="true"><use href="#i-clock"/></svg><span>计划任务</span></a></li>
                    <li class="<?php checkIfActive('admin:totp'); ?>"><a href="index.php?mod=admin:totp"><svg class="sidebar-icon" aria-hidden="true"><use href="#i-lock"/></svg><span>双因素认证</span></a></li>
                    <li class="<?php checkIfActive('admin:tools'); ?>"><a href="index.php?mod=admin:tools"><svg class="sidebar-icon" aria-hidden="true"><use href="#i-tool"/></svg><span>工具箱</span></a></li>
                    <li class="<?php checkIfActive('admin:set'); ?>"><a href="index.php?mod=admin:set"><svg class="sidebar-icon" aria-hidden="true"><use href="#i-settings-circle"/></svg><span>设置中心</span></a></li>
                    <?php doAction('navi_2'); ?>
                </ul>
            </nav>
        </section>

        <section class="sidebar-section">
            <div class="sidebar-label">Extensions</div>
            <nav class="sidebar-nav">
                <ul>
                    <li class="<?php checkIfActive('admin:plugins'); ?>"><a href="index.php?mod=admin:plugins"><svg class="sidebar-icon" aria-hidden="true"><use href="#i-grid"/></svg><span>插件管理</span></a></li>
                    <li><a href="<?php echo htmlspecialchars(PROJECT_WIKI_URL, ENT_QUOTES, 'UTF-8'); ?>/%E8%B4%B4%E5%90%A7%E4%BA%91%E7%AD%BE%E5%88%B0%E6%8F%92%E4%BB%B6%E5%BA%93" target="_blank"><svg class="sidebar-icon" aria-hidden="true"><use href="#i-download"/></svg><span>插件资源</span></a></li>
                    <li class="<?php checkIfActive('admin:update'); ?>"><a href="index.php?mod=admin:update"><svg class="sidebar-icon" aria-hidden="true"><use href="#i-refresh"/></svg><span>系统更新</span></a></li>
                    <?php doAction('navi_3'); doAction('navi_4'); ?>
                </ul>
            </nav>
        </section>
        <?php } ?>
        <?php } else { ?>
        <section class="sidebar-section">
            <div class="sidebar-label">Get Started</div>
            <nav class="sidebar-nav">
                <ul>
                    <li class="<?php checkIfActive('login'); ?>"><a href="index.php?mod=login"><svg class="sidebar-icon" aria-hidden="true"><use href="#i-log-in"/></svg><span>登录</span></a></li>
                    <li class="<?php checkIfActive('reg'); ?>"><a href="index.php?mod=reg"><svg class="sidebar-icon" aria-hidden="true"><use href="#i-user-plus"/></svg><span>注册</span></a></li>
                    <?php doAction('navi_10'); doAction('navi_11'); ?>
                </ul>
            </nav>
        </section>
        <?php } ?>

        <div class="sidebar-footer">
            <div>当前版本 V<?php echo SYSTEM_VER; ?></div>
            <a href="<?php echo htmlspecialchars(PROJECT_GITHUB_URL, ENT_QUOTES, 'UTF-8'); ?>" target="_blank">反馈与文档</a>
            <?php doAction('navi_5'); ?>
        </div>
    </aside>
    <div class="app-sidebar-backdrop" data-sidebar-close></div>

    <div class="app-main">
        <header class="app-topbar">
            <div class="topbar-left">
                <button type="button" class="topbar-menu" data-sidebar-toggle aria-label="打开导航"><svg class="icon-inline" aria-hidden="true"><use href="#i-menu"/></svg></button>
                <div>
                    <span class="topbar-kicker"><?php echo $isMember ? 'Control Center' : 'Welcome' ?></span>
                    <span class="topbar-title"><?php echo htmlspecialchars($currentLabel, ENT_QUOTES, 'UTF-8'); ?></span>
                </div>
            </div>
            <div class="topbar-right">
                <button type="button" class="topbar-action topbar-theme-toggle" data-theme-toggle aria-label="切换深色模式" title="切换深色模式"><svg class="icon-inline icon-sun" aria-hidden="true"><use href="#i-sun"/></svg><svg class="icon-inline icon-moon" aria-hidden="true"><use href="#i-moon"/></svg><span class="action-text">主题</span></button>
                <?php if (defined('CON_UID')) { ?>
                    <a class="topbar-action" href="index.php?mod=usercontrolback" title="返回 <?php echo htmlspecialchars(CON_NAME, ENT_QUOTES, 'UTF-8'); ?>"><svg class="icon-inline" aria-hidden="true"><use href="#i-back"/></svg><span class="action-text">返回 <?php echo htmlspecialchars(CON_NAME, ENT_QUOTES, 'UTF-8'); ?></span></a>
                <?php } ?>
                <?php if ($isMember) { ?>
                    <span class="topbar-user"><span class="user-chip"><?php echo htmlspecialchars($userInitial, ENT_QUOTES, 'UTF-8'); ?></span><span class="topbar-user-name"><?php echo htmlspecialchars($userName, ENT_QUOTES, 'UTF-8'); ?></span></span>
                    <a class="topbar-action" href="index.php?mod=admin:logout" title="退出登录"><svg class="icon-inline" aria-hidden="true"><use href="#i-log-out"/></svg><span class="action-text">退出</span></a>
                <?php } else { ?>
                    <a class="topbar-action" href="index.php?mod=login">登录</a>
                <?php } ?>
                <?php doAction('navi_6'); ?>
            </div>
        </header>
        <main class="app-content" role="main">
            <div class="app-content-inner">
