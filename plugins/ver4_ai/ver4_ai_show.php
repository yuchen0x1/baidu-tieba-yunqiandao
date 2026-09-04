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
    ver4_ai_uopt_set($uid, 'uai_style_emoji', $_POST['uai_style_emoji'] === 'ban' ? 'ban' : 'allow');
    ver4_ai_uopt_set($uid, 'uai_style_length', $_POST['uai_style_length'] === 'short' ? 'short' : ($_POST['uai_style_length'] === 'long' ? 'long' : 'medium'));
    ver4_ai_uopt_set($uid, 'uai_style_tags', trim((string) $_POST['uai_style_tags']));
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
    ver4_ai_uopt_set($uid, 'uai_thread_semi', $_POST['uai_thread_semi'] == '1' ? '1' : '0');
    ver4_ai_uopt_set($uid, 'uai_thread_topic', trim((string) $_POST['uai_thread_topic']));
    ver4_ai_uopt_set($uid, 'uai_thread_pids', trim((string) $_POST['uai_thread_pids']));
    redirect('index.php?plugin=ver4_ai&success=' . urlencode('设置已保存，AI 任务将按计划自动执行'));
}

//人设模板：另存为我的模板
if (isset($_POST['uai_tpl_save'])) {
    csrf(true, true);
    $tpls = ver4_ai_tpl_decode(option::uget('uai_persona_templates', $uid));
    $tpls = ver4_ai_tpl_add($tpls, isset($_POST['uai_tpl_name']) ? $_POST['uai_tpl_name'] : '', isset($_POST['uai_tpl_text']) ? $_POST['uai_tpl_text'] : '');
    ver4_ai_uopt_set($uid, 'uai_persona_templates', json_encode($tpls, JSON_UNESCAPED_UNICODE));
    redirect('index.php?plugin=ver4_ai&success=' . urlencode('人设模板已保存，可在「我的模板」中选用'));
}

//人设模板：删除我的模板
if (isset($_POST['uai_tpl_del'])) {
    csrf(true, true);
    $tpls = ver4_ai_tpl_decode(option::uget('uai_persona_templates', $uid));
    $tpls = ver4_ai_tpl_remove($tpls, isset($_POST['uai_tpl_index']) ? (int) $_POST['uai_tpl_index'] : -1);
    ver4_ai_uopt_set($uid, 'uai_persona_templates', json_encode($tpls, JSON_UNESCAPED_UNICODE));
    redirect('index.php?plugin=ver4_ai&success=' . urlencode('人设模板已删除'));
}

