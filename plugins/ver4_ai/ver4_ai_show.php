<?php if (!defined('SYSTEM_ROOT')) {
    die('Insufficient Permissions');
}
/**
 * 用户侧 AI 发帖/回帖设置页（index.php?plugin=ver4_ai）
 * 每个用户配置自己的大模型与目标贴吧，仅对自己绑定的百度账号生效。
 */
require_once SYSTEM_ROOT . '/plugins/ver4_ai/ver4_ai_client.php';

if (!defined('UID')) {
    ReDirect('index.php?mod=login');
    die;
}
loadhead('AI 发帖/回帖');
global $m;
$uid = UID;

//管理员站点开关
if (ver4_ai_cfg('ver4_ai_user_enable', '1') !== '1') {
    echo '<div class="page-heading"><div><div class="eyebrow">AI ASSISTANT</div><h1>AI 发帖/回帖</h1></div></div>';
    echo '<div class="alert alert-warning">管理员暂未开放 AI 发帖/回帖功能，如有需要请联系站长。</div>';
    loadfoot();
    die;
}

$sharedAllowed = ver4_ai_cfg('ver4_ai_shared_enable') === '1';
$hasShared = ver4_ai_shared_cfg() !== false;

//未绑定百度账号时给出引导
$bdRow = $m->once_fetch_array("SELECT COUNT(*) AS c FROM `" . DB_PREFIX . "baiduid` WHERE `uid` = {$uid}");
if ((int) $bdRow['c'] < 1) {
    echo '<div class="page-heading"><div><div class="eyebrow">AI ASSISTANT</div><h1>AI 发帖/回帖</h1></div></div>';
    echo '<div class="alert alert-warning">您还没有绑定百度账号，请先前往 <a href="index.php?mod=baiduid">百度账号</a> 页绑定后使用 AI 功能。</div>';
    loadfoot();
    die;
}

//保存设置
if (isset($_POST['uai_save'])) {
    csrf(true, true);
    ver4_ai_uopt_set($uid, 'uai_enable', $_POST['uai_enable'] == '1' ? '1' : '0');
    ver4_ai_uopt_set($uid, 'uai_api_url', trim((string) $_POST['uai_api_url']));
    $keyInput = trim((string) $_POST['uai_api_key']);
    if ($keyInput !== '') {
        ver4_ai_uopt_set($uid, 'uai_api_key', P::encrypt($keyInput)); //密文存储
    }
    ver4_ai_uopt_set($uid, 'uai_model', trim((string) $_POST['uai_model']));
    ver4_ai_uopt_set($uid, 'uai_temperature', (string) min(2, max(0, (float) $_POST['uai_temperature'])));
    ver4_ai_uopt_set($uid, 'uai_max_tokens', (string) min(8000, max(256, (int) $_POST['uai_max_tokens'])));
    ver4_ai_uopt_set($uid, 'uai_persona', trim((string) $_POST['uai_persona']));
    //回帖
    ver4_ai_uopt_set($uid, 'uai_reply_enable', $_POST['uai_reply_enable'] == '1' ? '1' : '0');
    ver4_ai_uopt_set($uid, 'uai_reply_kws', trim((string) $_POST['uai_reply_kws']));
    ver4_ai_uopt_set($uid, 'uai_reply_num', (string) max(1, min(10, (int) $_POST['uai_reply_num'])));
    ver4_ai_uopt_set($uid, 'uai_reply_daily', (string) max(1, min(100, (int) $_POST['uai_reply_daily'])));
    ver4_ai_uopt_set($uid, 'uai_reply_interval', (string) max(5, min(1440, (int) $_POST['uai_reply_interval'])));
    ver4_ai_uopt_set($uid, 'uai_reply_floor_rate', (string) min(100, max(0, (int) $_POST['uai_reply_floor_rate'])));
    ver4_ai_uopt_set($uid, 'uai_reply_context', $_POST['uai_reply_context'] == '1' ? '1' : '0');
    ver4_ai_uopt_set($uid, 'uai_reply_fallback', trim((string) $_POST['uai_reply_fallback']));
    ver4_ai_uopt_set($uid, 'uai_reply_pids', trim((string) $_POST['uai_reply_pids']));
    //发帖
    ver4_ai_uopt_set($uid, 'uai_thread_enable', $_POST['uai_thread_enable'] == '1' ? '1' : '0');
    ver4_ai_uopt_set($uid, 'uai_thread_kws', trim((string) $_POST['uai_thread_kws']));
    ver4_ai_uopt_set($uid, 'uai_thread_mode', $_POST['uai_thread_mode'] === 'hourly' ? 'hourly' : 'daily');
    ver4_ai_uopt_set($uid, 'uai_thread_hour', (string) min(23, max(0, (int) $_POST['uai_thread_hour'])));
    ver4_ai_uopt_set($uid, 'uai_thread_every', (string) max(1, min(24, (int) $_POST['uai_thread_every'])));
    ver4_ai_uopt_set($uid, 'uai_thread_daily', (string) max(1, min(24, (int) $_POST['uai_thread_daily'])));
    ver4_ai_uopt_set($uid, 'uai_thread_topic', trim((string) $_POST['uai_thread_topic']));
    ver4_ai_uopt_set($uid, 'uai_thread_pids', trim((string) $_POST['uai_thread_pids']));
    redirect('index.php?plugin=ver4_ai&success=' . urlencode('设置已保存，AI 任务将按计划自动执行'));
}

