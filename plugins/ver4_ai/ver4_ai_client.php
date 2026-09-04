<?php

if (!defined('SYSTEM_ROOT')) {
    die('Insufficient Permissions');
}
/**
 * AI 大模型客户端（OpenAI 兼容 /chat/completions 协议）
 * 配置分两层：
 *  - 管理员「站点共享配置」：tc_options 的 ver4_ai_* （可选，供用户回退使用）
 *  - 用户「个人配置」：users_options 的 uai_* （用户自己的 Key，只对自己绑定的百度账号生效）
 * 接口地址在请求前均做公网校验（协议/主机/DNS 解析目标），杜绝 SSRF。
 */

function ver4_ai_cfg($key, $default = '')
{
    $v = trim((string) option::get($key));
    return $v === '' ? $default : $v;
}

/** API Key 以 AES-256-GCM 密文存储，读取时解密 */
function ver4_ai_api_key()
{
    return ver4_ai_decrypt_key(trim((string) option::get('ver4_ai_api_key')));
}

function ver4_ai_decrypt_key($stored)
{
    if ($stored === '') {
        return '';
    }
    $plain = P::decrypt($stored);
    return $plain === '' ? $stored : $plain;
}

/**
 * 读取站点共享配置（管理员维护）
 * @return array|false 无可用 Key 时返回 false
 */
function ver4_ai_shared_cfg()
{
    $key = ver4_ai_api_key();
    if ($key === '') {
        return false;
    }
    return array(
        'base'        => rtrim(ver4_ai_cfg('ver4_ai_api_url', 'https://open.bigmodel.cn/api/coding/paas/v4'), '/'),
        'key'         => $key,
        'model'       => ver4_ai_cfg('ver4_ai_model', 'glm-5.3-flash'),
        'temperature' => min(2, max(0, (float) ver4_ai_cfg('ver4_ai_temperature', '0.9'))),
        'max_tokens'  => min(8000, max(256, (int) ver4_ai_cfg('ver4_ai_max_tokens', '1500'))),
        'persona'     => ver4_ai_cfg('ver4_ai_persona', ''),
    );
}

/**
 * 解析某用户的 AI 配置（个人配置优先，按站点开关回退共享配置）
 * @param int $uid
 * @return array|false 不可用返回 false；可用返回含 base/key/model/temperature/max_tokens/persona 及 reply/thread 参数
 */
