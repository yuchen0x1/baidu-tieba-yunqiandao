<?php

if (!defined('SYSTEM_ROOT')) {
    die('Insufficient Permissions');
}
global $i;

/**
 * 校验插件标识符，防止路径穿越与 SQL 注入
 */
function valid_plugin_id($plugin)
{
    return is_string($plugin) && preg_match('/^[A-Za-z0-9_\-]+$/', $plugin) && strpos($plugin, '..') === false;
}

/**
 * 安全提取插件 ZIP：强制单根目录，剥离仓库根目录后提取到插件标识目录。
 * 兼容 Gitee/GitHub 的 repository-main 归档结构，同时拒绝 Zip Slip 与覆盖既有插件。
 */
function extractPluginArchive($zip, $targetRoot)
{
    if (!$zip instanceof ZipArchive || !is_dir($targetRoot)) {
        return false;
    }
    $archiveRoot = '';
    $pluginId = '';
    $entries = array();
    for ($index = 0; $index < $zip->numFiles; $index++) {
        $name = $zip->getNameIndex($index);
        if ($name === false) {
            return false;
        }
        $name = str_replace('\\', '/', $name);
        if ($name === '' || strpos($name, "\0") !== false || strpos($name, '../') !== false || $name[0] === '/' || preg_match('/^[A-Za-z]:/', $name)) {
            return false;
        }
        $segments = explode('/', $name, 2);
        if (count($segments) < 2 || !valid_plugin_id($segments[0])) {
            return false;
        }
        if ($archiveRoot === '') {
            $archiveRoot = $segments[0];
        } elseif ($archiveRoot !== $segments[0]) {
            return false;
        }
        $entries[] = array($name, $segments[1]);
    }
    if ($archiveRoot === '') {
        return false;
    }
    $candidate = preg_replace('/(?:-master|master-|-(?:main|dev))$/i', '', $archiveRoot);
    $directPluginFiles = array();
    $nestedPluginDirs = array();
    foreach ($entries as $entry) {
        if (strpos($entry[1], '/') === false && preg_match('/^([A-Za-z0-9_-]+)\.php$/', $entry[1], $match)) {
            $directPluginFiles[] = $match[1];
        }
        if (preg_match('/^([A-Za-z0-9_-]+)\/\1\.php$/', $entry[1], $match) && valid_plugin_id($match[1])) {
            $nestedPluginDirs[] = $match[1];
        }
    }
    $directPluginFiles = array_values(array_unique($directPluginFiles));
    $nestedPluginDirs = array_values(array_unique($nestedPluginDirs));
    $sourcePrefix = '';
    if (count($directPluginFiles) === 1 && valid_plugin_id($directPluginFiles[0])) {
        $candidate = $directPluginFiles[0];
    } elseif (count($directPluginFiles) === 0 && count($nestedPluginDirs) === 1) {
        $candidate = $nestedPluginDirs[0];
        $sourcePrefix = $candidate . '/';
    }
    if (!valid_plugin_id($candidate)) {
        return false;
    }
    $pluginId = $candidate;
    $hasEntry = false;
    foreach ($entries as $entry) {
        if ($entry[1] === $sourcePrefix . $pluginId . '.php') {
            $hasEntry = true;
            break;
        }
    }
    if (!$hasEntry) {
        return false;
    }
    $base = rtrim(str_replace('\\', '/', realpath($targetRoot)), '/') . '/' . $pluginId;

    // V5.13 B4：目标目录已存在时，先整体备份到 plugins/.rollback 并移开旧目录；
    // 解压成功保留备份（可手动回滚），解压失败自动恢复旧目录。
    $tmpOld = '';
    if (is_dir($base)) {
        // 回滚备份目录与目标插件同根（<targetRoot>/.rollback），便于真实部署与测试一致
        $rollbackRoot = dirname($base) . '/.rollback';
        if (!is_dir($rollbackRoot) && !@mkdir($rollbackRoot, 0755, true) && !is_dir($rollbackRoot)) {
            return false;
        }
        $backupDir = $rollbackRoot . '/' . $pluginId . '_' . date('Ymd_His') . '_' . bin2hex(random_bytes(3));
        if (!plugin_copy_dir($base, $backupDir)) {
            return false;
        }
        $tmpOld = $base . '_old_' . bin2hex(random_bytes(4));
        if (!@rename($base, $tmpOld)) {
            DeleteFile($backupDir);
            return false;
        }
    }
    $fail = function () use ($base, $tmpOld) {
        DeleteFile($base);
        if ($tmpOld !== '') {
            @rename($tmpOld, $base);
        }
        return false;
    };
    foreach ($entries as $entry) {
        $relative = $entry[1];
        if ($sourcePrefix !== '') {
            if (strpos($relative, $sourcePrefix) !== 0) {
                continue;
            }
            $relative = substr($relative, strlen($sourcePrefix));
        }
        if ($relative === '') {
            continue;
        }
        if (strpos($relative, '../') !== false || $relative[0] === '/' || preg_match('/^[A-Za-z]:/', $relative)) {
            return $fail();
        }
        $target = $base . '/' . $relative;
        if (substr($target, 0, strlen($base . '/')) !== $base . '/') {
            return $fail();
        }
        if (substr($relative, -1) === '/') {
            if (!is_dir($target) && !mkdir($target, 0755, true) && !is_dir($target)) {
                return $fail();
            }
            continue;
        }
        $dir = dirname($target);
        if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
            return $fail();
        }
        $stream = $zip->getStream($entry[0]);
        if ($stream === false) {
            return $fail();
        }
        $content = stream_get_contents($stream);
        fclose($stream);
        if ($content === false || file_put_contents($target, $content, LOCK_EX) === false) {
            return $fail();
        }
    }
    // 解压成功：移开的旧目录不再需要（备份已保留在 .rollback）
    if ($tmpOld !== '') {
        DeleteFile($tmpOld);
    }
    return $pluginId;
}