//发帖草稿箱：发布一条候选（半自动模式）
if (isset($_POST['uai_draft_pub'])) {
    csrf(true, true);
    ver4_ai_ensure_table();
    ver4_ai_ensure_draft_table();
    $draftId = isset($_POST['uai_draft_id']) ? (int) $_POST['uai_draft_id'] : 0;
    //先原子占位（status 0→1），并发/双击只有一次请求能拿到发布权
    $m->pquery("UPDATE `" . DB_PREFIX . "ver4_ai_draft` SET `status` = 1, `published_at` = ? WHERE `id` = ? AND `uid` = ? AND `status` = 0", array(time(), $draftId, $uid));
    if ((int) $m->affected_rows() !== 1) {
        redirect('index.php?plugin=ver4_ai&success=' . urlencode('草稿不存在或已处理，请刷新页面'));
    }
    $draft = ver4_ai_draft_owned($uid, $draftId);
    $accRow = $m->once_fetch_array("SELECT `id`, `bduss` FROM `" . DB_PREFIX . "baiduid` WHERE `id` = " . (int) $draft['pid'] . " AND `uid` = {$uid}");
    if (empty($accRow['id'])) {
        $m->pquery("UPDATE `" . DB_PREFIX . "ver4_ai_draft` SET `status` = 0, `published_at` = 0 WHERE `id` = ?", array($draftId));
        redirect('index.php?plugin=ver4_ai&success=' . urlencode('该草稿所属的百度账号已不存在'));
    }
    $pubBduss = P::decrypt($accRow['bduss']);
    $pubTbs = empty($pubBduss) ? '' : misc::getTbs(0, $pubBduss, (int) $draft['pid']);
    $pubFid = (int) misc::getFid((string) $draft['kw']);
    $day = date('Ymd');
    if (empty($pubTbs) || $pubFid <= 0) {
        $m->pquery("UPDATE `" . DB_PREFIX . "ver4_ai_draft` SET `status` = 0, `published_at` = 0 WHERE `id` = ?", array($draftId));
        ver4_ai_insert_log((int) $draft['pid'], 0, (string) $draft['kw'], 1, 2, '半自动发布失败：账号凭据失效或贴吧不存在 @' . $draft['title'], $day, md5(''));
        redirect('index.php?plugin=ver4_ai&success=' . urlencode('发布失败：账号凭据可能已失效，或目标贴吧不存在'));
    }
    list($pubCode, $pubMsg, $newTid) = ver4_ai_post_thread($pubBduss, $pubTbs, (string) $draft['kw'], $pubFid, (string) $draft['title'], (string) $draft['content'], (int) $draft['pid']);
    if ($pubCode === 0) {
        $m->pquery("UPDATE `" . DB_PREFIX . "ver4_ai_draft` SET `tid` = ? WHERE `id` = ?", array((int) $newTid, $draftId));
        ver4_ai_insert_log((int) $draft['pid'], (int) $newTid, (string) $draft['kw'], 1, 1, '半自动发布成功 @' . $draft['title'], $day, md5($draft['title'] . $draft['content']));
        redirect('index.php?plugin=ver4_ai&success=' . urlencode('发布成功：《' . $draft['title'] . '》'));
    } else {
        //发布失败回滚为待发布，用户可重试
        $m->pquery("UPDATE `" . DB_PREFIX . "ver4_ai_draft` SET `status` = 0, `published_at` = 0 WHERE `id` = ?", array($draftId));
        ver4_ai_insert_log((int) $draft['pid'], 0, (string) $draft['kw'], 1, 2, '半自动发布失败：' . $pubMsg . ' @' . $draft['title'], $day, md5(''));
        redirect('index.php?plugin=ver4_ai&success=' . urlencode('发布失败：' . $pubMsg . '（草稿已保留，可稍后重试）'));
    }
}

//发帖草稿箱：丢弃一条候选
if (isset($_POST['uai_draft_drop'])) {
    csrf(true, true);
    $draft = ver4_ai_draft_owned($uid, isset($_POST['uai_draft_id']) ? (int) $_POST['uai_draft_id'] : 0);
    if ($draft !== false && (int) $draft['status'] === 0) {
        $m->pquery("UPDATE `" . DB_PREFIX . "ver4_ai_draft` SET `status` = 2 WHERE `id` = ?", array((int) $draft['id']));
    }
    redirect('index.php?plugin=ver4_ai&success=' . urlencode('候选已丢弃'));
}

