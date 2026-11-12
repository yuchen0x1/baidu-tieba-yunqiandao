# 贴吧云签到 · 全面分析与升级方案（V7.4 → V8.0）

- 编制日期：2026-11-10
- 分析对象：`release/v7.3.2` 分支 HEAD `9039c71`（`version.php` 为 7.4.0，`origin/main` 同提交）
- 分析方式：全仓静态审计（321 个跟踪文件、223 个 PHP 文件、约 4.29 万行非 vendor PHP），对照 `PROJECT_AUDIT_REPORT.md`、`docs/architecture-*.md`、`CHANGELOG.md` 与 CI 配置
- 限制：本机 Shell 无 `php` / `composer`（PowerShell 也未找到），**本次没有复跑 PHPUnit、lint、集成测试和容器冒烟**；文中"测试通过"均引用既有报告或 CI 配置，不代表本轮实测
- 工作区状态：`composer.json`、`lib/plugins.php`、`source/css/ui.css`、`templates/{index,analysis,admin-stat}.php`、`tests/ApiContractTest.php` 有未提交修改，`PROJECT_AUDIT_REPORT.md` 未跟踪。**开工前先提交或建分支**，否则后续修复会与这批改动混在一起

---

## 0. 结论先行

这个项目的功能覆盖、发布门禁、更新器安全（SHA-256 + 主机白名单 + DNS 固定）、SSRF 防线、Zip Slip 防护、API 合同化都做得比同类自托管项目扎实。**不建议做框架迁移、微服务化或前端重写**。真正需要的是：

1. **先堵住默认部署即可被利用的两个洞**：Caddy 把 `data/backups`、`data/log` 原样当静态文件对外提供；插件关键词可触发二阶 SQL 注入。
2. **修数据正确性**：`latest` 存"日号"导致跨月误判漏签；论坛名在入库前被 HTML 转义 + addslashes，落库即损坏。
3. **把 V7.4 的凭据保护做完整**：BDUSS 已掩码，但 OAuth Secret、SMTP 密码、通知 Token、打码密码仍是明文落库 + 明文回显。
4. **性能与可观测做一轮**：每请求 DDL、插件元数据 N+1、缺失复合索引、`ORDER BY RAND()` 默认值、日报发送时机错误、日志不轮转。

建议节奏：**V7.4.1 热修（1–2 天）→ V7.5 安全与正确性（2 周）→ V7.6 性能与观测（2 周）→ V7.7 UX（3 周）→ V8.0 结构（条件触发，不排期）**。

---

## 1. 现状盘点

### 1.1 规模与结构

| 项 | 数值 / 事实 |
| --- | --- |
| 跟踪文件 | 321 |
| PHP 文件 | 223（不含 vendor） |
| 非 vendor PHP 行数 | 42,862 |
| 最大文件 | `plugins/weltolk_backup_qq/weltolk_backup_qq_setting.php` 1663、`lib/sfc.functions.php` 1415、`plugins/weltolk_sign_qq/weltolk_sign_qq_show.php` 1363、`setting.php` 1147、`lib/class.misc.php` 1052、`lib/class.SignManager.php` 985、`lib/class.updater.php` 907 |
| 前端 | jQuery 3.6.4 + Bootstrap 3.4.1 + 自研 `components.js/ui.css`，无构建链 |
| 图表 | ECharts 5，运行时从 `cdn.jsdelivr.net` 加载（`lib/ui.php:143`） |
| 数据库 | MySQL/MariaDB，手写 MySQLi，无 ORM、无事务封装 |
| 表 | 16 张核心表（安装模板），全部 InnoDB |
| 插件 | 16 个内置插件，钩子式 ABI |
| 测试 | 16 个测试文件、161 个测试（既有报告），CI 覆盖 PHP 8.2/8.4 |
| 部署 | Docker Compose（Caddy + PHP-FPM 8.4 + MariaDB 11.4 + s6）、传统 PHP、Windows 脚本 |

### 1.2 已经做得好的部分（升级时不要破坏）

- **更新器**：Release 资产 SHA-256 强制校验、GitHub 主机/路径白名单、DNS 解析固定、逐跳重定向复核、`CURLOPT_PROXY` 置空、保护 `config.php/key.php/.env/install.lock`（`lib/class.updater.php:662-748`）。
- **SSRF 防线**：`wcurl::setPublicUrl()` 校验所有 DNS 答案并 `CURLOPT_RESOLVE` 固定，禁重定向/代理（`lib/class.wcurl.php:692-733`）；Webhook/Bark/ntfy/AI 均接入。
- **Zip Slip**：`safe_zip_extract()` 拒绝 `../`、绝对路径、盘符、NUL（`lib/sfc.functions.php:1353-1394`）。
- **API 合同**：`lib/api-contract.php` 单一事实源，方法/权限/文档同源。
- **调度**：任务级 `UPDATE ... WHERE running=0 OR running_at<timeout` 锁 + `task_runs` 历史 + 指数退避 + 告警去重（`lib/class.cron.php:234-258, 278-355`）。
- **凭据**：BDUSS/STOKEN/AI Key/实例 Secret 已 AES-256-GCM 加密；API Token 只存 SHA-256；V7.4 起账号页掩码 + CSRF 按需解密。
- **发布门禁**：`tools/release-gate.php` 串起 lint、secret 扫描、单测、版本一致性、迁移规范、入口 SQL 守卫、集成链路。

---

## 2. P0：默认部署即可利用 / 数据会错

### P0-1 Docker 默认把数据库备份和系统日志当静态文件公开

**证据**

