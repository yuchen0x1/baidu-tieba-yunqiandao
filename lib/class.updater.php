<?php

/**
 * GitHub API 达到匿名额度或上游明确返回 429 时抛出；不携带响应正文，避免把
 * 上游内容、认证信息或请求细节泄露到页面和日志。
 */
class ProjectUpdaterRateLimitException extends RuntimeException
{
    private $retryAfter;
    private $resetAt;
    private $limit;
    private $remaining;
    private $status;

    public function __construct($status, $retryAfter = 0, $resetAt = 0, $limit = null, $remaining = null)
    {
        $this->status = (int) $status;
        $this->retryAfter = max(0, min(604800, (int) $retryAfter));
        $this->resetAt = max(0, (int) $resetAt);
        $this->limit = $limit === null ? null : max(0, (int) $limit);
        $this->remaining = $remaining === null ? null : max(0, (int) $remaining);
        parent::__construct('GitHub 更新接口暂时限流（HTTP ' . $this->status . '）');
    }

    public function getRetryAfter()
    {
        return $this->retryAfter;
    }

    public function getResetAt()
    {
        return $this->resetAt;
    }

    public function getLimit()
    {
        return $this->limit;
    }

    public function getRemaining()
    {
        return $this->remaining;
    }

    public function getStatusCode()
    {
        return $this->status;
    }
}

/**
 * Release 在线更新仅支持 GitHub 正式 Release。
 * 版本检查只读取正式 latest Release；真正更新只由管理员主动触发。
 */