function ver4_ai_user_cfg($uid)
{
    //站点总开关
    if (ver4_ai_cfg('ver4_ai_user_enable', '1') !== '1') {
        return false;
    }
    if (option::uget('uai_enable', $uid) !== '1') {
        return false;
    }
    $sharedAllowed = ver4_ai_cfg('ver4_ai_shared_enable') === '1';
    $shared = ver4_ai_shared_cfg();

    $ownKey = ver4_ai_decrypt_key(trim((string) option::uget('uai_api_key', $uid)));
    if ($ownKey !== '') {
        //用户自己的 API 配置
        $base = trim((string) option::uget('uai_api_url', $uid));
        $model = trim((string) option::uget('uai_model', $uid));
        $cfg = array(
            'base'        => rtrim($base !== '' ? $base : ($shared !== false ? $shared['base'] : 'https://open.bigmodel.cn/api/coding/paas/v4'), '/'),
            'key'         => $ownKey,
            'model'       => $model !== '' ? $model : ($shared !== false ? $shared['model'] : 'glm-5.3-flash'),
            'temperature' => min(2, max(0, (float) (option::uget('uai_temperature', $uid) !== null && option::uget('uai_temperature', $uid) !== '' ? option::uget('uai_temperature', $uid) : ($shared !== false ? $shared['temperature'] : 0.9)))),
            'max_tokens'  => min(8000, max(256, (int) (option::uget('uai_max_tokens', $uid) !== null && option::uget('uai_max_tokens', $uid) !== '' ? option::uget('uai_max_tokens', $uid) : ($shared !== false ? $shared['max_tokens'] : 1500)))),
        );
    } elseif ($sharedAllowed && $shared !== false) {
        //回退站点共享配置
        $cfg = $shared;
    } else {
        return false;
    }
    //人设：用户自己的优先，其次共享
    $persona = trim((string) option::uget('uai_persona', $uid));
    $cfg['persona'] = $persona !== '' ? $persona : ($shared !== false ? $shared['persona'] : '');

    //内容风格
    $cfg['style_emoji']  = option::uget('uai_style_emoji', $uid) !== '' ? option::uget('uai_style_emoji', $uid) : '';
    $cfg['style_tags']   = trim((string) option::uget('uai_style_tags', $uid));
    $cfg['style_length'] = option::uget('uai_style_length', $uid) !== '' ? option::uget('uai_style_length', $uid) : 'medium';
    $cfg['style_prompt'] = ver4_ai_style_prompt(array(
        'style_emoji'  => $cfg['style_emoji'],
        'style_length' => $cfg['style_length'],
    ), 'reply');

    //回帖参数
    $cfg['reply_enable']    = option::uget('uai_reply_enable', $uid) === '1';
    $cfg['reply_kws']       = ver4_ai_lines(option::uget('uai_reply_kws', $uid));
    $cfg['reply_num']       = max(1, (int) (option::uget('uai_reply_num', $uid) !== null && option::uget('uai_reply_num', $uid) !== '' ? option::uget('uai_reply_num', $uid) : 2));
    $cfg['reply_daily']     = max(1, (int) (option::uget('uai_reply_daily', $uid) !== null && option::uget('uai_reply_daily', $uid) !== '' ? option::uget('uai_reply_daily', $uid) : 10));
    $cfg['reply_interval']  = max(5, min(1440, (int) (option::uget('uai_reply_interval', $uid) !== null && option::uget('uai_reply_interval', $uid) !== '' ? option::uget('uai_reply_interval', $uid) : 60)));
    $cfg['reply_floor_rate']= min(100, max(0, (int) (option::uget('uai_reply_floor_rate', $uid) !== null && option::uget('uai_reply_floor_rate', $uid) !== '' ? option::uget('uai_reply_floor_rate', $uid) : 0)));
    $cfg['reply_context']   = option::uget('uai_reply_context', $uid) !== '0';
    $cfg['reply_fallback']  = ver4_ai_lines(option::uget('uai_reply_fallback', $uid));
    $cfg['reply_pids']      = ver4_ai_pid_filter(option::uget('uai_reply_pids', $uid));

    //发帖参数
    $cfg['thread_enable']   = option::uget('uai_thread_enable', $uid) === '1';
    $cfg['thread_kws']      = ver4_ai_lines(option::uget('uai_thread_kws', $uid));
    $cfg['thread_mode']     = option::uget('uai_thread_mode', $uid) === 'hourly' ? 'hourly' : 'daily';
    $cfg['thread_hour']     = min(23, max(0, (int) (option::uget('uai_thread_hour', $uid) !== null && option::uget('uai_thread_hour', $uid) !== '' ? option::uget('uai_thread_hour', $uid) : 9)));
    $cfg['thread_every']    = max(1, min(24, (int) (option::uget('uai_thread_every', $uid) !== null && option::uget('uai_thread_every', $uid) !== '' ? option::uget('uai_thread_every', $uid) : 1)));
    $cfg['thread_daily']    = max(1, min(24, (int) (option::uget('uai_thread_daily', $uid) !== null && option::uget('uai_thread_daily', $uid) !== '' ? option::uget('uai_thread_daily', $uid) : 3)));
    $cfg['thread_topic']    = trim((string) option::uget('uai_thread_topic', $uid));
    $cfg['thread_pids']     = ver4_ai_pid_filter(option::uget('uai_thread_pids', $uid));
    return $cfg;
}

function ver4_ai_lines($raw)
{
    return array_values(array_filter(array_map('trim', explode("\n", (string) $raw)), 'strlen'));
}

/**
 * 参数化写入用户选项（users_options 无 (uid,name) 唯一键，沿用查改插模式，全部预处理语句）
 */
function ver4_ai_uopt_set($uid, $name, $value)
{
    global $m;
    $uid = (int) $uid;
    $name = (string) $name;
    $value = (string) $value;
    $exists = $m->pquery("SELECT `uid` FROM `" . DB_PREFIX . "users_options` WHERE `uid` = ? AND `name` = ? LIMIT 1", array($uid, $name));
    if ($exists !== false && $exists->fetch_array()) {
        $m->pquery("UPDATE `" . DB_PREFIX . "users_options` SET `value` = ? WHERE `uid` = ? AND `name` = ?", array($value, $uid, $name));
    } else {
        $m->pquery("INSERT INTO `" . DB_PREFIX . "users_options` (`uid`, `name`, `value`) VALUES (?, ?, ?)", array($uid, $name, $value));
    }
}

/** 解析「指定账号 ID」：空数组表示全部账号（注意 strlen('0')===1，必须按数值>0 过滤） */
function ver4_ai_pid_filter($raw)
{
    $normalized = str_replace(array(",", "，", " "), "\n", (string) $raw);
    return array_values(array_filter(array_map('intval', explode("\n", $normalized)), function ($v) {
        return $v > 0;
    }));
}