- `docker/Dockerfile:37` 生成的 Caddyfile：`root * $WORKDIR` + `file_server`，只对 `/source/*` 设缓存头，没有任何 `respond/deny` 规则。
- `.dockerignore` 只排除 `.git`、`.github`、`**/*.md`、`.env`、`docker/.env`、`mysql`、`vendor`、`setup/update_cache`、`setup/install.lock`（`.dockerignore:1-9`）；`data/`、`tests/`、`tools/`、`setup/*.sql`、`upgrade-report/`、`official-service/` 全部进入镜像。
- 定时备份写入 `data/backups/dbbackup_*.sql`（`lib/cron_system_dbbackup.php:24`），该目录只有 Apache 的 `.htaccess`（`lib/cron_system_dbbackup.php:28-31`）。**Caddy 不读 `.htaccess`**。
- 结构化日志写入 `data/log/system.log`（`lib/class.logger.php:45`），同样无 Web 层拦截。

**影响**：任何知道路径的人可下载整站 SQL 备份（含加密后的 BDUSS、用户邮箱、API Token 哈希、所有业务数据）和运行日志。这不是理论风险，默认 `docker compose up` 就成立。

**修复**

1. Caddyfile 增加：
   ```caddy
   @private path /data/* /setup/* /tools/* /tests/* /upgrade-report/* /official-service/* /docs/*
   respond @private 404
   ```
   注意：`/setup/install.php` 在未安装时仍需访问。推荐改为：安装完成后由 `.dockerignore` 不打包 `setup/install.php`，或 Caddy 只放行 `/setup/install.php` 与静态资源，其余 404。
2. `.dockerignore` 增加 `data/log`、`data/backups`、`tools`、`tests`、`upgrade-report`、`docs`、`official-service`、`*.sql`（`setup/install.template.sql` 除外时显式 `!setup/install.template.sql`）。
3. Apache/Nginx/Windows 三种部署在 README 给出等价规则；根目录补 `data/.htaccess`、`data/log/.htaccess`，并在安装向导里写。
4. 增加回归测试：CI docker-smoke 用 `curl -o /dev/null -w '%{http_code}'` 断言 `/data/log/system.log`、`/data/backups/x.sql`、`/setup/install.template.sql`、`/tools/lint.php` 均为 404。

### P0-2 插件关键词触发二阶 SQL 注入（普通用户 → cron 进程）

**证据**

- `misc::findFid($kw)` 直接把 `$kw` 拼进 SQL：`SELECT * FROM ... WHERE tieba = '{$kw}'`（`lib/class.misc.php:89`）。
- `misc::mSetFid($kw, $fid)` 同样直拼（`lib/class.misc.php:111`）。
- 调用链：用户在插件设置里保存目标贴吧/关键词 → cron 读取 → `misc::getFid($kw)` → `findFid($kw)`。至少三处：
  - `plugins/ver4_ai/cron/ai.php:264`（发主题帖，无条件调用）
  - `plugins/ver4_reply/cron/reply.php:328,404`（回帖/发主题帖）
  - `plugins/ver4_monitor/cron/monitor.php:119`
- 关键词只经过 `trim()` 分行（`plugins/ver4_ai/ver4_ai_client.php:455`），没有字符白名单。
- 注入发生在 cron/CLI 进程，连接权限是全库；`mysqli::query()` 不支持多语句，但 **UNION / 时间盲注 / 布尔盲注** 足以读出 `tc_options`（含 `crypto_key`、各类 Token）和其他用户数据。

**影响**：任何能使用 AI/回帖/监控插件的普通用户，可在管理员不知情的情况下读取整库敏感数据，最终可推导加密密钥。属于最高优先级。

**修复**

1. `findFid`/`mSetFid` 改为 `$m->pquery(... WHERE tieba = ? ...)`；表名继续走 `safe_table_name()`。
2. `getFid()` 只接受 `string` 且长度 ≤ 60，拒绝控制字符；返回值统一 `(int)`。
3. 所有插件在保存关键词时做 `misc::normalize_forum_keyword()`（trim、去 NUL、限长、拒绝 `' " \ ; -- /*` 之外的字符？贴吧名允许中文/字母/数字/下划线/部分符号，建议白名单 `^[\p{Han}A-Za-z0-9_+·\-]{1,60}$`，不匹配则拒绝保存并提示）。
4. 回归测试：`tests/SecurityHardeningTest.php` 增加 `findFid` 注入用例（用 mock `$m` 或源码断言 + `pquery` 存在性），CI 入口 SQL 守卫扩展到 `lib/class.misc.php` 的 `findFid/mSetFid`。

### P0-3 默认 Docker 中 `official-service/` 网关可被公网访问

**证据**：`official-service/index.php:24-29` 在非 CLI 下直接 `official_gateway_handle()`；`COPY ./` 把它放进镜像；Caddy 无拦截。未配置 `GW_ADMIN_TOKEN` 时管理端点返回 503，但 `/healthz`、`/v1/token` 仍然可达，会触碰 SQLite 与实例表。

**修复**：与 P0-1 同一条 `respond 404`；并在 `official-service/README.md` 明确"本目录不随 Web 根部署"。

### P0-4 论坛名在入库前被 HTML 转义 + SQL 转义，落库即损坏

**证据**

- `lib/class.misc.php:929`：`$vn = addslashes(htmlspecialchars($v['forum_name']));`
- `lib/class.misc.php:938`：把 `$vn` 直接拼进 `INSERT`。
- `lib/class.misc.php:420`：`$kw = addslashes($kw);`，随后 `lib/class.SignManager.php:187-195` 的 `logTiebaResult()` 又对 `$tieba` 做 `real_escape_string()` → **双重转义**。

**影响**：吧名含 `&`、`<`、`>`、`'`、`\` 时，数据库里存的是 `&amp;` 或 `\'`；后续用该名字请求百度签到会失败，健康度表 `tiebaHealth()` 与实际 `tieba` 列对不上，页面健康信息丢失。中文吧名大多不受影响，但这是静默数据损坏。

