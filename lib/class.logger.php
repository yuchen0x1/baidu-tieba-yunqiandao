<?php

if (!defined('SYSTEM_ROOT')) {
    die('Insufficient Permissions');
}

/**
 * 轻量结构化日志（V5.13 A4 可观测性）
 * 以 JSON 行追加写入 data/log/system.log，便于采集与检索。
 * 用法：Logger::info('msg', ['key' => 'val']); / Logger::error(...)
 */
class Logger
{
    const LEVELS = array('debug' => 0, 'info' => 1, 'warn' => 2, 'error' => 3);

    /** 日志根目录（相对于站点根） */
    const LOG_DIR = '/data/log';

    /** 当前请求标识（同一请求的多条日志可关联） */
    public static $requestId = '';

    /** 静默模式：为 true 时不写 error_log（供测试等无 stderr 场景使用） */
    public static $quiet = false;

    /**
     * 追加一行结构化日志
     * @param string $level   debug/info/warn/error
     * @param string $message 日志正文
     * @param array  $context 附加上下文（键值，必须可 JSON 序列化）
     */
    public static function write($level, $message, $context = array())
    {
        $level = strtolower((string) $level);
        if (!isset(self::LEVELS[$level])) {
            $level = 'info';
        }
        $dir = SYSTEM_ROOT . self::LOG_DIR;
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        if (self::$requestId === '') {
            self::$requestId = substr(str_replace('.', '', uniqid('r', true)), 0, 12);
        }
        $entry = array(
            'ts'     => date('c'),
            'level'  => $level,
            'req'    => self::$requestId,
            'msg'    => (string) $message,
            'ctx'    => is_array($context) ? $context : array(),
        );
        $line = json_encode($entry, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
        if ($line === false) {
            $line = json_encode(array('ts' => date('c'), 'level' => $level, 'msg' => (string) $message, 'ctx' => array()));
        }
        @file_put_contents($dir . '/system.log', $line . "\n", FILE_APPEND | LOCK_EX);
        if ($level === 'error' && !self::$quiet) {
            @error_log($message);
        }
    }

    public static function debug($message, $context = array())
    {
        self::write('debug', $message, $context);
    }

    public static function info($message, $context = array())
    {
        self::write('info', $message, $context);
    }

    public static function warn($message, $context = array())
    {
        self::write('warn', $message, $context);
    }

    public static function error($message, $context = array())
    {
        self::write('error', $message, $context);
    }
}
