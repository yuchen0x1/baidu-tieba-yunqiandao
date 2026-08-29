<?php

/**
 * GitHub Release 在线更新支持。
 * 版本检查只读取公开的 latest Release；真正更新只由管理员主动触发。
 */
class ProjectUpdater
{
    const DEFAULT_TIMEOUT_MS = 8000;

    public static function normalizeVersion($value)
    {
        $value = preg_replace('/^[vV][\s_-]*/', '', trim((string) $value));
        if ($value === '' || preg_match('/^\d+(?:\.\d+){1,3}(?:[-+][0-9A-Za-z.-]+)?$/', $value) !== 1) {
            throw new InvalidArgumentException('Release 版本号无效');
        }
        return $value;
    }

    public static function parseRelease(array $payload)
    {
        if (empty($payload['tag_name'])) {
            throw new InvalidArgumentException('Release 缺少 tag_name');
        }
        $version = self::normalizeVersion($payload['tag_name']);
        $releaseUrl = isset($payload['html_url']) ? trim((string) $payload['html_url']) : '';
        $sourceUrl = isset($payload['zipball_url']) ? trim((string) $payload['zipball_url']) : '';
        if (!self::isAllowedUrl($releaseUrl) || !self::isAllowedUrl($sourceUrl)) {
            throw new InvalidArgumentException('Release 下载地址不受信任');
        }
        $asset = self::selectZipAsset(isset($payload['assets']) && is_array($payload['assets']) ? $payload['assets'] : array());
        return array(
            'tag_name' => (string) $payload['tag_name'],
            'version' => $version,
            'name' => isset($payload['name']) && $payload['name'] !== '' ? (string) $payload['name'] : '百度贴吧云签到 V' . $version,
            'body' => isset($payload['body']) ? (string) $payload['body'] : '',
            'release_url' => $releaseUrl,
            'source_download_url' => $sourceUrl,
            'asset_name' => $asset['name'],
            'asset_download_url' => $asset['url'],
            'asset_size' => $asset['size'],
            'asset_digest' => $asset['digest'],
            'published_at' => isset($payload['published_at']) ? (string) $payload['published_at'] : '',
            'is_prerelease' => !empty($payload['prerelease'])
        );
    }

    public static function compareVersions($currentVersion, $latestVersion)
    {
        return version_compare(self::normalizeVersion($currentVersion), self::normalizeVersion($latestVersion));
    }

    public static function buildStatus($currentVersion, array $release)
    {
        $current = self::normalizeVersion($currentVersion);
        return array(
            'current_version' => $current,
            'latest_version' => $release['version'],
            'update_available' => self::compareVersions($current, $release['version']) < 0,
            'release' => $release
        );
    }

