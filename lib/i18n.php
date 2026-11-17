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
    private static $setting = null;

    /** Supported UI locales. User content and notification payloads are not translated here. */
    public static function locales()
    {
        return array('zh-CN', 'en-US');
    }

    /**
     * User preference: auto follows the browser/server language; explicit values pin a catalog.
     */
    public static function setting()
    {
        if (self::$setting !== null) {
            return self::$setting;
        }
        $value = '';
        if (isset($_COOKIE['tc_locale']) && is_string($_COOKIE['tc_locale'])) {
            $value = trim($_COOKIE['tc_locale']);
        }
        // Per-user setting wins over the browser cookie after login.
        if (class_exists('option', false) && defined('UID') && (int) UID > 0 && method_exists('option', 'uget')) {
            $userValue = (string) option::uget('i18n_lang');
            if ($userValue !== '') {
                $value = $userValue;
            }
        }
        if ($value === '' && class_exists('option', false)) {
            $globalValue = (string) option::get('i18n_lang');
            if ($globalValue !== '') {
                $value = $globalValue;
            }
        }
        if ($value !== 'zh-CN' && $value !== 'en-US') {
            $value = 'auto';
        }
        self::$setting = $value;
        return $value;
    }

    /** Resolve auto from the browser/server preference without using IP geolocation. */
    public static function resolve($setting = null, $candidates = null)
    {
        $setting = $setting === null ? self::setting() : (string) $setting;
        if ($setting === 'zh-CN' || $setting === 'en-US') {
            return $setting;
        }
        if (!is_array($candidates)) {
            $candidates = array();
            if (!empty($_SERVER['HTTP_ACCEPT_LANGUAGE'])) {
                foreach (explode(',', (string) $_SERVER['HTTP_ACCEPT_LANGUAGE']) as $candidate) {
                    $candidate = trim((string) preg_replace('/;.*$/', '', $candidate));
                    if ($candidate !== '') {
                        $candidates[] = $candidate;
                    }
                }
            }
        }
        foreach ($candidates as $candidate) {
            $candidate = strtolower((string) $candidate);
            if ($candidate === 'en' || strpos($candidate, 'en-') === 0) {
                return 'en-US';
            }
            if ($candidate === 'zh' || strpos($candidate, 'zh-') === 0) {
                return 'zh-CN';
            }
        }
        return 'zh-CN';
    }

    /**
     * 当前语言代码（安全白名单：仅字母数字下划线连字符）
     */
    public static function lang()
    {
        if (self::$lang !== null) {
            return self::$lang;
        }
        $setting = self::setting();
        if ($setting === 'auto' && class_exists('option', false)) {
            $opt = (string) option::get('i18n_lang');
            if ($opt === 'zh-CN' || $opt === 'en-US') {
                $setting = $opt;
            }
        }
        self::$lang = self::resolve($setting);
        return self::$lang;
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
        if (isset($dict[$key])) {
            $text = (string) $dict[$key];
        } elseif (self::lang() !== 'zh-CN') {
            $fallback = self::load('zh-CN');
            $text = isset($fallback[$key]) ? (string) $fallback[$key] : (string) $key;
        } else {
            $text = (string) $key;
        }
        if ($params) {
            foreach ($params as $k => $v) {
                $text = str_replace('{' . $k . '}', (string) $v, $text);
            }
        }
        return $text;
    }
}
