<?php
/**
 * 版本升级入口：列出 setup 目录下全部 update*to*.php 升级脚本，
 * 依据数据库 core_version 与程序 SYSTEM_VER 提示当前可执行的下一步。
 * 需以管理员身份登录旧版本后访问，脚本按提示逐个执行、不可跳版本。
 */
if (empty($_COOKIE['uid']) || empty($_COOKIE['pwd'])) {
    http_response_code(302);
    $headerName = 'Location';
    header($headerName . ': ../index.php', true, 302);
    die;
}
define('SYSTEM_DEV', true);
define('SYSTEM_NO_CHECK_VER', true);
require '../init.php';
global $i;
if (ROLE != 'admin') {
    msg('您需要先登录旧版本的云签到，才能继续升级');
}
$dbVersion = isset($i['opt']['core_version']) ? (string) $i['opt']['core_version'] : '';
$x = scandir(dirname(__FILE__));
$v = '';
foreach ($x as $value) {
    if ($value == '.' || $value == '..') {
        continue;
    }
    if (preg_match('/^update(\d+(?:\.\d+)*)to(\d+(?:\.\d+)*)\.php$/', $value, $g) !== 1) {
        continue;
    }
    $fromVersion = $g[1];
    $targetVersion = $g[2];
    if ($dbVersion !== '' && version_compare($dbVersion, $targetVersion, '>=')) {
        $other = '[ <font color="red">已安装</font> ] ';
    } elseif (version_compare($targetVersion, SYSTEM_VER, '>')) {
        $other = '[ <font color="orange">请先更新程序文件</font> ] ';
    } elseif ($dbVersion !== $fromVersion) {
        $other = '[ <font color="orange">请先升级到 ' . htmlspecialchars($fromVersion, ENT_QUOTES, 'UTF-8') . '</font> ] ';
    } else {
        $other = '[ <font color="green">下一步</font> ] ';
    }
    $v .= '<li>' . $other . '<a href="' . htmlspecialchars($value, ENT_QUOTES, 'UTF-8') . '" onclick="return confirm(\'你确定要升级到此版本吗？\');">从 ' . htmlspecialchars($fromVersion, ENT_QUOTES, 'UTF-8') . ' 升级到 ' . htmlspecialchars($targetVersion, ENT_QUOTES, 'UTF-8') . ' [ ' . htmlspecialchars($value, ENT_QUOTES, 'UTF-8') . ' ]</a></li><br/>';
}
?>
<!DOCTYPE html><html><head><meta http-equiv="Content-Type" content="text/html; charset=UTF-8" /><title>软件升级 - 百度贴吧云签到</title><meta name="generator" content="God.Kenvix's Blog (https://kenvix.com) and StusGame (http://www.stusgame.com)" /></head><body><script src="../source/js/jquery.min.js"></script><link rel="stylesheet" href="../source/css/bootstrap.min.css"><script src="../source/js/bootstrap.min.js"></script><style type="text/css">body { font-family:"微软雅黑","Microsoft YaHei";background: #eee; }</style><div class="navbar navbar-default" role="navigation">
  <!-- Brand and toggle get grouped for better mobile display -->
  <div class="navbar-header">
    <button type="button" class="navbar-toggle" data-toggle="collapse" data-target="#bs-example-navbar-collapse-1">
      <span class="sr-only">贴吧云签到</span>
      <span class="icon-bar"></span>
      <span class="icon-bar"></span>
      <span class="icon-bar"></span>
    </button>
    <a class="navbar-brand" href="update.php">贴吧云签到升级</a>
  </div>
  <div class="collapse navbar-collapse" id="bs-example-navbar-collapse-1">
    <ul class="nav navbar-nav">
          <li><a href="http://www.stusgame.com" target="_blank">StusGame</a></li>
    </ul>
  </div><!-- /.navbar-collapse -->
</div>
<div style="width:90%;margin: 0 auto;overflow: hidden;position: relative;">
<h2>请选择要升级的版本</h2><b>提示：</b>请务必逐步升级<br/><br/>
<?php echo $v ?></div>
