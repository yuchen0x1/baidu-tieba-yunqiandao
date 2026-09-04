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
     */
    function api_token_matches($stored, $provided)
    {
        $stored = (string) $stored;
        $provided = (string) $provided;
        if ($stored === '' || $provided === '') {
            return false;
        }
        if (hash_equals($stored, $provided)) {
            return true;
        }
        return hash_equals($stored, hash('sha256', $provided));
    }
}
