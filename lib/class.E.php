<?php

if (!defined('SYSTEM_ROOT')) {
    die('Insufficient Permissions');
}
/**
 * 异常类
 * 抛出异常：throw new E('异常',异常码) 或者 throw new Exception('异常',异常码)
 */
class E extends Exception
{
    public function __tostring()
    {
        self::display($this->code, $this->message, $this->file, $this->line, $this->getTrace());
    }

    public static function exception($e)
    {
        $ex = new ReflectionClass($e);
        self::display(
            $ex->getMethod('getCode')->invoke($e),
            $ex->getMethod('getMessage')->invoke($e),
            $ex->getMethod('getFile')->invoke($e),
            $ex->getMethod('getLine')->invoke($e),
            $ex->getMethod('getTrace')->invoke($e)
        );
    }

    public static function error($errno, $errstr, $errfile, $errline)
    {
        $errnoo = self::getErrorType($errno);
        if (!defined('SYSTEM_NO_ERROR') && SYSTEM_DEV == true) {
            echo '<div class="alert alert-danger alert-dismissable">';
            echo '<strong>' . $errnoo . '：</strong>' . htmlspecialchars($errstr, ENT_QUOTES, 'UTF-8') . '<br/>文件：' . htmlspecialchars($errfile, ENT_QUOTES, 'UTF-8') . ' @ ' . (int) $errline . '行</div>';
        }
        if (function_exists('doAction')) {
            doAction('error', $errno, $errstr, $errfile, $errline, $errnoo);
        }
    }

    public static function display($code, $message, $file, $line, $trace)
    {
        ob_clean();
        $showDetails = defined('SYSTEM_DEV') && SYSTEM_DEV && defined('ROLE') && ROLE === 'admin';
        if (!$showDetails) {
            error_log(sprintf('[%s] #%d %s @ %s:%d', date('c'), (int) $code, $message, $file, (int) $line));
            msg('系统发生内部错误，请稍后重试或联系管理员', true, true, true, false);
            return;
        }
        $msg = SYSTEM_FN . ' V' . SYSTEM_VER . ' (PHP ' . phpversion() . ') 在工作时发生致命的异常 @ ' . date('Y-m-d H:i:s') . '<br/><b>消息：</b>#' . (int) $code . ' - ' . htmlspecialchars($message, ENT_QUOTES, 'UTF-8') . '<br/><br/>';
        $msg .= '<table style="width:100%"><thead><th>文件</th><th>行</th><th>代码</th></thead><tbody>';
        $msg .= '<tr><td>' . htmlspecialchars($file, ENT_QUOTES, 'UTF-8') . '</td><td>' . (int) $line . '</td><td>[抛出异常]</td></tr>';
        foreach ($trace as $v) {
            $tracefile = isset($v['file']) ? $v['file'] : '';
            $traceline = isset($v['line']) ? $v['line'] : '';
            $msg .= '<tr><td>' . htmlspecialchars($tracefile, ENT_QUOTES, 'UTF-8') . '</td><td>' . (int) $traceline . '</td><td>' . htmlspecialchars($v['function'], ENT_QUOTES, 'UTF-8') . '</td></tr>';
        }
        $msg .= '</tbody></table>';
        if (function_exists('doAction')) {
            doAction('error_2', $code, $message, $file, $line, $trace);
        }
        msg($msg, true, true, true, true);
    }

    /**
     * 获取错误类型
     * @param int $errno 错误代码
     * @return string 错误类型
     */
    public static function getErrorType($errno)
    {
        switch ($errno) {
            case E_ERROR:
            case E_USER_ERROR:
                $errnoo = '致命错误';
                break;
            case E_WARNING:
            case E_USER_WARNING:
                $errnoo = '警告';
                break;
            case E_PARSE:
                $errnoo = '解析错误';
                break;
            case E_NOTICE:
            case E_USER_NOTICE:
                $errnoo = '提示';
                break;
            case E_CORE_ERROR:
                $errnoo = '核心错误';
                break;
            case E_CORE_WARNING:
                $errnoo = '核心警告';
                break;
            case E_COMPILE_ERROR:
                $errnoo = '编译错误';
                break;
            case E_COMPILE_WARNING:
                $errnoo = '编译警告';
                break;
            case 2048:
                $errnoo = '严谨性提示';
                break;
            default:
                $errnoo = '未知错误 [ #' . $errno . ' ]';
                break;
        }
        return $errnoo;
    }
}