class ProjectUpdater
{
    const DEFAULT_TIMEOUT_MS = 8000;
    const GEO_TIMEOUT_MS = 3000;
    const REMOTE_HOST_WHITELIST = array('api.github.com', 'github.com', 'objects.githubusercontent.com', 'release-assets.githubusercontent.com');
    const GITHUB_RELEASES_API_URL = 'https://api.github.com/repos/yuchen0x1/baidu-tieba-yunqiandao/releases/latest';
    const GITHUB_RELEASES_PAGE_URL = 'https://github.com/yuchen0x1/baidu-tieba-yunqiandao/releases/latest';
    const GITHUB_RELEASES_HISTORY_API_URL = 'https://api.github.com/repos/yuchen0x1/baidu-tieba-yunqiandao/releases?per_page=10';
    const GITHUB_REPOSITORY_API_URL = 'https://api.github.com/repos/yuchen0x1/baidu-tieba-yunqiandao';
    const HISTORY_CACHE_OPTION = 'update_release_history_cache';
    const HISTORY_CACHE_TTL = 1800;

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
        if ($releaseUrl === '') {
            $releaseUrl = 'https://github.com/yuchen0x1/baidu-tieba-yunqiandao/releases/tag/' . rawurlencode((string) $payload['tag_name']);
        }
        $sourceUrl = isset($payload['zipball_url']) ? trim((string) $payload['zipball_url']) : '';
        if ($sourceUrl === '') {
            $sourceUrl = 'https://api.github.com/repos/yuchen0x1/baidu-tieba-yunqiandao/zipball/' . rawurlencode((string) $payload['tag_name']);
        }
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
            'asset_checksum_url' => self::selectChecksumAsset(isset($payload['assets']) && is_array($payload['assets']) ? $payload['assets'] : array()),
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
        $releaseUrl = $apiUrl;
        if ($releaseUrl === null && defined('PROJECT_RELEASES_API_URL')) {
            $releaseUrl = trim((string) PROJECT_RELEASES_API_URL);
        }
        if ($releaseUrl === null || $releaseUrl === '') {
            $releaseUrl = self::GITHUB_RELEASES_API_URL;
        }
        try {
            return self::requestRelease($releaseUrl, 'github');
        } catch (ProjectUpdaterRateLimitException $e) {
            // The public GitHub API is shared by many installations. Its official
            // Releases page is a safe discovery fallback and needs no API quota.
            if (strcasecmp(rtrim($releaseUrl, '/'), rtrim(self::GITHUB_RELEASES_API_URL, '/')) !== 0) {
                throw $e;
            }
            try {
                return self::fetchLatestReleaseFromPage();
            } catch (Throwable $fallbackError) {
                // Keep the typed rate-limit error so callers can show a useful,
                // non-sensitive retry message instead of exposing page details.
                throw $e;
            }
        }
    }

    /**
     * Discover the latest tag from GitHub's official Releases page when the API
     * quota is exhausted. Asset URLs follow the fixed names emitted by the
     * release publisher; the checksum is still downloaded and verified at install.
     */
    private static function fetchLatestReleaseFromPage()
    {
        $response = self::requestSafeUrl(
            self::GITHUB_RELEASES_PAGE_URL,
            array('Accept: text/html'),
            self::DEFAULT_TIMEOUT_MS,
            false
        );
        if ($response['body'] === false || $response['error'] !== '' || $response['status'] < 200 || $response['status'] >= 300) {
            throw new RuntimeException('GitHub Release 页面暂时不可用');
        }
        $tag = self::extractReleaseTag($response);
        if ($tag === '') {
            throw new RuntimeException('GitHub Release 页面未找到有效版本标签');
        }
        $version = self::normalizeVersion($tag);
        $tagPath = rawurlencode($tag);
        $assetName = 'tieba-cloud-sign-windows-' . $tag . '.zip';
        $assetUrl = 'https://github.com/yuchen0x1/baidu-tieba-yunqiandao/releases/download/' . $tagPath . '/' . rawurlencode($assetName);
        $checksumUrl = $assetUrl . '.sha256';
        return self::parseRelease(array(
            'tag_name' => $tag,
            'name' => '百度贴吧云签到 V' . $version,
            'body' => 'GitHub API 当前达到匿名请求限额，已通过官方 Release 页面发现此版本。请打开 Release 页面查看完整版本说明。',
            'html_url' => 'https://github.com/yuchen0x1/baidu-tieba-yunqiandao/releases/tag/' . $tagPath,
            'zipball_url' => 'https://api.github.com/repos/yuchen0x1/baidu-tieba-yunqiandao/zipball/' . $tagPath,
            'published_at' => '',
            'prerelease' => false,
            'assets' => array(
                array('name' => $assetName, 'browser_download_url' => $assetUrl, 'size' => 0, 'digest' => ''),
                array('name' => $assetName . '.sha256', 'browser_download_url' => $checksumUrl, 'size' => 0, 'digest' => '')
            )
        ));
    }

    private static function extractReleaseTag(array $response)
    {
        $candidates = array();
        // 重定向结束后的最终地址通常就是 /releases/tag/<tag>
        if (!empty($response['url'])) {
            $path = parse_url((string) $response['url'], PHP_URL_PATH);
            if (is_string($path)) {
                $candidates[] = $path;
            }
        }
        $body = (string) $response['body'];
        // 锚定本仓库路径，避免把其他仓库的 tag 带进候选
        if (preg_match('~yuchen0x1/baidu-tieba-yunqiandao/releases/tag/([A-Za-z0-9._+-]+)~i', $body, $match)) {
            $candidates[] = '/yuchen0x1/baidu-tieba-yunqiandao/releases/tag/' . $match[1];
        }
        $prefix = '/yuchen0x1/baidu-tieba-yunqiandao/releases/tag/';
        foreach ($candidates as $path) {
            if (strpos($path, $prefix) !== 0) {
                continue;
            }
            $tag = rawurldecode(substr($path, strlen($prefix)));
            if (preg_match('/^[vV]?\\d+(?:\\.\\d+){1,3}$/', $tag) === 1) {
                return $tag;
            }
        }
        return '';
    }

    public static function primaryChannel()
    {
        return 'github';
    }

    /**
     * 获取历史 Release 列表（用于更新页“历史更新日志”）。
     * 结果缓存到 options 表以减少对 GitHub API 的请求；检测到更新的 latest tag 时自动失效。
     * @param string|null $latestTag 当前 latest Release 的 tag（用于缓存失效判断）
     * @return array 简化后的 Release 列表，按发布时间倒序
     * @throws RuntimeException 网络或数据不可用时抛出，调用方可静默降级
     */
    public static function fetchReleaseHistory($latestTag = null)
    {
        $cached = option::get(self::HISTORY_CACHE_OPTION);
        if ($cached !== null && $cached !== '') {
            $decoded = json_decode((string) $cached, true);
            if (is_array($decoded) && isset($decoded['items']) && is_array($decoded['items']) && !empty($decoded['items'])) {
                $fresh = (int) $decoded['time'] > 0 && (time() - (int) $decoded['time']) < self::HISTORY_CACHE_TTL;
                $tagMatched = $latestTag === null || !isset($decoded['latest_tag']) || $decoded['latest_tag'] === (string) $latestTag;
                if ($fresh && $tagMatched) {
                    return $decoded['items'];
                }
            }
        }
        $items = self::requestReleaseList(self::GITHUB_RELEASES_HISTORY_API_URL);
        option::set(self::HISTORY_CACHE_OPTION, json_encode(array(
            'time' => time(),
            'latest_tag' => (string) $latestTag,
            'items' => $items
        ), JSON_UNESCAPED_UNICODE));
        return $items;
    }

    private static function requestReleaseList($apiUrl)
    {
        if (!self::isAllowedUrl($apiUrl)) {
            throw new RuntimeException('更新服务器地址不受信任');
        }
        $response = self::requestSafeUrl($apiUrl, array('Accept: application/json'), self::DEFAULT_TIMEOUT_MS, true);
        $body = $response['body'];
        $error = $response['error'];
        $status = $response['status'];
        if ($body === false || $error !== '' || $status < 200 || $status >= 300) {
            if (self::isRateLimitedResponse($response, true)) {
                throw self::createRateLimitException($response);
            }
            throw new RuntimeException('无法获取历史 Release 列表（HTTP ' . $status . '）' . ($error !== '' ? '：' . $error : ''));
        }
        $payload = json_decode($body, true);
        if (!is_array($payload)) {
            throw new RuntimeException('历史 Release 列表数据无效');
        }
        $items = array();
        foreach ($payload as $entry) {
            if (!is_array($entry)) {
                continue;
            }
            //单条数据异常时跳过该条，不影响其余历史展示
            try {
                $release = self::parseRelease($entry);
            } catch (Throwable $e) {
                continue;
            }
            $items[] = array(
                'tag_name' => $release['tag_name'],
                'version' => $release['version'],
                'name' => $release['name'],
                //缓存与展示用截断正文，完整说明以 Release 页为准
                'body' => function_exists('mb_substr') ? mb_substr($release['body'], 0, 4000, 'UTF-8') : substr($release['body'], 0, 4000),
                'release_url' => $release['release_url'],
                'published_at' => $release['published_at'],
                'is_prerelease' => $release['is_prerelease']
            );
        }
        if (empty($items)) {
            throw new RuntimeException('历史 Release 列表为空');
        }
        // GitHub Releases API returns newest releases first.
        return $items;
    }

    public static function foreignFallbackEnabled()
    {
        return false;
    }

    /**
     * 保留旧接口名，兼容插件调用；返回固定的 GitHub 更新通道。
     */
    public static function determineChannel()
    {
        return self::primaryChannel();
    }

    private static function requestRelease($apiUrl, $channel)
    {
        if (!self::isAllowedUrl($apiUrl)) {
            throw new RuntimeException('更新服务器地址不受信任');
        }
        $response = self::requestSafeUrl($apiUrl, array('Accept: application/json'), self::DEFAULT_TIMEOUT_MS, true);
        $body = $response['body'];
        $error = $response['error'];
        $status = $response['status'];
        if ($body === false || $error !== '' || $status < 200 || $status >= 300) {
            if (self::isRateLimitedResponse($response, $channel === 'github')) {
                throw self::createRateLimitException($response);
            }
            throw new RuntimeException('无法连接 ' . self::channelLabel($channel) . ' 更新服务器' . ($error !== '' ? '：' . $error : '（HTTP ' . $status . '）'));
        }
        $payload = json_decode($body, true);
        if (!is_array($payload)) {
            throw new RuntimeException(self::channelLabel($channel) . ' 更新服务器返回的数据无效');
        }
        try {
            return self::parseRelease($payload);
        } catch (InvalidArgumentException $e) {
            throw new RuntimeException('[' . self::channelLabel($channel) . '] ' . $e->getMessage(), 0, $e);
        }
    }

    private static function channelLabel($channel)
    {
        return $channel === 'github' ? 'GitHub' : '更新';
    }

    private static function githubToken()
    {
        $token = getenv('UPDATE_GITHUB_TOKEN');
        return $token === false ? '' : trim((string) $token);
    }

    private static function isGitHubReleaseApiEndpoint($url)
    {
        $parts = parse_url((string) $url);
        if (!$parts || strtolower((string) $parts['host']) !== 'api.github.com') {
            return false;
        }
        $path = isset($parts['path']) ? (string) $parts['path'] : '';
        return in_array($path, array(
            '/repos/yuchen0x1/baidu-tieba-yunqiandao/releases',
            '/repos/yuchen0x1/baidu-tieba-yunqiandao/releases/latest'
        ), true);
    }

    /**
     * GitHub API calls are restricted to the official repository paths. Redirects
     * are followed manually so credentials are never forwarded to another host.
     */
    private static function requestSafeUrl($url, array $headers, $timeoutMs, $isApi)
    {
        $currentUrl = (string) $url;
        $token = $isApi ? self::githubToken() : '';
        $initialHost = strtolower((string) parse_url($currentUrl, PHP_URL_HOST));
        for ($redirects = 0; $redirects <= 5; $redirects++) {
            if (!self::isAllowedUrl($currentUrl) || !self::isSafeRemoteEndpoint($currentUrl)) {
                return array('body' => false, 'error' => '更新服务器地址不受信任', 'status' => 0, 'headers' => array(), 'url' => $currentUrl);
            }
            $parts = parse_url($currentUrl);
            $host = strtolower((string) $parts['host']);
            $responseHeaders = array();
            $requestHeaders = array_merge(array(
                'User-Agent: Tieba-Cloud-Sign-Updater/' . (defined('SYSTEM_VER') ? SYSTEM_VER : 'unknown')
            ), $headers);
            if ($isApi && $redirects === 0 && $host === $initialHost && $host === 'api.github.com' && self::isGitHubReleaseApiEndpoint($currentUrl) && $token !== '') {
                $requestHeaders[] = 'Authorization: Bearer ' . $token;
            }
            $curl = curl_init($currentUrl);
            if ($curl === false) {
                return array('body' => false, 'error' => '无法初始化更新连接', 'status' => 0, 'headers' => array(), 'url' => $currentUrl);
            }
            $port = isset($parts['port']) ? (int) $parts['port'] : 443;
            $ip = self::resolveSafeIp($host);
            $resolveAddress = strpos($ip, ':') !== false ? '[' . $ip . ']' : $ip;
            curl_setopt_array($curl, array(
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => false,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_SSL_VERIFYHOST => 2,
                CURLOPT_CONNECTTIMEOUT_MS => 5000,
                CURLOPT_TIMEOUT_MS => (int) $timeoutMs,
                CURLOPT_RESOLVE => array($host . ':' . $port . ':' . $resolveAddress),
                CURLOPT_HTTPHEADER => $requestHeaders,
                CURLOPT_HEADERFUNCTION => function ($handle, $line) use (&$responseHeaders) {
                    $line = trim((string) $line);
                    if ($line === '' || strpos($line, ':') === false) {
                        return strlen((string) $line) + 2;
                    }
                    list($name, $value) = explode(':', $line, 2);
                    $name = strtolower(trim($name));
                    if (in_array($name, array('retry-after', 'x-ratelimit-limit', 'x-ratelimit-remaining', 'x-ratelimit-reset', 'location'), true)) {
                        $responseHeaders[$name] = trim($value);
                    }
                    return strlen((string) $line) + 2;
                }
            ));
            $caInfo = self::findCaInfo();
            if ($caInfo !== false) {
                curl_setopt($curl, CURLOPT_CAINFO, $caInfo);
            }
            $body = curl_exec($curl);
            $error = curl_error($curl);
            $status = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
            $redirectUrl = curl_getinfo($curl, CURLINFO_REDIRECT_URL);
            curl_close($curl);
            if ((!is_string($redirectUrl) || $redirectUrl === '') && isset($responseHeaders['location'])) {
                $redirectUrl = $responseHeaders['location'];
            }
            if ($status >= 300 && $status < 400) {
                if ($redirects === 5 || !is_string($redirectUrl) || $redirectUrl === '') {
                    return array('body' => false, 'error' => '更新服务器重定向无效或次数过多', 'status' => $status, 'headers' => $responseHeaders, 'url' => $currentUrl);
                }
                $currentUrl = self::resolveRedirectUrl($currentUrl, $redirectUrl);
                if ($currentUrl === false) {
                    return array('body' => false, 'error' => '更新服务器重定向地址无效', 'status' => $status, 'headers' => $responseHeaders, 'url' => (string) $url);
                }
                continue;
            }
            return array('body' => $body, 'error' => $error, 'status' => $status, 'headers' => $responseHeaders, 'url' => $currentUrl);
        }
        return array('body' => false, 'error' => '更新服务器重定向次数过多', 'status' => 0, 'headers' => array(), 'url' => $currentUrl);
    }

    private static function resolveRedirectUrl($baseUrl, $redirectUrl)
    {
        $redirectUrl = trim((string) $redirectUrl);
        if ($redirectUrl === '') {
            return false;
        }
        if (parse_url($redirectUrl, PHP_URL_SCHEME) !== '') {
            return $redirectUrl;
        }
        $base = parse_url((string) $baseUrl);
        if (!$base || empty($base['scheme']) || empty($base['host'])) {
            return false;
        }
        if (strpos($redirectUrl, '/') === 0) {
            return $base['scheme'] . '://' . $base['host'] . (isset($base['port']) ? ':' . (int) $base['port'] : '') . $redirectUrl;
        }
        $basePath = isset($base['path']) ? (string) $base['path'] : '/';
        $directory = rtrim(str_replace('\\', '/', dirname($basePath)), '/');
        return $base['scheme'] . '://' . $base['host'] . (isset($base['port']) ? ':' . (int) $base['port'] : '') . ($directory === '' ? '/' : $directory . '/') . $redirectUrl;
    }

    private static function isRateLimitedResponse(array $response, $isGithub)
    {
        $status = isset($response['status']) ? (int) $response['status'] : 0;
        if ($status === 429) {
            return true;
        }
        if (!$isGithub || $status !== 403) {
            return false;
        }
        $headers = isset($response['headers']) && is_array($response['headers']) ? $response['headers'] : array();
        if (isset($headers['x-ratelimit-remaining']) && trim((string) $headers['x-ratelimit-remaining']) === '0') {
            return true;
        }
        $body = strtolower((string) (isset($response['body']) ? $response['body'] : ''));
        return strpos($body, 'api rate limit exceeded') !== false || strpos($body, 'rate limit exceeded') !== false;
    }

    private static function createRateLimitException(array $response)
    {
        $headers = isset($response['headers']) && is_array($response['headers']) ? $response['headers'] : array();
        $resetAt = isset($headers['x-ratelimit-reset']) && ctype_digit((string) $headers['x-ratelimit-reset']) ? (int) $headers['x-ratelimit-reset'] : 0;
        $retryAfter = 0;
        if (isset($headers['retry-after'])) {
            $retryValue = trim((string) $headers['retry-after']);
            if (ctype_digit($retryValue)) {
                $retryAfter = (int) $retryValue;
            } else {
                $retryTimestamp = strtotime($retryValue);
                if ($retryTimestamp !== false) {
                    $retryAfter = max(0, $retryTimestamp - time());
                }
            }
        }
        if ($retryAfter <= 0 && $resetAt > time()) {
            $retryAfter = $resetAt - time();
        }
        $limit = isset($headers['x-ratelimit-limit']) && ctype_digit((string) $headers['x-ratelimit-limit']) ? (int) $headers['x-ratelimit-limit'] : null;
        $remaining = isset($headers['x-ratelimit-remaining']) && ctype_digit((string) $headers['x-ratelimit-remaining']) ? (int) $headers['x-ratelimit-remaining'] : null;
        return new ProjectUpdaterRateLimitException(isset($response['status']) ? $response['status'] : 0, $retryAfter, $resetAt, $limit, $remaining);
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
        $resolveAddress = strpos($ip, ':') !== false ? '[' . $ip . ']' : $ip;
        // 将连接固定在已校验的 IP 上，防止 DNS 解析与实际连接之间被重绑定
        curl_setopt_array($curl, array(
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_CONNECTTIMEOUT_MS => self::GEO_TIMEOUT_MS,
            CURLOPT_TIMEOUT_MS => self::GEO_TIMEOUT_MS,
            CURLOPT_RESOLVE => array($host . ':' . $port . ':' . $resolveAddress),
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
        if (!$parts || empty($parts['scheme']) || empty($parts['host']) || !self::isAllowedUrl($url)) {
            return false;
        }
        return self::resolveSafeIp(strtolower((string) $parts['host'])) !== false;
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
        $ips = array_values(array_unique($ips));
        if (count($ips) === 0) {
            return false;
        }
        // Reject mixed public/private DNS answers instead of selecting a public
        // address while leaving clients vulnerable to alternate private records.
        foreach ($ips as $ip) {
            if (!self::isSafeIp($ip)) {
                return false;
            }
        }
        return $ips[0];
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
            // Prefer the signed Release asset. A source archive may be attempted for
            // diagnostics, but verifyPackageDigest always rejects it for online overwrite.
            $candidates = array();
            foreach (array($release['asset_download_url'], $release['source_download_url']) as $url) {
                if (!empty($url) && self::isAllowedUrl($url) && !in_array($url, $candidates, true)) {
                    $candidates[] = (string) $url;
                }
            }
            if (empty($candidates)) {
                throw new RuntimeException('Release 没有可信的下载地址');
            }
            $zipPath = $workDir . '/source.zip';
            $errors = array();
            $downloadedUrl = '';
            foreach ($candidates as $url) {
                $res = self::downloadToFile($url, $zipPath);
                if (!$res['ok']) {
                    $errors[] = '下载失败（HTTP ' . $res['status'] . '）';
                    continue;
                }
                if (!$res['is_zip']) {
                    // Reject non-ZIP bodies such as proxy or authentication error pages.
                    $errors[] = '返回内容不是 ZIP（' . $res['content_type'] . '，开头 ' . $res['snippet'] . '）';
                    continue;
                }
                $downloadedUrl = $url;
                break;
            }
            if ($downloadedUrl === '') {
                throw new RuntimeException('更新包下载失败，已尝试 ' . count($candidates) . ' 个地址：' . implode('；', $errors)
                    . '。请检查服务器与 GitHub 的网络连接，或到 Release 页手动下载更新包覆盖安装');
            }
            // ZIP 魔数只能证明“是个压缩包”，不能证明内容可信：安装前强制校验发布摘要
            self::verifyPackageDigest($zipPath, $release, $downloadedUrl);
            $zip = new ZipArchive();
            $opened = $zip->open($zipPath);
            if ($opened !== true) {
                throw new RuntimeException('更新包无法解析为有效 ZIP（错误码 ' . $opened . '，文件大小 ' . filesize($zipPath) . ' 字节）');
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

    /**
     * 从 GitHub Release 资产中选取 SHA-256 校验文件（.sha256）
     */
    private static function selectChecksumAsset(array $assets)
    {
        foreach ($assets as $asset) {
            $name = isset($asset['name']) ? (string) $asset['name'] : '';
            $url = isset($asset['browser_download_url']) ? trim((string) $asset['browser_download_url']) : '';
            if (strtolower(pathinfo($name, PATHINFO_EXTENSION)) === 'sha256' && self::isAllowedUrl($url)) {
                return $url;
            }
        }
        return '';
    }

    private static function isAllowedUrl($url)
    {
        $parts = parse_url((string) $url);
        if (!$parts || empty($parts['scheme']) || empty($parts['host']) || strtolower($parts['scheme']) !== 'https') {
            return false;
        }
        if (isset($parts['user']) || isset($parts['pass']) || (isset($parts['port']) && (int) $parts['port'] !== 443)) {
            return false;
        }
        $host = strtolower((string) $parts['host']);
        if (!in_array($host, self::REMOTE_HOST_WHITELIST, true)) {
            return false;
        }
        $path = isset($parts['path']) ? (string) $parts['path'] : '';
        if ($host === 'github.com') {
            $repositoryRoot = '/yuchen0x1/baidu-tieba-yunqiandao';
            return $path === $repositoryRoot || strpos($path, $repositoryRoot . '/') === 0;
        }
        if ($host === 'api.github.com') {
            $repositoryRoot = '/repos/yuchen0x1/baidu-tieba-yunqiandao';
            return $path === $repositoryRoot
                || $path === $repositoryRoot . '/releases'
                || $path === $repositoryRoot . '/releases/latest'
                || preg_match('#^' . preg_quote($repositoryRoot . '/zipball/', '#') . '[^/]+$#', $path) === 1;
        }
        return true;
    }

    /**
     * 下载到文件并做 ZIP 魔数校验，附诊断信息（HTTP 状态/Content-Type/开头字节）
     * @return array {ok, is_zip, status, content_type, snippet}
     */
    private static function downloadToFile($url, $zipPath)
    {
        $response = self::requestSafeUrl($url, array(), 120000, false);
        $body = $response['body'];
        $status = $response['status'];
        $error = $response['error'];
        $contentType = '';
        if ($body === false || $error !== '' || $status < 200 || $status >= 300) {
            return array('ok' => false, 'is_zip' => false, 'status' => $status, 'content_type' => $contentType, 'snippet' => $error);
        }
        if (file_put_contents($zipPath, $body, LOCK_EX) === false) {
            return array('ok' => false, 'is_zip' => false, 'status' => $status, 'content_type' => $contentType, 'snippet' => '写入临时文件失败');
        }
        $isZip = strlen($body) >= 2 && substr($body, 0, 2) === 'PK';
        $snippet = $isZip ? '' : strtolower(substr(preg_replace('/\s+/', ' ', strip_tags((string) $body)), 0, 80));
        return array('ok' => true, 'is_zip' => $isZip, 'status' => $status, 'content_type' => $contentType, 'snippet' => $snippet);
    }

    /**
     * 安装前校验下载包的 SHA-256：
     * 期望值优先取 Release 的 digest 字段（"sha256:<hex>"），否则读取随包发布的 .sha256 资产；
     * 拿不到可信摘要或哈希不一致时直接拒绝安装，保留原程序与数据。
     * 仅当下载的正是该摘要对应的 Release 附件时才校验（源码归档回退通道无对应摘要，一律拒绝）。
     */
    public static function verifyPackageDigest($zipPath, array $release, $downloadedUrl)
    {
        $assetUrl = isset($release['asset_download_url']) ? rtrim((string) $release['asset_download_url'], '/') : '';
        if ($assetUrl === '' || strcasecmp(rtrim((string) $downloadedUrl, '/'), $assetUrl) !== 0) {
            throw new RuntimeException('本次下载走的是源码归档回退通道，没有对应的发布摘要，已拒绝在线覆盖安装。请到 GitHub Release 页手动下载更新包');
        }
        $expected = '';
        $digest = trim((string) (isset($release['asset_digest']) ? $release['asset_digest'] : ''));
        if ($digest !== '' && preg_match('/^sha256:([0-9a-fA-F]{64})$/', $digest, $m)) {
            $expected = strtolower($m[1]);
        } else {
            $checksumUrl = isset($release['asset_checksum_url']) ? (string) $release['asset_checksum_url'] : '';
            if ($checksumUrl !== '') {
                $res = self::downloadToString($checksumUrl);
                if ($res['ok'] && preg_match('/^([0-9a-fA-F]{64})\b/', trim($res['body']), $m)) {
                    $expected = strtolower($m[1]);
                }
            }
        }
        if ($expected === '') {
            throw new RuntimeException('Release 未提供可信的 SHA-256 摘要（digest 字段与 .sha256 附件均缺失或不可用），已拒绝在线覆盖安装。请到 Release 页手动下载更新包');
        }
        $actual = hash_file('sha256', $zipPath);
        if (!is_string($actual) || !hash_equals($expected, $actual)) {
            throw new RuntimeException('更新包 SHA-256 校验失败（期望 ' . $expected . '，实际 ' . ($actual === false ? '无法计算' : $actual) . '），已拒绝安装，原程序未被修改');
        }
    }

    private static function downloadToString($url)
    {
        $response = self::requestSafeUrl($url, array(), self::DEFAULT_TIMEOUT_MS, false);
        $ok = $response['body'] !== false && $response['error'] === '' && $response['status'] >= 200 && $response['status'] < 300;
        return array('ok' => $ok, 'body' => $ok ? (string) $response['body'] : '');
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
