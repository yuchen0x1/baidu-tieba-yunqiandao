<?php

/**
 * Kenvix cURL类
 * @version 4.0 @ 2017-05-12
 * @copyright (c) Kenvix
 * @see https://kenvix.com
 * Class wcurl
 */
class wcurl
{
    /**
     * curl句柄
     * @var resource
     */
    public $conn;

    /**
     * Last validated public target and its pinned transport address.
     * @var array|false
     */
    protected $publicUrlTarget = false;

    /**
     * 构造函数，返回curl指针实例
     * @param string $file 网络文件 如果要使用POST提交文件，在文件路径前面加上@
     * @param array $head 可选，HTTP头
     * @throws Exception
     */
    public function __construct($file = '', array $head = array())
    {
        if (!function_exists('curl_exec')) {
            throw new Exception('服务器不支持cURL', -1);
        }
        $this->conn = curl_init();
        if ($this->conn === false) {
            throw new Exception('初始化cURL失败', -1);
        }
        $this->init($file, $head);
    }

    /**
     * 当wcurl类被当成字符串时的操作:执行curl并返回结果
     * @return string 返回值
     */
    public function __toString()
    {

        return $this->exec();
    }

    /**
     * 设置自定义的 Method 来代替"GET"或"HEAD"
     * @param $method
     * @return $this
     */
    public function setRequestMethod($method)
    {

        $this->set(CURLOPT_CUSTOMREQUEST, $method);
        return $this;
    }

    /**
     * 设置一个cURL传输选项
     * @param string $option 需要设置的选项
     * @param string $value  将设置在option选项上的值
     * @return $this
     */
    public function set($option, $value)
    {

        curl_setopt($this->conn, $option, $value);
        return $this;
    }

    /**
     * 通过数组批量设置cURL传输选项
     * @param array $option 需要设置的选项
     * @return $this
     */
    public function setAll($option)
    {

        curl_setopt_array($this->conn, $option);
        return $this;
    }

    /**
     * 执行curl并返回结果
     * @return string 返回值
     */
    public function exec()
    {

        return curl_exec($this->conn);
    }

    /**
     * 获取文件内容
     * @return string 获取的内容
     */
    public function get()
    {

        return $this->exec();
    }

    /**
     * POST 提交数据并获取返回获取的内容
     * @param $data array|string 提交的数据
     * @return string 获取的内容
     */
    public function post($data)
    {

        $this->set(CURLOPT_POST, 1);
        $this->set(CURLOPT_POSTFIELDS, self::buildFields($data));
        return $this->exec();
    }

    /**
     * PUT 并获取返回获取的内容
     * @param $data array|string 可选。提交的数据
     * @return string 获取的内容
     */
    public function put($data = null)
    {

        $this->set(CURLOPT_CUSTOMREQUEST, 'PUT');
        if (!is_null($data)) {
            $this->set(CURLOPT_POSTFIELDS, self::buildFields($data));
        }
        return $this->exec();
    }

    /**
     * DELETE 并获取返回获取的内容
     * @param $data array|string 可选。提交的数据
     * @return string 获取的内容
     */
    public function delete($data = null)
    {

        $this->set(CURLOPT_CUSTOMREQUEST, 'DELETE');
        if (!is_null($data)) {
            $this->set(CURLOPT_POSTFIELDS, self::buildFields($data));
        }
        return $this->exec();
    }

    /**
     * 添加一些Cookies，在访问的时候会携带它们
     * @param string|array $ck Cookies，数组或cookies字符串
     * @return $this
     */
    public function addCookie($ck)
    {

        if (is_array($ck)) {
            $r = '';
            foreach ($ck as $key => $value) {
                $r .= "{$key}={$value}; ";
            }
        } else {
            $r = $ck;
        }
        $this->set(CURLOPT_COOKIE, $r);
        return $this;
    }

    /**
     * 静态，获取网页返回的所有Cookies [从已经获取到的网页搜索] [不写文件]
     * ps: 搜索的网页需要打开CURLOPT_HEADER
     * @param string $text 网页内容
     * @return array Cookies
     */
    public static function readCookies($text)
    {

        $r = array();
        $sz = 0;
        preg_match_all("/set\-cookie:([^\r\n]*)/i", $text, $m1, PREG_SET_ORDER);
        while (!empty($m1[$sz][1])) {
            preg_match_all("/(.*?)=(.*?);/", $m1[$sz][1], $m2, PREG_SET_ORDER);
            foreach ($m2 as $value) {
                $r1 = trim($value[1]);
                $r[$r1] = trim($value[2]);
            }
            $sz++;
        }
        return $r;
    }

