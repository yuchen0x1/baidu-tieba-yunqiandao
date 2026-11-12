<?php

/**
 * MySQL 自动按需加载和配置
 * @author Kenvix
 *
 * V7.5：删除 mysql_* 兼容驱动（lib/class.mysql.php）。
 * 原因：mysql_* 扩展已在 PHP 7.0 移除，本项目最低要求 PHP 8.2，
 * 该分支永远无法执行；且它与 mysqli 驱动重复声明 wmysql 类，属误导性死代码。
 */

/**
 * 是否开启数据库长连接
 * bool true=开启 | false=关闭
 */
define('LONGSQL', false);

if (!class_exists('mysqli')) {
    // 明确的失败信息好过加载一个必然 fatal 的旧驱动
    throw new Exception('服务器缺少 mysqli 扩展，无法连接数据库。请安装 php-mysqli 后重试。', 10000);
}
require SYSTEM_ROOT . '/lib/class.mysqli.php';
require SYSTEM_ROOT . '/lib/class.S.php';
$m = new S(DB_HOST, DB_USER, DB_PASSWD, DB_NAME, LONGSQL, DB_SSL); //以后直接使用$m->函数()即可操作数据库