**修复**

1. 存储层只存原始值：删除 `htmlspecialchars` 与 `addslashes`，全部改 `pquery` 绑定。
2. 输出层统一 `htmlspecialchars(..., ENT_QUOTES, 'UTF-8')`（模板已在做）。
3. 增加一次性数据修复迁移：对 `tieba`/`sign_history` 中疑似 HTML 实体和反斜杠的值做 `html_entity_decode` + `stripslashes`（仅当解码后能匹配合法吧名且不产生重复时更新），并提供 `--dry-run` 工具。
4. 回归测试：构造含 `&`、`'` 的吧名，断言入库原样、日志原样、页面转义输出。

### P0-5 `latest` 存"日号"，跨月同号误判为已签到

**证据**

- 签到标记写入 `date('d')`：`lib/class.misc.php:420,477,483,579,936`；扫描条件 `latest != '{$today}'`（`lib/class.misc.php:590,608`）。
- 列类型 `varchar(10)`（`setup/install.template.sql:181`、`setup/template.table.sql:9`）。
- 全仓没有任何月初重置逻辑（`grep date('m')/first day` 无命中）。
- 既有路线图已列为待办：`upgrade-report/08-v2-gap-report-roadmap-2026-08-12.md:33,101`。

**影响**：账号停签一段时间后，若恢复日期的"日号"恰好等于上次签到日号（例如 1 月 15 日签过、2 月 15 日恢复），当天会被判定为已签到而跳过；统计数据在跨月边界也会把上月同号记录算进"今日已签"。

**修复（expand/contract，避免一次性改 17 个消费方）**

- **V7.4.1（expand）**：迁移 `007300` 增加 `last_sign_date DATE NULL` + 索引；`DoSign`/`DoSign_retry` 改为 `last_sign_date IS NULL OR last_sign_date <> CURDATE()`；成功/失败时同时写 `latest = date('d')` 与 `last_sign_date = CURDATE()`；旧统计继续读 `latest`，行为不变。
- **V7.5（contract）**：所有读方切到 `last_sign_date`；`latest` 标记 deprecated；`007400` 删除 `latest` 列与索引。
- 回填策略：`007300` 不猜测月份，统一置 `NULL`，让每个贴吧在升级后重签一次；避免把上月的 `latest` 误当本月。

---

## 3. P1：高优先级安全与正确性

### P1-1 敏感配置明文落库 + 明文回显

**证据**：以下字段走 `option::set()` 明文写入，且 `templates/admin-set.php` 用 `type="text"` 把值原样回显：

| 字段 | 写入 | 回显 |
| --- | --- | --- |
| `oauth_github_client_secret` | `setting.php:174` | `templates/admin-set.php:223` |
| `mail_smtppw` | `setting.php:145` | `templates/admin-set.php:329`（只读占位，但值仍在 POST 里） |
| `dama_password` | `setting.php:99` | `templates/admin-set.php:153` |
| `notify_serverchan_key` | `setting.php:153` | `templates/admin-set.php:378` |
| `notify_telegram_token` | `setting.php:154` | `templates/admin-set.php:382` |
| `notify_dingtalk_secret` | `setting.php:162` | `templates/admin-set.php:406` |
| `notify_bark_url` / `notify_wecom_webhook` / `notify_feishu_webhook` / `notify_wxpusher_token` / `notify_pushplus_token` | `setting.php:153-166` | `templates/admin-set.php:390-418` |

对比：BDUSS/STOKEN、AI Key、实例 Secret 都已 `P::encrypt`。V7.4 刚做了"凭据不进 HTML"，但这些配置仍是例外。

**修复**

1. 新增 `lib/class.SecretBox.php`：
   - `put($name, $plain)` → `option::set($name, P::encrypt($plain))`
   - `get($name)` → `P::decrypt(option::get($name))`
   - `masked($name)` → `credential_mask(get($name))`
   - `reencryptAll()` 供密钥轮换。
2. 迁移 `007301`：对上述字段批量 `enc:v1:` 加密（幂等，已加密跳过）。
3. 模板改为 `<input type="password" value="" placeholder="已配置（首尾掩码），输入新值覆盖">`；空提交表示"保持不变"，显式 `clear_xxx=1` 才清空。
4. 所有 `option::get()` 读取点改为 `SecretBox::get()`（`lib/class.misc.php:29` 的 SMTP、`lib/class.notify.php:100-140`、`lib/class.dama.php:54`、`lib/class.OAuth.php:149`）。
5. 测试：断言保存后 DB 值以 `enc:v1:` 开头、页面 HTML 不含明文、空提交不覆盖。

### P1-2 Session 未加固，OAuth state 与验证码暴露面偏大

**证据**：`session_start()` 出现在 `lib/globals.php:276,359,634`、`lib/class.OAuth.php:286`，**没有任何** `session_set_cookie_params` / `ini_set('session.*')`。实际安全属性完全取决于 `php.ini` 默认值（常见默认 `session.use_strict_mode=0`、`session.cookie_httponly=0`、无 SameSite）。

**修复**

- 新增 `lib/session.php`：`session_boot()` 统一设置 `use_strict_mode=1`、`cookie_httponly=1`、`cookie_samesite=Lax`、`cookie_secure`（支持 `X-Forwarded-Proto`）、`use_only_cookies=1`、独立 `session.name`。
- 登录成功后 `session_regenerate_id(true)`；登出 `session_destroy()` + 清 cookie。
- `set_site_cookie()` 的 Secure 判断补 `HTTP_X_FORWARDED_PROTO`（`lib/sfc.functions.php:59-78`），与 `init.php:110-111` 的 CSRF cookie 判断保持一致。

