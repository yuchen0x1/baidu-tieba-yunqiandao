<?php
define('SYSTEM_FN', '百度贴吧云签到');
define('SYSTEM_VER', '5.09');
define('SYSTEM_ROOT2', dirname(__FILE__));
define('SYSTEM_ROOT', dirname(__FILE__) . '/..');
define('SYSTEM_PAGE', isset($_REQUEST['mod']) ? strip_tags($_REQUEST['mod']) : 'default');
header("content-type:text/html; charset=utf-8");
require SYSTEM_ROOT2 . '/../lib/msg.php';
include SYSTEM_ROOT2 . '/../lib/class.wcurl.php';

function install_schema_ready()
{
    $configFile = SYSTEM_ROOT2 . '/../config.php';
    if (!file_exists($configFile)) {
        return false;
    }

    require_once $configFile;
    if (!class_exists('mysqli')) {
        return false;
    }

    $connection = @mysqli_init();
    if (!$connection) {
        return false;
    }
    $host = DB_HOST;
    $port = null;
    $hostSeparator = strrpos($host, ':');
    if ($hostSeparator !== false && strpos($host, ']') === false) {
        $port = (int) substr($host, $hostSeparator + 1);
        $host = substr($host, 0, $hostSeparator);
    }
    $flags = defined('DB_SSL') && DB_SSL ? MYSQLI_CLIENT_SSL : 0;
    try {
        $connected = @$connection->real_connect($host, DB_USER, DB_PASSWD, DB_NAME, $port, null, $flags);
    } catch (Throwable $exception) {
        $connected = false;
    }
    if (!$connected) {
        return false;
    }
    $connection->set_charset('utf8mb4');

    foreach (array('baiduid', 'cron', 'options', 'plugins', 'tieba', 'users', 'users_options', 'baiduid_health', 'cron_log_batch') as $table) {
        $tableName = $connection->real_escape_string(DB_PREFIX . $table);
        $result = $connection->query("SELECT COUNT(*) AS total FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = '{$tableName}'");
        if (!$result) {
            $connection->close();
            return false;
        }
        $row = $result->fetch_assoc();
        $result->free();
        if (empty($row['total'])) {
            $connection->close();
            return false;
        }
    }
    $adminTable = '`' . str_replace('`', '``', DB_PREFIX . 'users') . '`';
    $adminResult = $connection->query("SELECT COUNT(*) AS total FROM {$adminTable} WHERE `role` = 'admin'");
    if (!$adminResult) {
        $connection->close();
        return false;
    }
    $adminRow = $adminResult->fetch_assoc();
    $adminResult->free();
    $connection->close();
    return !empty($adminRow['total']);
}

if (file_exists(SYSTEM_ROOT2 . '/install.lock')) {
    msg('错误：安装锁定，请删除以下文件后再安装：<br/><br/>/setup/install.lock<br/><br/>或者点击下面的按钮返回站点取消安装：', '../');
}
/*
$csrf = !empty($_SERVER['HTTP_REFERER']) ? parse_url($_SERVER['HTTP_REFERER']) : '';
if ( isset($_GET['step']) && ( empty($csrf['host']) || $csrf['host'] != $_SERVER['SERVER_NAME'] ) ) {
    msg('安装程序检测到来源异常，已被拦截，请按步骤进行安装！点击返回重新操作','index.php');
}
*/

    $rawInstallStep = isset($_GET['step']) ? $_GET['step'] : '0';
    $installStep = is_scalar($rawInstallStep) ? (string) strip_tags((string) $rawInstallStep) : '0';
    $stepForDisplay = $installStep === '100' ? 2 : max(0, min(5, (int) $installStep));
    echo '<!DOCTYPE html><html lang="zh-CN"><head>';
    echo '<meta http-equiv="Content-Type" content="text/html; charset=UTF-8" /><meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">';
    echo '<link href="../favicon.ico" rel="shortcut icon"/>';
    echo '<link rel="stylesheet" href="../source/css/tokens.css?v=' . rawurlencode(SYSTEM_VER) . '">';
    echo '<link rel="stylesheet" href="../source/css/bootstrap.min.css">';
    echo '<link rel="stylesheet" href="install.css?v=' . rawurlencode(SYSTEM_VER) . '">';
    echo '<title>安装向导 - ' . SYSTEM_FN . '</title><meta name="description" content="百度贴吧云签到安装向导" /></head><body class="install-body">';
    echo '<script src="../source/js/jquery.min.js"></script>';
    echo '<script src="../source/js/bootstrap.min.js"></script>';
