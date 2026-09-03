<?php

if (!defined('SYSTEM_ROOT')) {
    die('Insufficient Permissions');
}
ob_start();
/**
 * 加载头部
 * @param string $title 页面标题
 */
function loadhead($title = '')
{

    if (defined('SYSTEM_NO_UI')) {
        return;
    }
    $pageHeading = empty($title) ? '' : strip_tags($title);
    $title = empty($title) ? strip_tags(SYSTEM_NAME) : $title . ' - ' . strip_tags(SYSTEM_NAME);
    doAction('top');
    echo '<!DOCTYPE html><html lang="zh-CN"><head>';
    echo '<meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />';
    echo '<meta http-equiv="charset" content="utf-8">';
    echo '<meta name="viewport" content="width=device-width, initial-scale=1.0">';
    echo '<title>' . $title . '</title>';
    echo '<meta name="csrf-token" content="' . htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') . '">';
    echo '<meta name="generator" content="Cloud Sign Studio Ver.' . SYSTEM_VER . '" />';
    echo '<link href="favicon.ico" rel="shortcut icon"/>';
    echo '<meta name="author" content="Kenvix (https://kenvix.com) at StusGame (http://lovelive.us)" />';
    echo '<meta http-equiv="X-UA-Compatible" content="IE=edge,chrome=1" />';
    echo '<meta name="theme-color" content="#5b3aff" />';
    echo '<script>(function(){try{var t=localStorage.getItem("tc-theme");if(t==="dark"||t==="light"){document.documentElement.setAttribute("data-theme",t);}else{var m=window.matchMedia&&window.matchMedia("(prefers-color-scheme: dark)");if(m&&m.matches){document.documentElement.setAttribute("data-theme","dark");}}}catch(e){}})();</script>';
    $uiVer = SYSTEM_VER . '-g4';
    echo '<link rel="stylesheet" href="source/css/tokens.css?v=' . $uiVer . '">';
    echo '<link rel="stylesheet" href="source/css/b3-compat.css?v=' . SYSTEM_VER . '">';
    echo '<link rel="stylesheet" href="source/css/bootstrap.min.css?v=' . SYSTEM_VER . '">';
    echo '<link rel="stylesheet" href="source/css/ui.css?v=' . $uiVer . '">';
    echo '<link rel="stylesheet" href="source/css/my.css?v=' . $uiVer . '">';
    echo '<link rel="stylesheet" href="source/css/components.css?v=' . $uiVer . '">';
    echo '<script src="source/js/jquery.min.js?v=' . SYSTEM_VER . '"></script>';
    echo '<script defer src="source/js/bootstrap.min.js"></script>';
    echo '<script defer src="source/js/js.js?v=' . SYSTEM_VER . '"></script>';
    echo '<script defer src="source/js/my.js?v=' . SYSTEM_VER . '"></script>';
    echo '<script defer src="source/js/components.js?v=' . $uiVer . '"></script>';
    echo '<meta name="keywords" content="' . htmlspecialchars(option::get('system_keywords'), ENT_QUOTES, 'UTF-8') . '" />';
    echo '<meta name="description" content="' . htmlspecialchars(option::get('system_description'), ENT_QUOTES, 'UTF-8') . '" />';
    doAction('header');
    echo '</head><body class="app-body">';
    // v4: 内联 SVG icon sprite，所有图标通过 <use href="#i-xxx"/> 引用
    $iconSpritePath = SYSTEM_ROOT . '/source/icons.svg';
    if (is_readable($iconSpritePath)) {
        echo file_get_contents($iconSpritePath);
    }
    template('navi');
    if ($pageHeading !== '') {
        echo '<div class="page-heading"><div><div class="eyebrow">Control Center</div><h1>' . htmlspecialchars($pageHeading, ENT_QUOTES, 'UTF-8') . '</h1></div></div>';
    }
    doAction('body');
}

/**
 * 清理页脚 HTML
 * @param string $html
 * @return string
 */
