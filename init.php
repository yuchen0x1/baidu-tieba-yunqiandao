<?php

/**
 * 贴吧云签到
 * 获取开发文档：https://github.com/yuchen0x1/baidu-tieba-yunqiandao/wiki
 */

define('SYSTEM_ROOT', dirname(__FILE__));
require SYSTEM_ROOT . '/version.php';
require_once SYSTEM_ROOT . '/lib/request.php';
define('PLUGIN_ROOT', dirname(__FILE__) . '/plugins/');
define('SYSTEM_ISCONSOLE', (PHP_SAPI === 'cli') || defined('STDIN'));
define('SYSTEM_PAGE', request_text_value($_REQUEST, 'mod', 'default'));
define('PROJECT_REPOSITORY_OWNER', 'yuchen0x1');
define('PROJECT_REPOSITORY_NAME', 'baidu-tieba-yunqiandao');
define('PROJECT_GITHUB_URL', 'https://github.com/' . PROJECT_REPOSITORY_OWNER . '/' . PROJECT_REPOSITORY_NAME);
define('PROJECT_GITHUB_API_URL', 'https://api.github.com/repos/' . PROJECT_REPOSITORY_OWNER . '/' . PROJECT_REPOSITORY_NAME);
define('PROJECT_RELEASES_URL', PROJECT_GITHUB_URL . '/releases');
define('PROJECT_RELEASES_API_URL', PROJECT_GITHUB_API_URL . '/releases/latest');
define('PROJECT_WIKI_URL', PROJECT_GITHUB_URL . '/wiki');
define('PROJECT_ISSUES_URL', PROJECT_GITHUB_URL . '/issues');
define('SUPPORT_URL', PROJECT_WIKI_URL);
if (defined('SYSTEM_NO_ERROR')) {
    error_reporting(0);
}

if (SYSTEM_ISCONSOLE) {
    function console_htmltag_delete($v)
    {
        $v = str_ireplace(array('</td>','</th>'), ' | ', $v);
        $v = str_ireplace(array('<br/>','</p>','</tr>','</thead>','</tbody>'), PHP_EOL, $v);
        $v = str_ireplace(array('&nbsp;'), ' ', $v);
        return SYSTEM_FN . ' Ver.' . SYSTEM_VER . ' ' . SYSTEM_VER_NOTE . ' - 控制台模式' . PHP_EOL . '==========================================================' . PHP_EOL . strip_tags($v);
    }
    ob_start('console_htmltag_delete');
}
require SYSTEM_ROOT . '/lib/msg.php';

//如需停止站点运行，请解除注释，即删除开头的 //
//msg('站点已关闭！请稍后再试，如有疑问请联系站长解决！');

$autoCreateInstallLock = getenv('TC_AUTO_CREATE_INSTALL_LOCK');
if (
    !file_exists(SYSTEM_ROOT . '/setup/install.lock')
    && file_exists(SYSTEM_ROOT . '/setup/install.php')
    && $autoCreateInstallLock !== false
    && filter_var($autoCreateInstallLock, FILTER_VALIDATE_BOOLEAN)
    && file_exists(SYSTEM_ROOT . '/config.php')
) {
    // Only lock an already initialized installation. A fresh database must keep
    // the installer accessible even when the container safeguard is enabled.
    require_once SYSTEM_ROOT . '/config.php';
    $lockConnection = class_exists('mysqli') ? @mysqli_init() : false;
    if ($lockConnection) {
        $lockEndpoint = parse_database_endpoint(DB_HOST);
        $lockHost = $lockEndpoint['host'];
        $lockPort = $lockEndpoint['port'];
        $lockFlags = defined('DB_SSL') && DB_SSL ? MYSQLI_CLIENT_SSL : 0;
        if (@$lockConnection->real_connect($lockHost, DB_USER, DB_PASSWD, DB_NAME, $lockPort, null, $lockFlags)) {
            $lockConnection->set_charset('utf8mb4');
            $lockTablesReady = true;
            foreach (array('baiduid', 'cron', 'options', 'plugins', 'tieba', 'users', 'users_options', 'baiduid_health', 'cron_log_batch', 'sign_history') as $lockTable) {
                $lockTableName = $lockConnection->real_escape_string(DB_PREFIX . $lockTable);
                $lockResult = $lockConnection->query(
                    "SELECT COUNT(*) AS total FROM information_schema.tables " .
                    "WHERE table_schema = DATABASE() AND table_name = '{$lockTableName}'"
                );
                if (!$lockResult) {
                    $lockTablesReady = false;
                    break;
                }
                $lockRow = $lockResult->fetch_assoc();
                $lockResult->free();
                if (empty($lockRow['total'])) {
                    $lockTablesReady = false;
                    break;
                }
            }
            if ($lockTablesReady) {
                $lockAdminTable = '`' . str_replace('`', '``', DB_PREFIX . 'users') . '`';
                $lockAdminResult = $lockConnection->query(
                    "SELECT COUNT(*) AS total FROM {$lockAdminTable} WHERE `role` = 'admin'"
                );
                $lockTablesReady = $lockAdminResult && !empty(($lockAdminResult->fetch_assoc())['total']);
                if ($lockAdminResult) {
                    $lockAdminResult->free();
                }
            }
            if ($lockTablesReady) {
                @file_put_contents(SYSTEM_ROOT . '/setup/install.lock', '1');
            }
        }
        $lockConnection->close();
    }
}
if (!file_exists(SYSTEM_ROOT . '/setup/install.lock') && file_exists(SYSTEM_ROOT . '/setup/install.php')) {
    // 不要在安装完成前自动创建锁：安装向导第 5 步完成时会自行写入 install.lock
    msg('<h2>检测到无 install.lock 文件</h2><ul><li><font size="4">如果您尚未安装本程序，请<a href="./setup/install.php">前往安装</a></font></li><li><font size="4">如果您已经安装本程序，请手动放置一个空的 install.lock 文件到 /setup 文件夹下，<b>为了您站点安全，在您完成它之前我们不会工作。</b></font></li></ul><br/><h4>为什么必须建立 install.lock 文件？</h4>它是云签到的保护文件，如果云签到检测不到它，就会认为站点还没安装，此时任何人都可以安装/重装云签到。<br/><br/>', false, true, false);
}