?>
<div class="install-shell">
  <header class="install-topbar">
    <a class="install-brand" href="install.php" aria-label="返回安装向导首页">
      <span class="install-brand-mark" aria-hidden="true">签</span>
      <span class="install-brand-copy"><strong>百度贴吧云签到</strong><small>安装向导</small></span>
    </a>
    <div class="install-topbar-meta">
      <span class="install-secure"><span class="install-secure-dot" aria-hidden="true"></span>安全安装</span>
      <a href="index.html">返回管理入口</a>
    </div>
  </header>
  <main class="install-main">
    <section class="install-intro" aria-labelledby="install-title">
      <div>
        <p class="install-kicker">SETUP WIZARD</p>
        <h1 id="install-title">快速完成站点安装</h1>
        <p>按照向导完成环境检查、数据库配置和管理员创建，几分钟即可开始使用。</p>
      </div>
      <span class="install-version">V<?php echo htmlspecialchars(SYSTEM_VER, ENT_QUOTES, 'UTF-8'); ?> · 稳定安装流程</span>
    </section>
    <nav class="install-steps" aria-label="安装进度">
      <?php
      $installSteps = array('许可协议', '环境检查', '运行环境', '站点信息', '执行安装', '完成');
      foreach ($installSteps as $index => $label) {
          $stepClass = $index < $stepForDisplay ? 'is-complete' : ($index === $stepForDisplay ? 'is-current' : '');
          echo '<div class="install-step ' . $stepClass . '"><span class="install-step-number">' . ($index < $stepForDisplay ? '✓' : ($index + 1)) . '</span><span>' . $label . '</span></div>';
      }
      ?>
    </nav>
    <section class="install-card">
      <div class="install-card-bar">
        <span class="install-card-eyebrow">当前安装步骤</span>
        <span class="install-lock-note">安装完成后将自动创建 <code>setup/install.lock</code></span>
      </div>
      <div class="install-content">