### P1-3 2FA 无尝试限制、无恢复码；登录限流只按"用户名+IP"

**证据**

- `lib/globals.php:735-755`：验证码错误只 `msg()`，无计数、无锁定。6 位码在 ±1 窗口内可被持续尝试。
- `lib/class.loginguard.php:86`：锁定条件是 `username = ? AND ip = ?`，攻击者换用户名即可绕过；单 IP 对多账号喷洒不受限。
- `lib/class.totp.php` 无恢复码实现。

**修复**

- `admin:2fa` 增加 `option`/表计数：同一 UID 10 分钟内 5 次失败 → 锁定 15 分钟并通知管理员邮箱。
- 登录审计增加"按 IP 聚合"：同一 IP 15 分钟失败 ≥ 20 次 → 全站登录限流（返回 429/提示）。
- TOTP 启用时生成 10 个一次性恢复码，哈希存储，登录页提供"使用恢复码"入口。

### P1-4 删除用户不清理凭据与历史数据

**证据**：`lib/sfc.functions.php:369-390`：

- `CleanUser()` 只删 `<prefix><users.t>` 里的贴吧行。
- `DeleteUser()` 只再删 `users_options` 与 `users`。
- **不删** `baiduid`（加密 BDUSS/STOKEN）、`baiduid_health`、`sign_history`、`users_oauth`、`notices`、`achievements`、`login_audit`、`task_runs` 相关记录。

**影响**：注销用户的百度凭据永久留在库里；隐私合规与存储膨胀双重问题。

**修复**：`DeleteUser()` 改为按 `uid` 级联删除（单事务或分批），保留 `login_audit` 但把 `username` 脱敏；增加 `tools/data-retention.php --dry-run` 清理历史孤儿行（`uid` 不在 `users` 的行）。

### P1-5 日报发送时机错误

**证据**：`lib/cron_system_sign.php:102` 每次 `system_sign` 运行都调用 `Reminder::sendDailySummary()`；该任务 `freq=0`，由 `do.php` 每分钟触发（`setup/install.template.sql:45`）。`sendDailySummary()` 只按"当天是否已发"去重（`lib/class.Reminder.php:105`），因此**用户当天第一批签到完成后就会收到"每日日报"**，此时绝大多数贴吧还没签。

**修复**

- 新增选项 `daily_report_hour`（默认 21）与 `daily_report_require_complete`（默认 1）。
- 发送条件：当前小时 ≥ 配置小时，且（`signSummary()['waiting'] == 0` 或已到 23:30 强制发送）。
- 把日报从 `cron_system_sign` 移到独立任务 `system_daily_report`（每小时检查一次），避免与签到批次耦合。

### P1-6 并发签到没有行级领取，重复签到

**证据**

- `misc::DoSign()` 先 `SELECT ... WHERE latest != today`，再逐条 `DoSign_All()`，中间无 `FOR UPDATE`/`SKIP LOCKED`/claim 标记（`lib/class.misc.php:574-618`）。
- 任务锁只保护 `system_sign` 这个任务（`lib/class.cron.php:234`），但手动"立即签到"走 `ajax.php:238` 直接调 `misc::DoSign()`，不经过任务锁。
- `do.php:87-91` 的 `sign_multith` 会再起 N 个内部 HTTP 进程；`SYSTEM_CRON_THREAD` 常量在 `do.php:23` 定义后**全仓无使用**，说明"多线程模式"并未真正闭环。

**修复**

- 给贴吧表加 `claim_owner VARCHAR(40) NULL`、`claimed_at INT UNSIGNED NULL`（或独立 `sign_queue` 表）。
- 领取语句：
  ```sql
  UPDATE <table> SET claim_owner=?, claimed_at=?
  WHERE id=? AND (claimed_at IS NULL OR claimed_at < ?)
  ```
  影响行数为 1 才执行签到，结束清空 claim。
- 或 MariaDB 11.4 下用 `SELECT ... FOR UPDATE SKIP LOCKED`（需事务）。
- 先删掉 `sign_multith` 的无锁内部 HTTP 方案，等行级领取上线后再恢复；或直接标记为"实验性，默认 0"并在 UI 说明。

### P1-7 插件在线安装无完整性校验

**证据**：`setting.php:383-410` 只校验 `https://gitee.com` 主机 + `.zip` 后缀 + 50MB 限制，然后解压安装。没有 SHA-256、没有签名、没有版本发布者校验。

**修复**：插件 ZIP 内要求 `plugin.json` 或 `_desc.php` 携带 `sha256` 与 `signature`（可用官方公钥或站点自持公钥），安装前校验；无签名时给出显式警告并要求管理员二次确认。长期可做"插件市场 + 签名清单"。

### P1-8 前端运行时依赖第三方 CDN，无 SRI / 无回退

**证据**：`lib/ui.php:140-146` 硬编码 `https://cdn.jsdelivr.net/npm/echarts@5/dist/echarts.min.js`，无 `integrity`、无本地回退。所有图表页在 `typeof echarts === 'undefined'` 时静默不渲染（`templates/analysis.php:232` 等）。

**影响**：中国大陆网络/内网部署图表全空；第三方 CDN 故障或投毒会影响所有实例；CSP 也无法收紧到 `'self'`。

**修复**：把 `echarts.min.js` 放入 `source/js/`（约 1MB），`loadChartAssets()` 优先本地、失败再 CDN；如保留 CDN，加 SRI 与 `crossorigin`。CI 增加"本地文件存在 + 版本号一致"断言。

### P1-9 计划任务密码仍可走 URL；多线程模式把密码放进内部 HTTP body

**证据**：README 与 `do.php:12-21` 仍支持 `do.php?pw=`；`do.php:90` 用 `sendRequest(SYSTEM_URL.'do.php?...', 'pw='.$cron_pw)`，若站点是 http，密码在明文请求体里。