function sanitize_footer_html($html)
{
    $allowed = '<a><b><i><u><em><strong><br><p><span><font><img><code><blockquote><ul><ol><li>';
    $html = strip_tags($html, $allowed);
    $html = preg_replace('/\son\w+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $html);
    $html = preg_replace('/(\s(?:href|src)\s*=\s*["\'])\s*javascript:[^"\']*(["\'])/i', '$1$2', $html);
    $html = preg_replace('/(\s(?:href|src)\s*=\s*)[^\s>]*javascript:[^\s>]*/i', '', $html);
    return $html;
}

/**
 * 加载底部
 * @param bool|string $copy 如果为string，则必须输入插件标识符，并显示插件版权，bool(true)则显示云签到版权
 */
function loadfoot($copy = false)
{

    global $i;
    if (defined('SYSTEM_NO_UI')) {
        return;
    }
    $icp = option::get('icp');
    echo '<footer class="app-footer">';
    if (!empty($icp)) {
        echo ' | <a href="http://beian.miit.gov.cn/" target="_blank">' . htmlspecialchars($icp, ENT_QUOTES, 'UTF-8') . '</a>';
    }
    echo '<br/>' . sanitize_footer_html(option::get('footer'));
    doAction('footer');
    if (is_string(($copy))) {
        if (isset($i['plugins']['desc'][$copy])) {
            $plug = $i['plugins']['desc'][$copy];
            echo '<br/><br/>';
            if (!empty($plug['plugin']['url'])) {
                echo sanitize_html_link($plug['plugin']['url'], $plug['plugin']['name']);
            }
            if (!empty($plug['plugin'][ 'version' ])) {
                echo ' V' . $plug['plugin'][ 'version' ];
            }
            echo ' // 作者：';
            if (!empty($plug['author']['url'])) {
                echo sanitize_html_link($plug['author']['url'], $plug['author']['author']);
            }
        }
    }
    if ($copy) {
        echo '<br/><br/>' . SYSTEM_FN . ' V' . SYSTEM_VER . ' // 作者: <a href="https://kenvix.com" target="_blank">Kenvix</a>  &amp; <a href="http://www.mokeyjay.com" target="_blank">mokeyjay</a> &amp;  <a href="http://fyy1999.lofter.com/" target="_blank">FYY</a> ';
    }
    echo '</footer></div></main></div></div></body></html>';
}

/**
 * 加载系统或插件的模板（或文件）
 * @param string $file 文件，如果表示为plugin:file，则加载plugin插件的file.php文件
 * @return mixed
 */
function template($file)
{

    if (strstr($file, ':')) {
        $parse = explode(':', $file);
        return include SYSTEM_ROOT . '/plugins/' . $parse[0] . '/' . $parse[1] . '.php';
    } else {
        return include SYSTEM_ROOT . '/templates/' . $file . '.php';
    }
}

/**
 * 输出 ECharts 统一加载标签（仅模板需要图表时调用一次）
 * 版本与本地缓存：跟随 SYSTEM_VER，缺省走 jsDelivr CDN
 * @param bool $return 是否返回而不输出
 * @return string|null
 */
function loadChartAssets($return = false)
{
    $ver = SYSTEM_VER . '-g4';
    $html = '<script src="https://cdn.jsdelivr.net/npm/echarts@5/dist/echarts.min.js?v=' . $ver . '"></script>';
    if ($return) {
        return $html;
    }
    echo $html;
}

/**
 * 检查是否应该标记导航为active
 * @param string $mod
 */
function checkIfActive($mod)
{

    global $i;
    if ($mod == $i['mode'][0] && !isset($_GET['plugin']) && !isset($_GET['pub_plugin']) && !isset($_GET['vip_plugin']) && !isset($_GET['pri_plugin'])) {
        echo 'active';
    } elseif (strpos($mod, 'admin:') === 0) {
        $a = explode(':', $mod);
        if ($a[0] == $i['mode'][0] && $a[1] == $i['mode'][1] && !isset($_GET['plugin']) && !isset($_GET['pub_plugin']) && !isset($_GET['vip_plugin']) && !isset($_GET['pri_plugin'])) {
            echo 'active';
        }
    }
}