<?php
if ($installStep === '0') {
    echo '<h2>阅读许可协议</h2><p class="install-lead">请先阅读并同意许可协议，确认版权信息和使用约定后继续。</p>';
    echo '<iframe src="../license.html" class="install-license-frame" title="百度贴吧云签到许可协议"></iframe>';
    echo '<br/><br/><input type="button" onclick="if(confirm(\'“我尊重原作者为云签事业付出的心血，在使用该永久免费的云签系统的同时将保护原作者的版权。\r\n保证原作者的名称、链接等版权信息不被删改、淡化或遮挡，如果我没有做到，自愿承担由此引发的所有不良后果”\r\n\r\n同意请确定，不同意请取消\')){location = \'install.php?step=1\';} else {alert(\'请立即删除所有与本程序相关的文件及其延伸产品\');location = \'index.html\';}" class="btn btn-success" value="我接受">&nbsp;&nbsp;&nbsp;';
    echo '<input type="button" onclick=";alert(\'请立即删除所有与本程序相关的文件及其延伸产品\');location = \'index.html\';" class="btn btn-danger" value="我拒绝">';
} else {
    switch ($installStep) {
        case '100':
            echo '<h2>手动修改配置</h2><br/>';
            echo '<div class="progress progress-striped">
  <div class="progress-bar progress-bar-success" role="progressbar" aria-valuenow="40" aria-valuemin="0" aria-valuemax="100" style="width: 25%">
    <span class="sr-only">25%</span>
  </div>
</div>';

            echo '请按照注释修改 /<b>config.php</b> ，重要位置已经用星号(*)标明<br/><br/><font color="red">警告：</font>切勿使用记事本修改；文件编码应该为 UTF-8 ( 无BOM )';
            echo '<br/><br/><div class="highlight"><pre><code class="html">
&lt?php 

//特别警告：禁止使用记事本编辑！

////////////////////////////以下选项只需在使用MySQL时填写////////////////////////////
//MySQL 数据库地址，普通主机一般为localhost
define(\'DB_HOST\',\'******\');
//MySQL 数据库用户名
define(\'DB_USER\',\'******\');
//MySQL 数据库密码
define(\'DB_PASSWD\',\'******\');
//MySQL 数据库名称
define(\'DB_NAME\',\'******\');
//MySQL 启用SSL连接，如需启用请将值改为1
define(\'DB_SSL\', 0);

////////////////////////////以下选项使用任何数据库都需填写////////////////////////////
//数据库前缀，建议保持默认
define(\'DB_PREFIX\',\'tc_\');

///////////////////////////////////////其他设置///////////////////////////////////////
//停用CSRF防御
//说明在 https://github.com/yuchen0x1/tieba-cloud-sign/wiki/%E5%85%B3%E4%BA%8E%E4%BA%91%E7%AD%BE%E5%88%B0CSRF%E9%98%B2%E5%BE%A1
define(\'ANTI_CSRF\',true);

//加密用盐，留空为不使用
define(\'SYSTEM_SALT\',\'\');
</code></pre></div>';
            echo '<br/>修改完成后，请点击下一步<br/><br/><input type="button" onclick="location = \'install.php?step=3&bae\'" class="btn btn-success" value="下一步 >>">';
            break;

        case '1':
            echo '<h2>准备安装：功能检查</h2><p class="install-lead">先确认当前服务器满足运行要求，标记为“必须”的项目需要全部可用。</p>';
            echo '<div class="progress progress-striped">
			  <div class="progress-bar progress-bar-success" role="progressbar" aria-valuenow="40" aria-valuemin="0" aria-valuemax="100" style="width: 10%">
			    <span class="sr-only">10%</span>
			  </div>
			</div>';
            define('DO_NOT_LOAD_UI', true);
            include SYSTEM_ROOT2 . '/check.php';
            echo '<input type="button" onclick="location = \'install.php?step=2\'" class="btn btn-success" value="下一步 >>">';
            break;

        case '2':
            echo '<h2>准备安装：设置运行环境</h2><p class="install-lead">请选择配置方式。不可写主机可以手动编辑配置文件，再返回继续。</p>';
            echo '<div class="progress progress-striped">
  <div class="progress-bar progress-bar-success" role="progressbar" aria-valuenow="40" aria-valuemin="0" aria-valuemax="100" style="width: 20%">
    <span class="sr-only">20%</span>
  </div>
</div>';
            echo '<h4>你是在应用引擎或者不可写的主机上使用本程序吗？</h4>';
            echo '<div class="install-choice-grid"><a class="install-choice" href="install.php?step=3"><strong>不是</strong><span>继续填写数据库与站点信息</span><b aria-hidden="true">→</b></a><a class="install-choice" href="install.php?step=100"><strong>是的</strong><span>手动修改 config.php 后继续</span><b aria-hidden="true">→</b></a></div>';
            break;

        case '3':
            echo '<h2>设置所需信息</h2><p class="install-lead">填写数据库连接信息和站点创始人信息，这些内容只用于完成本次安装。</p>';
            echo '<div class="progress progress-striped">
  <div class="progress-bar progress-bar-success" role="progressbar" aria-valuenow="40" aria-valuemin="0" aria-valuemax="100" style="width: 30%">
    <span class="sr-only">30%</span>
  </div>
</div>';
            echo '<form action="install.php?step=4" method="post">';
            echo '<h4 class="install-section-title">数据库信息</h4>';
            if (isset($_GET['isbae']) || isset($_GET['bae'])) {
                echo '<b>提示：</b>如果以下内容有错误，说明您上一步手动修改出现了问题，您可以 <a href="install.php?step=100" class="btn btn-warning btn-xs">返回</a><br/><br/>';
                @include_once(SYSTEM_ROOT2 . '/../config.php');
                echo '<div class="input-group"><span class="input-group-addon">数据库地址</span><input type="text" class="form-control" value="' . htmlspecialchars(DB_HOST, ENT_QUOTES, 'UTF-8') . '" disabled></div><br/>';
                echo '<div class="input-group"><span class="input-group-addon">数据库用户名</span><input type="text" class="form-control" value="' . htmlspecialchars(DB_USER, ENT_QUOTES, 'UTF-8') . '" disabled></div><br/>';
                echo '<div class="input-group"><span class="input-group-addon">数据库密码</span><input type="text" class="form-control" value="********" disabled></div><br/>';
                echo '<div class="input-group"><span class="input-group-addon">数据库名称</span><input type="text" class="form-control" value="' . htmlspecialchars(DB_NAME, ENT_QUOTES, 'UTF-8') . '" disabled></div><br/>';
                echo '<div class="input-group"><span class="input-group-addon">数据表前缀</span><input type="text" class="form-control" value="' . htmlspecialchars(DB_PREFIX, ENT_QUOTES, 'UTF-8') . '" disabled></div><br/>';
                echo '<div class="input-group"><span class="input-group-addon">数据库开启SSL</span><select name="dbssl" class="form-control" required="true"><option value="0">否</option><option value="1">是</option></select></div><br/>';
                echo '<input type="hidden" name="isbae" value="1">';
                echo '<input type="hidden" name="from_config" value="1">';
            } else {
                echo '<br/><b>提示 1：</b>如果您已经手动写好了 config.php ，请选择 [ <b>自动获得数据库配置信息</b> ] 为 <b>是</b><br/>';
                echo '<b>提示 2：</b>如果程序并未写入数据库 [ 安装完成后进入首页提示 Table XX doesn\'t exist  ] 请选择强制手动导入 SQL<br/><br/>';
                echo '<input type="checkbox" name="nosql" value="1"> 强制手动导入 SQL<br/><br/>';
                echo '<div class="input-group"><span class="input-group-addon">自动获得数据库配置信息</span><select name="from_config" class="form-control"  onchange="if(this.value == \'0\') { $(\'#db_config\').show(); } else { $(\'#db_config\').hide(); }"><option value="0">否</option><option value="1">是</option></select></div><br/>';
                echo '<div id="db_config"><div class="input-group"><span class="input-group-addon">数据库地址</span><input type="text" class="form-control" name="dbhost" value="localhost" placeholder=""></div><br/>';
                echo '<div class="input-group"><span class="input-group-addon">数据库用户名</span><input type="text" class="form-control" name="dbuser" placeholder=""></div><br/>';
                echo '<div class="input-group"><span class="input-group-addon">数据库密码</span><input type="text" class="form-control" name="dbpw" placeholder=""></div><br/>';
                echo '<div class="input-group"><span class="input-group-addon">数据库名称</span><input type="text" class="form-control" name="dbname" placeholder=""></div><br/>';
                echo '<div class="input-group"><span class="input-group-addon">数据表前缀</span><input type="text" class="form-control" name="dbprefix" value="tc_" placeholder=""></div><br/>';
                echo '<div class="input-group"><span class="input-group-addon">数据库开启SSL</span><select name="dbssl" class="form-control" required="true"><option value="0">否</option><option value="1">是</option></select></div><br/>';
                echo '</div>';
            }
            echo '<h4 class="install-section-title">站点创始人信息</h4>';
            echo '<div class="input-group"><span class="input-group-addon">创始人用户名</span><input type="text" required class="form-control" name="user" placeholder=""></div><br/>';
            echo '<div class="input-group"><span class="input-group-addon">创始人邮箱</span><input type="email" required class="form-control" name="mail" placeholder=""></div><br/>';
            echo '<div class="input-group"><span class="input-group-addon">创始人密码</span><input type="password" required class="form-control" name="pw" placeholder=""></div><br/>';
            //echo '<div class="input-group"><span class="input-group-addon">工具箱执行密码</span><input type="password" required class="form-control" name="toolpw" placeholder=""></div><br/>';
            echo '<br/><br/><input type="submit" class="btn btn-success" value="下一步 >>"></form>';
            break;

        case '4':
            $errorhappen = '';
            if (isset($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off' && $_SERVER['HTTPS'] !== '') {
                $http = 'https://';
            } else {
                $http = 'http://';
            }
            preg_match("/^.*\//", $_SERVER['SCRIPT_NAME'], $sysurl);
            $fromConfig = !empty($_POST['from_config']);
            if ($fromConfig) {
                require SYSTEM_ROOT2 . '/../config.php';
            } else {
                define('DB_HOST', isset($_POST['dbhost']) ? trim((string) $_POST['dbhost']) : '');
                define('DB_USER', isset($_POST['dbuser']) ? trim((string) $_POST['dbuser']) : '');
                define('DB_PASSWD', isset($_POST['dbpw']) ? (string) $_POST['dbpw'] : '');
                define('DB_NAME', isset($_POST['dbname']) ? trim((string) $_POST['dbname']) : '');
                define('DB_PREFIX', isset($_POST['dbprefix']) ? trim((string) $_POST['dbprefix']) : '');
                define('DB_SSL', !empty($_POST['dbssl']) ? 1 : 0);
            }
            if (!preg_match('/^[A-Za-z0-9_]+$/', DB_NAME) || !preg_match('/^[A-Za-z0-9_]+$/', DB_PREFIX)) {
                msg('数据库名称或表前缀包含非法字符，只允许使用字母、数字和下划线');
            }
            $sql  = str_ireplace('{VAR-PREFIX}', DB_PREFIX, file_get_contents(SYSTEM_ROOT2 . '/install.template.sql'));
            $sql  = str_ireplace('{VAR-DB}', DB_NAME, $sql);
            $installCryptoKey = bin2hex(random_bytes(32));
            $sql  = str_ireplace('{VAR-COOKIE-SALT}', $installCryptoKey, $sql);
            //$sql  = str_ireplace('{VAR-TOOLPW}', md5(md5(md5($_POST['toolpw']))), $sql);
            $sql  = str_ireplace('{VAR-SYSTEM-URL}', $http . $_SERVER['HTTP_HOST'] . str_ireplace('setup/', '', $sysurl[0]), $sql);
            $installUser = isset($_POST['user']) ? trim(strip_tags($_POST['user'])) : '';
            $installMail = isset($_POST['mail']) ? trim(strip_tags($_POST['mail'])) : '';
            $installPw = isset($_POST['pw']) ? (string) $_POST['pw'] : '';
            if (empty($installUser) || empty($installMail) || empty($installPw)) {
                msg('请正确填写创始人信息');
            }
            if (!preg_match('/^[A-Za-z0-9_\x{4e00}-\x{9fa5}]+$/u', $installUser)) {
                msg('创始人用户名包含非法字符');
            }
            if (!filter_var($installMail, FILTER_VALIDATE_EMAIL)) {
                msg('创始人邮箱格式不正确');
            }
            $installUser = addslashes($installUser);
            $installMail = addslashes($installMail);
            $sql .= "\n" . "INSERT INTO `" . DB_NAME . "`.`" . DB_PREFIX . "users` (`name`, `pw`, `email`, `role`) VALUES ('{$installUser}', '" . password_hash($installPw, PASSWORD_BCRYPT, ['cost' => 12]) . "', '{$installMail}', 'admin');";
            if (!isset($_POST['nosql'])) {
                require SYSTEM_ROOT . '/lib/mysql_autoload.php';
                global $m;
                $testInstall = $m->fetch_row($m->query("SHOW TABLES LIKE '" . DB_PREFIX . "users'"));
                if (!empty($testInstall[0])) {
                    if (!empty($_POST['force_user']) && !empty($_POST['force_pw'])) {
                        $force_user = !empty($_POST['force_user']) ? addslashes($_POST['force_user']) : msg('请输入原站点的管理员用户名');
                        $force_pw   = !empty($_POST['force_pw'])   ? addslashes($_POST['force_pw'])   : msg('请输入原站点的管理员密码');
                        $account    = $m->once_fetch_array("SELECT * FROM `" . DB_PREFIX . "users` WHERE `name` = '{$force_user}';");
                        if (empty($account['role'])) {
                            msg('原站点的管理员用户名错误，请返回重新输入');
                        } elseif (!password_verify($force_pw, $account['pw'])) {
                            msg('原站点的管理员密码错误，请返回重新输入<br/><br/>注：暂时不支持自定义加密方式的用户重装站点');
                        } elseif ($account['role'] != 'admin') {
                            msg('权限不足');
                        }
                    } else {
                        echo '<h2>请输入原站点的管理员用户名和密码</h2><br/>';
                        echo '<div class="progress progress-striped"><div class="progress-bar progress-bar-success" role="progressbar" aria-valuenow="40" aria-valuemin="0" aria-valuemax="100" style="width: 50%"><span class="sr-only">50%</span></div></div>';
                        echo '检测到站点已经安装过 ' . SYSTEM_FN . ' 了，如果需要继续安装，请输入待覆盖站点管理员用户名和密码（不是刚才输入的创始人用户名和密码）<br/><br/>如果继续安装，您的站点的数据将会全部恢复到云签到初始状态<br/><br/>';
                        echo '<form action="install.php?step=4" method="post">';
                        echo '<div class="input-group"><span class="input-group-addon" id="basic-addon1">用户名</span><input type="text" class="form-control" name="force_user" required></div><br/>';
                        echo '<div class="input-group"><span class="input-group-addon" id="basic-addon1">密码</span><input type="password" class="form-control" name="force_pw" required></div><br/>';
                        foreach ($_POST as $key => $value) {
                            echo '<input type="hidden" name="' . htmlspecialchars($key, ENT_QUOTES, 'UTF-8') . '" value="' . htmlspecialchars($value, ENT_QUOTES, 'UTF-8') . '">';
                        }
                        echo '<br/><input type="submit" class="btn btn-success" value="下一步 >>"></form>';
                        die;
                    }
                }
            }
            if (!isset($_POST['isbae'])) {
                $write_data = '<?php 

//特别警告：禁止使用记事本编辑！

////////////////////////////以下选项只需在使用MySQL时填写////////////////////////////
//MySQL 数据库地址，普通主机一般为localhost
define(\'DB_HOST\', ' . var_export(DB_HOST, true) . ');
//MySQL 数据库用户名
define(\'DB_USER\', ' . var_export(DB_USER, true) . ');
//MySQL 数据库密码
define(\'DB_PASSWD\', ' . var_export(DB_PASSWD, true) . ');
//MySQL 数据库名称
define(\'DB_NAME\', ' . var_export(DB_NAME, true) . ');
//MySQL 启用SSL连接，如需启用请将值改为1
define(\'DB_SSL\',' . (int) DB_SSL . ');

////////////////////////////以下选项使用任何数据库都需填写////////////////////////////
//数据库前缀，建议保持默认
define(\'DB_PREFIX\', ' . var_export(DB_PREFIX, true) . ');

///////////////////////////////////////其他设置///////////////////////////////////////
//停用CSRF防御
//说明在 https://github.com/yuchen0x1/tieba-cloud-sign/wiki/%E5%85%B3%E4%BA%8E%E4%BA%91%E7%AD%BE%E5%88%B0CSRF%E9%98%B2%E5%BE%A1
define(\'ANTI_CSRF\',true);

//加密用盐
define(\'SYSTEM_SALT\', ' . var_export($installCryptoKey, true) . ');';
                if (!$fromConfig && !file_put_contents('../config.php', $write_data)) {
                    $errorhappen .= '<b>无法写入配置文件 config.php ，请打开本程序根目录的 config.php 并按照注释修改它</b><br/><br/>';
                }
            }
            if (!isset($_POST['nosql'])) {
                try {
                    $m->multi_query($sql);
                } catch (Exception $ex) {
                    $errorhappen .= htmlspecialchars($ex->getMessage(), ENT_QUOTES, 'UTF-8');
                    $errorhappen .= '<br/><br/>自动安装失败，请手动复制下列语句到数据库管理软件(例如phpmyadmin)并运行：<br/>请无视其中的注释，直接导入即可<br/><div class="alert alert-success"><pre>' . htmlspecialchars($sql, ENT_QUOTES, 'UTF-8') . '</pre><br/><br/>';
                }
            } else {
                    $errorhappen .= '由于你选择了手动安装，请手动复制下列语句到数据库管理软件(例如phpmyadmin)并运行：<br/>请无视其中的注释，直接导入即可<br/><div class="alert alert-success"><pre>' . htmlspecialchars($sql, ENT_QUOTES, 'UTF-8') . '</pre><br/><br/>';
            }
            echo '<script src="stat.js?type=tcs&ver=' . SYSTEM_VER . '"></script>';
            if (!empty($errorhappen)) {
                echo '<h2>请手动安装</h2><br/>';
                echo '<div class="progress progress-striped">
  <div class="progress-bar progress-bar-success" role="progressbar" aria-valuenow="40" aria-valuemin="0" aria-valuemax="100" style="width: 60%">
    <span class="sr-only">60%</span>
  </div>
</div>';
                echo $errorhappen;
                echo '完成上述操作后，请点击下一步<br/><br/><input type="button" onclick="location = \'install.php?step=5\'" class="btn btn-success" value="下一步 >>">';
            } else {
                echo '<div class="progress progress-striped">
  <div class="progress-bar progress-bar-success" role="progressbar" aria-valuenow="40" aria-valuemin="0" aria-valuemax="100" style="width: 60%">
    <span class="sr-only">60%</span>
  </div>
</div>';
                echo '<meta http-equiv="refresh" content="0;url=install.php?step=5"><h2>请稍候</h2><br/>正在完成安装...';
            }
            break;

        case '5':
            $installReady = install_schema_ready();
            echo '<h2>安装完成</h2><p class="install-lead">站点已经准备就绪，最后确认安全锁文件后即可进入云签到。</p>';
            echo '<div class="progress progress-striped">
  <div class="progress-bar progress-bar-success" role="progressbar" aria-valuenow="40" aria-valuemin="0" aria-valuemax="100" style="width: 90%">
    <span class="sr-only">90%</span>
  </div>
</div>';
            echo '恭喜你，安装已经完成<br/><br/>请添加一个计划任务，文件为本程序根目录下的 <b>do.php</b><br/><br/>计划任务运行时间建议为每分钟运行 ( Linux Crontab参考：<b><font color="blue">* * * * *</font></b> )<br/><br/><br/>为保证站点安全，系统已在 /setup 文件夹下放置了 install.lock 文件，如果您的服务器不支持写入，请手动放置一个空的 install.lock 文件到此文件夹下，否则任何人都有权限重新安装您的云签到。<br/><br/><b>请您尊重作者，无论如何都不要删减云签到的版权</b><br/><br/><input type="button" onclick="location = \'../index.php\'" class="btn btn-success" value="进入我的云签到 >>">';
            if ($installReady) {
                if (@file_put_contents(SYSTEM_ROOT2 . '/install.lock', '1') === false) {
                    echo '<div class="alert alert-warning">安装已完成，但无法自动写入 setup/install.lock，请手动创建一个空的文件后再进入站点。</div>';
                }
            } else {
                echo '<div class="alert alert-warning">数据库未通过安装校验，系统不会写入 install.lock，请先完成数据库导入或重新运行自动安装。</div>';
            }
            break;

        default:
            msg('未定义操作');
            break;
    }
}
?>
      </div>
    </section>
  </main>
  <footer class="install-footer">百度贴吧云签到 · 安装向导 <span>请保留项目版权信息</span></footer>
</div>