**修复**

- 增加 `allow_web_cron` 选项，默认关闭；Web 触发改为 HMAC 签名 URL（`ts + nonce + HMAC(cron_pw)`，5 分钟有效，nonce 落库去重），避免长期密码进日志。
- 或只保留 CLI，README 的 Web 方式标注"不推荐，已弃用"。

### P1-10 更新器覆盖安装无文件级回滚

**证据**：`lib/class.updater.php:662-748` 先删保护文件、再 `CopyAll($root, SYSTEM_ROOT)`。`CopyAll()`（`lib/sfc.functions.php:483-514`）只覆盖不删除，且中途失败没有回滚；`dataBak` 只备份数据库，不备份代码。

**修复**：覆盖前把当前代码打包到 `data/backups/pre-update-files-*.zip`（排除 `data/`、`config.php`、`key.php`、`.env`），失败时自动解压还原；同时按发布包清单删除已移除的旧文件（保留白名单）。

---

## 4. P2：性能、数据模型与工程债

### 4.1 每请求都在做 DDL

- `SignManager::ensureHistoryTable()`（`lib/class.SignManager.php:156`）在 12 个方法入口调用；每个请求首次调用执行 `CREATE TABLE IF NOT EXISTS`。
- `cron::ensureTables()`（`lib/class.cron.php:158`）、`Notice::ensureTable()`（`lib/class.Notice.php:33`）、`OAuth::ensureTable()`（`lib/class.OAuth.php:44`）、`Achievement::ensureTable()`、`LoginGuard::table()`（`lib/class.loginguard.php:24`）、`invite_ensure_table()`（`lib/globals.php:79`）、`api.php:84-89` 的 `api_rate_limits` 建表同理。

**影响**：每次请求至少 1–3 次 `CREATE TABLE IF NOT EXISTS`，在并发下争抢 metadata lock；MariaDB 上高频 DDL 还会污染 query cache/统计。

**修复**：统一 `Schema::ensure($name)`，用 `option('schema_ready:<version>')` 或 `schema_migrations` 状态做进程内 + 请求间缓存；只在迁移后首次请求执行 DDL。生产环境默认关闭运行期 DDL，缺失表直接报可读错误。

### 4.2 插件元数据 N+1

`lib/plugins.php:628` 在 `getPluginInfo()` 里每个插件执行一次 `SELECT order`；`lib/plugins.php:684-690` 启动时对每个激活插件再 `include _desc.php` + 查询。16 个插件 = 每请求 16 次额外查询。

**修复**：`reg.php` 已一次性查出 `plugins` 全表，直接把 `order/status/options` 放进 `$i['plugins']['info']`；`getPluginInfo()` 改为纯数组组装，不再查库；`desc` 结果按 `plugin + mtime` 缓存到 `data/cache/plugins.php`（或 APCu 可选）。

### 4.3 缺失索引

安装模板中：

- `tieba` 只有 `uid`、`latest` 单列索引，**没有 `pid` 索引**（`setup/install.template.sql:183-185`），但按账号签到/列表查询大量使用 `WHERE pid=?`。
- `users_options` 只有 `uid`、`name` 单列索引（`:226-228`），实际查询是 `WHERE uid=? AND name=?`。
- `sign_history` 有 `(uid, created_at)`，但 `tiebaHealth()` 的相关子查询是 `WHERE uid=? AND tieba=? AND created_at>=?`（`lib/class.SignManager.php:930-960`），缺 `(uid, tieba, created_at)`。
- `login_audit` 查询是 `username + ip + ts`（`lib/class.loginguard.php:86`），缺复合索引。

建议迁移 `007302`：

```sql
ALTER TABLE `<p>tieba` ADD INDEX `pid_no_latest` (`pid`,`no`,`latest`);
ALTER TABLE `<p>users_options` ADD UNIQUE KEY `uid_name` (`uid`,`name`);
ALTER TABLE `<p>sign_history` ADD INDEX `uid_tieba_created` (`uid`,`tieba`,`created_at`);
ALTER TABLE `<p>login_audit` ADD INDEX `username_ip_ts` (`username`,`ip`,`ts`);
```

加 `users_options` 唯一键前需先去重（保留最新 id），迁移里做幂等去重。

### 4.4 签到抽样默认 `ORDER BY RAND()`

安装模板 `sign_scan` 默认 `'2'`（`setup/install.template.sql:...`），对应 `S::rand()` 的 `ORDER BY RAND() LIMIT n`（`lib/class.S.php:38-70`）。大表上每次签到都要全表随机排序。

**修复**：默认改为 `'1'`（JOIN 随机），并在设置页把 `ORDER BY RAND()` 标注"仅小表使用"；`cron_limit` 默认 10 保持，但增加"每轮上限 ≤ 200"的硬校验。

### 4.5 `tiebaHealth()` 每个贴吧 4 个相关子查询

`lib/class.SignManager.php:939-960` 对 `last_msg/last_exp/last_rank/last_level` 各写一个相关子查询，贴吧数 200 时是 800 次索引查找。建议改为：

```sql
SELECT t.tieba, t.success, t.error, t.total, t.last_time,
       h.message AS last_msg, h.exp_gained AS last_exp, h.sign_rank AS last_rank, h.forum_level AS last_level
FROM (SELECT tieba, SUM(...) ... FROM history WHERE uid=? AND created_at>=? GROUP BY tieba) t
LEFT JOIN history h ON h.id = (
  SELECT MAX(id) FROM history WHERE uid=t.uid AND tieba=t.tieba AND created_at>=?
)
```

或 MariaDB 11.4 的窗口函数 `ROW_NUMBER() OVER (PARTITION BY tieba ORDER BY id DESC)`。

