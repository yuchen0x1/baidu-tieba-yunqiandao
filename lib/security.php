<?php

if (!function_exists('safe_legacy_decode')) {
    /**
     * Decode simple legacy PHP serialized scalars/arrays without object support.
     *
     * @param mixed $value
     * @param mixed $default
     * @return mixed
     */
    function safe_legacy_decode($value, $default = array())
    {
        if (!is_string($value) || $value === '' || strlen($value) > 262144) {
            return $default;
        }
        $offset = 0;
        $ok = true;
        $decoded = safe_parse_legacy_value($value, $offset, 0, $ok);
        if (!$ok) {
            return $default;
        }
        $length = strlen($value);
        while ($offset < $length && ctype_space($value[$offset])) {
            $offset++;
        }
        return $offset === $length ? $decoded : $default;
    }
}

if (!function_exists('safe_parse_legacy_value')) {
    function safe_parse_legacy_value($data, &$offset, $depth, &$ok)
    {
        if (!$ok || $depth > 12) {
            $ok = false;
            return null;
        }
        $length = strlen($data);
        if ($offset >= $length) {
            $ok = false;
            return null;
        }

        $type = $data[$offset];
        $offset++;
        switch ($type) {
            case 'N':
                if (!safe_expect_char($data, $offset, ';')) {
                    $ok = false;
                }
                return null;
            case 'b':
                if (!safe_expect_char($data, $offset, ':')) {
                    $ok = false;
                    return null;
                }
                $bool = safe_read_until_char($data, $offset, ';', $ok);
                if ($bool === '0') {
                    return false;
                }
                if ($bool === '1') {
                    return true;
                }
                $ok = false;
                return null;
            case 'i':
                if (!safe_expect_char($data, $offset, ':')) {
                    $ok = false;
                    return null;
                }
                $number = safe_read_until_char($data, $offset, ';', $ok);
                if (!preg_match('/^-?\d+$/', $number)) {
                    $ok = false;
                    return null;
                }
                return (int) $number;
            case 'd':
                if (!safe_expect_char($data, $offset, ':')) {
                    $ok = false;
                    return null;
                }
                $number = safe_read_until_char($data, $offset, ';', $ok);
                if (!is_numeric($number)) {
                    $ok = false;
                    return null;
                }
                return (float) $number;
            case 's':
                if (!safe_expect_char($data, $offset, ':')) {
                    $ok = false;
                    return null;
                }
                $size = safe_read_until_char($data, $offset, ':', $ok);
                if (!preg_match('/^\d+$/', $size) || !safe_expect_char($data, $offset, '"')) {
                    $ok = false;
                    return null;
                }
                $size = (int) $size;
                if ($size > 65535 || $offset + $size + 2 > $length) {
                    $ok = false;
                    return null;
                }
                $string = substr($data, $offset, $size);
                $offset += $size;
                if (!safe_expect_char($data, $offset, '"') || !safe_expect_char($data, $offset, ';')) {
                    $ok = false;
                    return null;
                }
                return $string;
            case 'a':
                if (!safe_expect_char($data, $offset, ':')) {
                    $ok = false;
                    return null;
                }
                $count = safe_read_until_char($data, $offset, ':', $ok);
                if (!preg_match('/^\d+$/', $count) || !safe_expect_char($data, $offset, '{')) {
                    $ok = false;
                    return null;
                }
                $count = (int) $count;
                if ($count > 1000) {
                    $ok = false;
                    return null;
                }
                $array = array();
                for ($i = 0; $i < $count; $i++) {
                    $key = safe_parse_legacy_value($data, $offset, $depth + 1, $ok);
                    $value = safe_parse_legacy_value($data, $offset, $depth + 1, $ok);
                    if (!$ok || !(is_int($key) || is_string($key))) {
                        $ok = false;
                        return null;
                    }
                    $array[$key] = $value;
                }
                if (!safe_expect_char($data, $offset, '}')) {
                    $ok = false;
                    return null;
                }
                return $array;
            default:
                $ok = false;
                return null;
        }
    }
}

if (!function_exists('safe_expect_char')) {
    function safe_expect_char($data, &$offset, $char)
    {
        if ($offset >= strlen($data) || $data[$offset] !== $char) {
            return false;
        }
        $offset++;
        return true;
    }
}

if (!function_exists('safe_read_until_char')) {
    function safe_read_until_char($data, &$offset, $char, &$ok)
    {
        $pos = strpos($data, $char, $offset);
        if ($pos === false || $pos < $offset) {
            $ok = false;
            return '';
        }
        $value = substr($data, $offset, $pos - $offset);
        $offset = $pos + 1;
        return $value;
    }
}

if (!function_exists('safe_option_array')) {
    function safe_option_array($value)
    {
        $value = safe_legacy_decode($value, array());
        return is_array($value) ? $value : array();
    }
}

if (!function_exists('safe_sign_modes')) {
    function safe_sign_modes($value)
    {
        $modes = safe_legacy_decode($value, array());
        if (!is_array($modes)) {
            return array();
        }
        $result = array();
        foreach ($modes as $mode) {
            if (is_string($mode) || is_int($mode)) {
                $mode = (string) $mode;
                if (in_array($mode, array('1', '2', '3'), true)) {
                    $result[] = $mode;
                }
            }
        }
        return array_values(array_unique($result));
    }
}

if (!function_exists('safe_retry_state')) {
    function safe_retry_state($value)
    {
        $state = safe_legacy_decode($value, array());
        if (!is_array($state)) {
            return array('num' => 0, 'lastdo' => '');
        }
        $num = isset($state['num']) && is_int($state['num']) && $state['num'] >= 0
            ? $state['num'] : 0;
        $lastdo = isset($state['lastdo']) && is_string($state['lastdo'])
            && preg_match('/^\d{4}-\d{2}-\d{2}$/', $state['lastdo'])
            ? $state['lastdo'] : '';
        return array('num' => $num, 'lastdo' => $lastdo);
    }
}