header("content-type:text/html; charset=utf-8");
date_default_timezone_set('Asia/Shanghai');
// 为 CSRF 令牌提供一次性随机种子（HttpOnly + SameSite=Lax）
if (!SYSTEM_ISCONSOLE && empty($_COOKIE['tc_csrf']) && !headers_sent()) {
    try {
        $csrfCookieValue = bin2hex(random_bytes(16));
    } catch (Throwable $e) {
        $csrfCookieValue = md5(uniqid('', true) . microtime());
    }
    $isHttps = (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off')
        || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower((string) $_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https');
    setcookie('tc_csrf', $csrfCookieValue, array(
        'expires' => time() + 31536000,
        'path' => '/',
        'secure' => $isHttps,
        'httponly' => true,
        'samesite' => 'Lax'
    ));
    $_COOKIE['tc_csrf'] = $csrfCookieValue;
}
if (file_exists(SYSTEM_ROOT . '/key.php')) {
    include SYSTEM_ROOT . '/key.php';
}
require SYSTEM_ROOT . '/lib/class.E.php';
require SYSTEM_ROOT . '/lib/class.P.php';
require SYSTEM_ROOT . '/config.php';
require SYSTEM_ROOT . '/lib/mysql_autoload.php';
require SYSTEM_ROOT . '/lib/class.loginguard.php';
require SYSTEM_ROOT . '/lib/class.notify.php';
require SYSTEM_ROOT . '/lib/class.former.php';
require SYSTEM_ROOT . '/lib/class.smtp.php';
require SYSTEM_ROOT . '/lib/class.zip.php';
require SYSTEM_ROOT . '/lib/reg.php';
//系统地址统一以 / 结尾，保证 SYSTEM_URL . 'do.php' 等拼接在任何配置下都正确
define('SYSTEM_URL', rtrim(trim((string) option::get('system_url')), '/') . '/');
define('SYSTEM_NAME', option::get('system_name'));
//版本修订号
define('SYSTEM_REV', option::get('core_revision'));
//压缩包链接
define('UPDATE_SERVER_GITHUB', PROJECT_GITHUB_URL . '/archive/refs/heads/main.zip');
//压缩包内文件夹名
define('UPDATE_FNAME_GITHUB', 'baidu-tieba-yunqiandao-main');
//压缩包解压路径
define('UPDATE_CACHE', SYSTEM_ROOT . '/setup/update_cache/');
require SYSTEM_ROOT . '/lib/i18n.php';
require SYSTEM_ROOT . '/lib/sfc.functions.php';
require SYSTEM_ROOT . '/lib/class.updater.php';
require SYSTEM_ROOT . '/lib/ui.php';
require SYSTEM_ROOT . '/lib/class.SignManager.php';
if (!defined('SYSTEM_NO_PLUGIN')) {
    require SYSTEM_ROOT . '/lib/plugins.php';
}

// 注册 BDUSS 健康度检查（每天执行一次）
if (!defined('SYSTEM_DO_NOT_LOGIN')) {
    @include_once SYSTEM_ROOT . '/lib/class.cron.php';
    cron::set('system_health_check', 'lib/cron_system_health_check.php', 0, 'BDUSS 健康度检查（自动检测失效的百度账号）', 86400);
}
if (!defined('SYSTEM_NO_CHECK_LOGIN')) {
    require SYSTEM_ROOT . '/lib/globals.php';
}

