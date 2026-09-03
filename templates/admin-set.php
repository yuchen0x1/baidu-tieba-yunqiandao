<?php if (!defined('SYSTEM_ROOT')) {
    die('Insufficient Permissions');
}  if (ROLE != 'admin') {
    msg('权限不足！');
}
global $m,$i;

if (isset($_GET['ok'])) {
    echo '<div class="alert alert-success">设置保存成功</div>';
} elseif (isset($_GET['mailtestok'])) {
    echo '<div class="alert alert-success">一封邮件已经发送到您的邮箱 ' . $i['user']['email'] . '，请查收</div>';
} elseif (isset($_GET['bbstestok'])) {
    echo '<div class="alert alert-success">成功登陆到产品中心</div>';
}

if (isset($i['mode'][2]) && $i['mode'][2] == 'sign') {
    /*准备*/
    ?>
    <ul class="cs-tablist" role="tablist">
      <li><a href="index.php?mod=admin:set">全局设置</a></li>
      <li class="active"><a href="index.php?mod=admin:set:sign">签到设置</a></li>
    </ul>
    <script>
        function viewSignScanModeHelp() {
            str  = '该设置影响签到效率，不当的设置可能会导致效率降低并漏签<br/><br/>';
            str += '<b>1.永不随机，按顺序抽取</b><br/>该模式是数据库性能最高的模式，不会有贴吧漏签，采取"先来后到"的签到原则。但是如果启用了多线程功能，这会导致一个贴吧被重复签到多次，导致整体签到效率降低<br/><br/>';
            str += '<b>2.随机，使用 JOIN()</b><br/>该模式的数据库性能仅次于永不随机模式，随机抽取没有签到的贴吧并为其签到。多线程模式下可以较好地分配签到任务到每个线程，但是可能会有极少数贴吧漏签；此外，如果频繁清空和刷入新贴吧，也会增加漏签率<br/><br/>';
            str += '<b>3.随机，使用 ORDER BY RAND()</b><br/>能够很好地分配签到任务到每个线程，随机抽取没有签到的贴吧并为其签到，不会有贴吧漏签。但是，一个表的贴吧数量越多，该模式的数据库性能就会越差，如果你能很好地使用分表功能，建议使用本选项';
            str += '<br/><br/><b><font color="red">注意：</font></b>此处所指的 "漏签"，是指因为没有被扫描到而引发的未签到，而不是签到失败';
            alert(str);
        }
    </script>
    <?php
    /*开始*/
    $set2['title'] = '签到设置';
    $set2['name'] = 'signset';
    $set2['url'] = 'setting.php?mod=admin:set&type=sign';
    $set2['method'] = '1';

    $content2['cron_limit'] = array('td1' => '<b>单表单次签到执行数量</b><br/>0为一次性全部签到。此功能非常重要，设置为0会导致每次都扫描贴吧表，效率极低，请按需修改','type' => 'number','text' => '注意这是控制单个表的，当你有N个表时，单次签到数量为 N × 分表数','extra' => 'min="0" step="1"');
    $content2['bduss_num'] = array('td1' => '<b>最大允许用户绑定账号数</b><br/>0为无限，-1为禁止绑定，对管理员无效','type' => 'number','text' => '','extra' => 'min="-1" step="1"');
    $content2['tb_max'] = array('td1' => '<b>最大关注贴吧数量</b><br/>0为不限,对管理员无效','type' => 'number','text' => '','extra' => 'min="0" step="1"');
    $bsphtml = '<tr><td><b>禁止重复添加同一百度账号</b><br/>禁止添加用户名一样的百度账号<br/>对管理员无效(自动处理除外)</td><td>
	            <label><input type="radio" name="same_pid" value="0" ' . (option::get('same_pid') == '0' ? 'checked' : '') . '> 不禁止(可以重复添加)</label><br/>
	            <label><input type="radio" name="same_pid" value="1" ' . (option::get('same_pid') == '1' ? 'checked' : '') . '> 仅禁止同一云签到账号重复添加</label><br/>
	            <label><input type="radio" name="same_pid" value="2" ' . (option::get('same_pid') == '2' ? 'checked' : '') . '> 全局禁止(一旦有用户添加则其他用户不能添加)</label>
				<label><input type="radio" name="same_pid" value="3" ' . (option::get('same_pid') == '3' ? 'checked' : '') . '> 自动处理(检查到同一百度帐号时更新BDUSS与STOKEN)</label>
	        </td>
	    </tr>';
    $content2['same_pid'] = array('html' => $bsphtml,'type' => 'else');
    $content2['retry_max'] = array('td1' => '<b>签到失败重试次数</b><br/>0为无限，-1为不重试','type' => 'number','text' => '','extra' => 'min="-1" step="1"');
    $content2['sign_hour'] = array('td1' => '<b>签到开始时间</b><br/>24小时制。例如设为-1，则从0点开始签到','type' => 'number','text' => '','extra' => 'min="-1" step="1" max="24"');
    $content2['sign_sleep'] = array('td1' => '<b>签到间隔时间</b><br/>单位为毫秒，0为不暂停','type' => 'number','text' => '适量的间隔时间可以防止签到过快而失败的问题，但会导致签到效率降低','extra' => 'min="0" step="1"');
    $content2['sign_jitter'] = array('td1' => '<b>签到随机抖动</b><br/>单位为毫秒，每次签到在间隔基础上随机追加 0~该值 的等待时间，0为关闭','type' => 'number','text' => '随机化请求节奏可以降低被百度风控识别的概率','extra' => 'min="0" step="1"');
    $content2['client_version'] = array('td1' => '<b>模拟客户端版本号</b><br/>客户端签到与贴吧扫描使用的版本号','type' => 'text','text' => '百度更新客户端后旧版本号可能触发风控，可在此手动更新；留空使用内置默认值','extra' => '');
    $rotateHtml = '<tr><td><b>客户端指纹轮换</b><br/><br/>每次请求随机切换客户端类型（1-4）并携带随机设备标识<br/>降低同一设备特征被百度风控识别的概率</td><td>
	            <label><input type="radio" name="client_rotate" value="1" ' . (option::get('client_rotate') != '0' ? 'checked' : '') . '> 开启轮换（推荐）</label>
	            <label><input type="radio" name="client_rotate" value="0" ' . (option::get('client_rotate') == '0' ? 'checked' : '') . '> 固定客户端类型</label>
	        </td></tr>';
    $content2['client_rotate'] = array('html' => $rotateHtml,'type' => 'else');
    $shuffleHtml = '<tr><td><b>签到乱序</b></td><td>
	            <label><input type="radio" name="sign_shuffle" value="1" ' . (option::get('sign_shuffle') != '0' ? 'checked' : '') . '> 随机乱序（推荐，降低风控特征）</label><br/>
	            <label><input type="radio" name="sign_shuffle" value="0" ' . (option::get('sign_shuffle') == '0' ? 'checked' : '') . '> 按固定顺序</label>
	        </td></tr>';
    $content2['sign_shuffle'] = array('html' => $shuffleHtml,'type' => 'else');
    $content2['enable_addtieba'] = array('td1' => '<b>允许手动添加贴吧</b>','type' => 'checkbox','text' => '开启后用户可以手动添加贴吧，添加时必须≤最大关注贴吧数量','extra' => '');
    $healthDisableHtml = '<tr><td><b>失效账号自动停用</b><br/><br/>BDUSS 健康检查连续失败达到阈值后，自动停止该账号所有贴吧的签到并推送提醒<br/>重新绑定账号后需在「贴吧与签到」页手动恢复</td><td>
	        <label><input type="radio" name="health_auto_disable" value="1" ' . (option::get('health_auto_disable') == '1' ? 'checked' : '') . '> 启用自动停用</label>
	        <label><input type="radio" name="health_auto_disable" value="0" ' . (option::get('health_auto_disable') != '1' ? 'checked' : '') . '> 关闭（仅提醒）</label>
	        <br/><br/><div class="input-group">
	        <span class="input-group-addon">连续失败阈值</span>
	        <input type="number" min="1" max="20" step="1" class="form-control" name="health_fail_max" value="' . htmlspecialchars(option::get('health_fail_max') ?: '3', ENT_QUOTES, 'UTF-8') . '">
	        </div>
	        </td></tr>';
    $content2['health_auto_disable'] = array('html' => $healthDisableHtml,'type' => 'else');
    require_once SYSTEM_ROOT . '/lib/security.php';
    $sign_mode = safe_sign_modes(option::get('sign_mode'));
    $smhtml = '<tr><td><b>签到模式设置</b><br/>选择多个将在某个模式失败后使用下一种<br/>启用的签到模式越多，消耗的流量和时间越多</td><td>
	            <label><input type="checkbox" name="sign_mode[]" value="1" ' . (in_array('1', $sign_mode) ? 'checked' : '') . '> 模拟手机客户端签到</label><br/>
	            <label><input type="checkbox" name="sign_mode[]" value="3" ' . (in_array('3', $sign_mode) ? 'checked' : '') . '> 手机网页签到</label><br/>
	            <label><input type="checkbox" name="sign_mode[]" value="2" ' . (in_array('2', $sign_mode) ? 'checked' : '') . '> 网页签到</label>
	        </td>
	    </tr>';
    $content2['sign_mode'] = array('html' => $smhtml,'type' => 'else');
    $content2['sign_scan'] = array('td1' => '<b>贴吧数据表搜寻方法</b><br/><br/><input type="button" class="cs-btn cs-btn-secondary" onclick="viewSignScanModeHelp();" value="查看帮助">','type' => 'select','text' => '<br/>该设置影响签到效率，不当的设置可能会导致效率降低并漏签');
    $content2['sign_scan']['select'] = array('0' => '永不随机，按顺序抽取','1' => '随机，使用 JOIN','2' => '随机，使用 ORDER BY RAND()');
    $ft1 = option::get('fb');
    if (!empty($i['tabpart'])) {
        $temp = '';
        foreach ($i['tabpart'] as $value) {
            $temp .= $value . "\n";
        }
        $ft2 = trim($temp, "\n");
        unset($value);
    } else {
        $ft2 = '';
    }
    $fthtml = '<tr><td><b>贴吧数据分表</b><br/><br/>全部留空为不分表<br/>每行一个表名，无需填写表前缀<br/>错误的设置将导致签到程序不能正常工作<br/>当某一表存储的贴吧记录数目明显超过设定值时才能生效<br/>单个用户将终生使用某一表，所以请设置小点<br/>当所有的表的记录都超过设定值时，新的贴吧将往最后一个表写</td><td>
		<div class="input-group">
			  <span class="input-group-addon">记录超过此行数时分表</span>
			  <input type="number" min="0" step="1" class="form-control" name="fb" value="' . htmlspecialchars($ft1, ENT_QUOTES, 'UTF-8') . '">
		</div><br/>
		<textarea class="form-control" style="height:150px" name="fb_tables">' . htmlspecialchars($ft2, ENT_QUOTES, 'UTF-8') . '</textarea>
		</td>
	</tr>';
    $content2['fb_tables'] = array('html' => $fthtml,'type' => 'else');
    $proxyType = option::get('proxy_type');
    $proxyHtml = '<tr><td><b>全局网络代理</b><br/><br/>签到与扫描请求统一经由代理发出<br/>账号列表页可为单个账号设置独立代理覆盖全局<br/>带鉴权格式：socks5://用户名:密码@主机:端口</td><td>
		<div class="input-group">
			  <span class="input-group-addon">代理类型</span>
			  <select name="proxy_type" class="form-control" onchange="if(this.value == \'0\') { $(\'#proxy_set\').hide(); } else { $(\'#proxy_set\').show(); }">
			    <option value="0"' . ($proxyType == '0' || $proxyType === null || $proxyType === false ? ' selected' : '') . '>不使用代理</option>
			    <option value="1"' . ($proxyType == '1' ? ' selected' : '') . '>HTTP(S) 代理</option>
			    <option value="2"' . ($proxyType == '2' ? ' selected' : '') . '>SOCKS5 代理</option>
			  </select>
		</div><br/>
		<div id="proxy_set" style="' . ($proxyType == '1' || $proxyType == '2' ? '' : 'display:none') . '">
		<div class="input-group">
			  <span class="input-group-addon">主机:端口</span>
			  <input type="text" class="form-control" name="proxy_host" value="' . htmlspecialchars(option::get('proxy_host'), ENT_QUOTES, 'UTF-8') . '" placeholder="例如 127.0.0.1:7890">
		</div><br/>
		<div class="input-group">
			  <span class="input-group-addon">用户名(可选)</span>
			  <input type="text" class="form-control" name="proxy_user" value="' . htmlspecialchars(option::get('proxy_user'), ENT_QUOTES, 'UTF-8') . '">
		</div><br/>
		<div class="input-group">
			  <span class="input-group-addon">密码(可选)</span>
			  <input type="text" class="form-control" name="proxy_pass" value="' . htmlspecialchars(option::get('proxy_pass'), ENT_QUOTES, 'UTF-8') . '">
		</div>
		</div>
		</td></tr>';
    $content2['proxy'] = array('html' => $proxyHtml,'type' => 'else');
    $damaType = option::get('dama_type');
    $damaHtml = '<tr><td><b>打码平台</b><br/><br/>绑定账号遇到验证码时自动提交打码平台识别<br/>目前支持超级鹰（chaojiying.com）</td><td>
		<div class="input-group">
			  <span class="input-group-addon">打码平台</span>
			  <select name="dama_type" class="form-control" onchange="if(this.value == \'none\') { $(\'#dama_set\').hide(); } else { $(\'#dama_set\').show(); }">
			    <option value="none"' . ($damaType == 'none' || $damaType === null || $damaType === false ? ' selected' : '') . '>不使用打码平台</option>
			    <option value="chaojiying"' . ($damaType == 'chaojiying' ? ' selected' : '') . '>超级鹰</option>
			  </select>
		</div><br/>
		<div id="dama_set" style="' . ($damaType == 'chaojiying' ? '' : 'display:none') . '">
		<div class="input-group">
			  <span class="input-group-addon">账号</span>
			  <input type="text" class="form-control" name="dama_username" value="' . htmlspecialchars(option::get('dama_username'), ENT_QUOTES, 'UTF-8') . '">
		</div><br/>
		<div class="input-group">
			  <span class="input-group-addon">密码</span>
			  <input type="text" class="form-control" name="dama_password" value="' . htmlspecialchars(option::get('dama_password'), ENT_QUOTES, 'UTF-8') . '">
		</div><br/>
		<div class="input-group">
			  <span class="input-group-addon">软件ID(可选)</span>
			  <input type="text" class="form-control" name="dama_softid" value="' . htmlspecialchars(option::get('dama_softid'), ENT_QUOTES, 'UTF-8') . '">
		</div>
		</div>
		</td></tr>';
    $content2['dama'] = array('html' => $damaHtml,'type' => 'else');
    echo former::create($set2, $content2);
} else {
    /*准备*/
    ?>
    <ul class="cs-tablist" role="tablist">
      <li class="active"><a href="index.php?mod=admin:set">全局设置</a></li>
      <li><a href="index.php?mod=admin:set:sign">签到设置</a></li>
    </ul>
    <?php
    /*开始*/
    $set1['title'] = '全局设置';
    $set1['name'] = 'systemset';
    $set1['url'] = 'setting.php?mod=admin:set&type=system';
    $set1['method'] = '1';

    $content1['system_url'] = array('td1' => '<b>站点地址</b><br/>后面必须带上 /','type' => 'text','text' => '','extra' => '');
    $content1['system_name'] = array('td1' => '<b>站点名称</b><br/>支持 HTML','type' => 'text','text' => '','extra' => '');
    $content1['system_keywords'] = array('td1' => '<b>关键字</b>(Keywords)<br/>SEO功能，以半角逗号(,)为分隔符','type' => 'text','text' => '','extra' => '');
    $content1['system_description'] = array('td1' => '<b>描述</b>(Description)<br/>SEO功能，以半角逗号(,)为分隔符','type' => 'text','text' => '','extra' => '');
    $footer = htmlspecialchars(option::get('footer'), ENT_QUOTES, 'UTF-8');
    $footerhtml = '<tr><td><b>自定义底部信息</b><br/><br/>支持 HTML</td><td>
		<textarea name="footer" class="form-control" style="height:200px">' . $footer . '</textarea>
		</td></tr>';
    $content1['footer'] = array('html' => $footerhtml,'type' => 'else');
    $ann = htmlspecialchars(option::get('ann'), ENT_QUOTES, 'UTF-8');
    $annhtml = '<tr><td><b>主页公告信息</b><br/><br/>较长公告建议以&lt;br/&gt;开头<br/>支持 HTML</td><td>
		<textarea name="ann" class="form-control" style="height:200px">' . $ann . '</textarea>
		</td></tr>';
    $content1['ann'] = array('html' => $annhtml,'type' => 'else');
    $content1['sign_multith'] = array('td1' => '<b>计划任务线程数</b><br/>0单线程，此为模拟多线程','type' => 'number','text' => '','extra' => 'min="0" step="1"');
    if (!function_exists('fsockopen')) {
        $content1['cron_asyn'] = array('td1' => '<b>计划任务同时运行</b><br/>在高配机器上会加速任务，低配机器上可能会导致减速','type' => 'checkbox','text' => '当 do.php 被运行时，所有计划任务同时运行，有效提高计划任务效率','extra' => '');
    } else {
        $content1['cron_asyn'] = array('html' => '<input type="number" name="cron_asyn" value="0" hidden>','type' => 'else');
    }
    $content1['cron_pw'] = array('td1' => '<b>计划任务密码</b><br/>留空为无密码，不能包含空格等特殊字符<br/><a href="javascript:;" onclick="alert(\'你需要通过访问 <b>do.php?pw=密码</b> 执行计划任务<br/>例如：' . SYSTEM_URL . 'do.php?pw=yourpassword<br/><br/>若您要通过命令行执行计划任务，请加上参数 <b>--pw=密码</b><br/>例如：php do.php --pw=yourpassword<br/>命令行模式注意：你需要指明do.php的绝对路径，或者将do.php加入PATH\')">帮助：启用密码功能后如何执行计划任务？</a>','type' => 'text','text' => '','extra' => '');
    $reg1 = option::get('enable_reg') == 1 ? ' checked' : '' ;
    $reg3 = option::get('yr_reg');
    $reg4 = option::get('stop_reg');
    $reghtml = '<tr><td><b>注册相关设置</b><br/><br/>邀请码框留空表示无需邀请码<br/><br/>停止注册提示框输入指定提示内容</td><td>
		<div class="input-group">
		<input type="checkbox" name="enable_reg" value="1"' . $reg1 . '> 允许用户注册
		</div><br/>
		<div class="input-group">
			<span class="input-group-addon">邀请码设置</span><input type="text" name="yr_reg" id="yr_reg" value="' . htmlspecialchars($reg3, ENT_QUOTES, 'UTF-8') . '" class="form-control">
		</div><br/>
		<label class="checkbox-inline"><input type="checkbox" name="reg_mail_verify" value="1"' . (option::get('reg_mail_verify') == '1' ? ' checked' : '') . '> 注册时需要邮箱验证（需先在下方配置邮件服务，验证邮件有效期为24小时）</label>
		<div class="input-group">
			<span class="input-group-addon">停止注册提示</span><input type="text" name="stop_reg" id="stop_reg" value="' . htmlspecialchars($reg4, ENT_QUOTES, 'UTF-8') . '" class="form-control">
		</div>
		</td></tr>';
    $content1['reg'] = array('html' => $reghtml,'type' => 'else');
    $oauthhtml = '<tr><td><b>第三方登录（GitHub OAuth）</b><br/>在 GitHub「Settings → Developer settings → OAuth Apps」创建应用，<br/>回调地址填写 <b>' . htmlspecialchars(SYSTEM_URL . 'index.php?mod=oauth:callback&provider=github', ENT_QUOTES, 'UTF-8') . '</b></td><td>
		<div class="input-group">
		<input type="checkbox" name="oauth_github_enable" value="1"' . (option::get('oauth_github_enable') == '1' ? ' checked' : '') . '> 启用 GitHub 登录
		</div><br/>
		<div class="input-group">
			<span class="input-group-addon">Client ID</span><input type="text" name="oauth_github_client_id" value="' . htmlspecialchars(option::get('oauth_github_client_id'), ENT_QUOTES, 'UTF-8') . '" class="form-control">
		</div><br/>
		<div class="input-group">
			<span class="input-group-addon">Client Secret</span><input type="text" name="oauth_github_client_secret" value="' . htmlspecialchars(option::get('oauth_github_client_secret'), ENT_QUOTES, 'UTF-8') . '" class="form-control">
		</div><br/>
		<label class="checkbox-inline"><input type="checkbox" name="oauth_auto_register" value="1"' . (option::get('oauth_auto_register') == '1' ? ' checked' : '') . '> 新 GitHub 用户自动注册本站账号（跳过邀请码，仅在上方"允许用户注册"开启时生效）</label>
		</td></tr>';
    $content1['oauth'] = array('html' => $oauthhtml,'type' => 'else');
    if (function_exists('imagecreatetruecolor')) {
        $captchahtml = '<tr><td><b>注册/登录验证码</b><br/>可防止恶意用户爆破数据库，提升安全性<br/>点选难度可以查看示例。<br>';
        $captchahtml .= '<img alt="验证码" id="captcha" class="img-thumbnail" style="cursor:pointer;display:none;" alt="示例验证码加载失败">';
        $captchahtml .= '</td><td>
        <label><input type="radio" name="captcha" value="0" ' . (option::get('captcha') == 0 ? ' checked' : '') . '> 关闭</label><br>
        <label><input type="radio" name="captcha" value="1" ' . (option::get('captcha') == 1 ? ' checked' : '') . '> 简单</label> (容易被破解)<br>
        <label><input type="radio" name="captcha" value="2" ' . (option::get('captcha') == 2 ? ' checked' : '') . '> 中等</label> (可能被破解)<br>
        <label><input type="radio" name="captcha" value="3" ' . (option::get('captcha') == 3 ? ' checked' : '') . '> 困难</label> (推荐)<br>
        <label><input type="radio" name="captcha" value="4" ' . (option::get('captcha') == 4 ? ' checked' : '') . '> 极难</label> (难以辨识)
		</td></tr>';
        $content1['captcha'] = array('html' => $captchahtml,'type' => 'else');
    } else {
        $content1['captcha'] = array('html' => '<input type="number" name="captcha" value="0" hidden>','type' => 'else');
    }
    $content1['icp'] = array('td1' => '<b>ICP 备案信息</b><br/>没有请留空','type' => 'text','text' => '','extra' => '');
    $content1['cktime'] = array('td1' => '<b>Cookie有效期</b><br/>单位为秒，过大会导致浏览器无法记录','type' => 'number','text' => '','extra' => 'step="1" min="1"');
    $content1['dev'] = array('td1' => '<b>开发者模式</b>','type' => 'checkbox','text' => '生产环境下请勿开启','extra' => '');
        /*警告：超长内容*/
    //内容较长时用缓冲区更方便
    ob_start();
    ?>
        <tr><td><b>邮件综合设置</b>
        <br/><br/><input type="button" class="cs-btn cs-btn-secondary" onclick="location = '<?php echo SYSTEM_URL; ?>setting.php?mod=admin:testmail'" value="测试邮件发送">
        <br/><br/>测试前请先保存设置
        <br/><br/>无加密的SMTP服务器端口号通常为 25
        <br/>SSL加密的SMTP服务器端口号通常为 465
        <br/>TLS加密的SMTP服务器端口号通常为 587
        <br/><br/>身份验证通常为 LOGIN
        </td><td>
        <div class="input-group">
              <span class="input-group-addon">邮件发送模式</span>
              <select name="mail_mode" class="form-control"  onchange="if(this.value == 'SMTP') { $('#smtp_set').show(); } else { $('#smtp_set').hide(); }">
                <option value="MAIL" <?php if (option::get('mail_mode') == 'MAIL') {
                    echo 'selected';
                                     } ?>>PHP Mail 函数 [ 不支持附件 ]</option>
                <option value="SMTP" <?php if (option::get('mail_mode') == 'SMTP') {
                    echo 'selected';
                                     } ?>>SMTP [ 支持验证 ]</option>
              </select>
            </div><br/>

            <div class="input-group">
              <span class="input-group-addon">发件人邮箱</span>
              <input type="email" name="mail_name" class="form-control"  value="<?php echo htmlspecialchars(option::get('mail_name'), ENT_QUOTES, 'UTF-8') ?>">
            </div><br/>

            <div class="input-group">
                <span class="input-group-addon">发件人名称</span>
                <input type="text" name="mail_yourname" class="form-control" value="<?php echo htmlspecialchars(option::get('mail_yourname'), ENT_QUOTES, 'UTF-8') ?>" >
            </div><br/>

            <div id="smtp_set" <?php if (option::get('mail_mode') != 'SMTP') {
                echo 'style="display:none;"';
                               } ?>>
                <div class="input-group">
                    <span class="input-group-addon">SMTP服务器地址</span>
                    <input type="text" name="mail_host" class="form-control"  value="<?php echo htmlspecialchars(option::get('mail_host'), ENT_QUOTES, 'UTF-8') ?>">
                </div><br/>
                <div class="input-group">
                    <span class="input-group-addon">SMTP服务器端口</span>
                    <input type="number" name="mail_port" class="form-control"  value="<?php echo htmlspecialchars(option::get('mail_port'), ENT_QUOTES, 'UTF-8') ?>">
                </div><br/>
                <div class="input-group">
                    <span class="input-group-addon">加密方式</span>
                    <select name="mail_secure" class="form-control">
                    <option value="none" <?php if (option::get('mail_secure') == 'none') {
                        echo 'selected';
                                         } ?>>无</option>
                    <option value="ssl" <?php if (option::get('mail_secure') == 'ssl') {
                        echo 'selected';
                                        } ?>>SSL</option>
                    <option value="tls" <?php if (option::get('mail_secure') == 'tls') {
                        echo 'selected';
                                        } ?>>TLS</option>
                  </select>
                </div>
                <br/><div class="input-group">
                  <span class="input-group-addon">身份验证</span>
                  <select name="mail_auth" class="form-control"  onchange="if(this.value == '0') { $('#smtp_set_auth').hide(); } else { $('#smtp_set_auth').show(); }">
                    <option value="1" <?php if (option::get('mail_auth') == '1') {
                        echo 'selected';
                                      } ?>>LOGIN</option>
                    <option value="2" <?php if (option::get('mail_auth') == '2') {
                        echo 'selected';
                                      } ?>>PLAIN</option>
                    <option value="0" <?php if (option::get('mail_auth') == '0') {
                        echo 'selected';
                                      } ?>>无</option>
                  </select>
                </div><br/>

                <div id="smtp_set_auth" <?php if (option::get('mail_auth') == '0') {
                    echo 'style="display:none;"';
                                        } ?>>
                    <div class="input-group">
                      <span class="input-group-addon">SMTP用户名</span>
                      <input type="text" name="mail_smtpname" class="form-control" value="<?php echo htmlspecialchars(option::get('mail_smtpname'), ENT_QUOTES, 'UTF-8') ?>">
                    </div><br/>

                    <div class="input-group">
                      <span class="input-group-addon">SMTP密码</span>
                         <input type="text" onclick="$(this).attr('value','').attr('type','password').attr('name','mail_smtppw').removeAttr('readonly');" class="form-control" readonly value="保持原密码 ( 点击可以修改 )">
                    </div><br/>
                </div>
            </div>
        </td>
        </tr>
    <?php
    $mailhtml = ob_get_clean();
    $content1['mail'] = array('html' => $mailhtml,'type' => 'else');
    /*end 超长内容*/
    /*通知设置*/
    ob_start();
    $notifyChannel = option::get('notify_channel');
    $notifyEnable = option::get('notify_enable');
    $sel = function ($value) use ($notifyChannel) {
        return $notifyChannel == $value ? ' selected' : '';
    };
    ?>
        <tr><td><b>通知设置</b>
        <br/><br/><input type="button" class="cs-btn cs-btn-secondary" onclick="location = '<?php echo SYSTEM_URL; ?>setting.php?mod=admin:testnotify'" value="发送测试通知">
        <br/><br/>测试前请先保存设置
        <br/><br/>支持在签到失败、百度账号 BDUSS 失效时主动推送提醒
        </td><td>
        <div class="input-group">
            <span class="input-group-addon">启用通知</span>
            <select name="notify_enable" class="form-control">
                <option value="1"<?php echo $notifyEnable ? ' selected' : ''; ?>>启用</option>
                <option value="0"<?php echo $notifyEnable ? '' : ' selected'; ?>>关闭</option>
            </select>
        </div><br/>
        <div class="input-group">
            <span class="input-group-addon">渠道</span>
            <select name="notify_channel" class="form-control">
                <option value="none"<?php echo $sel('none'); ?>>不推送</option>
                <option value="serverchan"<?php echo $sel('serverchan'); ?>>Server酱 (ServerChan)</option>
                <option value="telegram"<?php echo $sel('telegram'); ?>>Telegram Bot</option>
                <option value="webhook"<?php echo $sel('webhook'); ?>>通用 Webhook</option>
                <option value="bark"<?php echo $sel('bark'); ?>>Bark (iOS 推送)</option>
                <option value="pushplus"<?php echo $sel('pushplus'); ?>>PushPlus (微信推送)</option>
                <option value="dingtalk"<?php echo $sel('dingtalk'); ?>>钉钉群机器人</option>
                <option value="wecom"<?php echo $sel('wecom'); ?>>企业微信群机器人</option>
                <option value="feishu"<?php echo $sel('feishu'); ?>>飞书群机器人</option>
                <option value="wxpusher"<?php echo $sel('wxpusher'); ?>>WxPusher (微信推送)</option>
                <option value="ntfy"<?php echo $sel('ntfy'); ?>>ntfy</option>
                <option value="mail"<?php echo $sel('mail'); ?>>邮件 (发送至管理员邮箱)</option>
            </select>
        </div><br/>
        <div class="input-group">
            <span class="input-group-addon">Server酱 SendKey</span>
            <input type="text" name="notify_serverchan_key" class="form-control" value="<?php echo htmlspecialchars(option::get('notify_serverchan_key'), ENT_QUOTES, 'UTF-8'); ?>" placeholder="sctp...">
        </div><br/>
        <div class="input-group">
            <span class="input-group-addon">Telegram Token</span>
            <input type="text" name="notify_telegram_token" class="form-control" value="<?php echo htmlspecialchars(option::get('notify_telegram_token'), ENT_QUOTES, 'UTF-8'); ?>" placeholder="123456:ABC...">
        </div><br/>
        <div class="input-group">
            <span class="input-group-addon">Telegram Chat ID</span>
            <input type="text" name="notify_telegram_chatid" class="form-control" value="<?php echo htmlspecialchars(option::get('notify_telegram_chatid'), ENT_QUOTES, 'UTF-8'); ?>" placeholder="-100... 或 123456789">
        </div><br/>
        <div class="input-group">
            <span class="input-group-addon">Bark URL</span>
            <input type="text" name="notify_bark_url" class="form-control" value="<?php echo htmlspecialchars(option::get('notify_bark_url'), ENT_QUOTES, 'UTF-8'); ?>" placeholder="https://api.day.app/your-key">
        </div><br/>
        <div class="input-group">
            <span class="input-group-addon">PushPlus Token</span>
            <input type="text" name="notify_pushplus_token" class="form-control" value="<?php echo htmlspecialchars(option::get('notify_pushplus_token'), ENT_QUOTES, 'UTF-8'); ?>" placeholder="your-token">
        </div><br/>
        <div class="input-group">
            <span class="input-group-addon">Webhook URL</span>
            <input type="text" name="notify_webhook_url" class="form-control" value="<?php echo htmlspecialchars(option::get('notify_webhook_url'), ENT_QUOTES, 'UTF-8'); ?>" placeholder="https://example.com/hook">
        </div><br/>
        <div class="input-group">
            <span class="input-group-addon">钉钉 Webhook</span>
            <input type="text" name="notify_dingtalk_webhook" class="form-control" value="<?php echo htmlspecialchars(option::get('notify_dingtalk_webhook'), ENT_QUOTES, 'UTF-8'); ?>" placeholder="https://oapi.dingtalk.com/robot/send?access_token=...">
        </div><br/>
        <div class="input-group">
            <span class="input-group-addon">钉钉加签密钥</span>
            <input type="text" name="notify_dingtalk_secret" class="form-control" value="<?php echo htmlspecialchars(option::get('notify_dingtalk_secret'), ENT_QUOTES, 'UTF-8'); ?>" placeholder="SEC 开头，未开启加签可留空">
        </div><br/>
        <div class="input-group">
            <span class="input-group-addon">企业微信 Webhook</span>
            <input type="text" name="notify_wecom_webhook" class="form-control" value="<?php echo htmlspecialchars(option::get('notify_wecom_webhook'), ENT_QUOTES, 'UTF-8'); ?>" placeholder="https://qyapi.weixin.qq.com/cgi-bin/webhook/send?key=...">
        </div><br/>
        <div class="input-group">
            <span class="input-group-addon">飞书 Webhook</span>
            <input type="text" name="notify_feishu_webhook" class="form-control" value="<?php echo htmlspecialchars(option::get('notify_feishu_webhook'), ENT_QUOTES, 'UTF-8'); ?>" placeholder="https://open.feishu.cn/open-apis/bot/v2/hook/...">
        </div><br/>
        <div class="input-group">
            <span class="input-group-addon">WxPusher AppToken</span>
            <input type="text" name="notify_wxpusher_token" class="form-control" value="<?php echo htmlspecialchars(option::get('notify_wxpusher_token'), ENT_QUOTES, 'UTF-8'); ?>" placeholder="AT_...">
        </div><br/>
        <div class="input-group">
            <span class="input-group-addon">WxPusher UID</span>
            <input type="text" name="notify_wxpusher_uid" class="form-control" value="<?php echo htmlspecialchars(option::get('notify_wxpusher_uid'), ENT_QUOTES, 'UTF-8'); ?>" placeholder="UID_...">
        </div><br/>
        <div class="input-group">
            <span class="input-group-addon">ntfy 服务器</span>
            <input type="url" name="notify_ntfy_server" class="form-control" value="<?php echo htmlspecialchars(option::get('notify_ntfy_server') ?: 'https://ntfy.sh', ENT_QUOTES, 'UTF-8'); ?>" placeholder="https://ntfy.sh">
        </div><br/>
        <div class="input-group">
            <span class="input-group-addon">ntfy Topic</span>
            <input type="text" name="notify_ntfy_topic" class="form-control" value="<?php echo htmlspecialchars(option::get('notify_ntfy_topic'), ENT_QUOTES, 'UTF-8'); ?>" placeholder="仅字母、数字、下划线和连字符">
        </div><br/>
        <label><input type="checkbox" name="notify_on_fail" value="1"<?php echo option::get('notify_on_fail') ? ' checked' : ''; ?>> 签到失败提醒</label><br/>
        <label><input type="checkbox" name="notify_on_health" value="1"<?php echo option::get('notify_on_health') ? ' checked' : ''; ?>> 账号失效提醒</label>
        </td>
        </tr>
    <?php
    $notifyhtml = ob_get_clean();
    $content1['notify'] = array('html' => $notifyhtml,'type' => 'else');
    $content1['api_token'] = array('td1' => '<b>开放 API Token</b><br/>留空为禁用 API。设置后可通过 <code>api.php?action=status</code> 等接口访问（需带 Bearer Token 头）', 'type' => 'text', 'text' => '', 'extra' => 'autocomplete="off" placeholder="随机字符串"');
    echo former::create($set1, $content1);
}
?>
<?php if (function_exists('imagecreatetruecolor')) : ?>
<script>
    $(function(){
        $("input[name='captcha'][value!='0']").on('click', function(){
            $("#captcha").attr('src', 'index.php?mod=captcha&level='+$("input[name='captcha']:checked").val());
            $("#captcha").fadeIn();
        });
        $("input[name='captcha'][value='0']").on('click', function(){
            $("#captcha").fadeOut();
        });
        $("#captcha").on('click', function(){
            $("#captcha").attr('src', 'index.php?mod=captcha&level='+$("input[name='captcha']:checked").val());
        });
    });
</script>
<?php endif; ?>
<br/><br/><?php echo SYSTEM_FN ?> V<?php echo SYSTEM_VER  . ' ' . SYSTEM_VER_NOTE ?> // 作者: <a href="https://kenvix.com" target="_blank">Kenvix</a>  &amp; <a href="http://www.mokeyjay.com/" target="_blank">mokeyjay</a> &amp;  <a href="http://fyy1999.lofter.com/" target="_blank">FYY</a>
