<?php

if (!defined('SYSTEM_ROOT')) {
    die('Insufficient Permissions');
}
require_once SYSTEM_ROOT . '/plugins/ver4_ai/ver4_ai_client.php';
global $m;

$ver4AiTabs = array('save', 'test');
if (isset($_POST['ver4_ai_save'])) {
    csrf(true, true);
    //API 基础配置
    option::set('ver4_ai_api_url', trim((string) $_POST['ver4_ai_api_url']));
    $keyInput = trim((string) $_POST['ver4_ai_api_key']);
    if ($keyInput !== '') {
        //前端以掩码占位，仅当提交了新 Key 才覆盖；密文存储
        option::set('ver4_ai_api_key', P::encrypt($keyInput));
    }
    option::set('ver4_ai_model', trim((string) $_POST['ver4_ai_model']));
    option::set('ver4_ai_temperature', min(2, max(0, (float) $_POST['ver4_ai_temperature'])));
    option::set('ver4_ai_max_tokens', min(8000, max(256, (int) $_POST['ver4_ai_max_tokens'])));
    option::set('ver4_ai_persona', trim((string) $_POST['ver4_ai_persona']));
    //AI 回帖
    option::set('ver4_ai_reply_enable', $_POST['ver4_ai_reply_enable'] == '1' ? '1' : '0');
    option::set('ver4_ai_reply_kws', trim((string) $_POST['ver4_ai_reply_kws']));
    option::set('ver4_ai_reply_pids', trim((string) $_POST['ver4_ai_reply_pids']));
    option::set('ver4_ai_reply_num', max(1, min(10, (int) $_POST['ver4_ai_reply_num'])));
    option::set('ver4_ai_reply_daily', max(1, min(100, (int) $_POST['ver4_ai_reply_daily'])));
    option::set('ver4_ai_reply_interval', max(5, min(1440, (int) $_POST['ver4_ai_reply_interval'])));
    option::set('ver4_ai_reply_floor_rate', min(100, max(0, (int) $_POST['ver4_ai_reply_floor_rate'])));
    option::set('ver4_ai_reply_context', $_POST['ver4_ai_reply_context'] == '1' ? '1' : '0');
    option::set('ver4_ai_reply_fallback', trim((string) $_POST['ver4_ai_reply_fallback']));
    //AI 发主题帖
    option::set('ver4_ai_thread_enable', $_POST['ver4_ai_thread_enable'] == '1' ? '1' : '0');
    option::set('ver4_ai_thread_kws', trim((string) $_POST['ver4_ai_thread_kws']));
    option::set('ver4_ai_thread_pids', trim((string) $_POST['ver4_ai_thread_pids']));
    option::set('ver4_ai_thread_mode', $_POST['ver4_ai_thread_mode'] === 'hourly' ? 'hourly' : 'daily');
    option::set('ver4_ai_thread_hour', min(23, max(0, (int) $_POST['ver4_ai_thread_hour'])));
    option::set('ver4_ai_thread_every', min(24, max(1, (int) $_POST['ver4_ai_thread_every'])));
    option::set('ver4_ai_thread_daily', max(1, min(24, (int) $_POST['ver4_ai_thread_daily'])));
    option::set('ver4_ai_thread_topic', trim((string) $_POST['ver4_ai_thread_topic']));
    echo '<div class="alert alert-success">AI 发帖/回帖设置已保存</div>';
} elseif (isset($_POST['ver4_ai_test'])) {
    csrf(true, true);
    require_once SYSTEM_ROOT . '/plugins/ver4_ai/ver4_ai_client.php';
    $testKw = trim((string) $_POST['ver4_ai_test_kw']);
    if ($testKw === '') {
        echo '<div class="alert alert-danger">请填写测试用贴吧名</div>';
    } else {
        $testErr = null;
        $thread = ver4_ai_gen_thread($testKw, $testErr);
        if ($thread === false) {
            echo '<div class="alert alert-danger">生成测试失败：' . htmlspecialchars((string) $testErr, ENT_QUOTES, 'UTF-8') . '</div>';
        } else {
            echo '<div class="alert alert-success">生成测试成功（未发帖）—— 标题：<b>' . htmlspecialchars($thread['title'], ENT_QUOTES, 'UTF-8') . '</b><br/>正文：<br/>' . nl2br(htmlspecialchars($thread['content'], ENT_QUOTES, 'UTF-8')) . '</div>';
        }
    }
}

