<?php

/**
 * Read a scalar request value without triggering array-to-string warnings.
 */
function request_scalar_value(array $source, $key, $default = '')
{
    if (!array_key_exists($key, $source) || !is_scalar($source[$key])) {
        return $default;
    }
    return (string) $source[$key];
}

function request_text_value(array $source, $key, $default = '')
{
    return trim(strip_tags(request_scalar_value($source, $key, $default)));
}

/**
 * Parse a MySQL endpoint in host, host:port, IPv6, or [IPv6]:port form.
 *
 * @param string $endpoint 数据库主机配置
 * @return array{host: string, port: int|null} 端口非法时原样返回主机并置空端口
 */
function parse_database_endpoint($endpoint)
{
    $endpoint = trim((string) $endpoint);
    if (preg_match('/^\[([^]]+)\](?::(\d+))?$/', $endpoint, $matches) === 1) {
        if (isset($matches[2])) {
            $port = (int) $matches[2];
            if ($port < 1 || $port > 65535) {
                return array('host' => $endpoint, 'port' => null);
            }
            return array('host' => $matches[1], 'port' => $port);
        }
        return array('host' => $matches[1], 'port' => null);
    }
    if (substr_count($endpoint, ':') === 1) {
        list($host, $port) = explode(':', $endpoint, 2);
        if ($host !== '' && ctype_digit($port)) {
            $port = (int) $port;
            if ($port >= 1 && $port <= 65535) {
                return array('host' => $host, 'port' => $port);
            }
            return array('host' => $endpoint, 'port' => null);
        }
    }
    return array('host' => $endpoint, 'port' => null);
}
