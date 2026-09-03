# 更新日志

## 2026-09-03 V5.12 通知、插件生态与现代化

### 新功能
- 通知渠道扩展至 11 个：新增钉钉群机器人（支持加签）、企业微信、飞书、WxPusher、ntfy（自托管 HTTPS 实例）。
- 新增云点赞插件 `ver4_like`：按贴吧配置、多账号、每吧限量、随机间隔，走贴吧客户端接口并继承账号级代理。
- 失效账号自动停用：BDUSS 健康检查连续失败达到阈值（可配 1-20 次）后自动停止该账号全部贴吧签到并推送提醒；账号所有者与管理员均可一键恢复。
- 插件在线安装：仅允许 `https://gitee.com` 的 ZIP 下载链接，禁止跨域重定向、限制 50MB；上传安装与在线安装统一走新的安全提取器（单根目录、Zip Slip 防护、拒绝覆盖、兼容 `仓库-main` 归档结构）。
- 逐用户 API Token：个人设置生成/撤销，数据库只存 SHA-256 哈希；旧全局 Token 完全兼容；用户 Token 严格隔离 status/sign/retry/health/logs/calendar。
- 签到历史与日历：新增 `sign_history` 表记录每个贴吧每次签到的结果，新增个人 90 天热力图页与 `api.php?action=calendar` 接口。

### 安全加固
- 移除工具箱"运行 SQL"功能（任意 SQL 执行面）；同时移除 `truntab` 后遗留的不可达死代码（裸 `$_POST` 拼接 TRUNCATE）。
- 新增 `wmysql::pquery` 参数化查询支撑，插件管理、计划任务、用户管理、找回密码、账号代理、个人设置、开放 API 日历/日志、登录审计、健康探针等全部后台 SQL 汇聚点迁移至预处理语句。
- 插件全部 GET 动作（启用/禁用/更新/卸载/重置/安装）增加 `valid_plugin_id` 白名单边界校验。
- 自定义分表名在进入建表语句前强制 `^[a-z0-9_]{1,64}$` 白名单校验。
- 批量修改用户组对 ID 强制 int 转换；后台添加用户与站内注册统一用户名/邮箱白名单校验。
- 计划任务动作名（启用/停用/删除/立即运行/排序）统一白名单校验。
- `tools/lint.php` 弃用 shell 拼接改为 `token_get_all` 进程内语法检查；`LoginGuard::auditLogin` 移除函数内 `$_POST` 直读；账号恢复参数增加数字白名单。

### 升级
- 数据库版本 5.11 → 5.12：`users` 表新增 `api_token_hash`（唯一索引），新建 `sign_history` 表，补齐通知与健康设置默认项；请访问 `/setup/update5.11to5.12.php` 完成升级。

## 2026-09-03 V5.11 账号体验

### 新功能
- 新增批量导入百度账号：账号页支持多行粘贴 BDUSS（可带 STOKEN，兼容 `BDUSS=xxx;STOKEN=yyy` 等格式），自动校验 Cookie、识别账号名并尊重配额与重复添加策略，单次最多 50 行。
- 新找回密码：登录页新增"忘记密码"入口，访客可通过注册邮箱自助重置（一次性令牌 30 分钟有效，5 分钟请求频率限制）。
- 新增注册邮箱验证（可选开关）：开启后新注册用户需点击邮件中的验证链接才能登录，验证页支持 5 分钟一次的重发；未开启时老账户登录不受影响。
- 新增访客可访问的 `index.php?mod=lostpw` 找回密码页与 `index.php?mod=verify` 邮箱验证页。

### 升级
- 数据库版本 5.10 → 5.11：`options` 表新增 `reg_mail_verify` 默认项；请访问 `/setup/update5.10to5.11.php` 完成升级。

## 2026-09-03 V5.10 签到引擎强化