//生成测试（用当前用户解析出的配置，不实际发帖）
if (isset($_POST['uai_test'])) {
    csrf(true, true);
    $testCfg = ver4_ai_user_cfg($uid);
    if ($testCfg === false) {
        echo '<div class="alert alert-danger">测试失败：请先保存设置并配置 API Key' . ($sharedAllowed && $hasShared ? '（或使用站点共享配置并开启总开关）' : '') . '</div>';
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
$aiUrl = htmlspecialchars($g('uai_api_url', ver4_ai_cfg('ver4_ai_api_url', 'https://open.bigmodel.cn/api/paas/v4')), ENT_QUOTES, 'UTF-8');
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
$threadSemi = $g('uai_thread_semi', '0');

//我的自定义人设模板
$myTpls = ver4_ai_tpl_decode(option::uget('uai_persona_templates', $uid));

//发帖草稿箱（半自动模式候选）
ver4_ai_ensure_table();
ver4_ai_ensure_draft_table();
$drafts = array();
$draftRs = $m->query("SELECT * FROM `" . DB_PREFIX . "ver4_ai_draft` WHERE `uid` = {$uid} AND `status` = 0 ORDER BY `created_at` DESC, `id` DESC LIMIT 15");
while ($d = $m->fetch_array($draftRs)) {
    $drafts[] = $d;
}
$draftHistory = array();
$draftHisRs = $m->query("SELECT `kw`, `title`, `status`, `tid`, `published_at` FROM `" . DB_PREFIX . "ver4_ai_draft` WHERE `uid` = {$uid} AND `status` != 0 ORDER BY `id` DESC LIMIT 8");
while ($d = $m->fetch_array($draftHisRs)) {
    $draftHistory[] = $d;
}

//内容审查拦截记录（status=5：敏感词/重复跳过）
$auditRows = array();
$auditRs = $m->query("SELECT `l`.`pid`, `l`.`kw`, `l`.`type`, `l`.`msg`, `l`.`created_at` FROM `" . DB_PREFIX . "ver4_ai_log` `l` INNER JOIN `" . DB_PREFIX . "baiduid` `b` ON `b`.`id` = `l`.`pid` WHERE `b`.`uid` = {$uid} AND `l`.`status` = 5 ORDER BY `l`.`id` DESC LIMIT 20");
while ($r = $m->fetch_array($auditRs)) {
    $auditRows[] = $r;
}

//状态解析
$resolved = ver4_ai_user_cfg($uid);
if ($hasOwnKey) {
    $sourceText = '我的 API Key';
} elseif ($sharedAllowed && $hasShared) {
    $sourceText = '站点共享配置';
} else {
    $sourceText = '未配置';
}
$statusBadge = $aiEnable == '1'
    ? ($resolved !== false ? '<span class="cs-badge cs-badge-success">运行中</span>' : '<span class="cs-badge cs-badge-danger">已启用但配置不可用</span>')
    : '<span class="cs-badge cs-badge-neutral">未开启</span>';

//我的百度账号列表
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

<div class="cs-alert cs-alert-info" role="alert" style="margin:0 1%">
    <svg class="cs-alert-icon" aria-hidden="true"><use href="#i-info"/></svg>
    <div class="cs-alert-body">
        当前状态：<?php echo $statusBadge; ?>　·　配置来源：<?php echo $sourceText; ?>　·　我的账号：<?php echo count($myAccounts); ?> 个
        <br/>接入你自己的大模型（OpenAI 兼容），AI 将代替你的百度账号在目标贴吧定时发主题帖、智能回帖。
    </div>
</div>

<?php if (isset($_GET['success'])): ?>
<div class="alert alert-success" style="margin:10px 1% 0"><?php echo htmlspecialchars((string) $_GET['success'], ENT_QUOTES, 'UTF-8'); ?></div>
<?php endif; ?>

<?php if (!empty($drafts)): ?>
<h4 style="margin:20px 1% 8px">📮 发帖草稿箱（半自动模式候选，7 天后自动清理）</h4>
<div style="margin:0 1%">
<div class="cs-table-wrap"><div class="cs-table-scroll">
<table class="table table-striped">
    <tr><th style="width:110px">目标贴吧</th><th>候选内容</th><th style="width:150px">生成时间</th><th style="width:170px">操作</th></tr>
    <?php foreach ($drafts as $d): ?>
    <tr>
        <td><b><?php echo htmlspecialchars((string) $d['kw'], ENT_QUOTES, 'UTF-8'); ?>吧</b><br/><span class="text-muted" style="font-size:12px">账号 ID <?php echo (int) $d['pid']; ?></span></td>
        <td><b><?php echo htmlspecialchars((string) $d['title'], ENT_QUOTES, 'UTF-8'); ?></b><br/><span style="white-space:pre-wrap;color:#555;font-size:13px"><?php echo htmlspecialchars(mb_substr((string) $d['content'], 0, 160), ENT_QUOTES, 'UTF-8'); ?><?php echo mb_strlen((string) $d['content']) > 160 ? '…' : ''; ?></span></td>
        <td><?php echo htmlspecialchars(date('m-d H:i', (int) $d['created_at']), ENT_QUOTES, 'UTF-8'); ?></td>
        <td>
            <form method="post" action="index.php?plugin=ver4_ai" style="display:inline" data-confirm="确认把《<?php echo htmlspecialchars((string) $d['title'], ENT_QUOTES, 'UTF-8'); ?>》发布到 <?php echo htmlspecialchars((string) $d['kw'], ENT_QUOTES, 'UTF-8'); ?>吧 吗？">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">
                <input type="hidden" name="uai_draft_id" value="<?php echo (int) $d['id']; ?>">
                <input type="submit" class="btn btn-success btn-sm" name="uai_draft_pub" value="发布">
            </form>
            <form method="post" action="index.php?plugin=ver4_ai" style="display:inline" data-confirm="确定丢弃这条候选吗？">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">
                <input type="hidden" name="uai_draft_id" value="<?php echo (int) $d['id']; ?>">
                <input type="submit" class="btn btn-default btn-sm" name="uai_draft_drop" value="丢弃">
            </form>
        </td>
    </tr>
    <?php endforeach; ?>
</table>
</div></div>
</div>
<?php elseif ($threadSemi == '1'): ?>
<div class="alert alert-info" style="margin:10px 1% 0">半自动模式已开启：计划任务到点后会为「目标贴吧」各生成 3 条候选，出现在这里的「发帖草稿箱」供你挑选发布。当前暂无待发布候选。</div>
<?php endif; ?>

<?php if (!empty($draftHistory)): ?>
<p style="margin:6px 1%">最近处理：<span class="text-muted" style="font-size:12px">
    <?php foreach ($draftHistory as $i => $h): ?>
        <?php echo ($i > 0 ? '　|　' : '') . htmlspecialchars((string) $h['kw'], ENT_QUOTES, 'UTF-8') . '吧《' . htmlspecialchars(mb_substr((string) $h['title'], 0, 14), ENT_QUOTES, 'UTF-8') . '》' . ((int) $h['status'] === 1 ? '<span class="cs-badge cs-badge-success">已发布</span>' : '<span class="cs-badge cs-badge-neutral">已丢弃</span>'); ?>
    <?php endforeach; ?>
</span></p>
<?php endif; ?>

<form method="post" action="index.php?plugin=ver4_ai">
    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">
    <table class="table table-striped form-table">
        <tr><th colspan="2"><h4>① 开关与模型服务</h4></th></tr>
        <tr>
            <td style="width:35%"><b>AI 功能总开关</b></td>
            <td>
                <select name="uai_enable" class="form-control" style="width:140px">
                    <option value="0" <?php echo $aiEnable != '1' ? 'selected' : ''; ?>>关闭</option>
                    <option value="1" <?php echo $aiEnable == '1' ? 'selected' : ''; ?>>开启</option>
                </select>
            </td>
        </tr>
        <tr>
            <td><b>服务商预设</b><br/>点击自动填入接口地址与常用模型</td>
            <td>
                <button type="button" class="cs-btn cs-btn-secondary cs-btn-sm ai-preset" data-url="https://open.bigmodel.cn/api/paas/v4" data-models="glm-5.3-flash,glm-4.6,glm-4.5-flash,glm-4-flash-250414">智谱 GLM</button>
                <button type="button" class="cs-btn cs-btn-secondary cs-btn-sm ai-preset" data-url="https://open.bigmodel.cn/api/coding/paas/v4" data-models="glm-5.3-flash">智谱编码套餐</button>
                <button type="button" class="cs-btn cs-btn-secondary cs-btn-sm ai-preset" data-url="https://api.deepseek.com/v1" data-models="deepseek-chat,deepseek-reasoner">DeepSeek</button>
                <button type="button" class="cs-btn cs-btn-secondary cs-btn-sm ai-preset" data-url="https://dashscope.aliyuncs.com/compatible-mode/v1" data-models="qwen-plus,qwen-turbo,qwen-max">通义千问</button>
                <button type="button" class="cs-btn cs-btn-secondary cs-btn-sm ai-preset" data-url="https://api.moonshot.cn/v1" data-models="moonshot-v1-8k,kimi-k2-0711-preview">月之暗面</button>
                <button type="button" class="cs-btn cs-btn-secondary cs-btn-sm ai-preset" data-url="https://api.openai.com/v1" data-models="gpt-4o-mini,gpt-4o">OpenAI</button>
            </td>
        </tr>
        <tr>
            <td><b>API 地址</b><br/>填到版本号为止</td>
            <td><input type="text" class="form-control" name="uai_api_url" id="uai_api_url" value="<?php echo $aiUrl; ?>" placeholder="https://open.bigmodel.cn/api/paas/v4"></td>
        </tr>
        <tr>
            <td><b>API Key</b><br/>仅自己可见，加密存储；留空表示保持不变<?php echo $hasOwnKey ? '（已配置）' : '（未配置）'; ?>
                <?php if ($sharedAllowed && $hasShared): ?><br/><span class="text-muted">站长已开放共享配置：不填 Key 将自动使用站点共享配置。</span><?php endif; ?>
            </td>
            <td><input type="password" class="form-control" name="uai_api_key" value="" placeholder="<?php echo $hasOwnKey ? '已配置，输入新值可覆盖' : 'sk-...'; ?>" autocomplete="new-password"></td>
        </tr>
        <tr>
            <td><b>模型名称</b><br/>点击输入框可从常用模型中选择</td>
            <td><input type="text" class="form-control" name="uai_model" id="uai_model" list="uai-model-list" value="<?php echo $aiModel; ?>" placeholder="glm-5.3-flash">
                <datalist id="uai-model-list">
                    <option value="glm-5.3-flash"><option value="glm-4.6"><option value="glm-4.5-flash"><option value="glm-4-flash-250414">
                    <option value="deepseek-chat"><option value="deepseek-reasoner">
                    <option value="qwen-plus"><option value="qwen-turbo"><option value="qwen-max">
                    <option value="moonshot-v1-8k"><option value="gpt-4o-mini"><option value="gpt-4o">
                </datalist>
            </td>
        </tr>
        <tr>
            <td><b>温度 / 最大 Token</b><br/>温度越高内容越发散；Token 上限建议 ≥1000（推理模型思考也占用）</td>
            <td>
                <input type="number" min="0" max="2" step="0.1" class="form-control" name="uai_temperature" value="<?php echo $aiTemp; ?>" style="width:120px;display:inline-block;">
                <input type="number" min="256" max="8000" step="64" class="form-control" name="uai_max_tokens" value="<?php echo $aiMaxTokens; ?>" style="width:140px;display:inline-block;">
            </td>
        </tr>
        <tr>
            <td><b>我的 AI 人设</b><br/>决定发帖回帖的说话风格，留空使用站点共享人设</td>
            <td>
                <select class="form-control" id="uai-persona-preset" style="width:260px;margin-bottom:6px">
                    <option value="">— 选择人设模板快速填充 —</option>
                    <?php if (!empty($myTpls)): ?>
                    <optgroup label="我的模板">
                        <?php foreach ($myTpls as $ti => $tpl): ?>
                        <option value="u:<?php echo $ti; ?>"><?php echo htmlspecialchars($tpl['name'], ENT_QUOTES, 'UTF-8'); ?></option>
                        <?php endforeach; ?>
                    </optgroup>
                    <?php endif; ?>
                    <optgroup label="日常社交">
                        <option value="tpl-default">贴吧老哥（日常水贴）</option>
                        <option value="tpl-warm">治愈系（温暖正能量）</option>
                        <option value="tpl-humor">段子手（幽默玩梗）</option>
                        <option value="tpl-lurker">潜水观察（言简意赅）</option>
                    </optgroup>
                    <optgroup label="内容创作">
                        <option value="tpl-knowledge">知识科普官（认真解答）</option>
                        <option value="tpl-digital">数码评测君（参数党）</option>
                        <option value="tpl-foodie">美食探店（吃货视角）</option>
                        <option value="tpl-game">游戏同好（开黑唠嗑）</option>
                        <option value="tpl-movie">影视追剧（剧评视角）</option>
                        <option value="tpl-office">职场吐槽（打工人视角）</option>
                        <option value="tpl-campus">校园日常（学生党）</option>
                    </optgroup>
                    <optgroup label="吧务运营">
                        <option value="tpl-baowu">吧务播报（正式通知）</option>
                        <option value="tpl-helper">热心互助（认真解答）</option>
                    </optgroup>
                </select>
                <textarea class="form-control" name="uai_persona" id="uai_persona" style="height:90px" placeholder="例如：你是一个热爱分享生活的贴吧老哥，说话自然、口语化…"><?php echo $aiPersona; ?></textarea>
                <div style="margin-top:6px">
                    <button type="button" class="cs-btn cs-btn-secondary cs-btn-sm" id="uai-tpl-save-btn">把当前人设存为我的模板</button>
                    <button type="button" class="cs-btn cs-btn-secondary cs-btn-sm" id="uai-tpl-del-btn"<?php echo empty($myTpls) ? ' disabled' : ''; ?>>删除选中的我的模板</button>
                    <span class="text-muted" style="font-size:12px">（最多保存 20 个，同名覆盖）</span>
                </div>
            </td>
        </tr>
        <tr>
            <td><b>内容风格</b><br/>emoji 与字数偏好，叠加在人设之上</td>
            <td>
                emoji：
                <select name="uai_style_emoji" class="form-control" style="width:120px;display:inline-block">
                    <option value="allow" <?php echo $g('uai_style_emoji', 'allow') == 'allow' ? 'selected' : ''; ?>>允许</option>
                    <option value="ban" <?php echo $g('uai_style_emoji', 'allow') == 'ban' ? 'selected' : ''; ?>>禁止</option>
                </select>
                　字数：
                <select name="uai_style_length" class="form-control" style="width:140px;display:inline-block">
                    <option value="short" <?php echo $g('uai_style_length', 'medium') == 'short' ? 'selected' : ''; ?>>偏短</option>
                    <option value="medium" <?php echo $g('uai_style_length', 'medium') == 'medium' ? 'selected' : ''; ?>>适中</option>
                    <option value="long" <?php echo $g('uai_style_length', 'medium') == 'long' ? 'selected' : ''; ?>>偏长</option>
                </select>
            </td>
        </tr>
        <tr>
            <td><b>话题标签</b><br/>发主题帖时自动附加在正文末尾，留空不附加</td>
            <td><input type="text" class="form-control" name="uai_style_tags" value="<?php echo htmlspecialchars($g('uai_style_tags'), ENT_QUOTES, 'UTF-8'); ?>" placeholder="例如：#日常分享# #记录生活#"></td>
        </tr>
        <tr><th colspan="2"><h4>② AI 智能回帖</h4></th></tr>
        <tr>
            <td><b>功能开关</b></td>
            <td>
                <select name="uai_reply_enable" class="form-control" style="width:140px">
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
                <select name="uai_reply_context" class="form-control" style="width:140px">
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
                <select name="uai_thread_enable" class="form-control" style="width:140px">
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
            <td><b>半自动模式</b><br/>开启后到点不再自动发帖，而是生成 3 条候选存入页面上方的「发帖草稿箱」，由你挑选一条手动发布</td>
            <td>
                <select name="uai_thread_semi" class="form-control" style="width:180px">
                    <option value="0" <?php echo $threadSemi != '1' ? 'selected' : ''; ?>>关闭（全自动发布）</option>
                    <option value="1" <?php echo $threadSemi == '1' ? 'selected' : ''; ?>>开启（生成候选待我发布）</option>
                </select>
            </td>
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
<div style="margin:0 1%">
<div class="cs-table-wrap"><div class="cs-table-scroll">
<table class="table table-striped">
    <tr><th>时间</th><th>账号</th><th>类型</th><th>结果</th><th>详情</th></tr>
    <?php foreach ($recentRows as $r): ?>
    <tr>
        <td><?php echo htmlspecialchars(date('m-d H:i', (int) $r['created_at']), ENT_QUOTES, 'UTF-8'); ?></td>
        <td>ID <?php echo (int) $r['pid']; ?></td>
        <td><?php echo (int) $r['type'] === 1 ? '发帖' : '回帖'; ?></td>
        <td><?php
            $st = (int) $r['status'];
            echo $st === 1 ? '<span class="cs-badge cs-badge-success">成功</span>' : ($st === 4 ? '<span class="cs-badge cs-badge-warning">生成失败</span>' : ($st === 3 ? '<span class="cs-badge cs-badge-warning">频率限制</span>' : ($st === 5 ? '<span class="cs-badge cs-badge-warning">审查拦截</span>' : ($st === 6 ? '<span class="cs-badge cs-badge-info">候选待发布</span>' : '<span class="cs-badge cs-badge-danger">失败</span>'))));
        ?></td>
        <td><?php echo htmlspecialchars(mb_substr((string) $r['msg'], 0, 60), ENT_QUOTES, 'UTF-8'); ?></td>
    </tr>
    <?php endforeach; ?>
</table>
</div></div>
<?php endif; ?>

<h4 style="margin-top:20px;">内容审查拦截记录 <span class="text-muted" style="font-size:12px">（命中敏感词或内容重复而被跳过的内容，最近 20 条，用于排查人设/敏感词配置）</span></h4>
<?php if (empty($auditRows)): ?>
<p class="text-muted" style="margin:6px 1%">暂无拦截记录：AI 生成的内容均通过了敏感词与重复检查。</p>
<?php else: ?>
<div style="margin:0 1%">
<div class="cs-table-wrap"><div class="cs-table-scroll">
<table class="table table-striped">
    <tr><th style="width:110px">时间</th><th style="width:70px">账号</th><th style="width:60px">类型</th><th style="width:80px">目标</th><th>拦截原因</th></tr>
    <?php foreach ($auditRows as $r): ?>
    <tr>
        <td><?php echo htmlspecialchars(date('m-d H:i', (int) $r['created_at']), ENT_QUOTES, 'UTF-8'); ?></td>
        <td>ID <?php echo (int) $r['pid']; ?></td>
        <td><?php echo (int) $r['type'] === 1 ? '发帖' : '回帖'; ?></td>
        <td><?php echo htmlspecialchars((string) $r['kw'] !== '' ? ((string) $r['kw'] . '吧') : '—', ENT_QUOTES, 'UTF-8'); ?></td>
        <td><?php echo htmlspecialchars((string) $r['msg'], ENT_QUOTES, 'UTF-8'); ?></td>
    </tr>
    <?php endforeach; ?>
</table>
</div></div>
</div>
<?php endif; ?>

<div class="alert alert-warning" style="margin-top:15px;">
    <b>温馨提示：</b>任务由系统计划任务每 5 分钟检查执行；请遵守目标贴吧的吧规，<span class="text-danger">请勿发布违法违规内容，后果由使用者自负</span>。
    若帖子发布成功但很快消失，多为百度风控（数据中心 IP 秒删），可尝试为账号配置住宅代理。
</div>
<script>
(function () {
    //服务商预设：一键填入接口地址与模型候选
    var urlInput = document.getElementById('uai_api_url');
    var modelInput = document.getElementById('uai_model');
    var datalist = document.getElementById('uai-model-list');
    Array.prototype.forEach.call(document.querySelectorAll('.ai-preset'), function (btn) {
        if (btn.dataset.csInit === '1') { return; }
        btn.dataset.csInit = '1';
        btn.addEventListener('click', function () {
            if (urlInput) { urlInput.value = btn.getAttribute('data-url') || ''; }
            var models = (btn.getAttribute('data-models') || '').split(',');
            if (datalist) {
                datalist.innerHTML = '';
                models.forEach(function (m) {
                    if (!m) { return; }
                    var opt = document.createElement('option');
                    opt.value = m.trim();
                    datalist.appendChild(opt);
                });
            }
            if (modelInput && models.length > 0) { modelInput.value = models[0].trim(); }
        });
    });
    //人设模板
    var templates = {
        'tpl-default': '你是一个百度贴吧的普通用户，说话自然、口语化、友善，像一个真实的贴吧老哥，熟悉贴吧文化。输出必须是纯中文文本，不要使用 markdown、不要加引号、不要提及你是AI或模型、不要用括号做解释。',
        'tpl-helper': '你是一个热心肠的贴吧用户，擅长解答各类问题，回复认真有条理，语气诚恳友善。输出必须是纯中文文本，不要使用 markdown、不要提及你是AI或模型。',
        'tpl-humor': '你是一个贴吧段子手，说话幽默风趣，偶尔玩梗接梗，但不过分夸张，像一个逗比又可爱的贴吧老哥。输出必须是纯中文文本，不要提及你是AI或模型。',
        'tpl-warm': '你是一个温暖治愈系的贴吧用户，说话温柔正能量，善于鼓励别人，偶尔分享生活中的小美好。输出必须是纯中文文本，不要提及你是AI或模型。',
        'tpl-baowu': '你是一个贴吧的吧务播报员，说话正式简洁、条理清晰，发布吧内通知与活动信息时用词规范。输出必须是纯中文文本，不要提及你是AI或模型。',
        'tpl-lurker': '你是一个长期潜水的贴吧老用户，话不多但每句都在点子上，言简意赅，偶尔用一句到位的短评表达观点。输出必须是纯中文文本，不要提及你是AI或模型。',
        'tpl-knowledge': '你是一个贴吧知识科普官，擅长把复杂的概念讲得通俗易懂，回复有理有据、结构清晰，语气耐心友善。输出必须是纯中文文本，不要使用 markdown、不要提及你是AI或模型。',
        'tpl-digital': '你是一个数码爱好者，熟悉手机、路由器、电脑等数码产品，聊技术时专业但不堆砌参数，像一个真实的参数党老哥。输出必须是纯中文文本，不要提及你是AI或模型。',
        'tpl-foodie': '你是一个美食探店爱好者，聊起吃的头头是道，描述食物时让人垂涎欲滴，语气热情接地气。输出必须是纯中文文本，不要提及你是AI或模型。',
        'tpl-game': '你是一个资深游戏玩家，熟悉各类游戏，聊游戏时热情投入，像开黑时和队友唠嗑一样自然。输出必须是纯中文文本，不要提及你是AI或模型。',
        'tpl-movie': '你是一个影视追剧爱好者，看过大量影视剧，聊剧情、聊演员都有自己的见解，不剧透关键情节。输出必须是纯中文文本，不要提及你是AI或模型。',
        'tpl-office': '你是一个职场打工人，偶尔吐槽加班和开会，但整体积极向上，分享职场经验和心得时真实不做作。输出必须是纯中文文本，不要提及你是AI或模型。',
        'tpl-campus': '你是在校大学生，分享校园日常、考试、社团生活，语气青春活泼，偶尔自嘲学业压力。输出必须是纯中文文本，不要提及你是AI或模型。'
    };
    var personaSelect = document.getElementById('uai-persona-preset');
    var personaInput = document.getElementById('uai_persona');
    var myTemplateTexts = <?php
        $tplTexts = array();
        foreach ($myTpls as $tpl) {
            $tplTexts[] = $tpl['text'];
        }
        echo json_encode($tplTexts, JSON_UNESCAPED_UNICODE);
    ?>;
    function submitHidden(action, fields) {
        var form = document.createElement('form');
        form.method = 'post';
        form.action = 'index.php?plugin=ver4_ai';
        var csrf = document.createElement('input');
        csrf.type = 'hidden';
        csrf.name = 'csrf_token';
        csrf.value = '<?php echo htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8'); ?>';
        form.appendChild(csrf);
        Object.keys(fields).forEach(function (name) {
            var input = document.createElement('input');
            input.type = 'hidden';
            input.name = name;
            input.value = fields[name];
            form.appendChild(input);
        });
        var submit = document.createElement('input');
        submit.type = 'hidden';
        submit.name = action;
        submit.value = '1';
        form.appendChild(submit);
        document.body.appendChild(form);
        form.submit();
    }
    if (personaSelect && personaInput) {
        personaSelect.addEventListener('change', function () {
            var v = personaSelect.value;
            if (v.indexOf('u:') === 0) {
                var idx = parseInt(v.slice(2), 10);
                if (myTemplateTexts[idx] !== undefined) { personaInput.value = myTemplateTexts[idx]; }
            } else if (v && templates[v]) {
                personaInput.value = templates[v];
            }
        });
    }
    var tplSaveBtn = document.getElementById('uai-tpl-save-btn');
    if (tplSaveBtn) {
        tplSaveBtn.addEventListener('click', function () {
            var text = personaInput ? personaInput.value.trim() : '';
            if (!text) { alert('请先在下方输入框写好人设内容，再保存为模板'); return; }
            var name = prompt('给这个人设模板起个名字（最多 30 字）：', '');
            if (name === null) { return; }
            name = name.trim();
            if (!name) { alert('模板名不能为空'); return; }
            submitHidden('uai_tpl_save', { 'uai_tpl_name': name, 'uai_tpl_text': text });
        });
    }
    var tplDelBtn = document.getElementById('uai-tpl-del-btn');
    if (tplDelBtn) {
        tplDelBtn.addEventListener('click', function () {
            var v = personaSelect ? personaSelect.value : '';
            if (v.indexOf('u:') !== 0) { alert('请先在下拉框的「我的模板」分组中选中要删除的模板'); return; }
            var name = personaSelect.options[personaSelect.selectedIndex] ? personaSelect.options[personaSelect.selectedIndex].text : '';
            if (!confirm('确定删除我的模板「' + name + '」吗？')) { return; }
            submitHidden('uai_tpl_del', { 'uai_tpl_index': String(parseInt(v.slice(2), 10)) });
        });
    }

    // 带确认文案的表单：confirm 文本从 data-confirm 属性读取，避免把动态文本拼进 JS 字符串
    Array.prototype.forEach.call(document.querySelectorAll('form[data-confirm]'), function (form) {
        if (form.dataset.csConfirm === '1') { return; }
        form.dataset.csConfirm = '1';
        form.addEventListener('submit', function (e) {
            if (!window.confirm(form.getAttribute('data-confirm'))) { e.preventDefault(); }
        });
    });
})();
</script>
<?php loadfoot(); ?>