$aiUrl = htmlspecialchars(ver4_ai_cfg('ver4_ai_api_url', 'https://open.bigmodel.cn/api/coding/paas/v4'), ENT_QUOTES, 'UTF-8');
$aiModel = htmlspecialchars(ver4_ai_cfg('ver4_ai_model', 'glm-5.3-flash'), ENT_QUOTES, 'UTF-8');
$aiTemp = htmlspecialchars(ver4_ai_cfg('ver4_ai_temperature', '0.9'), ENT_QUOTES, 'UTF-8');
$aiMaxTokens = htmlspecialchars(ver4_ai_cfg('ver4_ai_max_tokens', '1500'), ENT_QUOTES, 'UTF-8');
$aiPersona = htmlspecialchars(ver4_ai_cfg('ver4_ai_persona', ''), ENT_QUOTES, 'UTF-8');
$hasKey = trim((string) option::get('ver4_ai_api_key')) !== '';
$replyEnable = ver4_ai_cfg('ver4_ai_reply_enable', '0');
$replyKws = htmlspecialchars((string) option::get('ver4_ai_reply_kws'), ENT_QUOTES, 'UTF-8');
$replyPids = htmlspecialchars((string) option::get('ver4_ai_reply_pids'), ENT_QUOTES, 'UTF-8');
$replyNum = htmlspecialchars(ver4_ai_cfg('ver4_ai_reply_num', '2'), ENT_QUOTES, 'UTF-8');
$replyDaily = htmlspecialchars(ver4_ai_cfg('ver4_ai_reply_daily', '10'), ENT_QUOTES, 'UTF-8');
$replyInterval = htmlspecialchars(ver4_ai_cfg('ver4_ai_reply_interval', '60'), ENT_QUOTES, 'UTF-8');
$replyFloorRate = htmlspecialchars(ver4_ai_cfg('ver4_ai_reply_floor_rate', '0'), ENT_QUOTES, 'UTF-8');
$replyContext = ver4_ai_cfg('ver4_ai_reply_context', '1');
$replyFallback = htmlspecialchars((string) option::get('ver4_ai_reply_fallback'), ENT_QUOTES, 'UTF-8');
$threadEnable = ver4_ai_cfg('ver4_ai_thread_enable', '0');
$threadKws = htmlspecialchars((string) option::get('ver4_ai_thread_kws'), ENT_QUOTES, 'UTF-8');
$threadPids = htmlspecialchars((string) option::get('ver4_ai_thread_pids'), ENT_QUOTES, 'UTF-8');
$threadMode = ver4_ai_cfg('ver4_ai_thread_mode', 'daily');
$threadHour = htmlspecialchars(ver4_ai_cfg('ver4_ai_thread_hour', '9'), ENT_QUOTES, 'UTF-8');
$threadEvery = htmlspecialchars(ver4_ai_cfg('ver4_ai_thread_every', '1'), ENT_QUOTES, 'UTF-8');
$threadDaily = htmlspecialchars(ver4_ai_cfg('ver4_ai_thread_daily', '3'), ENT_QUOTES, 'UTF-8');
$threadTopic = htmlspecialchars(ver4_ai_cfg('ver4_ai_thread_topic', ''), ENT_QUOTES, 'UTF-8');
$cronRow = $m->once_fetch_array("SELECT `enable`, `freq` FROM `" . DB_PREFIX . "cron` WHERE `name` = 'ver4_ai_do' LIMIT 1");
$cronOn = !empty($cronRow['enable']);
?>

