<?php

if (!defined('SYSTEM_ROOT')) {
    die('Insufficient Permissions');
}

/**
 * cron 计划任务操作类
 *
 * V5.13 增强：
 * - 任务级运行历史表 task_runs（logRun/recentRuns/latestRun）
 * - 跨进程防重入锁（acquireLock/releaseLock，cron 表 running/running_at）
 * - 按 nextrun 调度（runall），失败指数退避重试与失败通知（scheduleAfterRun）
 * - 完整单任务执行 runOnce（加锁→执行→记录→解锁→排程）
 */
class cron
{
    /** 任务级运行历史表名 */
    const TASK_RUNS_TABLE = 'task_runs';

    /**
     * 获取计划任务所有数据
     * @param string $name 计划任务名称
     * @return array
    */
    public static function get($name)
    {
        global $i;
        if (isset($i['cron'][$name])) {
            return $i['cron'][$name];
        }
    }

    /**
     * 获取计划任务指定数据
     * @param string $name 计划任务名称
     * @param string $set 设置项名
     */
    public static function sget($name, $set)
    {
        global $i;
        if (isset($i['cron'][$name][$set])) {
            return $i['cron'][$name][$set];
        }
    }

    /**
     * 通过数组改变或添加计划任务 (不存在时自动添加)
     * @param $name string 全局唯一计划任务名称
     * @param $set  array 设置项
     */
    public static function aset($name, $set)
    {
        global $m;

        //列名来自固定白名单，值全部经 bind_param 参数化绑定
        $allowed = array('file', 'no', 'enable', 'desc', 'freq', 'lastdo', 'nextrun', 'orde', 'log', 'retry', 'max_retry', 'last_status', 'last_duration', 'running', 'running_at');
        $cols = array('`name`');
        $ph = array('?');
        $params = array((string) $name);
        $dupCols = array();
        foreach ($allowed as $col) {
            if (isset($set[$col])) {
                $cols[] = '`' . $col . '`';
                $ph[] = '?';
                $params[] = $set[$col];
                $dupCols[] = $col;
            }
        }
        $updateParts = array();
        foreach ($dupCols as $col) {
            $updateParts[] = '`' . $col . '` = ?';
            $params[] = $set[$col];
        }

        $sql = "INSERT INTO `" . DB_PREFIX . "cron` (" . implode(', ', $cols) . ') VALUES (' . implode(', ', $ph) . ')'
            . ' ON DUPLICATE KEY UPDATE ' . implode(', ', $updateParts) . ';';
        $m->pquery($sql, $params);
    }

    /**
     * 改变或添加计划任务 (不存在时自动添加)
     * WARNING:请使用更先进的 aset() 代替他
     * $name 全局唯一计划任务名称
     * $file 计划任务文件，执行时以include方式执行function，function名称为cron_计划任务名称
     * $no 忽略任务
     * $desc 计划任务描述
     * $freq 执行频率
     *       -1：一次性任务，执行完毕后系统会删除
     *       0 ：默认，当do.php被执行时，该任务始终被运行
     *       其他正整数：运行时间间隔，单位秒（$lastdo - $freq）
     * $lastdo 上次执行，系统会写入
     * $log 执行日志，系统会写入
    */
    public static function set($name, $file = '', $no = 0, $desc = '', $freq = 0, $lastdo = '', $log = '')
    {
        global $m;
        $set = array();

        if (!empty($file)) {
            $set['file'] = $file;
        }
        if (!empty($no)) {
            $set['no'] = $no;
        }
        if (!empty($desc)) {
            $set['desc'] = $desc;
        }
        if (!empty($freq)) {
            $set['freq'] = $freq;
        }
        if (!empty($lastdo)) {
            $set['lastdo'] = $lastdo;
        }
        if (!empty($log)) {
            $set['log'] = $log;
        }

        self::aset($name, $set);
    }

    /**
     * 直接添加一个计划任务
     * @param string $name 计划任务名
     * @param string $set  任务设置
     */
    public static function add($name, $set)
    {
        global $m;

        //列白名单 + 参数化写入（历史实现为 addslashes 拼接）
        $columns = array('file', 'no', 'desc', 'freq', 'lastdo', 'log');
        $cols = '`name`';
        $marks = '?';
        $params = array((string) $name);
        foreach ($columns as $col) {
            if (isset($set[$col])) {
                $cols .= ', `' . $col . '`';
                $marks .= ', ?';
                $params[] = (string) $set[$col];
            }
        }
        $m->pquery("INSERT IGNORE INTO `" . DB_PREFIX . "cron` ({$cols}) VALUES ({$marks})", $params);
    }