### 4.6 数据模型细节

- `users.email VARCHAR(40)`（`setup/install.template.sql:207`）对现代邮箱偏短；建议 `VARCHAR(191)`。
- `users.auth_version` 从 V5.07 起存在但**全仓无读取**，是死列；要么删除，要么真正用于"改密后踢下线"。
- `cron` 表同时有 `no`、`enable`、`orde`、`nextrun`，语义重叠；建议在文档中固化优先级（`enable` 优先于 `no`），并在 UI 只暴露一个开关。
- `baiduid(uid, portrait)` 无唯一约束，扫码/导入并发可产生重复账号；建议去重后加唯一索引（注意 `portrait=''` 的历史行）。
- `plugins.order` 是保留字，虽然反引号可用，迁移到 `sort_order` 可减少踩坑。

### 4.7 大文件与职责

- `lib/sfc.functions.php` 1415 行：文件、网络、备份、CSV、Cookie、密码、用户清理全在一起。
- `lib/class.misc.php` 1052 行：签到协议、贴吧扫描、FID 缓存、代理、日志混合。
- `setting.php` 1147 行：路由 + 业务 + 文件导出 + 插件设置 + 用户管理。
- `lib/globals.php` 768 行：路由、认证、TOTP、OAuth、注册、验证。

**原则**：不为了"现代架构"重写；只做**按测试覆盖逐步抽取**。优先抽 `AuthService`（登录/登出/2FA/限流）、`SignEngine`（DoSign* + FID + 协议）、`BackupService`（dataBak/恢复），每抽一个先补集成测试。

### 4.8 前端与可访问性

- 365 处内联 `style=`、51 处内联 `onclick/onchange/onsubmit`（`templates/`），使 CSP 无法启用。
- Bootstrap 3.4.1 已 EOL（2019 年后无安全更新）；`b3-compat.css` 已经是过渡层。
- `lang/zh-CN.php` 与 `I18N` 存在，但模板中仅 6 处使用，属于"半成品"。
- `templates/login.php:25`、`templates/reg.php:33` 用 `strip_tags($_GET['error_msg'])` 输出；虽不足以形成标签注入，但建议统一 `htmlspecialchars`。
- `templates/control.php:0`、`admin-update.php:0` 等模板几乎没有输出转义调用（多为静态 HTML + 常量，但新增字段时容易漏）。

### 4.9 运维与可观测

- `data/log/system.log` 无轮转（`lib/class.logger.php` 只有追加），长期运行会无限增长。
- Caddy 访问日志 `level warn`，与 `Logger::$requestId` 无关联；建议把 request id 写入响应头 `X-Request-Id` 并透传到日志。
- `healthz.php?mode=deep` 无认证，泄露任务新鲜度/错误计数（数量级，不致命）。
- `metrics.php` 只有全局计数，缺"每任务耗时直方图、签到队列积压、最近成功时间"。
- 无 HSTS、无 CSP、无 `Permissions-Policy`；Caddy 只有 3 个安全头（`docker/Dockerfile:37`）。
- `tools/lint.php` **没有** `PHP_SAPI !== 'cli'` 守卫（其余工具大多有），Web 可达时会全仓扫描并输出警告；虽不致命，但应统一加守卫。

### 4.10 合规

- `composer.json` 声明 `AGPL-3.0-only`，仓库**没有 `LICENSE` 文件**，而 `license.html` 是自拟"最终用户使用许可协议书"（含"不得删减版权"等限制）。GitHub API 因此显示 `NOASSERTION`。
- 建议：根目录放 AGPL-3.0 全文 `LICENSE`；`license.html` 改为"附加说明/致谢"，明确与 AGPL 不冲突；README 徽章与 composer 保持一致；插件目录已有 `LICENSE` 的保留并在致谢页登记。

### 4.11 发布与镜像体积

- `docs/screenshots` 2.3MB、`upgrade-report` 384KB、`source` 861KB 全部进镜像与 Windows 发布包；发布包只排除 Markdown，PNG 仍在内。
- `COPY ./` 把 `tests/`、`tools/`、`upgrade-report/` 打进运行时镜像；建议多阶段构建或 `.dockerignore` 排除。
- 发布包没有 SBOM/签名；Docker 镜像没有 cosign/provenance。可在 `release.yml` 增加 `docker buildx --provenance` 与 `cosign sign`（可选）。

---

## 5. 升级路线（建议按版本拆分）

### V7.4.1 热修（1–2 天，必须先发）

目标：只修"默认可利用/会丢数据"的问题，不做结构改动。

| # | 工作 | 文件 | 验收 |
| --- | --- | --- | --- |
| 1 | Caddy/`.dockerignore` 封禁 `data/*`、`tools/*`、`tests/*`、`setup/*.sql`、`official-service/*` | `docker/Dockerfile`, `.dockerignore` | CI docker-smoke 断言 404 |
| 2 | `findFid/mSetFid` 参数化 + 关键词规范化 | `lib/class.misc.php` | 注入用例失败、正常签到通过 |
| 3 | 论坛名入库去 HTML/addslashes，改 `pquery` | `lib/class.misc.php:929-938` | 含 `&`/`'` 吧名原样落库 |
| 4 | `DoSign_All` 去掉 `addslashes`，日志不再双重转义 | `lib/class.misc.php:420`, `lib/class.SignManager.php:187` | 日志值等于原始吧名 |
| 5 | 迁移 `007300` 增 `last_sign_date` + 索引 + 双写 | `setup/migrations/007300_*.php`, `lib/class.misc.php` | 跨月同号场景能重签 |
| 6 | 迁移 `007301` 敏感配置加密 | 新迁移 + `SecretBox` | DB 值 `enc:v1:`，页面无明文 |
| 7 | ECharts 本地化 + CDN 回退 | `lib/ui.php`, `source/js/echarts.min.js` | 断网仍出图 |
| 8 | `tools/lint.php` 加 CLI 守卫 | `tools/lint.php` | Web 访问 403/文本拒绝 |

