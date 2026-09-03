<?php

if (!defined('SYSTEM_ROOT')) {
    die('Insufficient Permissions');
}
/**
 * AI 大模型客户端（OpenAI 兼容 /chat/completions 协议）
 * 供 ver4_ai 设置页与计划任务共用；接口地址在请求前均做公网校验（协议/主机/DNS 解析目标），杜绝 SSRF。
 */

function ver4_ai_cfg($key, $default = '')
{
    $v = trim((string) option::get($key));
    return $v === '' ? $default : $v;
}

/** API Key 以 AES-256-GCM 密文存储，读取时解密 */
function ver4_ai_api_key()
{
    $enc = trim((string) option::get('ver4_ai_api_key'));
    if ($enc === '') {
        return '';
    }
    $plain = P::decrypt($enc);
    return $plain === '' ? $enc : $plain;
}

function ver4_ai_endpoint()
{
    $base = rtrim(ver4_ai_cfg('ver4_ai_api_url', 'https://open.bigmodel.cn/api/coding/paas/v4'), '/');
    return $base . '/chat/completions';
}

/**
 * 校验请求目标：仅允许 http/https 公网地址，且 DNS 解析结果不得落在私有/保留网段
 * @return bool
 */
function ver4_ai_url_allowed($url)
{
    if (!wcurl::isAllowedPublicUrl($url)) {
        return false;
    }
    $host = (string) parse_url($url, PHP_URL_HOST);
    if ($host === '' || filter_var($host, FILTER_VALIDATE_IP)) {
        return true; // isAllowedPublicUrl 已校验过 IP 字面量
    }
    $records = @dns_get_record($host, DNS_A | DNS_AAAA);
    if (!is_array($records) || count($records) === 0) {
        //解析失败时退回 gethostbyname 单结果校验
        $ip = @gethostbyname($host);
        if ($ip === $host) {
            return false;
        }
        return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false;
    }
    foreach ($records as $rec) {
        $ip = isset($rec['ip']) ? $rec['ip'] : (isset($rec['ipv6']) ? $rec['ipv6'] : '');
        if ($ip === '' || filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
            return false;
        }
    }
    return true;
}

/**
 * 调用大模型对话接口
 * @param array $messages OpenAI 消息数组
 * @param string|null $err 失败时写入错误描述
 * @return string|false 成功返回首条回复文本，失败返回 false
 */
function ver4_ai_chat(array $messages, &$err = null)
{
    $url = ver4_ai_endpoint();
    if (!ver4_ai_url_allowed($url)) {
        $err = '接口地址不可用（仅允许公网 http/https 地址）';
        return false;
    }
    $apiKey = ver4_ai_api_key();
    if ($apiKey === '') {
        $err = '尚未配置 API Key';
        return false;
    }
    $payload = json_encode(array(
        'model' => ver4_ai_cfg('ver4_ai_model', 'glm-5.3-flash'),
        'messages' => $messages,
        'temperature' => min(2, max(0, (float) ver4_ai_cfg('ver4_ai_temperature', '0.9'))),
        'max_tokens' => min(8000, max(256, (int) ver4_ai_cfg('ver4_ai_max_tokens', '1500'))),
        'stream' => false,
    ), JSON_UNESCAPED_UNICODE);
    if ($payload === false || $payload === '') {
        //输入含非法编码字节时 json_encode 会失败，避免发出空请求体
        $err = '生成内容包含非法编码，已拒绝请求';
        return false;
    }
    $ch = new wcurl($url, array(
        'Content-Type: application/json',
        'Authorization: Bearer ' . $apiKey,
    ));
    $ch->setTimeOut(90000); //参数单位为毫秒（CURLOPT_TIMEOUT_MS）
    $resp = $ch->post($payload);
    $body = json_decode((string) $resp, true);
    if (!is_array($body)) {
        $err = '接口返回无法解析：' . mb_substr((string) $resp, 0, 120);
        return false;
    }
    if (isset($body['error'])) {
        $errMsg = is_array($body['error']) ? (isset($body['error']['message']) ? (string) $body['error']['message'] : json_encode($body['error'], JSON_UNESCAPED_UNICODE)) : (string) $body['error'];
        $err = '接口报错：' . mb_substr($errMsg, 0, 160);
        return false;
    }
    $text = '';
    if (isset($body['choices'][0]['message']['content']) && is_string($body['choices'][0]['message']['content'])) {
        $text = trim($body['choices'][0]['message']['content']);
    }
    if ($text === '') {
        $err = '接口未返回内容';
        return false;
    }
    return $text;
}