### 新功能
- 新增全局网络代理：支持 HTTP(S) 与 SOCKS5（远程 DNS 解析），签到、扫描、TBS、扫码绑定等请求统一走代理工厂。
- 新增账号级代理覆盖：百度账号列表页可为单个账号配置独立代理（`http://host:port` 或 `socks5://user:pass@host:port`），留空跟随全局，`off` 单独禁用。
- 新增打码平台接入：目前支持超级鹰；绑定账号遇到验证码时自动识别并重试一次，识别失败自动退还题分。
- 模拟客户端版本号 options 化（签到设置页可改），百度更新客户端后无需改代码即可切换版本号。
- 新增签到随机抖动（sign_jitter）：在签到间隔基础上随机追加等待，随机化请求节奏降低风控特征。
- 新增签到乱序开关（sign_shuffle）：默认随机乱序执行签到队列。
- 新增"开源致谢"板块（index.php?mod=thanks），登记本项目所依赖的全部开源项目与作者。

### 升级
- 数据库版本 5.09 → 5.10：`baiduid` 表新增 `proxy` 列，`options` 表新增 11 项默认设置；请访问 `/setup/update5.09to5.10.php` 完成升级。

## 未发布

### 可靠性与运维
- 更新通道调整为仅使用 Gitee 国内源，禁用 GitHub、海外 GeoIP 探测和海外回退。
- Gitee Release 查询与下载仅使用 `UPDATE_GITEE_TOKEN`；受限仓库或 API 限流时需要在部署环境配置该只读令牌。
- 新增 `tools/publish-gitee.ps1`，仅从干净的版本 Tag 生成 Windows ZIP 和 SHA-256 文件；必须显式传入 `-Publish` 才会发布到 Gitee。
- 修复登录失败审计未保存用户名、导致同账号限流规则无法命中的问题。
- 修复数组形式请求参数在核心入口触发类型错误或转换警告的问题。
- 固定 bcrypt cost 并保证自动重哈希后的登录 Cookie 使用最新密码哈希。
- 新增 `/healthz.php` 存活与就绪检查，以及 Windows 环境检查启动器和健康检查脚本。
- CI 扩展为 PHP 8.2/8.4 测试矩阵、依赖审计、全仓 PHP 语法和 PowerShell 脚本检查。
- Release 同时发布 Windows ZIP 的 SHA-256 校验文件。
- Windows 更新检查补齐 Gitee 失败后的 GitHub 回退，并严格隔离两个平台的访问 Token。

## 2026-08-30 V5.09 安装向导 UI 升级

### 前端体验
- 安装向导改为响应式卡片布局，新增品牌头部、安全提示和清晰的步骤进度指示器。
- 优化许可协议、环境检查、运行环境选择和站点信息表单的层级、间距与移动端显示。
- 安装页静态资源使用版本号缓存标识，更新后可立即加载最新界面。
- 修复无数据库、空表单和异常步骤参数下的安装页错误，并同步新装数据库版本。
- 新增按版本 tag 自动构建并发布 Windows 部署包的 GitHub Actions 工作流。

## 2026-08-29 V5.08 在线更新

### 新功能
- 系统更新页改为读取当前仓库 GitHub 正式 Release，按版本号检测更新，不再把开发中的 commit 当作稳定版本。
- 检测到更新时显示版本说明、Release 页面和 Windows 部署包下载入口，并支持管理员在线更新源码。
- 新增 `api.php?action=update` 版本检查接口和 `windows/check-update.ps1` 独立检查脚本。

### 安全与兼容
- 在线更新强制 HTTPS 并限制可信 GitHub 下载域名，更新前再次确认最新 tag。
- 更新时保留 `config.php`、`key.php`、环境变量文件、安装锁和数据库；失败时清理临时文件。
- 支持 `UPDATE_GITHUB_TOKEN` 提高 GitHub API 速率限制，支持 `SSL_CERT_FILE` / `CURL_CA_BUNDLE` 修复 Windows PHP CA 配置。

## 2026-08-12 V5.07 安全加固 + 开放 API + 报表

### 新功能
- 开放 API (`api.php`): Bearer Token 认证，支持 status/health/sign/retry/health_check/logs 接口，可供青龙面板、n8n、Home Assistant 等外部系统调用。
- 签到报表页 (`templates/admin-stat-chart.php`): ECharts 折线图展示最近 30 天签到趋势，总执行次数、成功率、最近执行时间卡片。
- 签到看板新增“立即复签”“立即全部签到”按钮。
- 通知渠道新增 Bark (iOS) + PushPlus (微信)，现共支持 6 种渠道。
- BDUSS/STOKEN 加密落库 (AES-256-GCM)，存量数据升级时自动加密，读取时透明解密，兼容旧明文数据。
- 升级脚本 `setup/update5.06to5.07.php`: 加密存量 BDUSS、tieba.latest 扩展为 VARCHAR(10)、users 表加 created_at/last_login/auth_version、生成 crypto_key。