<form method="post" action="index.php?mod=admin:setplug&plug=ver4_ai">
    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">
    <table class="table table-striped">
        <tr><th colspan="2"><h4>大模型接口（OpenAI 兼容）</h4></th></tr>
        <tr>
            <td style="width:35%"><b>API 地址</b><br/>填到版本号为止，如 https://open.bigmodel.cn/api/coding/paas/v4</td>
            <td><input type="text" class="form-control" name="ver4_ai_api_url" value="<?php echo $aiUrl; ?>" placeholder="https://api.deepseek.com/v1"></td>
        </tr>
        <tr>
            <td><b>API Key</b><br/>加密存储；留空表示保持不变<?php echo $hasKey ? '（已配置）' : '（未配置）'; ?></td>
            <td><input type="password" class="form-control" name="ver4_ai_api_key" value="" placeholder="<?php echo $hasKey ? '已配置，输入新值可覆盖' : 'sk-...'; ?>" autocomplete="new-password"></td>
        </tr>
        <tr>
            <td><b>模型名称</b></td>
            <td><input type="text" class="form-control" name="ver4_ai_model" value="<?php echo $aiModel; ?>" placeholder="glm-5.3-flash"></td>
        </tr>
        <tr>
            <td><b>温度 / 最大 Token</b><br/>温度 0-2；Token 上限建议 ≥1000（推理模型思考也占用）</td>
            <td>
                <input type="number" min="0" max="2" step="0.1" class="form-control" name="ver4_ai_temperature" value="<?php echo $aiTemp; ?>" style="width:120px;display:inline-block;">
                <input type="number" min="256" max="8000" step="64" class="form-control" name="ver4_ai_max_tokens" value="<?php echo $aiMaxTokens; ?>" style="width:140px;display:inline-block;">
            </td>
        </tr>
        <tr>
            <td><b>全局人设（System Prompt）</b><br/>对回帖与发帖同时生效</td>
            <td><textarea class="form-control" name="ver4_ai_persona" style="height:90px"><?php echo $aiPersona; ?></textarea></td>
        </tr>
        <tr><th colspan="2"><h4>AI 智能回帖</h4></th></tr>
        <tr>
            <td><b>功能开关</b></td>
            <td>
                <select name="ver4_ai_reply_enable" class="form-control" style="width:120px">
                    <option value="0" <?php echo $replyEnable != '1' ? 'selected' : ''; ?>>关闭</option>
                    <option value="1" <?php echo $replyEnable == '1' ? 'selected' : ''; ?>>开启</option>
                </select>
            </td>
        </tr>
        <tr>
            <td><b>目标贴吧</b><br/>每行一个贴吧名（不带"吧"字），留空表示不执行</td>
            <td><textarea class="form-control" name="ver4_ai_reply_kws" style="height:100px" placeholder="例如：&#10;来日易在心中"><?php echo $replyKws; ?></textarea></td>
        </tr>
        <tr>
            <td><b>指定百度账号 ID</b><br/>每行一个，留空表示使用全部已绑定账号</td>
            <td><textarea class="form-control" name="ver4_ai_reply_pids" style="height:60px" placeholder="留空 = 全部账号"><?php echo $replyPids; ?></textarea></td>
        </tr>
        <tr>
            <td><b>每吧每次回帖数 / 每账号每日上限</b></td>
            <td>
                <input type="number" min="1" max="10" class="form-control" name="ver4_ai_reply_num" value="<?php echo $replyNum; ?>" style="width:100px;display:inline-block;">
                <input type="number" min="1" max="100" class="form-control" name="ver4_ai_reply_daily" value="<?php echo $replyDaily; ?>" style="width:100px;display:inline-block;">
            </td>
        </tr>
        <tr>
            <td><b>回帖轮间隔（分钟）</b><br/>计划任务每 5 分钟检查一次，到间隔才发起一轮回帖</td>
            <td><input type="number" min="5" max="1440" class="form-control" name="ver4_ai_reply_interval" value="<?php echo $replyInterval; ?>" style="width:120px"></td>
        </tr>
        <tr>
            <td><b>楼中楼回复比例</b><br/>0-100 百分比，概率走楼中楼</td>
            <td><input type="number" min="0" max="100" step="5" class="form-control" name="ver4_ai_reply_floor_rate" value="<?php echo $replyFloorRate; ?>" style="width:120px"></td>
        </tr>
        <tr>
            <td><b>读取帖子内容作为上下文</b><br/>开启后拉取首楼内容，让回复更贴合主题（多一次接口请求）</td>
            <td>
                <select name="ver4_ai_reply_context" class="form-control" style="width:120px">
                    <option value="1" <?php echo $replyContext == '1' ? 'selected' : ''; ?>>开启</option>
                    <option value="0" <?php echo $replyContext != '1' ? 'selected' : ''; ?>>关闭</option>
                </select>
            </td>
        </tr>
        <tr>
            <td><b>兜底回帖内容库</b><br/>每行一条；大模型失败时随机选用，留空则跳过该次回帖</td>
            <td><textarea class="form-control" name="ver4_ai_reply_fallback" style="height:80px"><?php echo $replyFallback; ?></textarea></td>
        </tr>
        <tr><th colspan="2"><h4>AI 定时发主题帖</h4></th></tr>
        <tr>
            <td><b>功能开关</b></td>
            <td>
                <select name="ver4_ai_thread_enable" class="form-control" style="width:120px">
                    <option value="0" <?php echo $threadEnable != '1' ? 'selected' : ''; ?>>关闭</option>
                    <option value="1" <?php echo $threadEnable == '1' ? 'selected' : ''; ?>>开启</option>
                </select>
            </td>
        </tr>
        <tr>
            <td><b>目标贴吧</b><br/>每行一个贴吧名（不带"吧"字），留空表示不执行</td>
            <td><textarea class="form-control" name="ver4_ai_thread_kws" style="height:100px" placeholder="例如：&#10;来日易在心中"><?php echo $threadKws; ?></textarea></td>
        </tr>
        <tr>
            <td><b>指定百度账号 ID</b><br/>每行一个，留空表示使用全部已绑定账号</td>
            <td><textarea class="form-control" name="ver4_ai_thread_pids" style="height:60px" placeholder="留空 = 全部账号"><?php echo $threadPids; ?></textarea></td>
        </tr>
        <tr>
            <td><b>发帖模式</b><br/>每日：到设定的小时发一帖；每小时：每 N 小时发一帖</td>
            <td>
                <select name="ver4_ai_thread_mode" class="form-control" style="width:140px">
                    <option value="daily" <?php echo $threadMode != 'hourly' ? 'selected' : ''; ?>>每日定时</option>
                    <option value="hourly" <?php echo $threadMode == 'hourly' ? 'selected' : ''; ?>>每 N 小时</option>
                </select>
                每日点(0-23时)：<input type="number" min="0" max="23" class="form-control" name="ver4_ai_thread_hour" value="<?php echo $threadHour; ?>" style="width:90px;display:inline-block;">
                每 N 小时：<input type="number" min="1" max="24" class="form-control" name="ver4_ai_thread_every" value="<?php echo $threadEvery; ?>" style="width:90px;display:inline-block;">
            </td>
        </tr>
        <tr>
            <td><b>每账号每日发帖上限</b></td>
            <td><input type="number" min="1" max="24" class="form-control" name="ver4_ai_thread_daily" value="<?php echo $threadDaily; ?>" style="width:120px"></td>
        </tr>
        <tr>
            <td><b>发帖主题方向</b><br/>告诉大模型这个吧发什么内容</td>
            <td><textarea class="form-control" name="ver4_ai_thread_topic" style="height:80px" placeholder="例如：分享日常见闻、生活感悟、兴趣爱好等积极向上的内容"><?php echo $threadTopic; ?></textarea></td>
        </tr>
        <tr>
            <td></td>
            <td><input type="submit" class="btn btn-primary" name="ver4_ai_save" value="保存设置"></td>
        </tr>
    </table>
