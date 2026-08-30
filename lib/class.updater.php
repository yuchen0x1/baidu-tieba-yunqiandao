<?php

/**
 * Release 在线更新支持（Gitee/GitHub 双通道）。
 * 自动判定服务器所处网络位置：国内服务器走 Gitee 通道，国外服务器走 GitHub 通道。
 * 版本检查只读取公开的 latest Release；真正更新只由管理员主动触发。
 */
class ProjectUpdater
{
    const DEFAULT_TIMEOUT_MS = 8000;
    const GEO_TIMEOUT_MS = 3000;
    const GITHUB_RELEASES_API_URL = 'https://api.github.com/repos/yuchen0x1/baidu-tieba-yunqiandao/releases/latest';
    const GITEE_RELEASES_API_URL = 'https://gitee.com/api/v5/repos/yuchen0x1/baidu-tieba-yunqiandao/releases/latest';
    const REMOTE_HOST_WHITELIST = array('qifu-api.baidubce.com', 'api.ip.sb', 'gitee.com', 'api.github.com');

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
        $overrideUrl = $apiUrl;
        if ($overrideUrl === null && defined('PROJECT_RELEASES_API_URL')) {
            $configured = trim((string) PROJECT_RELEASES_API_URL);
            // init.php 会默认把该常量定义为 GitHub 地址；仅当显式自定义时才跳过通道判定，保持最高优先
            if ($configured !== '' && $configured !== self::GITHUB_RELEASES_API_URL) {
                $overrideUrl = $configured;
            }
        }
        if ($overrideUrl !== null) {
            return self::requestRelease($overrideUrl, 'custom');
        }
        $channel = self::determineChannel();
        if ($channel === 'gitee') {
            try {
                return self::requestRelease(self::GITEE_RELEASES_API_URL, 'gitee');
            } catch (RuntimeException $giteeError) {
                try {
                    // Gitee 通道失败，自动回退 GitHub
                    return self::requestRelease(self::GITHUB_RELEASES_API_URL, 'github');
                } catch (RuntimeException $githubError) {
                    throw new RuntimeException('Gitee 通道获取更新失败：' . $giteeError->getMessage() . '；GitHub 回退通道也失败：' . $githubError->getMessage(), 0, $githubError);
                }
            }
        }
        return self::requestRelease(self::GITHUB_RELEASES_API_URL, 'github');
    }

    /**
     * 判定更新通道（结果静态缓存）：
     * a) 百度 IP 属地接口返回"中国/China" → gitee；
     * b) ip.sb GeoIP 返回"CN" → gitee；
     * c) 两者都不可用时竞速 Gitee 与 GitHub 的 latest Release，先成功者胜；
     * d) 全部失败 → 兜底 github。
     */
    public static function determineChannel()
    {
        static $cached = null;
        if ($cached !== null) {
            return $cached;
        }
        $body = self::geoFetch('https://qifu-api.baidubce.com/ip/local/geo/v1/district');
        if ($body !== false && self::bodyIndicatesChina($body)) {
            $cached = 'gitee';
            return $cached;
        }
        $body = self::geoFetch('https://api.ip.sb/geoip');
        if ($body !== false && self::bodyIndicatesCn($body)) {
            $cached = 'gitee';
            return $cached;
        }
        $raced = self::raceReleaseChannel();
        $cached = $raced !== null ? $raced : 'github';
        return $cached;
    }

    private static function requestRelease($apiUrl, $channel)
    {
        if (!self::isAllowedUrl($apiUrl)) {
            throw new RuntimeException('更新服务器地址不受信任');
        }
        $curl = self::createReleaseCurl($apiUrl);
        if ($curl === false) {
            throw new RuntimeException('无法初始化更新连接');
        }
        $body = curl_exec($curl);
        $error = curl_error($curl);
        $status = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);
        if ($body === false || $error !== '' || $status < 200 || $status >= 300) {
            throw new RuntimeException('无法连接 ' . self::channelLabel($channel) . ' 更新服务器' . ($error !== '' ? '：' . $error : '（HTTP ' . $status . '）'));
        }
        $payload = json_decode($body, true);
        if (!is_array($payload)) {
            throw new RuntimeException(self::channelLabel($channel) . ' 更新服务器返回的数据无效');
        }
        $payload = self::normalizeGiteePayload($payload, $apiUrl);
        try {
            return self::parseRelease($payload);
        } catch (InvalidArgumentException $e) {
            throw new RuntimeException('[' . self::channelLabel($channel) . '] ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Gitee API v5 的 Release 响应可能缺少 html_url / zipball_url 字段，
     * 按仓库路径补齐官方标准的源码归档地址，保证源码 zip 与 Release 资产两条路都可用。
     */
    private static function normalizeGiteePayload(array $payload, $apiUrl)
    {
        if (strtolower((string) parse_url((string) $apiUrl, PHP_URL_HOST)) !== 'gitee.com') {
            return $payload;
        }
        $tag = isset($payload['tag_name']) ? trim((string) $payload['tag_name']) : '';
        $path = (string) parse_url((string) $apiUrl, PHP_URL_PATH);
        if ($tag === '' || preg_match('#/repos/([^/]+)/([^/]+)(?:/|$)#', $path, $matches) !== 1) {
            return $payload;
        }
        $base = 'https://gitee.com/' . $matches[1] . '/' . $matches[2];
        if (!isset($payload['html_url']) || trim((string) $payload['html_url']) === '') {
            $payload['html_url'] = $base . '/releases/tag/' . rawurlencode($tag);
        }
        if (!isset($payload['zipball_url']) || trim((string) $payload['zipball_url']) === '') {
            $payload['zipball_url'] = $base . '/repository/archive/' . rawurlencode($tag) . '.zip';
        }
        return $payload;
    }

    private static function channelLabel($channel)
    {
        if ($channel === 'gitee') {
            return 'Gitee';
        }
        if ($channel === 'github') {
            return 'GitHub';
        }
        return '自定义';
    }

    private static function createReleaseCurl($apiUrl)
    {
        if (!function_exists('curl_init')) {
            return false;
        }
        $curl = curl_init($apiUrl);
        if ($curl === false) {
            return false;
        }
        $headers = array(
            'Accept: application/vnd.github+json',
            'User-Agent: Tieba-Cloud-Sign-Updater/' . (defined('SYSTEM_VER') ? SYSTEM_VER : 'unknown')
        );
        $host = strtolower((string) parse_url((string) $apiUrl, PHP_URL_HOST));
        // GitHub 凭据绝不发送给 Gitee，反之亦然
        $token = getenv('UPDATE_GITHUB_TOKEN');
        if ($token !== false && trim((string) $token) !== '' && strpos($host, 'github.com') !== false) {
            $headers[] = 'Authorization: Bearer ' . trim((string) $token);
        }
        $giteeToken = getenv('UPDATE_GITEE_TOKEN');
        if ($giteeToken !== false && trim((string) $giteeToken) !== '' && strpos($host, 'gitee.com') !== false) {
            $headers[] = 'Authorization: Bearer ' . trim((string) $giteeToken);
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
        return $curl;
    }

    /**
     * 竞速 Gitee 与 GitHub 的 latest Release：先成功返回含 tag_name 数据者胜出。
     * 无法判定时返回 null，由调用方兜底。
     */
    private static function raceReleaseChannel()
    {
        if (!function_exists('curl_multi_init')) {
            return null;
        }
        $multi = curl_multi_init();
        if ($multi === false) {
            return null;
        }
        $candidates = array(
            'gitee' => self::GITEE_RELEASES_API_URL,
            'github' => self::GITHUB_RELEASES_API_URL
        );
        $handles = array();
        foreach ($candidates as $channel => $url) {
            if (!self::isAllowedUrl($url)) {
                continue;
            }
            $curl = self::createReleaseCurl($url);
            if ($curl === false) {
                continue;
            }
            $handles[$channel] = $curl;
            curl_multi_add_handle($multi, $curl);
        }
        $winner = null;
        if (!empty($handles)) {
            $running = null;
            do {
                curl_multi_exec($multi, $running);
                while (($info = curl_multi_info_read($multi)) !== false) {
                    $done = $info['handle'];
                    $channel = array_search($done, $handles, true);
                    if ($channel === false) {
                        continue;
                    }
                    $body = curl_multi_getcontent($done);
                    $status = (int) curl_getinfo($done, CURLINFO_HTTP_CODE);
                    if ($info['result'] === CURLE_OK && $status >= 200 && $status < 300 && is_string($body) && $body !== '') {
                        $payload = json_decode($body, true);
                        if (is_array($payload) && !empty($payload['tag_name'])) {
                            $winner = $channel;
                        }
                    }
                    curl_multi_remove_handle($multi, $done);
                    curl_close($done);
                    unset($handles[$channel]);
                    if ($winner !== null) {
                        break;
                    }
                }
                if ($winner !== null || $running === 0) {
                    break;
                }
                if (curl_multi_select($multi, 0.2) === -1) {
                    usleep(50000);
                }
            } while (true);
        }
        foreach ($handles as $curl) {
            curl_multi_remove_handle($multi, $curl);
            curl_close($curl);
        }
        curl_multi_close($multi);
        return $winner;
    }

    /**
     * 安全地抓取 GeoIP 判定接口（3 秒超时）。仅允许白名单主机的 http/https 地址，
     * 且先 DNS 解析再校验解析结果，拒绝环回、私有与保留地址。
     */
    private static function geoFetch($url)
    {
        if (!function_exists('curl_init') || !self::isSafeRemoteEndpoint($url)) {
            return false;
        }
        $parts = parse_url((string) $url);
        if (!$parts || empty($parts['host'])) {
            return false;
        }
        $host = strtolower((string) $parts['host']);
        $ip = self::resolveSafeIp($host);
        if ($ip === false) {
            return false;
        }
        $curl = curl_init($url);
        if ($curl === false) {
            return false;
        }
        $port = strtolower((string) $parts['scheme']) === 'http' ? 80 : 443;
        // 将连接固定在已校验的 IP 上，防止 DNS 解析与实际连接之间被重绑定
        curl_setopt_array($curl, array(
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_CONNECTTIMEOUT_MS => self::GEO_TIMEOUT_MS,
            CURLOPT_TIMEOUT_MS => self::GEO_TIMEOUT_MS,
            CURLOPT_RESOLVE => array($host . ':' . $port . ':' . $ip),
            CURLOPT_HTTPHEADER => array(
                'Accept: application/json',
                'User-Agent: Tieba-Cloud-Sign-Updater/' . (defined('SYSTEM_VER') ? SYSTEM_VER : 'unknown')
            )
        ));
        $caInfo = self::findCaInfo();
        if ($caInfo !== false) {
            curl_setopt($curl, CURLOPT_CAINFO, $caInfo);
        }
        $body = curl_exec($curl);
        $status = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);
        return $body !== false && $status >= 200 && $status < 300 ? $body : false;
    }

    private static function bodyIndicatesChina($body)
    {
        $body = (string) $body;
        return strpos($body, '中国') !== false
            || stripos($body, 'China') !== false
            || strpos($body, '\u4e2d\u56fd') !== false;
    }

    private static function bodyIndicatesCn($body)
    {
        $body = (string) $body;
        $payload = json_decode($body, true);
        if (is_array($payload)) {
            foreach (array('country_code', 'countryCode', 'country') as $key) {
                if (isset($payload[$key]) && is_string($payload[$key]) && strtoupper(trim($payload[$key])) === 'CN') {
                    return true;
                }
            }
        }
        return strpos($body, 'CN') !== false;
    }

    private static function isSafeRemoteEndpoint($url)
    {
        $parts = parse_url((string) $url);
        if (!$parts || empty($parts['scheme']) || empty($parts['host'])) {
            return false;
        }
        $scheme = strtolower((string) $parts['scheme']);
        if ($scheme !== 'http' && $scheme !== 'https') {
            return false;
        }
        $host = strtolower((string) $parts['host']);
        if ($host === '' || !in_array($host, self::REMOTE_HOST_WHITELIST, true)) {
            return false;
        }
        return self::resolveSafeIp($host) !== false;
    }

    /**
     * 先 DNS 解析，再校验解析出的 IP；返回第一个安全 IP，全部不安全或解析失败返回 false。
     */
    private static function resolveSafeIp($host)
    {
        $host = strtolower(trim((string) $host));
        if ($host === '') {
            return false;
        }
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return self::isSafeIp($host) ? $host : false;
        }
        $ips = @gethostbynamel($host);
        if (!is_array($ips)) {
            $ips = array();
        }
        if (function_exists('dns_get_record')) {
            $records = @dns_get_record($host, DNS_AAAA);
            if (is_array($records)) {
                foreach ($records as $record) {
                    if (!empty($record['ipv6']) && filter_var($record['ipv6'], FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
                        $ips[] = $record['ipv6'];
                    }
                }
            }
        }
        if (count($ips) === 0) {
            $single = @gethostbyname($host);
            if (is_string($single) && $single !== $host && filter_var($single, FILTER_VALIDATE_IP)) {
                $ips[] = $single;
            }
        }
        foreach ($ips as $ip) {
            if (self::isSafeIp($ip)) {
                return $ip;
            }
        }
        return false;
    }

    private static function isSafeIp($ip)
    {
        if (!filter_var($ip, FILTER_VALIDATE_IP)) {
            return false;
        }
        // 拒绝私有与保留地址（含环回 127.0.0.0/8、链路本地 169.254.0.0/16 等）
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
            return false;
        }
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            $segments = explode('.', (string) $ip);
            $first = (int) $segments[0];
            $second = isset($segments[1]) ? (int) $segments[1] : -1;
            if ($first === 0 || $first === 10 || $first === 127 || ($first === 100 && $second >= 64 && $second <= 127) || ($first === 172 && $second >= 16 && $second <= 31) || ($first === 192 && $second === 168) || ($first === 169 && $second === 254)) {
                return false;
            }
            return true;
        }
        // IPv6：环回 ::1、未指定 ::、ULA fc00::/7、链路本地 fe80::/10
        $lower = strtolower((string) $ip);
        if ($lower === '::1' || $lower === '::' || preg_match('/^f[cd]/', $lower) === 1 || preg_match('/^fe[89ab]/', $lower) === 1) {
            return false;
        }
        return true;
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
        return in_array(strtolower($parts['host']), array('api.github.com', 'github.com', 'codeload.github.com', 'objects.githubusercontent.com', 'release-assets.githubusercontent.com', 'gitee.com'), true);
    }

    private static function download($url)
    {
        $curl = curl_init($url);
        if ($curl === false) {
            return false;
        }
        $headers = array('User-Agent: Tieba-Cloud-Sign-Updater');
        $host = strtolower((string) parse_url((string) $url, PHP_URL_HOST));
        $token = getenv('UPDATE_GITHUB_TOKEN');
        // GitHub 凭据不发送给其他主机（如 Gitee）
        if ($token !== false && trim((string) $token) !== '' && strpos($host, 'github.com') !== false) {
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