### 安全修复
- CSRF 收紧: POST/PUT/PATCH/DELETE 请求强制要求 Token，不再仅依赖 Referer 回退。
- CSRF Token 密钥: 优先使用 SYSTEM_SALT，其次使用数据库 crypto_key，不再使用硬编码公开字符串。
- 管理员操作参数强转: xorder 插件排序、用户批量操作均强制 int 或正则过滤，阻止 SQL 注入。
- 移除已废弃的 mcrypt 死代码，替换为 openssl AES-256-GCM。

---

## 2026-08-12 V5.06 本地升级执行批次

### 新功能
- 通知中心 (`lib/class.notify.php`): 支持 Server酱 / Telegram Bot / 通用 Webhook / 邮件四种渠道，可在签到失败、百度账号 BDUSS 失效时主动推送，设置页可发送测试通知。
- 登录防护 (`lib/class.loginguard.php`): 同一账号 15 分钟内失败 5 次锁定 15 分钟，登录审计记录 (账号/IP/UA/结果) 可在工具箱查看。
- 签到统计与日志闭环: 每次签到/复签/健康检查后写入 `cron_log_batch`，失败时触发通知。
- 数据库升级脚本 `setup/update5.05to5.06.php`: MyISAM → InnoDB、补充索引、新增登录审计表与通知默认配置。

### 安全修复
- 修复 `wcurl` 默认关闭 TLS 证书校验的问题，现默认校验证书与主机名。
- 百度接口全部由 `http://` 改为 `https://`，避免 BDUSS/密码被中间人截获。
- 异常处理器脱敏: 非管理员/非开发模式下不再输出 SQL 、文件路径与调用栈，改为日志记录+通用提示。
- 登录成功后自动将旧式 md5 链密码升级为 bcrypt (cost 12)。
- 修复 `DoSign_retry` 死代码 bug: `date('Y-m-d')` 与 `latest` (日号) 类型不匹配导致复签任务永远不执行。
- 默认关闭 `wmzz_debug` 插件 (phpinfo 流出)。

### 前端/工程
- 静态资源统一加 `?v=` 版本号，配合 Caddy 强缓存。

## 2026-08-04 本地现代化升级

- 修复 `init.php` 中 `SYSTEM_ISCONSOLE` 使用 `or` 导致 `STDIN` 判断失效的运算符优先级问题。
- `config.php` 支持通过环境变量覆盖数据库、CSRF、加密盐等配置，Docker 不再依赖启动时用 sed 改写配置文件。
- 修复 `lib/class.misc.php` 中 `latest` 查询日期未加引号的隐患。
- `setup/check.php` 更新为 PHP 8.1+ / MySQLi 必选，移除已废弃的 `mysql_*` 与 `safe_mode` 检查，补充 `password_hash`、OpenSSL、`allow_url_fopen` 检查。
- `ajax.php` 服务器信息面板改用 `php_sapi_name()`，移除已废弃的 `safe_mode` 分支。
- Docker Compose 升级为 Compose V2：官方 `mariadb:11.4`、命名卷、健康检查、重启策略、`.env` 示例，移除旧版 `links` 写法。
- Compose 的 web 服务改为从仓库内 Dockerfile 本地构建镜像，默认不再拉取上游旧镜像；并修复 Caddy 配置目录权限告警。
- Dockerfile 增加 `php84-opcache` / `php84-openssl`、OPcache 配置、Caddy 安全响应头、gzip 压缩与容器 `HEALTHCHECK`。
- PHP-FPM 池配置 `clear_env = no`，避免 FPM 清空环境变量后回退到 `127.0.0.1` 数据库连接导致 `Connection refused`。
- Compose 健康检查改为请求静态 `favicon.ico`，避免未登录 302 跳转导致 busybox `wget` 误报失败。
- 修复 `init.php` 对 `TC_AUTO_CREATE_INSTALL_LOCK` 的布尔判断，`false` 字符串不再被误判为真值而自动创建 `install.lock`。
- 新增 `.dockerignore` 与 CI 工作流（PHP 语法检查、Compose 校验）。
- 新增 `SECURITY.md`。