/**
 * V5.13 B4：递归复制目录
 */
function plugin_copy_dir($src, $dst)
{
    if (!is_dir($src)) {
        return false;
    }
    if (!is_dir($dst) && !@mkdir($dst, 0755, true) && !is_dir($dst)) {
        return false;
    }
    $items = @scandir($src);
    if ($items === false) {
        return false;
    }
    foreach ($items as $item) {
        if ($item === '.' || $item === '..') {
            continue;
        }
        $s = $src . '/' . $item;
        $d = $dst . '/' . $item;
        if (is_dir($s)) {
            if (!plugin_copy_dir($s, $d)) {
                return false;
            }
        } elseif (is_file($s)) {
            if (!@copy($s, $d)) {
                return false;
            }
        }
    }
    return true;
}

/**
 * V5.13 B4：插件回滚备份根目录（插件覆盖安装前自动备份）
 */
function plugin_rollback_dir()
{
    return SYSTEM_ROOT . '/plugins/.rollback';
}

/**
 * V5.13 B4：备份插件目录到 .rollback/<plugin>_<ts>_<rand>，成功返回备份路径，失败返回 false
 */
function plugin_backup_plugin($plugin)
{
    if (!valid_plugin_id($plugin)) {
        return false;
    }
    $src = SYSTEM_ROOT . '/plugins/' . $plugin;
    if (!is_dir($src)) {
        return false;
    }
    $root = plugin_rollback_dir();
    if (!is_dir($root) && !@mkdir($root, 0755, true) && !is_dir($root)) {
        return false;
    }
    $backup = $root . '/' . $plugin . '_' . date('Ymd_His') . '_' . bin2hex(random_bytes(3));
    if (!plugin_copy_dir($src, $backup)) {
        return false;
    }
    return $backup;
}

/**
 * V5.13 B4：从指定备份恢复插件目录（仅接受 .rollback 下的合法备份路径）
 */
function plugin_restore_plugin($plugin, $backupDir)
{
    if (!valid_plugin_id($plugin) || !is_string($backupDir) || !is_dir($backupDir)) {
        return false;
    }
    $backupPath = rtrim(str_replace('\\', '/', realpath($backupDir)), '/');
    // 仅接受 <插件根>/.rollback/<plugin>_<ts>_<hex> 结构的合法备份
    if (!preg_match('#^(.+)/\.rollback/' . preg_quote($plugin, '#') . '_\d{8}_\d{6}_[0-9a-f]+$#i', $backupPath, $mm)) {
        return false;
    }
    $dest = $mm[1] . '/' . $plugin;
    DeleteFile($dest);
    return @rename($backupDir, $dest);
}

/**
 * V5.13 B4：清理某插件的全部回滚备份
 */
function plugin_purge_rollback($plugin)
{
    if (!valid_plugin_id($plugin)) {
        return;
    }
    $root = plugin_rollback_dir();
    if (!is_dir($root)) {
        return;
    }
    $dirs = glob($root . '/' . $plugin . '_*');
    if ($dirs) {
        foreach ($dirs as $d) {
            DeleteFile($d);
        }
    }
}