    /**
     * GET/POST获取网页返回的所有Cookies [自行抓取网页] [不写文件]
     * ps: 将会自动打开CURLOPT_HEADER
     * @param string|bool $postdata 是否POST提交数据，留空或false表示GET获取，若需要提交数据则传入数组
     * @return array Cookies
     */
    public function getCookies($postdata = false)
    {

        $this->set(CURLOPT_HEADER, 1);
        if ($postdata != false) {
            return self::readCookies($this->post($postdata));
        } else {
            return self::readCookies($this->exec());
        }
    }

    /**
     * 获取一个cURL连接资源句柄的信息
     * @param string $opt 要获取的信息，参见 http://cn2.php.net/manual/zh/function.curl-getinfo.php
     * @return string 信息
     */
    public function getInfo($opt)
    {

        return curl_getinfo($this->conn, $opt);
    }

    /**
     * 返回错误代码
     * @return string 错误代码
     */
    public function errno()
    {

        return curl_errno($this->conn);
    }

    /**
     * 返回错误信息
     * @return string 错误信息
     */
    public function error()
    {

        return curl_error($this->conn);
    }

    /**
     * 返回一个带错误代码的curl错误信息
     * @return string 错误信息
     */
    public function errMsg()
    {

        return '#' . $this->errno() . ' - ' . $this->error();
    }

    /**
     * 运行一个curl函数
     * @param string $func 函数名称，不需要带curl_
     * @param ... 其他传给此函数的参数
     * @return string 此函数的返回值
     */

    public function run($func)
    {

        $args = array_slice(func_get_args(), 1);
        return call_user_func_array('curl_' . $func, $args);
    }

    /**
     * 关闭并释放cURL资源
     */
    public function close()
    {
        // DEPRECATED since PHP 8.5.0
        if (PHP_VERSION_ID <= 80000) {
            @curl_close($this->conn);
        }
    }

    /**
     * 静态 HTTP CURL GET 快速用法
     * @param string $url 要抓取的URL
     * @return string 抓取结果
     */
    public static function xget($url)
    {

        $fuckoldphp = new self($url);
        return $fuckoldphp->exec();
    }

    /**
     * 设置代理
     * @param array $proxy 代理配置 array('type' => 'http|socks5', 'host' => 'x.x.x.x', 'port' => 8080, 'user' => '', 'pass' => '')
     * @return $this
     */
    public function setProxy(array $proxy)
    {
        if (empty($proxy['host']) || empty($proxy['port'])) {
            return $this;
        }
        $host = (string) $proxy['host'];
        $endpoint = strpos($host, ':') !== false ? '[' . $host . ']:' . $proxy['port'] : $host . ':' . $proxy['port'];
        $this->set(CURLOPT_PROXY, $endpoint);
        $type = isset($proxy['type']) && strtolower((string) $proxy['type']) === 'socks5' ? 'socks5' : 'http';
        if ($type === 'socks5' && defined('CURLPROXY_SOCKS5_HOSTNAME')) {
            // 远程DNS解析，避免本地DNS污染
            $this->set(CURLOPT_PROXYTYPE, CURLPROXY_SOCKS5_HOSTNAME);
        }
        if (isset($proxy['user']) && $proxy['user'] !== '') {
            $this->set(CURLOPT_PROXYUSERPWD, $proxy['user'] . ':' . (isset($proxy['pass']) ? $proxy['pass'] : ''));
        }
        return $this;
    }

    /**
     * 设置超时时间 单位:毫秒
     * @param int $time 超时时间
     * @return $this
     */
    public function setTimeOut($time)
    {

        $this->set(CURLOPT_NOSIGNAL, 1);
// 参见：http://www.laruence.com/2014/01/21/2939.html
        $this->set(CURLOPT_TIMEOUT_MS, $time);
        return $this;
    }

    /**
     * 设置全部的HTTP Header
     * @param array $head
     * @return $this
     */
    public function setHeader(array $head)
    {

        $this->set(CURLOPT_HTTPHEADER, $head);
        return $this;
    }

    /**
     * 设置URL
     * @param string $url 网络文件 如果要使用POST提交文件，在文件路径前面加上@
     * @return $this
     */
    public function setUrl($url)
    {

        $this->set(CURLOPT_URL, $url);
        return $this;
    }