/** 用户配置的对话端点 */
function ver4_ai_endpoint($cfg)
{
    return $cfg['base'] . '/chat/completions';
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
 * @param array $cfg ver4_ai_user_cfg/ver4_ai_shared_cfg 产出的配置
 * @param array $messages OpenAI 消息数组
 * @param string|null $err 失败时写入错误描述
 * @return string|false 成功返回首条回复文本，失败返回 false
 */
function ver4_ai_chat($cfg, array $messages, &$err = null)
{
    $url = ver4_ai_endpoint($cfg);
    if (!ver4_ai_url_allowed($url)) {
        $err = '接口地址不可用（仅允许公网 http/https 地址）';
        return false;
    }
    if (empty($cfg['key'])) {
        $err = '尚未配置 API Key';
        return false;
    }
    $payload = json_encode(array(
        'model' => $cfg['model'],
        'messages' => $messages,
        'temperature' => $cfg['temperature'],
        'max_tokens' => $cfg['max_tokens'],
        'stream' => false,
    ), JSON_UNESCAPED_UNICODE);
    if ($payload === false || $payload === '') {
        //输入含非法编码字节时 json_encode 会失败，避免发出空请求体
        $err = '生成内容包含非法编码，已拒绝请求';
        return false;
    }
    $ch = new wcurl($url, array(
        'Content-Type: application/json',
        'Authorization: Bearer ' . $cfg['key'],
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

/**
 * 内容风格提示词片段（emoji / 字数偏好），追加到生成要求
 * @param array $cfg 用户配置
 * @param string $kind reply|thread
 * @return string
 */
function ver4_ai_style_prompt($cfg, $kind)
{
    $parts = array();
    if (isset($cfg['style_emoji']) && $cfg['style_emoji'] === 'ban') {
        $parts[] = '不要使用任何 emoji 表情符号。';
    } elseif (!empty($cfg['style_emoji']) && $cfg['style_emoji'] === 'allow') {
        $parts[] = '可以适当使用 1-2 个 emoji 表情，但不要堆砌。';
    }
    $len = isset($cfg['style_length']) ? $cfg['style_length'] : 'medium';
    if ($kind === 'reply') {
        if ($len === 'short') { $parts[] = '回帖控制在 10 到 25 个字。'; }
        elseif ($len === 'long') { $parts[] = '回帖写在 40 到 90 个字，可以多聊两句。'; }
    } else {
        if ($len === 'short') { $parts[] = '正文控制在 50 到 100 个字。'; }
        elseif ($len === 'long') { $parts[] = '正文写在 150 到 300 个字，内容更充实。'; }
    }
    return $parts === array() ? '' : implode('', $parts);
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
function ver4_ai_gen_reply($cfg, $kw, $title, $content, &$err = null)
{
    $sys = !empty($cfg['persona']) ? $cfg['persona'] . "\n你现在在百度贴吧「" . $kw . "吧」回帖。" : '你在百度贴吧「' . $kw . '吧」回帖，像一个真实的贴吧用户那样说话。';
    $user = '帖子标题：' . $title . "\n";
    $content = trim((string) $content);
    if ($content !== '') {
        $user .= '楼主内容：' . mb_substr($content, 0, 300) . "\n";
    }
    $user .= "\n请根据以上帖子写一条回帖：15到60个字，口语化、自然、符合上下文，可以直接输出回帖内容本身，不要任何前缀或解释。";
    if (!empty($cfg['style_prompt'])) {
        $user .= "\n风格要求：" . $cfg['style_prompt'];
    }
    return ver4_ai_chat($cfg, array(
        array('role' => 'system', 'content' => $sys),
        array('role' => 'user', 'content' => $user),
    ), $err);
}

/**
 * 生成一个主题帖（标题 + 正文）
 * @return array|false array('title' =>, 'content' =>)
 */
function ver4_ai_gen_thread($cfg, $kw, &$err = null)
{
    $sys = !empty($cfg['persona']) ? $cfg['persona'] . "\n你现在要在百度贴吧「" . $kw . "吧」发布一个新主题帖。" : '你在百度贴吧「' . $kw . '吧」发主题帖，像一个真实的贴吧用户那样说话。';
    $topic = trim((string) (isset($cfg['thread_topic']) ? $cfg['thread_topic'] : ''));
    if ($topic !== '') {
        $sys .= '主题方向：' . $topic . '。';
    }
    $user = "请生成这个主题帖，严格按以下格式输出，不要输出其他任何内容：\n标题：12到20字的标题\n正文：80到200字的正文，口语化自然，像真实贴吧用户发的帖子，可分1到2段。";
    if (!empty($cfg['style_prompt'])) {
        $user .= "\n风格要求：" . $cfg['style_prompt'];
    }
    $text = ver4_ai_chat($cfg, array(
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
    //话题标签：追加到正文末尾（已包含则不重复）
    if (!empty($cfg['style_tags']) && mb_stripos($body, $cfg['style_tags']) === false) {
        $body .= "\n\n" . $cfg['style_tags'];
    }
    $body = mb_substr($body, 0, 600);
    if ($title === '' || $body === '') {
        $err = '生成内容解析失败：' . mb_substr($text, 0, 120);
        return false;
    }
    return array('title' => $title, 'content' => $body);
}
