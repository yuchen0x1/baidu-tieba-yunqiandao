<?php

/**
 * Release 在线更新仅支持 Gitee 国内通道。
 * 版本检查只读取正式 latest Release；真正更新只由管理员主动触发。
 */
class ProjectUpdater
{
    const DEFAULT_TIMEOUT_MS = 8000;
    const GITEE_RELEASES_API_URL = 'https://gitee.com/api/v5/repos/yuchen0x1/baidu-tieba-yunqiandao/releases/latest';
    const GITEE_RELEASES_HISTORY_API_URL = 'https://gitee.com/api/v5/repos/yuchen0x1/baidu-tieba-yunqiandao/releases?per_page=10&page=1';
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
            // 仅允许显式的 Gitee 自定义地址，默认地址仍使用国内正式 Release。
            if ($configured !== '' && $configured !== self::GITEE_RELEASES_API_URL) {
                $overrideUrl = $configured;
            }
        }
        if ($overrideUrl !== null) {
            return self::requestRelease($overrideUrl, 'custom');
        }
        return self::requestRelease(self::GITEE_RELEASES_API_URL, 'gitee');
    }

    public static function primaryChannel()
    {
        return 'gitee';
    }

    /**
     * 获取历史 Release 列表（用于更新页“历史更新日志”）。
     * 结果缓存到 options 表以减少对 Gitee API 的请求；检测到更新的 latest tag 时自动失效。
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
        $items = self::requestReleaseList(self::GITEE_RELEASES_HISTORY_API_URL);
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
        $curl = self::createReleaseCurl($apiUrl, true);
        if ($curl === false) {
            throw new RuntimeException('无法初始化更新连接');
        }
        $body = curl_exec($curl);
        $error = curl_error($curl);
        $status = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);
        //与 requestRelease 相同的降级策略：凭据被拒时匿名重试
        if (in_array($status, array(401, 403, 404), true) && self::hasCredentialForUrl($apiUrl)) {
            $curl = self::createReleaseCurl($apiUrl, false);
            if ($curl !== false) {
                $body = curl_exec($curl);
                $error = curl_error($curl);
                $status = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
                curl_close($curl);
            }
        }
        if ($body === false || $error !== '' || $status < 200 || $status >= 300) {
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
                $release = self::parseRelease(self::normalizeGiteePayload($entry, $apiUrl));
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
        //Gitee 列表端点按最旧在前返回，统一反转为最新在前
        return array_reverse($items);
    }

    public static function foreignFallbackEnabled()
    {
        return false;
    }

    /**
     * 保留旧接口名，兼容插件调用；返回固定的国内更新通道。
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
        $curl = self::createReleaseCurl($apiUrl, true);
        if ($curl === false) {
            throw new RuntimeException('无法初始化更新连接');
        }
        $body = curl_exec($curl);
        $error = curl_error($curl);
        $status = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);
        if (in_array($status, array(401, 403, 404), true) && self::hasCredentialForUrl($apiUrl)) {
            $curl = self::createReleaseCurl($apiUrl, false);
            if ($curl !== false) {
                $body = curl_exec($curl);
                $error = curl_error($curl);
                $status = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
                curl_close($curl);
            }
        }
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
        return '自定义';
    }

    private static function createReleaseCurl($apiUrl, $includeAuth = true)
    {
        if (!function_exists('curl_init')) {
            return false;
        }
        $curl = curl_init($apiUrl);
        if ($curl === false) {
            return false;
        }
        $headers = array(
            'Accept: application/json',
            'User-Agent: Tieba-Cloud-Sign-Updater/' . (defined('SYSTEM_VER') ? SYSTEM_VER : 'unknown')
        );
        $host = strtolower((string) parse_url((string) $apiUrl, PHP_URL_HOST));
        // 更新凭据只会发送给国内 Gitee API。
        $giteeToken = getenv('UPDATE_GITEE_TOKEN');
        if ($includeAuth && $giteeToken !== false && trim((string) $giteeToken) !== '' && $host === 'gitee.com') {
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

    private static function hasCredentialForUrl($url)
    {
        $host = strtolower((string) parse_url((string) $url, PHP_URL_HOST));
        if ($host === 'gitee.com') {
            return trim((string) getenv('UPDATE_GITEE_TOKEN')) !== '';
        }
        return false;
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
            //多通道回退：Release 附件（预上传固定文件，通常不走归档风控）→ 源码归档 → Gitee 自动源码包
            //（部分服务器出口 IP 会被 Gitee 风控拦截匿名归档下载，返回 HTML 页而非 ZIP）
            $candidates = array();
            foreach (array($release['asset_download_url'], $release['source_download_url']) as $url) {
                if (!empty($url) && self::isAllowedUrl($url) && !in_array($url, $candidates, true)) {
                    $candidates[] = (string) $url;
                }
            }
            if (preg_match('#^(https://gitee\.com/[^/]+/[^/]+)/#', $release['release_url'], $m) && !empty($release['tag_name'])) {
                $candidates[] = $m[1] . '/archive/refs/tags/' . rawurlencode((string) $release['tag_name']) . '.zip';
            }
            if (empty($candidates)) {
                throw new RuntimeException('Release 没有可信的下载地址');
            }
            $zipPath = $workDir . '/source.zip';
            $errors = array();
            $downloaded = false;
            foreach ($candidates as $url) {
                $res = self::downloadToFile($url, $zipPath);
                if (!$res['ok']) {
                    $errors[] = '下载失败（HTTP ' . $res['status'] . '）';
                    continue;
                }
                if (!$res['is_zip']) {
                    //典型场景：Gitee 风控/登录页 HTML。给出类型与片段便于定位
                    $errors[] = '返回内容不是 ZIP（' . $res['content_type'] . '，开头 ' . $res['snippet'] . '）';
                    continue;
                }
                $downloaded = true;
                break;
            }
            if (!$downloaded) {
                throw new RuntimeException('更新包下载失败，已尝试 ' . count($candidates) . ' 个地址：' . implode('；', $errors)
                    . '。部分数据中心 IP 会被 Gitee 匿名风控拦截：可在服务器环境变量配置 UPDATE_GITEE_TOKEN（站长自己的 Gitee 个人令牌，仅发送至 gitee.com）后重试，或到 Release 页手动下载更新包覆盖安装');
            }
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

    private static function isAllowedUrl($url)
    {
        $parts = parse_url((string) $url);
        if (!$parts || empty($parts['scheme']) || empty($parts['host']) || strtolower($parts['scheme']) !== 'https') {
            return false;
        }
        return strtolower($parts['host']) === 'gitee.com';
    }

    /**
     * 下载到文件并做 ZIP 魔数校验，附诊断信息（HTTP 状态/Content-Type/开头字节）
     * @return array {ok, is_zip, status, content_type, snippet}
     */
    private static function downloadToFile($url, $zipPath)
    {
        $curl = curl_init($url);
        if ($curl === false) {
            return array('ok' => false, 'is_zip' => false, 'status' => 0, 'content_type' => '', 'snippet' => 'curl 初始化失败');
        }
        $headers = array('User-Agent: Tieba-Cloud-Sign-Updater/' . (defined('SYSTEM_VER') ? SYSTEM_VER : 'unknown'));
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        // 国内 Gitee 私有 Release 的源码包使用独立只读令牌。
        $giteeToken = getenv('UPDATE_GITEE_TOKEN');
        if ($giteeToken !== false && trim((string) $giteeToken) !== '' && $host === 'gitee.com') {
            $headers[] = 'Authorization: Bearer ' . trim((string) $giteeToken);
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
        $contentType = (string) curl_getinfo($curl, CURLINFO_CONTENT_TYPE);
        $error = curl_error($curl);
        curl_close($curl);
        if ($body === false || $error !== '' || $status < 200 || $status >= 300) {
            return array('ok' => false, 'is_zip' => false, 'status' => $status, 'content_type' => $contentType, 'snippet' => $error);
        }
        if (file_put_contents($zipPath, $body, LOCK_EX) === false) {
            return array('ok' => false, 'is_zip' => false, 'status' => $status, 'content_type' => $contentType, 'snippet' => '写入临时文件失败');
        }
        //ZIP 魔数校验：Gitee 对部分出口 IP 会返回风控/登录 HTML 页
        $isZip = strlen($body) >= 2 && substr($body, 0, 2) === 'PK';
        $snippet = $isZip ? '' : strtolower(substr(preg_replace('/\s+/', ' ', strip_tags((string) $body)), 0, 80));
        return array('ok' => true, 'is_zip' => $isZip, 'status' => $status, 'content_type' => $contentType, 'snippet' => $snippet);
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
