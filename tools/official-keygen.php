<?php

/**
 * 官方授权密钥对生成器（仅限官方运营者在本机执行，产物不入仓库）。
 *
 *   php tools/official-keygen.php                 # 生成并打印
 *   php tools/official-keygen.php -o /path/dir    # 私钥写入指定目录（应在仓库外/Secret Manager）
 *
 * 职责划分：
 *   私钥 → 官方授权服务器（GW_LICENSE_PRIVATE_KEY[_FILE]），绝不进入 Git、实例、镜像、发布包；
 *   公钥 → 粘贴到 lib/official-config.php 的 TCS_OFFICIAL_LICENSE_PUBKEY（随开源仓库分发）。
 */

if (PHP_SAPI !== 'cli') {
    die('仅支持命令行运行：php tools/official-keygen.php');
}

$outDir = null;
foreach (array_slice($argv, 1) as $i => $arg) {
    if ($arg === '-o' && isset($argv[$i + 2])) {
        $outDir = $argv[$i + 2];
    }
}

$key = openssl_pkey_new(array(
    'private_key_bits' => 2048,
    'private_key_type' => OPENSSL_KEYTYPE_RSA,
));
if ($key === false) {
    fwrite(STDERR, "密钥生成失败（需要 ext-openssl）\n");
    exit(1);
}
if (!openssl_pkey_export($key, $privatePem)) {
    fwrite(STDERR, "私钥导出失败\n");
    exit(1);
}
$details = openssl_pkey_get_details($key);
$publicPem = $details['key'];

echo "==================== License 签发私钥（机密，绝不入库） ====================\n";
echo $privatePem;
echo "\n==================== 验签公钥（公开，粘贴进 lib/official-config.php） ====================\n";
echo $publicPem;
echo "\n后续步骤：\n";
echo "  1. 私钥 -> 官方授权服务器的 GW_LICENSE_PRIVATE_KEY[_FILE]（推荐 Secret Manager 注入）\n";
echo "  2. 公钥 -> lib/official-config.php 常量 TCS_OFFICIAL_LICENSE_PUBKEY（保留换行）\n";
echo "  3. 在官方网关 /v1/license/issue 签发 License；实例后台导入后由公钥本地验签\n";

if ($outDir !== null) {
    if (!is_dir($outDir) && !mkdir($outDir, 0700, true)) {
        fwrite(STDERR, "无法创建输出目录：{$outDir}\n");
        exit(1);
    }
    $privatePath = rtrim($outDir, '/\\') . '/license-private.pem';
    if (file_put_contents($privatePath, $privatePem, LOCK_EX) === false) {
        fwrite(STDERR, "私钥写入失败：{$privatePath}\n");
        exit(1);
    }
    chmod($privatePath, 0600);
    echo "\n私钥已写入：{$privatePath}（权限 0600；请确认该目录不在任何 Git 仓库内）\n";
}
