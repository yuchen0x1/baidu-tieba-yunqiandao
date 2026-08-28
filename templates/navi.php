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
    'admin:tools' => '工具箱',
    'admin:set' => '设置中心',
    'admin:users' => '用户管理',
    'admin:stat' => '统计信息',
    'admin:sign' => '签到看板',
    'admin:cron' => '计划任务',
    'admin:update' => '系统更新',
    'admin:plugins' => '插件管理',
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
                    <li class="<?php checkIfActive('default'); ?>"><a href="index.php"><span class="sidebar-icon glyphicon glyphicon-home"></span><span>工作台</span></a></li>
                    <li class="<?php checkIfActive('baiduid'); ?>"><a href="index.php?mod=baiduid"><span class="sidebar-icon glyphicon glyphicon-link"></span><span>百度账号</span></a></li>
                    <li class="<?php checkIfActive('showtb'); ?>"><a href="index.php?mod=showtb"><span class="sidebar-icon glyphicon glyphicon-calendar"></span><span>贴吧与签到</span></a></li>
                    <li class="<?php checkIfActive('set'); ?>"><a href="index.php?mod=set"><span class="sidebar-icon glyphicon glyphicon-cog"></span><span>个人设置</span></a></li>
                    <?php doAction('navi_1'); ?>
                </ul>
            </nav>
        </section>

        <?php if (ROLE == 'admin') { ?>
        <section class="sidebar-section">
            <div class="sidebar-label">Operations</div>
            <nav class="sidebar-nav">
                <ul>
                    <li class="<?php checkIfActive('admin:stat'); ?>"><a href="index.php?mod=admin:stat"><span class="sidebar-icon glyphicon glyphicon-stats"></span><span>统计信息</span></a></li>
                    <li class="<?php checkIfActive('admin:sign'); ?>"><a href="index.php?mod=admin:sign"><span class="sidebar-icon glyphicon glyphicon-dashboard"></span><span>签到看板</span></a></li>
                    <li class="<?php checkIfActive('admin:users'); ?>"><a href="index.php?mod=admin:users"><span class="sidebar-icon glyphicon glyphicon-user"></span><span>用户管理</span></a></li>
                    <li class="<?php checkIfActive('admin:cron'); ?>"><a href="index.php?mod=admin:cron"><span class="sidebar-icon glyphicon glyphicon-time"></span><span>计划任务</span></a></li>
                    <li class="<?php checkIfActive('admin:tools'); ?>"><a href="index.php?mod=admin:tools"><span class="sidebar-icon glyphicon glyphicon-wrench"></span><span>工具箱</span></a></li>
                    <li class="<?php checkIfActive('admin:set'); ?>"><a href="index.php?mod=admin:set"><span class="sidebar-icon glyphicon glyphicon-adjust"></span><span>设置中心</span></a></li>
                    <?php doAction('navi_2'); ?>
                </ul>
            </nav>
        </section>

        <section class="sidebar-section">
            <div class="sidebar-label">Extensions</div>
            <nav class="sidebar-nav">
                <ul>
                    <li class="<?php checkIfActive('admin:plugins'); ?>"><a href="index.php?mod=admin:plugins"><span class="sidebar-icon glyphicon glyphicon-th-large"></span><span>插件管理</span></a></li>
                    <li><a href="<?php echo htmlspecialchars(PROJECT_WIKI_URL, ENT_QUOTES, 'UTF-8'); ?>/%E8%B4%B4%E5%90%A7%E4%BA%91%E7%AD%BE%E5%88%B0%E6%8F%92%E4%BB%B6%E5%BA%93" target="_blank"><span class="sidebar-icon glyphicon glyphicon-download-alt"></span><span>插件资源</span></a></li>
                    <li class="<?php checkIfActive('admin:update'); ?>"><a href="index.php?mod=admin:update"><span class="sidebar-icon glyphicon glyphicon-refresh"></span><span>系统更新</span></a></li>
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
                    <li class="<?php checkIfActive('login'); ?>"><a href="index.php?mod=login"><span class="sidebar-icon glyphicon glyphicon-log-in"></span><span>登录</span></a></li>
                    <li class="<?php checkIfActive('reg'); ?>"><a href="index.php?mod=reg"><span class="sidebar-icon glyphicon glyphicon-user"></span><span>注册</span></a></li>
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
                <button type="button" class="topbar-menu" data-sidebar-toggle aria-label="打开导航"><span class="glyphicon glyphicon-menu-hamburger"></span></button>
                <div>
                    <span class="topbar-kicker"><?php echo $isMember ? 'Control Center' : 'Welcome' ?></span>
                    <span class="topbar-title"><?php echo htmlspecialchars($currentLabel, ENT_QUOTES, 'UTF-8'); ?></span>
                </div>
            </div>
            <div class="topbar-right">
                <button type="button" class="topbar-action topbar-theme-toggle" data-theme-toggle aria-label="切换深色模式" title="切换深色模式"><span class="glyphicon glyphicon-adjust" aria-hidden="true"></span><span class="action-text">主题</span></button>
                <?php if (defined('CON_UID')) { ?>
                    <a class="topbar-action" href="index.php?mod=usercontrolback"><span class="glyphicon glyphicon-transfer"></span><span class="action-text">返回 <?php echo htmlspecialchars(CON_NAME, ENT_QUOTES, 'UTF-8'); ?></span></a>
                <?php } ?>
                <?php if ($isMember) { ?>
                    <span class="topbar-user"><span class="user-chip"><?php echo htmlspecialchars($userInitial, ENT_QUOTES, 'UTF-8'); ?></span><span class="topbar-user-name"><?php echo htmlspecialchars($userName, ENT_QUOTES, 'UTF-8'); ?></span></span>
                    <a class="topbar-action" href="index.php?mod=admin:logout"><span class="glyphicon glyphicon-log-out"></span><span class="action-text">退出</span></a>
                <?php } else { ?>
                    <a class="topbar-action" href="index.php?mod=login">登录</a>
                <?php } ?>
                <?php doAction('navi_6'); ?>
            </div>
        </header>
        <main class="app-content" role="main">
            <div class="app-content-inner">
