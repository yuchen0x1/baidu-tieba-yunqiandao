<?php

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

$failed = array();
$count = 0;
foreach ($iterator as $file) {
    if (!$file->isFile() || strtolower($file->getExtension()) !== 'php') {
        continue;
    }
    $count++;
    //进程内语法检查：TOKEN_PARSE 让解析器对非法语法抛出 ParseError，
    //无需为每个文件启动 php -l 子进程，也就不存在命令注入面。
    $source = @file_get_contents($file->getPathname());
    if ($source === false) {
        $failed[] = $file->getPathname() . ' (无法读取文件)';
        continue;
    }
    try {
        token_get_all($source, TOKEN_PARSE);
    } catch (ParseError $e) {
        $failed[] = $file->getPathname() . ' : ' . $e->getMessage();
    }
}

if ($failed !== array()) {
    fwrite(STDERR, "PHP syntax failures:\n" . implode("\n", $failed) . "\n");
    exit(1);
}
echo "PHP syntax check passed: {$count} files\n";
