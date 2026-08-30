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
    $command = escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($file->getPathname());
    exec($command, $output, $exitCode);
    if ($exitCode !== 0) {
        $failed[] = $file->getPathname();
    }
    $output = array();
}

if ($failed !== array()) {
    fwrite(STDERR, "PHP syntax failures:\n" . implode("\n", $failed) . "\n");
    exit(1);
}
echo "PHP syntax check passed: {$count} files\n";