if (!function_exists('safe_table_names')) {
    function safe_table_names($value)
    {
        $tables = safe_legacy_decode($value, array());
        if (!is_array($tables)) {
            return array();
        }
        $result = array();
        foreach ($tables as $table) {
            if (is_string($table) && preg_match('/^[a-z0-9_]{1,64}$/', $table)) {
                $result[] = $table;
            }
        }
        return array_values(array_unique($result));
    }
}

if (!function_exists('safe_table_name')) {
    /**
     * 单个贴吧分表名白名单校验：只允许小写字母/数字/下划线（≤64 位），
     * 不合法时回退到默认表。用于 users.t、TABLE 常量等来自数据库或用户配置、
     * 最终要拼进 SQL 标识符位置的值（二阶注入防线）。
     */
    function safe_table_name($value, $fallback = 'tieba')
    {
        if (!is_scalar($value)) {
            return $fallback;
        }
        $value = strtolower(trim((string) $value));
        return preg_match('/^[a-z0-9_]{1,64}$/', $value) ? $value : $fallback;
    }
}

if (!function_exists('safe_announcement_hooks')) {
    function safe_announcement_hooks($value)
    {
        $allowed = array('index_1', 'index_2', 'index_3', 'navi_7', 'navi_8', 'navi_9', 'login_page_1', 'login_page_2', 'login_page_3', 'reg_page_1', 'reg_page_2', 'reg_page_3', 'header', 'footer');
        $hooks = safe_legacy_decode($value, array());
        if (!is_array($hooks)) {
            return array();
        }
        return array_values(array_unique(array_intersect($allowed, array_map('strval', $hooks))));
    }
}

if (!function_exists('api_token_storable')) {
    /**
     * 将管理员输入的全局 API Token 转为可落库的形态：仅存 SHA-256 哈希，不再明文保存。
     * 已经是 64 位十六进制（历史哈希或用户恰好输入 64 hex）原样保留，避免无法回推比对。
     */
    function api_token_storable($token)
    {
        $token = trim((string) $token);
        if ($token === '') {
            return '';
        }
        if (strlen($token) === 64 && ctype_xdigit($token)) {
            return $token;
        }
        return hash('sha256', $token);
    }
}

if (!function_exists('api_token_matches')) {
    /**
     * 校验请求携带的 Token 是否匹配库中存储值。
     * 兼容两种历史形态：明文存储（升级前的旧站）与哈希存储。
     * 注意：存储值为哈希形态时，「哈希本身」不能作为凭据通过校验，只比对
     * SHA-256(提供的 Token)——否则拖库者无需还原原 Token 即可调用 API。
     */
    function api_token_matches($stored, $provided)
    {
        $stored = (string) $stored;
        $provided = (string) $provided;
        if ($stored === '' || $provided === '') {
            return false;
        }
        $storedIsHash = strlen($stored) === 64 && ctype_xdigit($stored);
        if (!$storedIsHash && hash_equals($stored, $provided)) {
            return true; // 旧版明文存储形态
        }
        return hash_equals($stored, hash('sha256', $provided));
    }
}

if (!function_exists('credential_mask')) {
    /**
     * 把敏感凭据渲染为掩码形态：只保留首尾少量字符供比对，中间全部打码。
     * 用于账号列表等场景，避免 BDUSS/STOKEN 明文进入 HTML 源码与截图。
     * 长度不足 12 时整体打码（不泄露长度信息）。
     */
    function credential_mask($value)
    {
        $value = (string) $value;
        if ($value === '') {
            return '';
        }
        $len = strlen($value);
        if ($len < 12) {
            return str_repeat('*', min(12, max(4, $len)));
        }
        $head = substr($value, 0, 4);
        $tail = substr($value, -4);
        return $head . str_repeat('*', min(24, max(8, $len - 8))) . $tail;
    }
}

if (!function_exists('normalize_forum_keyword')) {
    /**
     * 规范化用户可控制的贴吧名/关键词，返回空串表示不合法。
     *
     * 为什么需要：贴吧名会从插件配置（AI 回帖、自动回帖、帖子监控、封禁）
     * 一路传到 misc::getFid() → findFid() 的 SQL 与百度请求。历史上该链路
     * 只用 trim() 过滤，任何能配置插件的普通用户都可借此注入 SQL。
     * 这里做两道收敛：去掉控制字符，再按贴吧名的真实字符集白名单校验。
     *
     * 允许：汉字、字母、数字、下划线、连字符、加号、间隔号（如「海贼王·剧场版」）。
     * @param mixed $kw 原始关键词
     * @param int   $maxLength 最大字符数（贴吧名实测不超过 30，留余量到 60）
     * @return string 合法时返回规范化后的关键词，否则返回空串
     */
    function normalize_forum_keyword($kw, $maxLength = 60)
    {
        if (!is_scalar($kw)) {
            return '';
        }
        $kw = (string) $kw;
        //剥离 NUL、换行、制表等控制字符（含 %00 注入与多语句分隔的常见载体）
        $kw = preg_replace('/[\x00-\x1F\x7F]+/u', '', $kw);
        if (!is_string($kw)) {
            return '';
        }
        $kw = trim($kw);
        if ($kw === '') {
            return '';
        }
        if (mb_strlen($kw, 'UTF-8') > $maxLength) {
            return '';
        }
        return preg_match('/^[\p{Han}A-Za-z0-9_+\-·]{1,' . (int) $maxLength . '}$/u', $kw) === 1 ? $kw : '';
    }
}