> 备注：第 6 项如时间紧可放 V7.5，但第 1–5 项不建议再拖。

### V7.5 安全与正确性（8–12 人日）

| 主题 | 内容 | 验收 |
| --- | --- | --- |
| Session | `session_boot()` 统一参数、登录后 regen、登出销毁 | Set-Cookie 含 HttpOnly/SameSite/Secure（HTTPS） |
| 登录/2FA | 按 IP 聚合限流、2FA 失败锁定、恢复码 | 喷洒测试被 429；恢复码可登录一次 |
| CSRF | 移除写操作的 Referer 回退；破坏性 GET 全部改 POST + token | 无 token 的 POST 全部拒绝 |
| 用户删除 | 级联清理 `baiduid/health/history/oauth/notice/achievement` | 删除后无孤儿行 |
| 日报 | 独立 `system_daily_report` + 完成度/时间窗 | 21 点后仅发一次完整日报 |
| 插件安装 | SHA-256 必填、可选签名、失败回滚 | 篡改包被拒 |
| 更新器 | 文件级备份 + 失败还原 + 旧文件清单清理 | 模拟中断后可回到旧版本 |
| `latest` contract | 全部读方切 `last_sign_date`，删除旧列 | 全仓无 `latest` 业务引用 |
| 选项 API | `option::set/add/uset/uadd` 全部 `pquery`，缓存原始值 | 含引号设置保存后同请求读取一致 |
| 数据修复 | 历史吧名 `html_entity_decode/stripslashes` 工具 | dry-run 报告 + 幂等执行 |
| 许可证 | 增加 `LICENSE`（AGPL-3.0），修订 `license.html` | GitHub 识别 AGPL-3.0 |
| 安全头 | HSTS、CSP report-only、Permissions-Policy | 浏览器无 CSP 违规阻断 |

### V7.6 性能与可观测（8–12 人日）

| 主题 | 内容 | 验收 |
| --- | --- | --- |
| 签到领取 | 行级 claim 或 `SKIP LOCKED`，去掉无锁多线程 | 并发 3 进程无重复签到 |
| 索引 | 4 组复合索引迁移 | `EXPLAIN` 命中新索引 |
| DDL 收敛 | `Schema::ensure` + 生产禁运行期 DDL | 请求期无 `CREATE TABLE` |
| 插件缓存 | 元数据一次加载 + desc 文件缓存 | 每请求插件查询 ≤ 1 |
| 分析查询 | `tiebaHealth` 改 `MAX(id)` join/窗口函数 | 200 吧页面 P95 下降 ≥ 50% |
| 日志 | 轮转（按天/大小）、级别选项、request id 透传 | 日志不会无限增长 |
| 指标 | 任务耗时直方图、队列积压、最近成功时间 | Grafana 可出 4 张图 |
| 静态分析 | PHPStan level 5（`lib/` 起步，baseline） | CI 通过且无新增错误 |
| 前端 | 移除内联 handler、CSP 收紧到 `'self'`、图表懒加载 | CSP 强制模式无违规 |

### V7.7 UX 与前端（12–20 人日）

1. **Bootstrap 3 迁移策略**：不追求一次性重写。先做 `bs5-compat.css`，把 `panel/well/input-group-addon/btn-default` 等映射到 BS5 类；按页面分批（每批 4–5 个模板）迁移并截图回归。
2. **移动端**：贴吧列表/日志表格在 <768px 转卡片；批量操作条固定底部；表单两栏转单栏。
3. **可访问性**：所有表单补 `label/for`、错误用 `aria-live`、焦点环可见、对比度 ≥ AA；目标 Lighthouse Accessibility ≥ 90。
4. **首次使用引导**：绑定百度账号 → 同步贴吧 → 设置签到时段 → 测试通知，四步向导。
5. **i18n**：要么把导航/登录/设置页补完并加 `en-US`，要么删除 `I18N` 与 `lang/` 避免误导。
6. **截图与文档**：重拍 7.5 工作台/分析/账号页；README 功能表与 API 表同步。

### V8.0 结构演进（条件触发，30–60 人日）

只在满足触发条件时做，且必须走"测试先行 + 双写过渡"：

- 触发 1：`lib/` 单文件超过 2000 行且改动冲突频繁 → 抽 `AuthService`、`SignEngine`、`Scheduler`、`PluginHost`。
- 触发 2：需要跨用户实时查询贴吧绑定关系 → 引入 Repository 层，逐步替换动态表拼接。
- 触发 3：出现跨语言任务生产者或秒级调度需求 → 通过 `cron_1/cron_2` 钩子接队列，不改核心调度。
- 触发 4：单表 > 500 万行且出现可量化慢查询 → 才评估合并分表（expand/contract）。
- **不做**：Laravel/ThinkPHP 重写、React/Vue 全量替换、微服务拆分、ORM 全量替换。

---

## 6. 迁移清单（编号建议）

| 版本 | 迁移 | 内容 | 可回滚 |
| --- | --- | --- | --- |
| 7.5 | `007300_forum_name_repair.php` | 历史吧名实体/反斜杠修复（幂等） | 不回滚原值 |
| 7.5 | `007301_sign_marker_reset.php` | `latest` 从日号切换为完整日期并强制重签一次 | 重置不可逆 |
| 7.5 | `007302_secret_encrypt.php` | 敏感 option 批量 `enc:v1:` 加密 | 解密回写（谨慎） |
| 7.5 | `007303_indexes.php` | `tieba`/`users_options`/`sign_history`/`login_audit` 索引 | 删索引 |
| 7.5 follow-up | `007500_sign_claim.php` | `claim_owner/claimed_at` + `claim_expiry`，原子领取与 TTL 接管 | 删列/索引 |

