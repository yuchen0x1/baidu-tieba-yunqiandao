<?php

if (!defined('SYSTEM_ROOT')) {
    die('Insufficient Permissions');
}
global $m;
require_once SYSTEM_ROOT . '/lib/security.php';

/**
 * 升级模式提示：管理员一键升级为第一路径（根目录 upgrade.php，自动备份+迁移+清缓存，
 * 不依赖 /setup/ 可访问）；CLI 为第二路径。历史上不少站点按安装指引封禁了 /setup/，
 * 门禁页若只给 setup/ 内的 Web 链接会让用户困在死路。
 */
if (!function_exists('upgrade_notice_html')) {
    function upgrade_notice_html($legacyScript = '')
    {
        $coreVersion = htmlspecialchars($GLOBALS['i']['opt']['core_version'], ENT_QUOTES, 'UTF-8');
        $ver = htmlspecialchars(SYSTEM_VER, ENT_QUOTES, 'UTF-8');
        $html = '数据库中的云签到版本(' . $coreVersion . ')与文件版本(' . $ver . ')不符，站点已进入升级模式。<br/><br/>'
            . '<b>方式一（推荐，管理员一键完成）</b>：<a class="btn btn-primary" href="upgrade.php">点击一键升级到 V' . $ver . '</a><br/>'
            . '<span class="text-muted">自动备份数据库 → 自动运行迁移器 → 自动清理缓存，全程无需 SSH。非管理员请先登录管理员账号。</span><br/><br/>'
            . '<b>方式二</b>：SSH 进入站点根目录执行统一迁移器<br/><code>php tools/migrate.php up</code><br/><br/>'
            . '升级前请先备份数据库（方式一已包含自动备份）。';
        if ($legacyScript !== '') {
            $html .= '<br/><span class="text-muted">也可以运行专用脚本 ' . htmlspecialchars($legacyScript, ENT_QUOTES, 'UTF-8') . '（需 /setup/ 未被 Web 服务器封禁）。</span>';
        }
        return $html;
    }
}

/**
 * 环境准备
 */
$today       = date("Y-m-d");
$i           = array();

$i['plugins']['hook'] = array(); //挂载列表
//注册全局信息变量 $i
$i['db']['host'] = DB_HOST;
$i['db']['user'] = DB_USER;
$i['db']['prefix'] = DB_PREFIX;
$i['db']['passwd'] = DB_PASSWD;
$i['db']['name'] = DB_NAME;

//_POST _GET _REQUEST
$i['post']    = $_POST;
$i['get']     = $_GET;
$i['request'] = $_REQUEST;
$ws = $m->query("SELECT * FROM " . DB_PREFIX . "options");
while ($wsr = $m->fetch_array($ws)) {
    $key = $wsr['name'];
    $i['opt'][$key] = $wsr['value'];
}
$rs = $m->query("SELECT *  FROM `" . DB_NAME . "`.`" . DB_PREFIX . "cron` ORDER BY `orde` ASC");
while ($rsr = $m->fetch_array($rs)) {
    $key = $rsr['name'];
    $i['cron'][$key] = $rsr;
}

//贴吧分表列表：'tieba' 为基础表、始终参与查询；自定义分表来自 fb_tables 设置并经白名单校验
$i['tabpart'] = safe_table_names(isset($i['opt']['fb_tables']) ? $i['opt']['fb_tables'] : '');
$i['table'] = array_values(array_unique(array_merge(array('tieba'), $i['tabpart']))); //贴吧表列表

//当前页面/模式, $i['mode'][0] 一般表示页面
if (request_text_value($_REQUEST, 'mod') !== '') {
    $i['mode'] = explode(':', request_text_value($_REQUEST, 'mod'));
} else {
    $i['mode'][0] = 'default';
}

if ((empty($i['opt']['core_version']) || SYSTEM_VER != $i['opt']['core_version']) && !defined('SYSTEM_NO_CHECK_VER')) {
    if (empty($i['opt']['core_version'])) {
        $i['opt']['core_version'] = '3.45';
    }
    $upgradeFile = SYSTEM_ROOT . '/setup/update' . $i['opt']['core_version'] . 'to' . SYSTEM_VER . '.php';
    if (file_exists($upgradeFile)) {
        msg(upgrade_notice_html('setup/update' . $i['opt']['core_version'] . 'to' . SYSTEM_VER . '.php'));
    } elseif (version_compare($i['opt']['core_version'], '6.6', '>=') && version_compare($i['opt']['core_version'], SYSTEM_VER, '<')) {
        // 6.6 及以上版本由统一迁移器直接升到当前版本，无需逐版本升级脚本
        msg(upgrade_notice_html());
    } else {
        msg('严重错误：数据库中的云签到版本(' . htmlspecialchars($i['opt']['core_version'], ENT_QUOTES, 'UTF-8') . ')与文件版本(' . SYSTEM_VER . ')不符，且未找到对应的升级脚本。请先运行 setup/update.php 逐步升级，不要跳过中间版本。');
    }
}

if (!defined('SYSTEM_NO_PLUGIN')) {
    //所有插件列表
    $i['plugins'] = array('all' => array() , 'actived' => array() , 'info' => array());
    $plugin_all_query = $m->query("SELECT * FROM `" . DB_PREFIX . "plugins` ORDER BY `order` ASC");
    while ($plugin_all_var = $m->fetch_array($plugin_all_query)) {
        $i['plugins']['all'][] = $plugin_all_var['name'];
        $i['plugins']['info'][$plugin_all_var['name']] = $plugin_all_var;
        $i['plugins']['info'][$plugin_all_var['name']]['options'] = safe_option_array($plugin_all_var['options']);
        if ($plugin_all_var['status'] == '1') {
            $i['plugins']['actived'][] = $plugin_all_var['name'];
        }
    }
}

//autoload（大小写无关解析见 lib/class.loader.php，兼容 Linux 容器文件系统）
require_once SYSTEM_ROOT . '/lib/class.loader.php';
function class_autoload($c)
{
    $file = lib_class_file($c);
    if ($file !== null) {
        include $file;
    } else {
        throw new Exception('无法加载此类：' . $c, 10001);
    }
}
spl_autoload_register('class_autoload');

//升级入口（根目录 upgrade.php、setup/upgrade.php）会预定义 SYSTEM_DEV 以标记维护上下文，此处不得覆盖
if (!defined('SYSTEM_DEV')) {
    if (option::get('dev') != 1) {
        define('SYSTEM_DEV', false);
    } else {
        define('SYSTEM_DEV', true);
    }
}

set_exception_handler(array('E','exception'));
set_error_handler(array('E','error'));