    /**
     * 删除计划任务
     */
    public static function del($name)
    {
        global $m;
        $m->pquery("DELETE FROM `" . DB_NAME . "`.`" . DB_PREFIX . "cron` WHERE `name` = ?", array((string) $name));
    }

    /**
     * 确保 task_runs 表存在（幂等，供升级脚本与首次运行调用）。
     */
    public static function ensureTables()
    {
        static $ready = false;
        if ($ready) {
            return;
        }
        global $m;
        $m->query("CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . self::TASK_RUNS_TABLE . "` (
            `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            `name` VARCHAR(40) NOT NULL DEFAULT '',
            `status` VARCHAR(20) NOT NULL DEFAULT '',
            `message` TEXT,
            `duration_ms` INT UNSIGNED NOT NULL DEFAULT 0,
            `started_at` INT UNSIGNED NOT NULL DEFAULT 0,
            `finished_at` INT UNSIGNED NOT NULL DEFAULT 0,
            PRIMARY KEY (`id`),
            KEY `name` (`name`),
            KEY `status` (`status`),
            KEY `started_at` (`started_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
        $ready = true;
    }

    /**
     * 写入一条任务级运行记录。
     */
    public static function logRun($name, $status, $message, $duration_ms = 0, $started_at = 0)
    {
        global $m;
        self::ensureTables();
        $m->pquery(
            "INSERT INTO `" . DB_PREFIX . self::TASK_RUNS_TABLE . "` (`name`, `status`, `message`, `duration_ms`, `started_at`, `finished_at`) VALUES (?, ?, ?, ?, ?, ?)",
            array(
                substr((string) $name, 0, 40),
                substr((string) $status, 0, 20),
                substr((string) $message, 0, 1000),
                (int) $duration_ms,
                (int) $started_at,
                time(),
            )
        );
    }

    /**
     * 获取最近 N 条任务运行历史。
     */
    public static function recentRuns($limit = 20)
    {
        global $m;
        self::ensureTables();
        $limit = min(100, max(1, (int) $limit));
        $rs = $m->query("SELECT * FROM `" . DB_PREFIX . self::TASK_RUNS_TABLE . "` ORDER BY `id` DESC LIMIT {$limit}");
        $rows = array();
        while ($r = $m->fetch_array($rs)) {
            $rows[] = $r;
        }
        return $rows;
    }

    /**
     * 获取某任务最近一条运行记录。
     */
    public static function latestRun($name)
    {
        global $m;
        self::ensureTables();
        $name = $m->real_escape_string((string) $name);
        return $m->once_fetch_array("SELECT * FROM `" . DB_PREFIX . self::TASK_RUNS_TABLE . "` WHERE `name` = '{$name}' ORDER BY `id` DESC LIMIT 1");
    }

    /**
     * 任务防重入锁（基于 cron 表 running/running_at，跨 Web/CLI 进程有效）。
     * 超时时间由选项 cron_lock_timeout 控制，进程异常退出后锁会自动过期。
     * @param string $name 任务名
     * @return bool 是否成功获取锁
     */
    public static function acquireLock($name)
    {
        global $m;
        $timeout = max(60, (int) option::get('cron_lock_timeout'));
        $now = time();
        $ok = $m->pquery(
            "UPDATE `" . DB_NAME . "`.`" . DB_PREFIX . "cron` SET `running` = 1, `running_at` = ? WHERE `name` = ? AND (`running` = 0 OR `running_at` < ?)",
            array($now, (string) $name, $now - $timeout)
        );
        if (!$ok) {
            return false;
        }
        return $m->affected_rows() === 1;
    }

    /**
     * 释放任务防重入锁。
     */
    public static function releaseLock($name)
    {
        global $m;
        $m->pquery("UPDATE `" . DB_NAME . "`.`" . DB_PREFIX . "cron` SET `running` = 0, `running_at` = 0 WHERE `name` = ?", array((string) $name));
    }

    /**
     * 判断任务是否处于启用状态（兼容旧 no 字段与新 enable 字段）。
     */
    public static function isEnabled($cs)
    {
        $no = isset($cs['no']) ? (int) $cs['no'] : 0;
        $enable = isset($cs['enable']) ? (int) $cs['enable'] : 1;
        return $no !== 1 && $enable !== 0;
    }

    /**
     * 执行完成后的调度写入：更新 lastdo/last_status/耗时/retry 与 nextrun。
     * - 成功：清空 retry，按频率排下次执行；
     * - 失败：指数退避重试（retry × backoff），达到上限后进入正常周期并可选通知。
     * @param string $name    任务名
     * @param string $freq    执行频率（'-1' 一次性 / '0' 始终 / 正整数秒）
     * @param bool   $ok      是否执行成功
     * @param string $log     执行日志
     * @param int    $durationMs 耗时（毫秒）
     */
    public static function scheduleAfterRun($name, $freq, $ok, $log = '', $durationMs = 0)
    {
        global $m;
        $now = time();
        $maxRetry = max(0, (int) option::get('cron_max_retry'));
        $backoff = max(30, (int) option::get('cron_retry_backoff'));
        $durationMs = (int) $durationMs;

        if ($freq == '-1') {
            // 一次性任务：执行后即删除（无论成败，避免死循环）
            self::del($name);
            return;
        }

        if ($ok) {
            $next = ($freq == '0') ? 0 : $now + (int) $freq;
            self::aset($name, array(
                'lastdo'        => $now,
                'nextrun'       => $next,
                'retry'         => 0,
                'last_status'   => 'success',
                'last_duration' => $durationMs,
                'log'           => $log,
            ));
            return;
        }

        // 失败：读取当前累计重试次数
        $escaped = $m->real_escape_string((string) $name);
        $row = $m->once_fetch_array("SELECT `retry` FROM `" . DB_NAME . "`.`" . DB_PREFIX . "cron` WHERE `name` = '{$escaped}' LIMIT 1");
        $retry = isset($row['retry']) ? (int) $row['retry'] + 1 : 1;

        if ($retry <= $maxRetry) {
            // 指数退避：第 1 次失败等 backoff×1，第 2 次等 backoff×2……
            $next = $now + $backoff * $retry;
            self::aset($name, array(
                'lastdo'        => $now,
                'nextrun'       => $next,
                'retry'         => $retry,
                'last_status'   => 'error',
                'last_duration' => $durationMs,
                'log'           => $log,
            ));
        } else {
            // 达到最大重试：进入正常周期，并通知
            $next = ($freq == '0') ? ($now + max(300, $backoff)) : ($now + (int) $freq);
            self::aset($name, array(
                'lastdo'        => $now,
                'nextrun'       => $next,
                'retry'         => 0,
                'last_status'   => 'error',
                'last_duration' => $durationMs,
                'log'           => $log,
            ));
            require_once SYSTEM_ROOT . '/lib/class.logger.php';
            Logger::error('计划任务连续失败，已达最大重试次数', array('name' => $name, 'max_retry' => $maxRetry, 'log' => substr((string) $log, 0, 500)));
            require_once SYSTEM_ROOT . '/lib/class.webhook.php';
            Webhook::push('task_fail', array('task' => $name, 'max_retry' => $maxRetry, 'message' => substr((string) $log, 0, 500)));
            if (option::get('cron_notify_on_fail') == '1' && class_exists('Notify')) {
                require_once SYSTEM_ROOT . '/lib/class.notify.php';
                Notify::send(SYSTEM_FN . ' 计划任务执行失败', "计划任务「{$name}」已连续失败（超过最大重试 {$maxRetry} 次）。\n最近执行时间：" . date('Y-m-d H:i:s', $now) . "\n日志：" . (string) $log, 'cron_fail');
            }
        }
    }

    /**
     * 完整执行单个计划任务：加锁 → 执行 → 记录运行历史 → 解锁 → 排程。
     * 可同时用于「定时调度」「手动运行」「手动重试」。
     * @param string $name 计划任务名称
     * @return mixed 执行成功给出日志，否则 false
     */
    public static function runOnce($name)
    {
        global $m;
        $name = (string) $name;
        if (!preg_match('/^[A-Za-z0-9_]+$/', $name)) {
            return false;
        }
        $escaped = $m->real_escape_string($name);
        $row = $m->once_fetch_array("SELECT * FROM `" . DB_NAME . "`.`" . DB_PREFIX . "cron` WHERE `name` = '{$escaped}' LIMIT 1");
        if (empty($row['name'])) {
            return false;
        }

        if (!self::acquireLock($name)) {
            self::logRun($name, 'skipped', '任务已在运行或运行锁未释放（锁超时 ' . (int) option::get('cron_lock_timeout') . ' 秒）', 0, time());
            return false;
        }

        $start = microtime(true);
        self::logRun($name, 'running', '开始执行', 0, time());
        try {
            $ret = self::run($row['file'], $row['name']);
            $ok = ($ret !== false);
        } catch (Throwable $e) {
            $ret = '执行异常：' . $e->getMessage();
            $ok = false;
            require_once SYSTEM_ROOT . '/lib/class.logger.php';
            Logger::error('计划任务执行异常', array('name' => $name, 'error' => $e->getMessage()));
        }
        $durationMs = intval((microtime(true) - $start) * 1000);
        self::releaseLock($name);

        self::logRun($name, $ok ? 'success' : 'error', $ok ? (string) $ret : '失败：' . (string) $ret, $durationMs);
        self::scheduleAfterRun($row['name'], (string) $row['freq'], $ok, (string) $ret, $durationMs);
        return $ret;
    }

    /**
     * 执行一个计划任务
     *
     * @param string $file 计划任务文件
     * @param string $name 计划任务名称
     * @return mixed 执行成功给出日志，否则false
     */

    public static function run($file, $name)
    {
        $GLOBALS['in_cron'] = true;
        $name = (string) $name;
        $file = (string) $file;
        if (!preg_match('/^[A-Za-z0-9_]+$/', $name)) {
            self::aset($name, array('log' => '[' . date('Y-m-d H:i:s') . ']计划任务启动失败，任务名称不合法'));
            return false;
        }
        $file = str_replace('\\', '/', $file);
        if (!preg_match('~^(lib/[A-Za-z0-9_\-/]+\.php|plugins/[A-Za-z0-9_\-]+/[A-Za-z0-9_\-/]+\.php)$~', $file) || strpos($file, '..') !== false || $file[0] === '/') {
            self::aset($name, array('log' => '[' . date('Y-m-d H:i:s') . ']计划任务启动失败，任务文件不合法'));
            return false;
        }
        if (file_exists(SYSTEM_ROOT . '/' . $file)) {
            include_once SYSTEM_ROOT . '/' . $file;
            if (function_exists('cron_' . $name)) {
                return call_user_func('cron_' . $name);
            } else {
                self::aset($name, array('log' => '[' . date('Y-m-d H:i:s') . ']计划任务启动失败，处理此任务的函数不存在'));
                return false;
            }
        } else {
            self::aset($name, array('log' => '[' . date('Y-m-d H:i:s') . ']计划任务启动失败，任务文件不存在'));
            return false;
        }
    }

    /**
     * 异步执行计划任务（伪）
     * @param string  $name 计划任务名称
     */
    public static function arun($name)
    {
        $name = (string) $name;
        if (!preg_match('/^[A-Za-z0-9_]+$/', $name)) {
            self::aset($name, array('log' => '[' . date('Y-m-d H:i:s') . ']计划任务启动失败，任务名称不合法'));
            return false;
        }
        $url = SYSTEM_URL . 'do.php?mod=runcron&cron=' . urlencode($name);
        $cpw = option::get('cron_pw');
        $post = '';
        if (!empty($cpw)) {
            $post = 'pw=' . urlencode($cpw);
        }

        if (!sendRequest($url, $post)) {
            @option::set('cron_asyn', 0);
            self::aset($name, array('log' => '[' . date('Y-m-d H:i:s') . ']计划任务启动失败：在调用 fsockopen() 时失败，请检查主机是否支持此函数。目前系统已将[计划任务同时运行]关闭。'));
        }
    }

    /**
     * 按运行顺序运行所有计划任务（V5.13：按 nextrun 调度 + 防重入 + 失败退避重试）。
     */
    public static function runall()
    {
        global $m;
        $cron = $m->query("SELECT *  FROM `" . DB_NAME . "`.`" . DB_PREFIX . "cron` ORDER BY  `orde` ASC ");
        while ($cs = $m->fetch_array($cron)) {
            if (!self::isEnabled($cs)) {
                continue;
            }
            // 一次性任务：执行后由 scheduleAfterRun 删除
            if ($cs['freq'] == '-1') {
                self::runOnce($cs['name']);
                continue;
            }
            // 未到点则跳过（freq=0 / nextrun=0 视为始终待执行）
            if ((int) $cs['nextrun'] > time()) {
                continue;
            }
            self::runOnce($cs['name']);
        }
    }
}