//生成测试（用当前用户解析出的配置，不实际发帖）
if (isset($_POST['uai_test'])) {
    csrf(true, true);
    $testCfg = ver4_ai_user_cfg($uid);
    if ($testCfg === false) {
        echo '<div class="alert alert-danger">测试失败：请先保存设置并配置 API Key' . ($sharedAllowed && $hasShared ? '（或选择使用站点共享配置并开启总开关）' : '') . '</div>';
    } else {
        $testCfg['thread_topic'] = trim((string) option::uget('uai_thread_topic', $uid));
        $testKw = trim((string) $_POST['uai_test_kw']);
        $testErr = null;
        if ($testKw === '') {
            echo '<div class="alert alert-danger">请填写测试用贴吧名</div>';
        } else {
            $thread = ver4_ai_gen_thread($testCfg, $testKw, $testErr);
            if ($thread === false) {
                echo '<div class="alert alert-danger">生成测试失败：' . htmlspecialchars((string) $testErr, ENT_QUOTES, 'UTF-8') . '</div>';
            } else {
                echo '<div class="alert alert-success">生成测试成功（未发帖）—— 标题：<b>' . htmlspecialchars($thread['title'], ENT_QUOTES, 'UTF-8') . '</b><br/>正文：<br/>' . nl2br(htmlspecialchars($thread['content'], ENT_QUOTES, 'UTF-8')) . '</div>';
            }
        }
    }
}

//读取当前用户配置（表单回显）
$g = function ($key, $default = '') use ($uid) {
    $v = option::uget($key, $uid);
    return ($v === null || $v === '') ? $default : $v;
};
$aiEnable = $g('uai_enable', '0');
$aiUrl = htmlspecialchars($g('uai_api_url', ver4_ai_cfg('ver4_ai_api_url', 'https://open.bigmodel.cn/api/coding/paas/v4')), ENT_QUOTES, 'UTF-8');
$aiModel = htmlspecialchars($g('uai_model', ver4_ai_cfg('ver4_ai_model', 'glm-5.3-flash')), ENT_QUOTES, 'UTF-8');
$aiTemp = htmlspecialchars($g('uai_temperature', ver4_ai_cfg('ver4_ai_temperature', '0.9')), ENT_QUOTES, 'UTF-8');
$aiMaxTokens = htmlspecialchars($g('uai_max_tokens', ver4_ai_cfg('ver4_ai_max_tokens', '1500')), ENT_QUOTES, 'UTF-8');
$aiPersona = htmlspecialchars($g('uai_persona', ''), ENT_QUOTES, 'UTF-8');
$hasOwnKey = trim((string) option::uget('uai_api_key', $uid)) !== '';
$replyEnable = $g('uai_reply_enable', '0');
$replyKws = htmlspecialchars($g('uai_reply_kws'), ENT_QUOTES, 'UTF-8');
$replyNum = htmlspecialchars($g('uai_reply_num', '2'), ENT_QUOTES, 'UTF-8');
$replyDaily = htmlspecialchars($g('uai_reply_daily', '10'), ENT_QUOTES, 'UTF-8');
$replyInterval = htmlspecialchars($g('uai_reply_interval', '60'), ENT_QUOTES, 'UTF-8');
$replyFloorRate = htmlspecialchars($g('uai_reply_floor_rate', '0'), ENT_QUOTES, 'UTF-8');
$replyContext = $g('uai_reply_context', '1');
$replyFallback = htmlspecialchars($g('uai_reply_fallback'), ENT_QUOTES, 'UTF-8');
$replyPids = htmlspecialchars($g('uai_reply_pids'), ENT_QUOTES, 'UTF-8');
$threadEnable = $g('uai_thread_enable', '0');
$threadKws = htmlspecialchars($g('uai_thread_kws'), ENT_QUOTES, 'UTF-8');
$threadMode = $g('uai_thread_mode', 'daily');
$threadHour = htmlspecialchars($g('uai_thread_hour', '9'), ENT_QUOTES, 'UTF-8');
$threadEvery = htmlspecialchars($g('uai_thread_every', '1'), ENT_QUOTES, 'UTF-8');
$threadDaily = htmlspecialchars($g('uai_thread_daily', '3'), ENT_QUOTES, 'UTF-8');
$threadTopic = htmlspecialchars($g('uai_thread_topic'), ENT_QUOTES, 'UTF-8');
$threadPids = htmlspecialchars($g('uai_thread_pids'), ENT_QUOTES, 'UTF-8');

