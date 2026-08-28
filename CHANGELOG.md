# 更新日志

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