所有迁移必须：`up/down` 幂等、带 `version/desc`、在 `release-gate` 的迁移规范检查中通过。

---

## 7. 测试与验收矩阵

| 层级 | 新增内容 | 运行方式 |
| --- | --- | --- |
| 单元 | `findFid` 注入、论坛名规范化、`SecretBox` 加解密、`option` 引号值、`last_sign_date` 判定、日报时间窗 | `composer test` |
| 源码守卫 | 入口 SQL 守卫扩展到 `lib/class.misc.php`、`lib/class.option.php`；禁止新增 `addslashes` 到 SQL 上下文 | `composer lint` |
| 集成 | 迁移 007300–007500 的 up/down/幂等；并发签到 claim；删除用户级联 | `composer integration`（MariaDB 11.4） |
| 容器 | `/data/*`、`/tools/*`、`/setup/*.sql` 404；`/healthz` 200；图表断网可用 | CI `docker-smoke` |
| 浏览器 | 登录 → 绑定 → 同步 → 签到 → 分析 → 插件设置，桌面/移动双视口 | Playwright（建议新增到 CI，nightly） |
| 安全 | CSP report-only 无违规、Cookie 属性、2FA 锁定、插件包篡改拒绝 | 手工 + 脚本 |

> 2026-11-12 已在 PHP 8.3.36 本机完成 PHPUnit/lint/secret scan/release gate；数据库集成、Docker 与浏览器回归仍需隔离环境，不以静态结论替代。

---

## 8. 发布与回滚

1. 从 `main` 新建 `release/v7.5`（当前 HEAD 在 `release/v7.3.2` 但版本已是 7.4.0，分支名已失真）。
2. 先提交现有未提交修改（四卡布局、插件索引兜底、composer 测试入口），单独一个 commit，便于回滚。
3. 每个 P0 单独 commit + 对应测试，禁止"顺手重构"混入。
4. 发布前跑 `composer gate`；打 tag 后由 `release.yml` 生成包与 SHA-256。
5. 回滚：数据库迁移保留 `down`；代码回滚用 tag；文件级更新器在 V7.5 后支持自动还原。

---

## 9. 关键文件索引（便于排期）

| 主题 | 文件 |
| --- | --- |
| 部署暴露面 | `docker/Dockerfile:37`、`.dockerignore`、`lib/cron_system_dbbackup.php:24-31` |
| SQL 注入 | `lib/class.misc.php:89,111`、`plugins/ver4_ai/cron/ai.php:264`、`plugins/ver4_reply/cron/reply.php:328,404` |
| 论坛名损坏 | `lib/class.misc.php:420,929-938`、`lib/class.SignManager.php:187-195` |
| 签到标记 | `lib/class.misc.php:579-608`、`setup/install.template.sql:181` |
| 敏感配置 | `setting.php:99,145,153-174`、`templates/admin-set.php:153,223,378-418` |
| Session | `lib/globals.php:276,359,634`、`lib/class.OAuth.php:286`、`lib/sfc.functions.php:59-78` |
| 2FA/限流 | `lib/globals.php:735-755`、`lib/class.loginguard.php:86` |
| 删除用户 | `lib/sfc.functions.php:369-390` |
| 日报 | `lib/cron_system_sign.php:102`、`lib/class.Reminder.php:104-110` |
| 并发 | `lib/class.misc.php:574-618`、`do.php:23,87-91` |
| 性能 | `lib/class.SignManager.php:156,930-960`、`lib/plugins.php:628,684-690`、`lib/class.S.php:38-70` |
| 前端 | `lib/ui.php:140-146`、`source/css/bootstrap.min.css`、`templates/` 内联样式 |
| 合规 | `composer.json`、`license.html`（缺 `LICENSE`） |
| CI | `.github/workflows/ci.yml`、`tools/release-gate.php`、`tools/lint.php` |

---

## 10. 不建议做的事

1. **不要为了"现代化"引入框架或 ORM**：插件 ABI、动态分表、升级器都依赖现有目录与函数式入口，重写收益远低于风险。
2. **不要立刻合并动态分表**：当前跨用户统计已由 `sign_history` 绕开；插件仍直接拼 `<prefix><users.t>`，合并会破坏兼容（`docs/architecture-evolution.md` 已论证）。
3. **不要引入消息队列**：当前任务全部由应用自身产生，分钟级 `nextrun` 足够；引入 Redis 会破坏"零 Composer 运行时依赖"的部署优势。
4. **不要一次性把 Bootstrap 3 换成 5**：365 处内联样式决定了必须分批；先 compat 层，再逐页迁移。
5. **不要用"全站重设计"阻塞安全修复**：P0/P1 与 UI 解耦，先发 7.4.1。

---

## 11. 建议排期（1 人全职）

| 周 | 内容 |
| --- | --- |
| W1 | V7.4.1 热修（P0-1～P0-5 + 加密 + ECharts + lint 守卫），发版 |
| W2–W3 | V7.5 安全（Session/2FA/CSRF/删除用户/插件签名/更新器回滚） |
| W4 | V7.5 数据正确性（`latest` contract、吧名修复、索引、option API），发版 |
| W5–W6 | V7.6 性能（claim/索引/DDL 收敛/查询重写/日志/指标） |
| W7–W9 | V7.7 UX（BS 迁移批次、移动端、无障碍、引导、i18n 取舍） |
| W10+ | V8.0 仅在触发条件满足时启动 |

如果只有 2–3 天可投入：**只做 V7.4.1 的第 1、2、3、5 项**，其余排后。