/** 通用提取：去掉模型可能附带的引号/前后空白 */
function ver4_ai_clean_text($text)
{
    $text = trim((string) $text);
    $text = preg_replace('/^[“"\'\s]+|[“"\'\s]+$/u', '', $text);
    return trim((string) $text);
}

/**
 * 生成一条回帖内容
 * @return string|false
 */
function ver4_ai_gen_reply($kw, $title, $content, &$err = null)
{
    $persona = ver4_ai_cfg('ver4_ai_persona', '');
    $sys = $persona !== '' ? $persona . "\n你现在在百度贴吧「" . $kw . "吧」回帖。" : '你在百度贴吧「' . $kw . '吧」回帖，像一个真实的贴吧用户那样说话。';
    $user = '帖子标题：' . $title . "\n";
    $content = trim((string) $content);
    if ($content !== '') {
        $user .= '楼主内容：' . mb_substr($content, 0, 300) . "\n";
    }
    $user .= "\n请根据以上帖子写一条回帖：15到60个字，口语化、自然、符合上下文，可以直接输出回帖内容本身，不要任何前缀或解释。";
    return ver4_ai_chat(array(
        array('role' => 'system', 'content' => $sys),
        array('role' => 'user', 'content' => $user),
    ), $err);
}

/**
 * 生成一个主题帖（标题 + 正文）
 * @return array|false array('title' =>, 'content' =>)
 */
function ver4_ai_gen_thread($kw, &$err = null)
{
    $persona = ver4_ai_cfg('ver4_ai_persona', '');
    $topic = ver4_ai_cfg('ver4_ai_thread_topic', '');
    $sys = $persona !== '' ? $persona . "\n你现在要在百度贴吧「" . $kw . "吧」发布一个新主题帖。" : '你在百度贴吧「' . $kw . '吧」发主题帖，像一个真实的贴吧用户那样说话。';
    if ($topic !== '') {
        $sys .= '主题方向：' . $topic . '。';
    }
    $user = "请生成这个主题帖，严格按以下格式输出，不要输出其他任何内容：\n标题：12到20字的标题\n正文：80到200字的正文，口语化自然，像真实贴吧用户发的帖子，可分1到2段。";
    $text = ver4_ai_chat(array(
        array('role' => 'system', 'content' => $sys),
        array('role' => 'user', 'content' => $user),
    ), $err);
    if ($text === false) {
        return false;
    }
    $title = '';
    $body = '';
    if (preg_match('/标\s*题[：:]\s*(.+)[\r\n]+正\s*文[：:]?\s*([\s\S]+)/u', $text, $mm)) {
        $title = ver4_ai_clean_text($mm[1]);
        $body = ver4_ai_clean_text($mm[2]);
    } else {
        //兜底：首行为标题，其余为正文
        $lines = preg_split('/[\r\n]+/', trim($text));
        $title = ver4_ai_clean_text($lines[0]);
        $body = ver4_ai_clean_text(implode("\n", array_slice($lines, 1)));
    }
    $title = mb_substr($title, 0, 40);
    $body = mb_substr($body, 0, 500);
    if ($title === '' || $body === '') {
        $err = '生成内容解析失败：' . mb_substr($text, 0, 120);
        return false;
    }
    return array('title' => $title, 'content' => $body);
}