/**
 * V5.13 B4：校验插件清单（manifest）
 * 新式 _desc.php 或旧式头部注释皆可；返回 array('ok'=>bool,'errors'=>array)
 */
function plugin_manifest_validate($plugin)
{
    $errors = array();
    if (!valid_plugin_id($plugin)) {
        $errors[] = '插件标识不合法';
        return array('ok' => false, 'errors' => $errors);
    }
    $path = SYSTEM_ROOT . '/plugins/' . $plugin . '/';
    if (!file_exists($path . $plugin . '.php')) {
        $errors[] = '缺少入口文件 ' . $plugin . '.php';
        return array('ok' => false, 'errors' => $errors);
    }
    if (file_exists($path . $plugin . '_desc.php')) {
        $info = include $path . $plugin . '_desc.php';
    } else {
        $d = getOldPluginData($plugin . '/' . $plugin . '.php');
        $info = array(
            'plugin' => array(
                'name'    => isset($d['Name']) ? $d['Name'] : '',
                'version' => isset($d['Version']) ? $d['Version'] : '',
            ),
            'author' => array('author' => isset($d['Author']) ? $d['Author'] : ''),
        );
    }
    $p = (is_array($info) && isset($info['plugin'])) ? $info['plugin'] : array();
    if (empty($p['name'])) {
        $errors[] = '清单缺少 name';
    }
    if (empty($p['version'])) {
        $errors[] = '清单缺少 version';
    }
    $author = (is_array($info) && isset($info['author']['author'])) ? $info['author']['author'] : '';
    if (empty($author)) {
        $errors[] = '清单缺少 author';
    }
    return array('ok' => count($errors) === 0, 'errors' => $errors);
}

/**
 * 加载所有激活的插件
 */
function loadplugins()
{

    global $i;
    if (defined('SYSTEM_PLUGINS_LOADED')) {
        return;
    }
    foreach ($i['plugins']['actived'] as $value) {
        if (file_exists(SYSTEM_ROOT . '/plugins/' . $value . '/' . $value . '.php') && !is_dir(SYSTEM_ROOT . '/plugins/' . $value . '/' . $value . '.php')) {
            include SYSTEM_ROOT . '/plugins/' . $value . '/' . $value . '.php';
        }
    }
    define('SYSTEM_PLUGINS_LOADED', true);
}

/**
 * 激活插件
 * 如果存在，系统会调用 插件名_callback.php 的 callback_init()
 * @return bool
 */
function activePlugin($plugin)
{

    global $m;
    if (!valid_plugin_id($plugin)) {
        return false;
    }
    if (file_exists(SYSTEM_ROOT . '/plugins/' . $plugin . '/' . $plugin . '.php')) {
        $m->pquery("UPDATE `" . DB_PREFIX . "plugins` SET `status` = '1' WHERE `name` = ?", array($plugin));
        $callback_file =  SYSTEM_ROOT . '/plugins/' . $plugin . '/' . $plugin . '_callback.php';
        if (file_exists($callback_file)) {
            require_once $callback_file;
            if (function_exists('callback_init')) {
                callback_init();
            }
        }
        return true;
    } else {
        return false;
    }
}

/**
 * 禁用插件
 * 如果存在，系统会调用 插件名_callback.php 的 callback_inactive()
 * @return bool
 */
function inactivePlugin($plugin)
{

    global $m;
    if (!valid_plugin_id($plugin)) {
        return false;
    }
    if (file_exists(SYSTEM_ROOT . '/plugins/' . $plugin . '/' . $plugin . '.php')) {
        $m->pquery("UPDATE `" . DB_PREFIX . "plugins` SET `status` = '0' WHERE `name` = ?", array($plugin));
        $callback_file =  SYSTEM_ROOT . '/plugins/' . $plugin . '/' . $plugin . '_callback.php';
        if (file_exists($callback_file)) {
            require_once $callback_file;
            if (function_exists('callback_inactive')) {
                callback_inactive();
            }
        }
        return true;
    } else {
        return false;
    }
}

/**
 * 安装插件
 * 如果存在，系统会调用 插件名_callback.php 的 callback_install()
 * @return  bool
 */