    public static function fetchLatestRelease($apiUrl = null)
    {
        if ($apiUrl === null) {
            $apiUrl = defined('PROJECT_RELEASES_API_URL')
                ? PROJECT_RELEASES_API_URL
                : 'https://api.github.com/repos/yuchen0x1/baidu-tieba-yunqiandao/releases/latest';
        }
        if (!self::isAllowedUrl($apiUrl)) {
            throw new RuntimeException('更新服务器地址不受信任');
        }
        $curl = curl_init($apiUrl);
        if ($curl === false) {
            throw new RuntimeException('无法初始化更新连接');
        }
        $headers = array(
            'Accept: application/vnd.github+json',
            'User-Agent: Tieba-Cloud-Sign-Updater/' . (defined('SYSTEM_VER') ? SYSTEM_VER : 'unknown')
        );
        $token = getenv('UPDATE_GITHUB_TOKEN');
        if ($token !== false && trim((string) $token) !== '') {
            $headers[] = 'Authorization: Bearer ' . trim((string) $token);
        }
        curl_setopt_array($curl, array(
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_CONNECTTIMEOUT_MS => 3000,
            CURLOPT_TIMEOUT_MS => self::DEFAULT_TIMEOUT_MS,
            CURLOPT_HTTPHEADER => $headers
        ));
        $caInfo = self::findCaInfo();
        if ($caInfo !== false) {
            curl_setopt($curl, CURLOPT_CAINFO, $caInfo);
        }
        $body = curl_exec($curl);
        $error = curl_error($curl);
        $status = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);
        if ($body === false || $error !== '' || $status < 200 || $status >= 300) {
            throw new RuntimeException('无法连接 GitHub 更新服务器' . ($error !== '' ? '：' . $error : ''));
        }
        $payload = json_decode($body, true);
        if (!is_array($payload)) {
            throw new RuntimeException('更新服务器返回的数据无效');
        }
        try {
            return self::parseRelease($payload);
        } catch (InvalidArgumentException $e) {
            throw new RuntimeException($e->getMessage(), 0, $e);
        }
    }

    public static function installSourceRelease(array $release)
    {
        if (empty($release['source_download_url']) || !self::isAllowedUrl($release['source_download_url'])) {
            throw new RuntimeException('Release 没有可信的源码下载地址');
        }
        if (!class_exists('ZipArchive')) {
            throw new RuntimeException('服务器缺少 PHP zip 扩展');
        }
        if (!defined('UPDATE_CACHE') || !defined('SYSTEM_ROOT')) {
            throw new RuntimeException('更新目录未配置');
        }
        if (!is_dir(UPDATE_CACHE) && !mkdir(UPDATE_CACHE, 0777, true) && !is_dir(UPDATE_CACHE)) {
            throw new RuntimeException('无法创建更新缓存目录');
        }
        try {
            $suffix = bin2hex(random_bytes(8));
        } catch (Throwable $e) {
            $suffix = md5(uniqid('', true));
        }
        $workDir = rtrim(UPDATE_CACHE, '/\\') . '/release_' . $suffix;
        $extractDir = $workDir . '/extract';
        if (!mkdir($extractDir, 0777, true) && !is_dir($extractDir)) {
            throw new RuntimeException('无法创建更新临时目录');
        }
        try {
            $contents = self::download($release['source_download_url']);
            $zipPath = $workDir . '/source.zip';
            if ($contents === '' || $contents === false || file_put_contents($zipPath, $contents, LOCK_EX) === false) {
                throw new RuntimeException('无法下载更新包');
            }
            $zip = new ZipArchive();
            if ($zip->open($zipPath) !== true) {
                throw new RuntimeException('更新包不是有效的 ZIP 文件');
            }
            if (!function_exists('safe_zip_extract') || !safe_zip_extract($zip, $extractDir)) {
                $zip->close();
                throw new RuntimeException('更新包安全检查失败');
            }
            $zip->close();
            $root = self::findSingleRootDirectory($extractDir);
            if ($root === false) {
                throw new RuntimeException('更新包目录结构无效');
            }
            foreach (array('config.php', 'key.php', 'app.conf', 'config.yaml', '.env', 'docker/.env', 'setup/install.lock') as $relative) {
                $protected = $root . '/' . $relative;
                if (is_file($protected) && !unlink($protected)) {
                    throw new RuntimeException('无法保护站点文件：' . $relative);
                }
            }
            if (!function_exists('CopyAll') || CopyAll($root, SYSTEM_ROOT) !== true) {
                throw new RuntimeException('无法写入更新文件');
            }
            return true;
        } finally {
            if (function_exists('DeleteFile')) {
                DeleteFile($workDir);
            }
        }
    }

    private static function selectZipAsset(array $assets)
    {
        $selected = array('name' => '', 'url' => '', 'size' => 0, 'digest' => '');
        foreach ($assets as $asset) {
            $name = isset($asset['name']) ? (string) $asset['name'] : '';
            $url = isset($asset['browser_download_url']) ? trim((string) $asset['browser_download_url']) : '';
            if (strtolower(pathinfo($name, PATHINFO_EXTENSION)) !== 'zip' || !self::isAllowedUrl($url)) {
                continue;
            }
            $candidate = array(
                'name' => $name,
                'url' => $url,
                'size' => isset($asset['size']) ? (int) $asset['size'] : 0,
                'digest' => isset($asset['digest']) ? (string) $asset['digest'] : ''
            );
            if ($selected['url'] === '' || stripos($name, 'windows') !== false) {
                $selected = $candidate;
            }
            if (stripos($name, 'windows') !== false) {
                break;
            }
        }
        return $selected;
    }

    private static function isAllowedUrl($url)
    {
        $parts = parse_url((string) $url);
        if (!$parts || empty($parts['scheme']) || empty($parts['host']) || strtolower($parts['scheme']) !== 'https') {
            return false;
        }
        return in_array(strtolower($parts['host']), array('api.github.com', 'github.com', 'codeload.github.com', 'objects.githubusercontent.com', 'release-assets.githubusercontent.com'), true);
    }

    private static function download($url)
    {
        $curl = curl_init($url);
        if ($curl === false) {
            return false;
        }
        $headers = array('User-Agent: Tieba-Cloud-Sign-Updater');
        $token = getenv('UPDATE_GITHUB_TOKEN');
        if ($token !== false && trim((string) $token) !== '') {
            $headers[] = 'Authorization: Bearer ' . trim((string) $token);
        }
        curl_setopt_array($curl, array(
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_CONNECTTIMEOUT_MS => 5000,
            CURLOPT_TIMEOUT_MS => 120000,
            CURLOPT_HTTPHEADER => $headers
        ));
        $caInfo = self::findCaInfo();
        if ($caInfo !== false) {
            curl_setopt($curl, CURLOPT_CAINFO, $caInfo);
        }
        $body = curl_exec($curl);
        $status = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $error = curl_error($curl);
        curl_close($curl);
        return $body !== false && $error === '' && $status >= 200 && $status < 300 ? $body : false;
    }

    private static function findSingleRootDirectory($directory)
    {
        $entries = @scandir($directory);
        if (!is_array($entries)) {
            return false;
        }
        $directories = array();
        $files = array();
        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $path = $directory . '/' . $entry;
            is_dir($path) ? $directories[] = $path : $files[] = $path;
        }
        return count($directories) === 1 && count($files) === 0 ? $directories[0] : false;
    }

    private static function findCaInfo()
    {
        foreach (array(getenv('SSL_CERT_FILE'), getenv('CURL_CA_BUNDLE'), ini_get('curl.cainfo'), ini_get('openssl.cafile'), 'C:/Program Files/Git/usr/ssl/certs/ca-bundle.crt', '/etc/ssl/certs/ca-certificates.crt', '/etc/pki/tls/certs/ca-bundle.crt') as $candidate) {
            if ($candidate !== false && $candidate !== '' && is_file($candidate) && is_readable($candidate)) {
                return $candidate;
            }
        }
        return false;
    }
}