//我的百度账号列表（供指定账号 ID 参考）
$myAccounts = array();
$accRs = $m->query("SELECT `id`, `name` FROM `" . DB_PREFIX . "baiduid` WHERE `uid` = {$uid} ORDER BY `id` ASC");
while ($a = $m->fetch_array($accRs)) {
    $myAccounts[] = array('id' => (int) $a['id'], 'name' => (string) $a['name']);
}
$accountHint = '';
foreach ($myAccounts as $a) {
    $accountHint .= htmlspecialchars($a['name'], ENT_QUOTES, 'UTF-8') . ' = ID ' . $a['id'] . '；';
}
//最近执行记录
$recentRows = array();
$logRs = $m->query("SELECT `l`.`pid`, `l`.`kw`, `l`.`type`, `l`.`status`, `l`.`msg`, `l`.`created_at` FROM `" . DB_PREFIX . "ver4_ai_log` `l` INNER JOIN `" . DB_PREFIX . "baiduid` `b` ON `b`.`id` = `l`.`pid` WHERE `b`.`uid` = {$uid} ORDER BY `l`.`id` DESC LIMIT 8");
while ($r = $m->fetch_array($logRs)) {
    $recentRows[] = $r;
}
?>
<div class="page-heading"><div><div class="eyebrow">AI ASSISTANT</div><h1>AI 发帖/回帖</h1></div></div>
<p class="text-muted">接入你自己的大模型（OpenAI 兼容接口，如智谱 / DeepSeek / 各类中转站），让 AI 代替你的百度账号在目标贴吧定时发主题帖、智能回帖。所有任务仅在站点开关开启时按计划自动执行。</p>
<?php if (isset($_GET['success'])): ?>
<div class="alert alert-success"><?php echo htmlspecialchars((string) $_GET['success'], ENT_QUOTES, 'UTF-8'); ?></div>
<?php endif; ?>
<form method="post" action="index.php?plugin=ver4_ai">
    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">
    <table class="table table-striped">
        <tr><th colspan="2"><h4>① 大模型接口（OpenAI 兼容）</h4></th></tr>
        <tr>
            <td style="width:35%"><b>AI 功能总开关</b></td>
            <td>
                <select name="uai_enable" class="form-control" style="width:120px">
                    <option value="0" <?php echo $aiEnable != '1' ? 'selected' : ''; ?>>关闭</option>
                    <option value="1" <?php echo $aiEnable == '1' ? 'selected' : ''; ?>>开启</option>
                </select>
            </td>
        </tr>
        <tr>
            <td><b>API 地址</b><br/>填到版本号为止</td>
            <td><input type="text" class="form-control" name="uai_api_url" value="<?php echo $aiUrl; ?>" placeholder="https://open.bigmodel.cn/api/coding/paas/v4"></td>
        </tr>
        <tr>
            <td><b>API Key</b><br/>仅自己可见，加密存储；留空表示保持不变<?php echo $hasOwnKey ? '（已配置）' : '（未配置）'; ?>
                <?php if ($sharedAllowed && $hasShared): ?><br/><span class="text-muted">站长已开放站点共享配置：不填 Key 将自动使用共享配置。</span><?php endif; ?>
            </td>
            <td><input type="password" class="form-control" name="uai_api_key" value="" placeholder="<?php echo $hasOwnKey ? '已配置，输入新值可覆盖' : 'sk-...'; ?>" autocomplete="new-password"></td>
        </tr>
        <tr>
            <td><b>模型名称</b></td>
            <td><input type="text" class="form-control" name="uai_model" value="<?php echo $aiModel; ?>" placeholder="glm-5.3-flash"></td>
        </tr>
        <tr>
            <td><b>温度 / 最大 Token</b><br/>温度 0-2；Token 上限建议 ≥1000（推理模型思考也占用）</td>
            <td>
                <input type="number" min="0" max="2" step="0.1" class="form-control" name="uai_temperature" value="<?php echo $aiTemp; ?>" style="width:120px;display:inline-block;">
                <input type="number" min="256" max="8000" step="64" class="form-control" name="uai_max_tokens" value="<?php echo $aiMaxTokens; ?>" style="width:140px;display:inline-block;">
            </td>
        </tr>
        <tr>
            <td><b>我的 AI 人设（System Prompt）</b><br/>留空时使用站点共享人设</td>
            <td><textarea class="form-control" name="uai_persona" style="height:80px" placeholder="例如：你是一个热爱分享生活的贴吧老哥，说话自然、口语化…"><?php echo $aiPersona; ?></textarea></td>
        </tr>
        <tr><th colspan="2"><h4>② AI 智能回帖</h4></th></tr>
        <tr>
            <td><b>功能开关</b></td>
            <td>
                <select name="uai_reply_enable" class="form-control" style="width:120px">
                    <option value="0" <?php echo $replyEnable != '1' ? 'selected' : ''; ?>>关闭</option>
                    <option value="1" <?php echo $replyEnable == '1' ? 'selected' : ''; ?>>开启</option>
                </select>
            </td>
        </tr>
        <tr>
            <td><b>目标贴吧</b><br/>每行一个贴吧名（不带"吧"字），留空表示不执行</td>
            <td><textarea class="form-control" name="uai_reply_kws" style="height:90px" placeholder="例如：&#10;来日易在心中"><?php echo $replyKws; ?></textarea></td>
        </tr>
        <tr>
            <td><b>每吧每次回帖数 / 每账号每日上限</b></td>
            <td>
                <input type="number" min="1" max="10" class="form-control" name="uai_reply_num" value="<?php echo $replyNum; ?>" style="width:100px;display:inline-block;">
                <input type="number" min="1" max="100" class="form-control" name="uai_reply_daily" value="<?php echo $replyDaily; ?>" style="width:100px;display:inline-block;">
            </td>
        </tr>
        <tr>
            <td><b>回帖轮间隔（分钟）</b><br/>系统每 5 分钟检查一次，到间隔才发起一轮回帖</td>
            <td><input type="number" min="5" max="1440" class="form-control" name="uai_reply_interval" value="<?php echo $replyInterval; ?>" style="width:120px"></td>
        </tr>
        <tr>
            <td><b>楼中楼回复比例</b><br/>0-100 百分比，概率走楼中楼</td>
            <td><input type="number" min="0" max="100" step="5" class="form-control" name="uai_reply_floor_rate" value="<?php echo $replyFloorRate; ?>" style="width:120px"></td>
        </tr>
        <tr>
            <td><b>读取帖子内容作为上下文</b><br/>回复更贴合主题，但每帖多一次接口请求</td>
            <td>
                <select name="uai_reply_context" class="form-control" style="width:120px">
                    <option value="1" <?php echo $replyContext == '1' ? 'selected' : ''; ?>>开启</option>
                    <option value="0" <?php echo $replyContext != '1' ? 'selected' : ''; ?>>关闭</option>
                </select>
            </td>
        </tr>
        <tr>
            <td><b>兜底回帖内容库</b><br/>每行一条；大模型失败时随机选用，留空则跳过</td>
            <td><textarea class="form-control" name="uai_reply_fallback" style="height:70px"><?php echo $replyFallback; ?></textarea></td>
        </tr>
        <tr>
            <td><b>仅对指定账号生效</b><br/>填账号 ID（<?php echo $accountHint ?: '尚无绑定账号'; ?>），每行一个，留空 = 我的所有账号</td>
            <td><textarea class="form-control" name="uai_reply_pids" style="height:50px" placeholder="留空 = 全部"><?php echo $replyPids; ?></textarea></td>
        </tr>
        <tr><th colspan="2"><h4>③ AI 定时发主题帖</h4></th></tr>
        <tr>
            <td><b>功能开关</b></td>
            <td>
                <select name="uai_thread_enable" class="form-control" style="width:120px">
                    <option value="0" <?php echo $threadEnable != '1' ? 'selected' : ''; ?>>关闭</option>
                    <option value="1" <?php echo $threadEnable == '1' ? 'selected' : ''; ?>>开启</option>
                </select>
            </td>
        </tr>
        <tr>
            <td><b>目标贴吧</b><br/>每行一个贴吧名（不带"吧"字），留空表示不执行</td>
            <td><textarea class="form-control" name="uai_thread_kws" style="height:90px" placeholder="例如：&#10;来日易在心中"><?php echo $threadKws; ?></textarea></td>
        </tr>
        <tr>
            <td><b>发帖模式</b><br/>每日：到设定的小时发一帖；每 N 小时：按间隔发帖</td>
            <td>
                <select name="uai_thread_mode" class="form-control" style="width:140px">
                    <option value="daily" <?php echo $threadMode != 'hourly' ? 'selected' : ''; ?>>每日定时</option>
                    <option value="hourly" <?php echo $threadMode == 'hourly' ? 'selected' : ''; ?>>每 N 小时</option>
                </select>
                每日点(0-23时)：<input type="number" min="0" max="23" class="form-control" name="uai_thread_hour" value="<?php echo $threadHour; ?>" style="width:90px;display:inline-block;">
                每 N 小时：<input type="number" min="1" max="24" class="form-control" name="uai_thread_every" value="<?php echo $threadEvery; ?>" style="width:90px;display:inline-block;">
            </td>
        </tr>
        <tr>
            <td><b>每账号每日发帖上限</b></td>
            <td><input type="number" min="1" max="24" class="form-control" name="uai_thread_daily" value="<?php echo $threadDaily; ?>" style="width:120px"></td>
        </tr>
        <tr>
            <td><b>发帖主题方向</b><br/>告诉大模型这个吧发什么内容</td>
            <td><textarea class="form-control" name="uai_thread_topic" style="height:70px" placeholder="例如：分享日常见闻、生活感悟、兴趣爱好等积极向上的内容"><?php echo $threadTopic; ?></textarea></td>
        </tr>
        <tr>
            <td><b>仅对指定账号生效</b><br/>填账号 ID，每行一个，留空 = 我的所有账号</td>
            <td><textarea class="form-control" name="uai_thread_pids" style="height:50px" placeholder="留空 = 全部"><?php echo $threadPids; ?></textarea></td>
        </tr>
        <tr>
            <td></td>
            <td><input type="submit" class="btn btn-primary" name="uai_save" value="保存设置"></td>
        </tr>
    </table>
