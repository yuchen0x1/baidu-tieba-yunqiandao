<?php

if (!defined('SYSTEM_ROOT')) {
    die('Insufficient Permissions');
}
require_once SYSTEM_ROOT . '/plugins/ver4_ai/ver4_ai_client.php';
global $m;

if (isset($_POST['ver4_ai_save'])) {
    csrf(true, true);
    //站点开关
    option::set('ver4_ai_user_enable', $_POST['ver4_ai_user_enable'] == '1' ? '1' : '0');
    option::set('ver4_ai_shared_enable', $_POST['ver4_ai_shared_enable'] == '1' ? '1' : '0');
    //站点共享 API 配置（用户未填自己 Key 时，若允许共享则回退使用）
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
    echo '<div class="alert alert-success">AI 设置已保存</div>';
} elseif (isset($_POST['ver4_ai_test'])) {
    csrf(true, true);
    $shared = ver4_ai_shared_cfg();
    if ($shared === false) {
        echo '<div class="alert alert-danger">共享配置不可用：请先保存 API Key</div>';
    } else {
        $testKw = trim((string) $_POST['ver4_ai_test_kw']);
        if ($testKw === '') {
            echo '<div class="alert alert-danger">请填写测试用贴吧名</div>';
        } else {
            $shared['thread_topic'] = '';
            $testErr = null;
            $thread = ver4_ai_gen_thread($shared, $testKw, $testErr);
            if ($thread === false) {
                echo '<div class="alert alert-danger">生成测试失败：' . htmlspecialchars((string) $testErr, ENT_QUOTES, 'UTF-8') . '</div>';
            } else {
                echo '<div class="alert alert-success">生成测试成功（未发帖）—— 标题：<b>' . htmlspecialchars($thread['title'], ENT_QUOTES, 'UTF-8') . '</b><br/>正文：<br/>' . nl2br(htmlspecialchars($thread['content'], ENT_QUOTES, 'UTF-8')) . '</div>';
            }
        }
    }
}

$userEnable = ver4_ai_cfg('ver4_ai_user_enable', '1');
$sharedEnable = ver4_ai_cfg('ver4_ai_shared_enable', '0');
$aiUrl = htmlspecialchars(ver4_ai_cfg('ver4_ai_api_url', 'https://open.bigmodel.cn/api/coding/paas/v4'), ENT_QUOTES, 'UTF-8');
$aiModel = htmlspecialchars(ver4_ai_cfg('ver4_ai_model', 'glm-5.3-flash'), ENT_QUOTES, 'UTF-8');
$aiTemp = htmlspecialchars(ver4_ai_cfg('ver4_ai_temperature', '0.9'), ENT_QUOTES, 'UTF-8');
$aiMaxTokens = htmlspecialchars(ver4_ai_cfg('ver4_ai_max_tokens', '1500'), ENT_QUOTES, 'UTF-8');
$aiPersona = htmlspecialchars(ver4_ai_cfg('ver4_ai_persona', ''), ENT_QUOTES, 'UTF-8');
$hasKey = trim((string) option::get('ver4_ai_api_key')) !== '';
$userCountRow = $m->once_fetch_array("SELECT COUNT(*) AS c FROM `" . DB_PREFIX . "users_options` WHERE `name` = 'uai_enable' AND `value` = '1'");
$userEnabledCount = (int) $userCountRow['c'];
$cronRow = $m->once_fetch_array("SELECT `enable` FROM `" . DB_PREFIX . "cron` WHERE `name` = 'ver4_ai_do' LIMIT 1");
$cronOn = !empty($cronRow['enable']);
?>

<form method="post" action="index.php?mod=admin:setplug&plug=ver4_ai">
    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">
    <table class="table table-striped">
        <tr><th colspan="2"><h4>站点开关</h4></th></tr>
        <tr>
            <td style="width:35%"><b>允许用户使用 AI 发帖/回帖</b><br/>关闭后所有用户的 AI 功能立即停止执行</td>
            <td>
                <select name="ver4_ai_user_enable" class="form-control" style="width:120px">
                    <option value="1" <?php echo $userEnable == '1' ? 'selected' : ''; ?>>允许</option>
                    <option value="0" <?php echo $userEnable != '1' ? 'selected' : ''; ?>>禁止</option>
                </select>
                （当前已有 <?php echo $userEnabledCount; ?> 个用户开启了 AI 功能）
            </td>
        </tr>
        <tr><th colspan="2"><h4>站点共享配置（可选）</h4></th></tr>
        <tr>
            <td><b>允许用户回退使用共享配置</b><br/>用户未填写自己的 API Key 时，改用下方站点配置执行（消耗站长额度，请谨慎开启）</td>
            <td>
                <select name="ver4_ai_shared_enable" class="form-control" style="width:120px">
                    <option value="0" <?php echo $sharedEnable != '1' ? 'selected' : ''; ?>>关闭</option>
                    <option value="1" <?php echo $sharedEnable == '1' ? 'selected' : ''; ?>>开启</option>
                </select>
            </td>
        </tr>
        <tr>
            <td><b>API 地址</b><br/>填到版本号为止</td>
            <td><input type="text" class="form-control" name="ver4_ai_api_url" value="<?php echo $aiUrl; ?>" placeholder="https://open.bigmodel.cn/api/coding/paas/v4"></td>
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
            <td><b>共享人设（System Prompt）</b><br/>用户未填写自己的人设时生效</td>
            <td><textarea class="form-control" name="ver4_ai_persona" style="height:90px"><?php echo $aiPersona; ?></textarea></td>
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
    <b>生成测试</b>（使用共享配置生成一个主题帖，但不实际发帖）：
    <input type="text" class="form-control" name="ver4_ai_test_kw" placeholder="贴吧名，如：来日易在心中" style="width:200px" required>
    <input type="submit" class="btn btn-success" name="ver4_ai_test" value="测试生成">
</form>
<p class="text-muted" style="margin-top:10px;">
    用户在个人面板的「<a href="index.php?plugin=ver4_ai">AI 发帖/回帖</a>」页配置自己的大模型与目标贴吧；
    计划任务 <code>ver4_ai_do</code> <?php echo $cronOn ? '<font color="green"><b>已注册</b></font>' : '<font color="red"><b>未注册（请重新激活插件）</b></font>'; ?>，
    请在 <a href="index.php?mod=admin:cron">计划任务</a> 页确认其处于启用状态（建议每 5 分钟执行一次）。
</p>
<div class="alert alert-warning">
    <b>风控提示（实测）：</b>数据中心 IP（如云服务器）发布的主题帖与回帖会被百度反垃圾「秒删」——接口返回成功但帖子随即被移除，属百度侧行为。
    缓解方式：在「百度账号」页为账号配置<b>住宅代理</b>（AI 发帖/回帖遵循账号级代理设置），或降低发帖频率、提升账号在目标吧的等级与活跃度后逐步放量。
</div>
