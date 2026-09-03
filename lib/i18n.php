<?php

if (!defined('SYSTEM_ROOT')) {
    die('Insufficient Permissions');
}

/**
 * 轻量国际化骨架（V5.13 A5）
 * 默认中文（zh-CN），可按需在 lang/ 目录新增语言包。
 *
 * 用法：
 *   I18N::t('login.welcome')                  // 直接返回翻译
 *   I18N::t('sign.done', array('{user}' => $name))  // 参数替换
 *
 * 语言选项：option::get('i18n_lang')，留空默认 zh-CN。
 */
class I18N
{
    private static $dict = null;
    private static $lang = null;

    /**
     * 当前语言代码（安全白名单：仅字母数字下划线连字符）
     */
    public static function lang()
    {
        if (self::$lang !== null) {
            return self::$lang;
        }
        $lang = 'zh-CN';
        if (class_exists('option', false)) {
            $opt = (string) option::get('i18n_lang');
            if ($opt !== '' && preg_match('/^[A-Za-z0-9_\-]+$/', $opt)) {
                $lang = $opt;
            }
        }
        self::$lang = $lang;
        return $lang;
    }

    /**
     * 加载指定语言包（默认当前语言），返回词条数组
     */
    public static function load($lang = null)
    {
        if ($lang === null) {
            $lang = self::lang();
        }
        $file = SYSTEM_ROOT . '/lang/' . $lang . '.php';
        if (is_file($file)) {
            $dict = include $file;
            if (is_array($dict)) {
                return $dict;
            }
        }
        return array();
    }

    /**
     * 当前语言包（进程内缓存）
     */
    public static function dict()
    {
        if (self::$dict === null) {
            self::$dict = self::load();
        }
        return self::$dict;
    }

    /**
     * 翻译词条；未命中时返回 key 本身
     * @param string $key    词条键，如 'login.welcome'
     * @param array  $params 替换参数，如 array('{user}' => 'name')
     */
    public static function t($key, $params = array())
    {
        $dict = self::dict();
        $text = isset($dict[$key]) ? (string) $dict[$key] : (string) $key;
        if ($params) {
            foreach ($params as $k => $v) {
                $text = str_replace('{' . $k . '}', (string) $v, $text);
            }
        }
        return $text;
    }
}