</form>
<hr/>
<form method="post" action="index.php?plugin=ver4_ai" class="form-inline">
    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">
    <b>生成测试</b>（用我的配置生成一个主题帖，不实际发帖）：
    <input type="text" class="form-control" name="uai_test_kw" placeholder="贴吧名" style="width:180px" required>
    <input type="submit" class="btn btn-success" name="uai_test" value="测试生成">
</form>
<?php if (!empty($recentRows)): ?>
<h4 style="margin-top:20px;">最近执行记录</h4>
<table class="table table-striped">
    <tr><th>时间</th><th>账号</th><th>类型</th><th>结果</th><th>详情</th></tr>
    <?php foreach ($recentRows as $r): ?>
    <tr>
        <td><?php echo htmlspecialchars(date('m-d H:i', (int) $r['created_at']), ENT_QUOTES, 'UTF-8'); ?></td>
        <td>ID <?php echo (int) $r['pid']; ?></td>
        <td><?php echo (int) $r['type'] === 1 ? '发帖' : '回帖'; ?></td>
        <td><?php
            $st = (int) $r['status'];
            echo $st === 1 ? '<font color="green">成功</font>' : ($st === 4 ? '<font color="orange">生成失败</font>' : ($st === 3 ? '<font color="orange">频率限制</font>' : '<font color="red">失败</font>'));
        ?></td>
        <td><?php echo htmlspecialchars(mb_substr((string) $r['msg'], 0, 60), ENT_QUOTES, 'UTF-8'); ?></td>
    </tr>
    <?php endforeach; ?>
</table>
<?php endif; ?>
<div class="alert alert-warning" style="margin-top:15px;">
    <b>温馨提示：</b>任务由系统计划任务每 5 分钟检查执行；请遵守目标贴吧的吧规，<span class="text-danger">请勿发布违法违规内容，后果由使用者自负</span>。
    若帖子发布成功但很快消失，多为百度风控（数据中心 IP 秒删），可尝试为账号配置住宅代理。
</div>
<?php loadfoot(); ?>