function installPlugin($plugin)
{

    global $m,$i;
    if (!valid_plugin_id($plugin)) {
        return false;
    }
    if (file_exists(SYSTEM_ROOT . '/plugins/' . $plugin . '/' . $plugin . '.php')) {
        $info = getPluginInfo($plugin);
    //安装前判断版本
        if (isset($info['plugin']['forphp']) && strtolower($info['plugin']['forphp']) != 'all' && defined('PHP_VERSION')) {
            if (substr(PHP_VERSION, 0, 3) < $info['plugin']['forphp']) {
                msg('你的PHP版本过低(' . substr(PHP_VERSION, 0, 3) . ')，无法安装该插件');
            }
        }
        if (isset($info['plugin']['version'])) {
            $ver = $info['plugin']['version'];
        } else {
            $ver = '';
        }
        $m->pquery("INSERT IGNORE INTO `" . DB_PREFIX . "plugins` (`name`,`status`,`ver`,`options`) VALUES (?,?,?,'')", array($plugin, '0', $ver));
        $callback_file =  SYSTEM_ROOT . '/plugins/' . $plugin . '/' . $plugin . '_callback.php';
        if (file_exists($callback_file)) {
            require_once $callback_file;
            if (function_exists('callback_install')) {
                callback_install();
            }
        }
        return true;
    } else {
        return false;
    }
}

/**
 * 卸载插件
 * 如果存在，系统会调用 插件名_callback.php 的 callback_remove()
 */
function uninstallPlugin($plugin, $delete = true)
{

    global $m;
    if (!valid_plugin_id($plugin)) {
        return false;
    }
    inactivePlugin($plugin);
    $callback_file =  SYSTEM_ROOT . '/plugins/' . $plugin . '/' . $plugin . '_callback.php';
    if (file_exists($callback_file)) {
        require_once $callback_file;
        if (function_exists('callback_remove')) {
            callback_remove();
        }
    }
    $m->pquery("DELETE FROM `" . DB_PREFIX . "plugins` WHERE `name` = ?", array($plugin));
    if ($delete) {
        DeleteFile(SYSTEM_ROOT . '/plugins/' . $plugin);
    }
}

/**
 * 调用插件自定义保存设置函数
 * 插件调用方法：setting.php?mod=setplugin:插件名称
 * 然后系统会调用 插件名_callback.php 的 callback_setting()
 */
function settingPlugin($plugin)
{

    global $m;
    $callback_file =  SYSTEM_ROOT . '/plugins/' . $plugin . '/' . $plugin . '_callback.php';
    if (file_exists($callback_file)) {
        require_once $callback_file;
        if (function_exists('callback_setting')) {
            callback_setting();
        }
    }
}

/**
 * 升级插件
 * 系统会调用 插件名_callback.php 的 callback_update(数据库中的版本号,插件文件中的版本号)，并传入当前数据库的版本号、当前插件文件中说明的版本号
 * callback_update() 如果返回新的版本号，新版本号由系统记录到数据库；如果返回false，将终止操作且不记录到数据库
 * @return bool
 */
function updatePlugin($plugin)
{

    global $m,$i;
    if (isset($i['plugins']['desc'][$plugin])) {
        if (version_compare($i['plugins']['info'][$plugin]['ver'], $i['plugins']['desc'][$plugin]['plugin']['version']) != -1) {
            return false;
        }
    } else {
        return false;
    }
    $callback_file =  SYSTEM_ROOT . '/plugins/' . $plugin . '/' . $plugin . '_callback.php';
    if (file_exists($callback_file)) {
        require_once $callback_file;
        if (function_exists('callback_update')) {
            $ver = callback_update($i['plugins']['info'][$plugin]['ver'], $i['plugins']['desc'][$plugin]['plugin']['version']);
            if (!$ver) {
                return false;
            } else {
                $m->pquery("UPDATE `" . DB_PREFIX . "plugins` SET `ver` = ? WHERE `name` = ?", array($ver, $plugin));
                return true;
            }
        } else {
            return false;
        }
    } else {
        return false;
    }
}

/**
* 获取所有插件列表，未定义插件名称的插件将不予获取
* 插件目录：/plugins
* 仅识别 插件目录/插件/插件.php 目录结构的插件
* @param bool true为获取完整信息，false为获取标识符列表
* @return array 插件信息
*/
function getPlugins($full = true)
{

    $path = SYSTEM_ROOT . '/plugins/';
    $res  = listDir($path);
    $r    = array();
    foreach ($res as $x) {
        if (is_dir($path . $x) && file_exists($path . $x . '/' . $x . '.php')) {
            if ($full) {
                $r[] = getPluginInfo($x);
            } else {
                $r[] = $x;
            }
        }
    }
    return $r;
}

