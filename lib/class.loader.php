<?php

if (!defined('SYSTEM_ROOT')) {
    die('Insufficient Permissions');
}

/**
 * lib 目录类文件解析（大小写不敏感 + 命名别名）。
 * 供 spl_autoload（reg.php）与静态回归测试共用：
 * 类名大小写在 Linux 容器上是敏感的，直接按小写拼文件名会漏掉
 * class.SignManager.php、class.P.php 这类混合命名文件；此处以真实目录清单为准。
 */

/**
 * lib 下全部 class.*.php 的真实文件名映射：小写键 => 文件名（大小写为磁盘真实值）
 */
function lib_class_map()
{
    static $map = null;
    if ($map === null) {
        $map = array();
        $files = scandir(SYSTEM_ROOT . '/lib');
        foreach ($files ?: array() as $file) {
            if (is_file(SYSTEM_ROOT . '/lib/' . $file) && preg_match('/^class\.(.+)\.php$/i', $file, $m)) {
                $map[strtolower($m[1])] = $file;
            }
        }
    }
    return $map;
}

/**
 * 历史命名别名：逻辑类名小写 => 文件键。
 * - wmysql：class.mysql.php 与 class.mysqli.php 都声明 wmysql，运行时经 mysql_autoload.php 显式加载，别名仅作兜底；
 * - projectupdater / projectupdaterratelimitexception：class.updater.php 声明
 *   ProjectUpdater 与 ProjectUpdaterRateLimitException（文件键 updater）。
 * 新增偏差文件时必须在此登记，tests/AutoloadCaseTest.php 会强制校验。
 */
function lib_class_aliases()
{
    return array(
        'wmysql'        => 'mysqli',
        'projectupdater' => 'updater',
        'projectupdaterratelimitexception' => 'updater',
    );
}

/**
 * 解析类名到 lib 下的真实文件路径；找不到返回 null
 */
function lib_class_file($class)
{
    $key = strtolower((string) $class);
    $aliases = lib_class_aliases();
    if (isset($aliases[$key])) {
        $key = $aliases[$key];
    }
    $map = lib_class_map();
    return isset($map[$key]) ? SYSTEM_ROOT . '/lib/' . $map[$key] : null;
}