</form>
<hr/>
<form method="post" action="index.php?mod=admin:setplug&plug=ver4_ai" class="form-inline">
    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">
    <b>生成测试</b>（调用大模型生成一个主题帖，但不实际发帖）：
    <input type="text" class="form-control" name="ver4_ai_test_kw" placeholder="贴吧名，如：来日易在心中" style="width:200px" required>
    <input type="submit" class="btn btn-success" name="ver4_ai_test" value="测试生成">
</form>
<p class="text-muted" style="margin-top:10px;">
    计划任务 <code>ver4_ai_do</code> <?php echo $cronOn ? '<font color="green"><b>已注册</b></font>' : '<font color="red"><b>未注册（请重新激活插件）</b></font>'; ?>；
    请在 <a href="index.php?mod=admin:cron">计划任务</a> 页确认其处于启用状态（建议每 5 分钟执行一次）。
    发帖/回帖记录可在计划任务日志与本插件数据表 <?php echo DB_PREFIX; ?>ver4_ai_log 中查看。
    <span class="text-danger">请勿利用本插件发布违法违规内容，后果由使用者自负。</span>
</p>
<div class="alert alert-warning">
    <b>风控提示（实测）：</b>数据中心 IP（如云服务器）发布的主题帖与回帖会被百度反垃圾「秒删」——接口返回成功但帖子随即被移除，属百度侧行为。
    缓解方式：在「百度账号」页为账号配置<b>住宅代理</b>（本插件发帖/回帖遵循账号级代理设置），或降低发帖频率、提升账号在目标吧的等级与活跃度后逐步放量。
</div>
