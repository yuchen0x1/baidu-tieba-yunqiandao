<?php

function parse_database_endpoint($endpoint)
{
    $endpoint = trim((string) $endpoint);
    if (preg_match('/^\[([^]]+)\](?::(\d+))?$/', $endpoint, $matches) === 1) {
        return array('host' => $matches[1], 'port' => isset($matches[2]) ? (int) $matches[2] : null);
    }
    if (substr_count($endpoint, ':') === 1) {
        list($host, $port) = explode(':', $endpoint, 2);
        if ($host !== '' && ctype_digit($port)) {
            return array('host' => $host, 'port' => (int) $port);
        }
    }
    return array('host' => $endpoint, 'port' => null);
}

function project_health_check($root, $mode = 'ready')
{
    $root = rtrim((string) $root, '/\\');
    $mode = strtolower(trim((string) $mode));
    if ($mode !== 'live' && $mode !== 'ready') {
        $mode = 'ready';
    }
    $checks = array(
        'php' => PHP_VERSION_ID >= 80200,
        'mysqli' => class_exists('mysqli'),
    );
    if ($mode === 'live') {
        return array('ok' => $checks['php'], 'status' => $checks['php'] ? 'ok' : 'unhealthy', 'mode' => $mode, 'checks' => $checks);
    }

    $checks['install_lock'] = is_file($root . '/setup/install.lock');
    $checks['config'] = is_file($root . '/config.php');
    $checks['database'] = false;
    $checks['schema'] = false;
    if ($checks['config'] && $checks['mysqli']) {
        require_once $root . '/config.php';
        if (defined('DB_HOST') && defined('DB_USER') && defined('DB_PASSWD') && defined('DB_NAME') && defined('DB_PREFIX')) {
            $endpoint = parse_database_endpoint(DB_HOST);
            $connection = @mysqli_init();
            try {
                if ($connection) {
                    $connection->options(MYSQLI_OPT_CONNECT_TIMEOUT, 3);
                }
                $flags = defined('DB_SSL') && DB_SSL ? MYSQLI_CLIENT_SSL : 0;
                $checks['database'] = $connection && @$connection->real_connect(
                    $endpoint['host'],
                    DB_USER,
                    DB_PASSWD,
                    DB_NAME,
                    $endpoint['port'],
                    null,
                    $flags
                );
                if ($checks['database']) {
                    $requiredTables = array('baiduid', 'cron', 'options', 'plugins', 'tieba', 'users', 'users_options');
                    $checks['schema'] = true;
                    foreach ($requiredTables as $table) {
                        $name = $connection->real_escape_string(DB_PREFIX . $table);
                        $result = $connection->query("SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = '{$name}' LIMIT 1");
                        if (!$result || $result->num_rows !== 1) {
                            $checks['schema'] = false;
                            if ($result) {
                                $result->free();
                            }
                            break;
                        }
                        $result->free();
                    }
                }
            } catch (Throwable $exception) {
                $checks['database'] = false;
                $checks['schema'] = false;
            } finally {
                if ($connection) {
                    $connection->close();
                }
            }
        }
    }
    $ok = !in_array(false, $checks, true);
    return array('ok' => $ok, 'status' => $ok ? 'ok' : 'not_ready', 'mode' => $mode, 'checks' => $checks);
}
