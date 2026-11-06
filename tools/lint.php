<?php
// @lint-ignore 本文件是扫描器本身，内含危险模式规则字符串，故豁免自检。

$root = dirname(__DIR__);
$iterator = new RecursiveIteratorIterator(
    new RecursiveCallbackFilterIterator(
        new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
        static function (SplFileInfo $file) use ($root) {
            if ($file->isDir()) {
                return !in_array($file->getFilename(), array('.git', 'vendor'), true);
            }
            return strtolower($file->getExtension()) === 'php';
        }
    )
);

/**
 * 高危模式（硬失败）：存在即阻断
 * key 为要匹配的文本（小写），value 为说明
 * 用字符串拼接避免本文件（扫描器）自身命中。
 */
$hardRules = array(
    'ev' . 'al(' => 'eval() 动态代码执行（RCE 风险）',
    'mysql_' . 'query(' => '废弃 mysql_* API，请使用 mysqli/参数化查询',
    'mysql_real_escape_' . 'string(' => '废弃 mysql_* API，请使用 pquery 参数化绑定',
);

/**
 * 警告模式（正则，提示不阻断）：建议改进
 */
$warnPatterns = array(
    '/addslashes\s*\(/i'   => '建议改用 pquery 参数化绑定（白名单列/表名 + 绑定值）',
    '/\b(?:system|shell_exec|passthru|popen|proc_open)\s*\(/i' => '命令执行调用，需人工确认是否有意为之',
);

$failed = array();
$warnings = array();
$count = 0;
foreach ($iterator as $file) {
    if (!$file->isFile() || strtolower($file->getExtension()) !== 'php') {
        continue;
    }
    $count++;
    $path = $file->getPathname();
    //进程内语法检查：TOKEN_PARSE 让解析器对非法语法抛出 ParseError，
    //无需为每个文件启动 php -l 子进程，也就不存在命令注入面。
    $source = @file_get_contents($path);
    if ($source === false) {
        $failed[] = $path . ' (无法读取文件)';
        continue;
    }
    if (function_exists('token_get_all')) {
        try {
            token_get_all($source, TOKEN_PARSE);
        } catch (ParseError $e) {
            $failed[] = $path . ' : ' . $e->getMessage();
            continue;
        }
    } else {
        // 环境未启用 tokenizer 扩展时，降级使用 php -l 校验语法
        $cmd = escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($path);
        exec($cmd . ' 2>&1', $lintOut, $lintCode);
        if ($lintCode !== 0) {
            $failed[] = $path . ' : ' . implode(' ', $lintOut);
            continue;
        }
    }

    // 高危/警告模式扫描（文件头部 @lint-ignore 注释可豁免整个文件）
    $head = strtolower(substr($source, 0, 400));
    if (strpos($head, '@lint-ignore') !== false) {
        continue;
    }
    $lower = strtolower($source);
    foreach ($hardRules as $needle => $desc) {
        if (strpos($lower, $needle) !== false) {
            $failed[] = $path . ' : [高危] ' . $desc;
        }
    }
    foreach ($warnPatterns as $pattern => $desc) {
        if (preg_match($pattern, $source)) {
            $warnings[] = $path . ' : [警告] ' . $desc;
        }
    }
}

if ($warnings !== array()) {
    echo "PHP lint warnings:\n" . implode("\n", $warnings) . "\n";
}
if ($failed !== array()) {
    fwrite(STDERR, "PHP lint failures:\n" . implode("\n", $failed) . "\n");
    exit(1);
}
echo "PHP syntax & security check passed: {$count} files\n";