    /**
     * 设置Referrer(Referer)
     * @param $ref
     * @return $this
     */
    public function setReferrer($ref)
    {

        $this->set(CURLOPT_REFERER, $ref);
        return $this;
    }

    /**
     * 是否允许自动跟随
     * @param bool $v
     * @return $this
     */
    public function allowAutoReferrer($v)
    {

        $this->set(CURLOPT_AUTOREFERER, $v);
        return $this;
    }

    /**
     * 重置会话句柄的所有的选项到LIBCURL默认值。要初始化到WCURL默认值，使用init()方法
     * @return $this
     */
    public function reset()
    {

        if (function_exists('curl_reset')) {
            curl_reset($this->conn);
        } else {
            if (is_resource($this->conn)) {
                if (PHP_VERSION_ID <= 80000) {
                    curl_close($this->conn);
                }
            }
            $this->conn = curl_init();
        }
        return $this;
    }

    /**
     * 初始化wcurl或重置会话句柄的所有的选项到wcurl默认值
     * @param string $file
     * @param array  $head
     * @return $this
     */
    public function init($file = '', array $head = array('User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/96.0.4664.45 Safari/537.36'))
    {

        $this->reset();
        if (!empty($file)) {
            $this->setUrl($file);
        }
        $this->setHeader($head)->setAll(array(//wcurl默认设定
            CURLOPT_RETURNTRANSFER  => true, //将curl获取的信息以文件流的形式返回，而不是直接输出
            CURLOPT_SSL_VERIFYPEER  => true, //校验服务端 TLS 证书
            CURLOPT_SSL_VERIFYHOST  => 2, //校验主机名
            CURLOPT_FOLLOWLOCATION  => true //跟随重定向 会将服务器服务器返回的"Location: "放在header中递归的返回给服务器
        ));
        $caInfo = self::findCaInfo();
        if ($caInfo !== false) {
            $this->set(CURLOPT_CAINFO, $caInfo);
        }
        return $this;
    }

    /**
     * 探测可用的 CA 证书包（Windows PHP 常缺省无 CA 配置，TLS 校验会全部失败）
     * @return string|false 证书包路径，未找到返回 false
     */
    public static function findCaInfo()
    {
        foreach (array(
            getenv('SSL_CERT_FILE'),
            getenv('CURL_CA_BUNDLE'),
            ini_get('curl.cainfo'),
            ini_get('openssl.cafile'),
            'C:/Program Files/Git/usr/ssl/certs/ca-bundle.crt',
            'C:/Program Files/Git/mingw64/etc/ssl/certs/ca-bundle.crt',
            '/etc/ssl/certs/ca-certificates.crt',
            '/etc/pki/tls/certs/ca-bundle.crt',
            '/usr/local/share/certs/ca-root-nss.crt',
        ) as $candidate) {
            if ($candidate !== false && $candidate !== '' && is_file($candidate) && is_readable($candidate)) {
                return $candidate;
            }
        }
        return false;
    }

    /**
     * Check that an address is a globally routable IPv4 or IPv6 address.
     * @param string $ip
     * @return bool
     */
    public static function isPublicIpAddress($ip)
    {
        $ip = trim((string) $ip);
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
            return false;
        }
        if (!function_exists('inet_pton')) {
            return false;
        }
        $packed = @inet_pton($ip);
        if ($packed === false) {
            return false;
        }

        if (strlen($packed) === 4) {
            // FILTER_FLAG_NO_RES_RANGE does not cover every IANA special-purpose
            // IPv4 range (for example documentation, benchmarking and multicast).
            foreach (array(
                '100.64.0.0/10',   // shared address space
                '192.0.0.0/24',    // IETF protocol assignments
                '192.0.2.0/24',    // documentation (TEST-NET-1)
                '192.88.99.0/24',  // deprecated 6to4 relay anycast
                '198.18.0.0/15',   // benchmarking
                '198.51.100.0/24', // documentation (TEST-NET-2)
                '203.0.113.0/24',  // documentation (TEST-NET-3)
                '224.0.0.0/4',     // multicast
            ) as $range) {
                if (self::ipMatchesCidr($packed, $range)) {
                    return false;
                }
            }
            return true;
        }

