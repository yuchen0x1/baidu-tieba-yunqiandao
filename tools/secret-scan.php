<?php

/**
 * 仓库 Secret 扫描（发布门禁之一）：对 Git 追踪文件做高信号凭据模式检查。
 *
 *   php tools/secret-scan.php
 *
 * 规则：
 *  - 只扫 git ls-files 追踪中的文件（.gitignore 内的私钥目录天然排除）；
 *  - 误报行可在行尾加注释标记 `secret-scan:allow`（如 RFC 官方测试向量）；
 *  - 命中即退出码 1，输出 文件:行号 与命中的规则名（不回显内容，避免 Secret 二次扩散）。
 */

if (PHP_SAPI !== 'cli') {
    die('仅支持命令行运行：php tools/secret-scan.php');
}

$repoRoot = dirname(__DIR__);
$patterns = array(
    'pem-private-key'      => '/-----BEGIN [A-Z ]*PRIVATE KEY-----/',
    'openai-style-key'     => '/\bsk-[A-Za-z0-9]{20,}\b/',
    'github-token'         => '/\bgh[pousr]_[A-Za-z0-9]{20,}\b/',
    'github-fine-grained'  => '/\bgithub_pat_[A-Za-z0-9_]{20,}\b/',
    'aws-access-key'       => '/\bAKIA[0-9A-Z]{16}\b/',
    'google-api-key'       => '/\bAIza[0-9A-Za-z_\-]{30,}\b/',
    'slack-token'          => '/\bxox[baprs]-[A-Za-z0-9\-]{10,}\b/',
    'token-in-url-query'   => '/(access_token|api_key|apikey)=[A-Za-z0-9_\-]{16,}/i',
    'credential-literal'   => '/\b(api_?key|secret|password|passwd|private_key|access_token)\b\s*(=|=>|:)\s*([\'"])[^\'"]{16,}\3/i',
);

exec('git -C ' . escapeshellarg($repoRoot) . ' ls-files -z', $filesRaw, $code);
if ($code !== 0) {
    fwrite(STDERR, "git ls-files 失败（需要在 Git 仓库内运行）\n");
    exit(1);
}
$files = array();
foreach (explode("\0", implode('', $filesRaw) ?? '') as $f) {
    if ($f !== '') {
        $files[] = $f;
    }
}

$hits = array();
$scanned = 0;
foreach ($files as $rel) {
    $path = $repoRoot . '/' . $rel;
    if (!is_file($path) || filesize($path) > 4 * 1024 * 1024) {
        continue;
    }
    $lines = file($path, FILE_IGNORE_NEW_LINES);
    if ($lines === false) {
        continue;
    }
    $scanned++;
    foreach ($lines as $ln => $line) {
        if (strpos($line, 'secret-scan:allow') !== false) {
            continue;
        }
        foreach ($patterns as $name => $pattern) {
            if (preg_match($pattern, $line)) {
                $hits[] = sprintf('%s:%d [%s]', $rel, $ln + 1, $name);
                break; //一行报一条即可，避免刷屏
            }
        }
    }
}

if ($hits) {
    fwrite(STDERR, "发现疑似 Secret（共 " . count($hits) . " 处，已扫描 {$scanned} 个文件）：\n");
    foreach ($hits as $hit) {
        fwrite(STDERR, "  {$hit}\n");
    }
    fwrite(STDERR, "确认为误报的行尾可加标记 `secret-scan:allow`；真实泄露请立即吊销并轮换。\n");
    exit(1);
}
echo "secret-scan：已扫描 {$scanned} 个 Git 追踪文件，未发现疑似 Secret。\n";