/**
 * 获取插件信息
 * @param string $plugin 插件标识符
 * @return bool|array 无效插件返回false，成功返回 插件名_desc.php 中的信息
 */
function getPluginInfo($plugin)
{

    if (!valid_plugin_id($plugin)) {
        return false;
    }
    $path = SYSTEM_ROOT . '/plugins/' . $plugin . '/';
    if (!file_exists($path . $plugin . '.php')) {
        return false;
    }
    if (file_exists($path . $plugin . '_desc.php')) {
        $r = include $path . $plugin . '_desc.php';
    } else {
        $d = getOldPluginData($plugin . '/' . $plugin . '.php');
        $r = array(
            'plugin' => array(
                'name'      => $d['Name'],
                'version'    => $d['Version'],
                'description' => $d['Description'],
                'url'        => $d['Url'],
                'for'        => $d['For'],
                'old'        => true
            ),
            'view'   => array(
                'setting'    => false,
                'show'      => false,
                'vip'        => false,
                'private'    => false,
                'public'      => false
            ),
            'author' => array(
                'author'      => $d['Author'],
                'url'        => $d['AuthorUrl']
            )
        );
    }
    $r['plugin']['id'] = $plugin;
    $r['core']['setting'] = file_exists($path . $plugin . '_setting.php');
    $r['core']['show'] = file_exists($path . $plugin . '_show.php');
    $r['core']['vip'] = file_exists($path . $plugin . '_vip.php');
    $r['core']['private'] = file_exists($path . $plugin . '_private.php');
    $r['core']['public'] = file_exists($path . $plugin . '_public.php');
//取插件加载顺序
    global $m;
    $qRes = $m->pquery('Select `order` From `' . DB_NAME . '`.`' . DB_PREFIX . 'plugins` Where `name` = ? LIMIT 1', array($plugin));
    $q = $qRes ? $m->fetch_array($qRes) : array('order' => 0);
    $r['plugin']['order'] = empty($q['order']) ? 0 : intval($q['order']);
    $manifest = plugin_manifest_validate($plugin);
    $r['plugin']['manifest_ok'] = $manifest['ok'];
    $r['plugin']['manifest_errors'] = $manifest['errors'];
//否则将会是00000001这样的数

    return $r;
}

/**
 * 获取旧式插件信息
 */
function getOldPluginData($pluginFile)
{

    global $i;
    $pluginPath = SYSTEM_ROOT . '/plugins/';
    $pluginData = file_get_contents($pluginPath . $pluginFile);
    preg_match("/Plugin Name:(.*)/i", $pluginData, $plugin_name);
    preg_match("/Version:(.*)/i", $pluginData, $version);
    preg_match("/Plugin URL:(.*)/i", $pluginData, $plugin_url);
    preg_match("/Description:(.*)/i", $pluginData, $description);
    preg_match("/For:(.*)/i", $pluginData, $For);
    preg_match("/Author:(.*)/i", $pluginData, $author_name);
    preg_match("/Author URL:(.*)/i", $pluginData, $author_url);
    $ret = explode('/', $pluginFile);
    $plugin = $ret[0];
    @$setting = (file_exists($pluginPath . $plugin . '/' . $plugin . '_setting.php') && in_array($pluginFile, $i['plugins']['actived'])) ? true : false;
    $plugin_name = isset($plugin_name[1]) ? strip_tags(trim($plugin_name[1])) : '';
    $version = isset($version[1]) ? strip_tags(trim($version[1])) : '';
    $description = isset($description[1]) ? strip_tags(trim($description[1])) : '';
    $plugin_url = isset($plugin_url[1]) ? strip_tags(trim($plugin_url[1])) : '';
    $author = isset($author_name[1]) ? strip_tags(trim($author_name[1])) : '';
    $For = isset($For[1]) ? strip_tags(trim($For[1])) : '';
    $author_url = isset($author_url[1]) ? strip_tags(trim($author_url[1])) : '';
    $For = str_ireplace(array('v',"\r",'+',' '), '', $For);
    if (!is_numeric($For)) {
        $For = 'all';
    }

    return array(
        'Name' => $plugin_name,
        'Version' => $version,
        'Description' => $description,
        'Url' => $plugin_url,
        'Author' => $author,
        'For' => $For,
        'AuthorUrl' => $author_url,
        'Setting' => $setting,
        'Plugin' => $plugin,
    );
}

foreach ($i['plugins']['actived'] as $pluginInfo) {
    $i['plugins']['desc'][$pluginInfo] = getPluginInfo($pluginInfo);
}

loadplugins();
