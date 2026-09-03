<?php

/**
 * 项目健康检查（healthz.php 就绪探针使用）
 * @param string $root 站点根目录
 * @param string $mode live=仅进程级检查（不依赖安装与数据库），ready=含数据库与核心表 Schema
 * @return array{ok: bool, status: string, mode: string, checks: array<string, bool>}
 */
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
                    $requiredTables = array('baiduid', 'cron', 'options', 'plugins', 'tieba', 'users', 'users_options', 'sign_history');
                    $checks['schema'] = true;
                    $tableStmt = $connection->prepare("SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ? LIMIT 1");
                    if (!$tableStmt) {
                        $checks['schema'] = false;
                    } else {
                        $tableName = '';
                        $tableStmt->bind_param('s', $tableName);
                        foreach ($requiredTables as $table) {
                            $tableName = DB_PREFIX . $table;
                            $tableStmt->execute();
                            $tableStmt->store_result();
                            if ($tableStmt->num_rows !== 1) {
                                $checks['schema'] = false;
                                break;
                            }
                        }
                        $tableStmt->close();
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
