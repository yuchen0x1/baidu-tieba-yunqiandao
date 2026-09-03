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
    if ($mode !== 'live' && $mode !== 'ready' && $mode !== 'deep') {
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
    if ($mode === 'deep') {
        // 深检：任务运行历史表、cron 新鲜度、失败堆积（依赖 V5.13 调度新结构，逐项容错）
        $checks['task_runs'] = false;
        $checks['cron_fresh'] = false;
        $checks['cron_error'] = false;
        $checks['task_runs_error_24h'] = false;
        if ($checks['database'] && $checks['mysqli']) {
            require_once $root . '/config.php';
            if (defined('DB_HOST') && defined('DB_USER') && defined('DB_PASSWD') && defined('DB_NAME') && defined('DB_PREFIX')) {
                $endpoint = parse_database_endpoint(DB_HOST);
                $prefix = DB_PREFIX;
                $connection = @mysqli_init();
                try {
                    if ($connection) {
                        $connection->options(MYSQLI_OPT_CONNECT_TIMEOUT, 3);
                    }
                    $flags = defined('DB_SSL') && DB_SSL ? MYSQLI_CLIENT_SSL : 0;
                    if ($connection && @$connection->real_connect($endpoint['host'], DB_USER, DB_PASSWD, DB_NAME, $endpoint['port'], null, $flags)) {
                        $probe = $connection->query("SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = '" . $connection->real_escape_string($prefix . 'task_runs') . "' LIMIT 1");
                        $checks['task_runs'] = $probe && $probe->num_rows === 1;

                        $row = $connection->query("SELECT MAX(`lastdo`) AS lastdo FROM `" . $prefix . "cron`");
                        if ($row && ($r2 = $row->fetch_assoc())) {
                            $lastdo = (int) $r2['lastdo'];
                            $checks['cron_fresh'] = $lastdo > 0 && (time() - $lastdo) < 86400;
                        }

                        $row = $connection->query("SELECT COUNT(*) AS c FROM `" . $prefix . "cron` WHERE `last_status` = 'error'");
                        if ($row && ($r2 = $row->fetch_assoc())) {
                            $checks['cron_error'] = ((int) $r2['c']) === 0;
                        }

                        if ($checks['task_runs']) {
                            $row = $connection->query("SELECT COUNT(*) AS c FROM `" . $prefix . "task_runs` WHERE `status` = 'error' AND `started_at` >= " . (time() - 86400));
                            if ($row && ($r2 = $row->fetch_assoc())) {
                                $checks['task_runs_error_24h'] = ((int) $r2['c']) < 10;
                            }
                        }
                    }
                } catch (Throwable $exception) {
                    $checks['task_runs'] = false;
                    $checks['cron_fresh'] = false;
                    $checks['cron_error'] = false;
                    $checks['task_runs_error_24h'] = false;
                } finally {
                    if ($connection) {
                        $connection->close();
                    }
                }
            }
        }
    }
    $ok = !in_array(false, $checks, true);
    return array('ok' => $ok, 'status' => $ok ? 'ok' : 'not_ready', 'mode' => $mode, 'checks' => $checks);
}