        if (strlen($packed) !== 16) {
            return false;
        }
        // IPv6 global unicast is within 2000::/3. This also excludes NAT64,
        // link-local, site-local, unique-local, multicast and other non-global ranges.
        if (!self::ipMatchesCidr($packed, '2000::/3')) {
            return false;
        }
        foreach (array(
            '2001::/32',       // Teredo
            '2001:2::/48',     // benchmarking
            '2001:10::/28',    // deprecated ORCHID
            '2001:20::/28',    // ORCHIDv2
            '2001:db8::/32',   // documentation
            '2002::/16',       // 6to4 (can encapsulate a private IPv4 address)
            '3fff::/20',       // documentation
        ) as $range) {
            if (self::ipMatchesCidr($packed, $range)) {
                return false;
            }
        }
        return true;
    }

    /**
     * Match a packed IPv4/IPv6 address against a CIDR range.
     * @param string $packed
     * @param string $cidr
     * @return bool
     */
    private static function ipMatchesCidr($packed, $cidr)
    {
        $parts = explode('/', $cidr, 2);
        $network = @inet_pton($parts[0]);
        if ($network === false || strlen($network) !== strlen($packed)) {
            return false;
        }
        $bits = isset($parts[1]) ? (int) $parts[1] : strlen($packed) * 8;
        $maxBits = strlen($packed) * 8;
        if ($bits < 0 || $bits > $maxBits) {
            return false;
        }
        $wholeBytes = (int) floor($bits / 8);
        if ($wholeBytes > 0 && substr($packed, 0, $wholeBytes) !== substr($network, 0, $wholeBytes)) {
            return false;
        }
        $remainingBits = $bits % 8;
        if ($remainingBits === 0) {
            return true;
        }
        $mask = (0xff << (8 - $remainingBits)) & 0xff;
        return (ord($packed[$wholeBytes]) & $mask) === (ord($network[$wholeBytes]) & $mask);
    }

    /**
     * Return true only when every resolved address is a valid public IP.
     * @param array $addresses
     * @return bool
     */
    public static function arePublicIpAddresses(array $addresses)
    {
        if (count($addresses) === 0) {
            return false;
        }
        foreach ($addresses as $address) {
            if (!self::isPublicIpAddress($address)) {
                return false;
            }
        }
        return true;
    }

    /**
     * Fetch one DNS record family. Kept separate so focused tests can inject
     * deterministic A/AAAA answers without making network requests.
     *
     * @param string $host
     * @param int $type
     * @return array|false
     */
    protected static function resolveDnsRecords($host, $type)
    {
        if (!function_exists('dns_get_record')) {
            return false;
        }
        try {
            return @dns_get_record($host, $type);
        } catch (Throwable $e) {
            return false;
        }
    }

    /**
     * Resolve and validate every A/AAAA answer for a host.
     *
     * @param string $host
     * @return array|false List of validated addresses, or false on failure
     */
    protected static function resolveHostAddresses($host)
    {
        if (!defined('DNS_A') || !defined('DNS_AAAA')) {
            return false;
        }

        // Query A and AAAA separately. A combined query is not supported
        // consistently across PHP platforms and may silently omit one family.
        $addresses = array();
        $invalid = false;
        foreach (array(DNS_A, DNS_AAAA) as $type) {
            $records = static::resolveDnsRecords($host, $type);
            if (!is_array($records)) {
                return false;
            }
            foreach ($records as $record) {
                if (!is_array($record)) {
                    $invalid = true;
                    continue;
                }
                if ($type === DNS_A && isset($record['type']) && $record['type'] === 'A') {
                    if (!isset($record['ip']) || !self::isPublicIpAddress($record['ip'])) {
                        $invalid = true;
                        continue;
                    }
                    $addresses[] = $record['ip'];
                } elseif ($type === DNS_AAAA && isset($record['type']) && $record['type'] === 'AAAA') {
                    if (!isset($record['ipv6']) || !self::isPublicIpAddress($record['ipv6'])) {
                        $invalid = true;
                        continue;
                    }
                    $addresses[] = $record['ipv6'];
                }
            }
        }

        if ($invalid || !self::arePublicIpAddresses($addresses)) {
            return false;
        }
        return $addresses;
    }

    /**
     * Parse and resolve a notification target; returns the parsed URL and one
     * validated address to pin to cURL, or false on any invalid/unsafe answer.
     * @param string $url
     * @return array|false
     */
    protected static function resolvePublicUrl($url)
    {
        $url = (string) $url;
        if ($url === '' || strpos($url, '\\') !== false || preg_match('/[\x00-\x20\x7f]/', $url)) {
            return false;
        }
        try {
            $parts = parse_url($url);
        } catch (Throwable $e) {
            return false;
        }
        if (!is_array($parts) || empty($parts['scheme']) || empty($parts['host']) || isset($parts['user']) || isset($parts['pass'])) {
            return false;
        }
        $scheme = strtolower((string) $parts['scheme']);
        if (!in_array($scheme, array('http', 'https'), true)) {
            return false;
        }
        $port = isset($parts['port']) ? (int) $parts['port'] : ($scheme === 'https' ? 443 : 80);
        if ($port < 1 || $port > 65535) {
            return false;
        }

        $host = strtolower(trim((string) $parts['host']));
        if (strlen($host) > 1 && $host[0] === '[' && substr($host, -1) === ']') {
            $host = substr($host, 1, -1);
        }
        if ($host === '') {
            return false;
        }

        if (filter_var($host, FILTER_VALIDATE_IP)) {
            if (!self::isPublicIpAddress($host)) {
                return false;
            }
            return array('host' => $host, 'port' => $port, 'ip' => $host, 'is_ip' => true);
        }

        $dnsHost = rtrim($host, '.');
        if ($dnsHost === '' || $dnsHost === 'localhost' || substr($dnsHost, -6) === '.local' || substr($dnsHost, -9) === '.internal' || substr($dnsHost, -8) === '.cluster') {
            return false;
        }
        // Reject malformed names and legacy numeric IPv4 spellings (e.g. 127.1 or 0x7f000001).
        if (strlen($dnsHost) > 253 || preg_match('/^(?:[0-9]+|0x[0-9a-f]+)(?:\.(?:[0-9]+|0x[0-9a-f]+))*$/i', $dnsHost) ||
            preg_match('/^(?=.{1,253}$)[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?(?:\.[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?)*$/i', $dnsHost) !== 1) {
            return false;
        }
        $addresses = static::resolveHostAddresses($dnsHost);
        if ($addresses === false || count($addresses) === 0) {
            return false;
        }
        return array('host' => $host, 'port' => $port, 'ip' => $addresses[0], 'is_ip' => false);
    }

    /**
     * Validate a user-configurable target and pin cURL to a validated DNS answer.
     * Redirects and environment-configured proxies are disabled so transport cannot
     * escape the validated address. Redirect responses are returned to the caller.
     * @param string $url
     * @return bool
     */
    public function setPublicUrl($url)
    {
        $target = static::resolvePublicUrl($url);
        if ($target === false) {
            return false;
        }

        if (!$target['is_ip'] && !defined('CURLOPT_RESOLVE')) {
            return false;
        }
        if (!curl_setopt($this->conn, CURLOPT_URL, $url) ||
            !curl_setopt($this->conn, CURLOPT_FOLLOWLOCATION, false)) {
            return false;
        }
        if (defined('CURLOPT_PROXY') && !curl_setopt($this->conn, CURLOPT_PROXY, '')) {
            return false;
        }
        if (!$target['is_ip']) {
            // CURLOPT_RESOLVE keeps the original hostname for TLS/SNI and Host,
            // while forcing the connection to the address that was validated.
            $address = strpos($target['ip'], ':') !== false ? '[' . $target['ip'] . ']' : $target['ip'];
            $resolve = $target['host'] . ':' . $target['port'] . ':' . $address;
            if (!curl_setopt($this->conn, CURLOPT_RESOLVE, array($resolve))) {
                return false;
            }
        }
        $this->publicUrlTarget = array(
            'url' => $url,
            'host' => $target['host'],
            'port' => $target['port'],
            'ip' => $target['ip'],
            'is_ip' => $target['is_ip'],
            'follow_redirects' => false,
        );
        return true;
    }

    /**
     * Validate that an HTTP(S) URL resolves only to public IPv4/IPv6 addresses.
     * @param string $url
     * @return bool
     */
    public static function isAllowedPublicUrl($url)
    {
        return static::resolvePublicUrl($url) !== false;
    }

    /**
     * 构建用于CURL的POST表单
     * @param string|array $data
     * @return string
     */
    public static function buildFields($data)
    {

        if (is_array($data)) {
            return http_build_query($data);
        } else {
            return $data;
        }
    }

    /**
     * 获取CURL连接
     * @return resource
     */
    public function getConnection()
    {

        return $this->conn;
    }

    /**
     * 销毁类的时候自动释放cURL资源
     */
    public function __destruct()
    {

        $this->close();
    }
}
