<div align="center">

# 百度贴吧云签到（Tieba Cloud Sign）

[![PHP](https://img.shields.io/badge/PHP-%3E%3D8.2-777BB4?logo=php&logoColor=white)](https://www.php.net/)
[![MariaDB](https://img.shields.io/badge/MariaDB-11.4-003545?logo=mariadb&logoColor=white)](https://mariadb.org/)
[![Docker](https://img.shields.io/badge/Docker-Compose%20V2-2496ED?logo=docker&logoColor=white)](https://www.docker.com/)

</div>

> 把「每天打开贴吧逐个签到」这件事，交给一台你自己掌控的服务器。

这是一个可自托管的百度贴吧自动签到站点。登录后台绑定百度账号并刷新关注贴吧后，系统会按计划任务自动完成签到、对失败项自动复签、定期检查账号凭据健康状态，并把每一批结果写入日志——从此告别漏签和忘签。

本仓库持续正常更新，维护范围包括现代 PHP / Docker 运行环境、开放 API、通知推送、健康检查以及安全与兼容性修复。

**项目速览**

- 技术栈：PHP 8.2 及以上（推荐 8.4）· MySQL/MariaDB · Docker Compose V2
- 部署形态：Docker Compose 一键起站，或任意传统 PHP 环境
- 授权协议：AGPL-3.0
- 用户交流：QQ 群 `1016190748`

---

## 一、功能一览

**自动签到引擎**

- `do.php` 调度内置计划任务：签到调用 `misc::DoSign()`，失败复签调用 `misc::DoSign_retry()`。
- 签到看板与统计：记录签到、复签、健康检查批次，并汇总成功、失败和等待数量。

**账号与数据管理**

- 多用户、多百度账号、多贴吧，全部通过 Web 后台维护，覆盖账号、贴吧、计划任务和插件。
- BDUSS/STOKEN 健康检查：检查百度账号凭据状态，结果保存到 `baiduid_health`。

**扩展能力**

- 通知中心：支持 Server 酱、Telegram Bot、通用 Webhook、Bark、PushPlus 和邮件。
- 开放 API：`api.php` 提供 Bearer Token 认证的状态、健康、签到、复签、健康检查和日志接口。
- 插件体系：仓库内置灌水、点赞、封禁、删帖、审查、QQ 推送、备份等插件。

**部署与运维**

- Docker 部署：本地构建 PHP 8.4 + Caddy Web 镜像，配套 MariaDB 11.4、健康检查和命名卷。
- 在线更新：管理员可在后台「系统更新」页面通过 Gitee 国内主通道检查并安装新版本（见 5.4 节）。
- 健康检查：`/healthz.php?mode=live` 检查 PHP 存活，`/healthz.php?mode=ready` 同时检查安装锁、数据库和核心表。

## 二、先选部署方式：环境要求对照

| 部署方式 | 适合谁 | 环境要求 |
| --- | --- | --- |
| Docker Compose（推荐） | 想少折腾环境、快速起站的用户 | Docker Engine；Docker Compose V2（使用 `docker compose` 命令）；能够访问百度贴吧及所选通知服务的网络环境 |
| 传统 PHP | 已有 PHP 主机，想并入现有站点的用户 | PHP `>= 8.2`（`composer.json` 的平台要求；推荐仍在安全支持期内的 PHP 8.4）；MySQL/MariaDB 和可创建数据库表的账号；Nginx、Caddy、Apache 等 Web 服务器；PHP 扩展 `curl`、`json`、`mysqli`，按功能可能还需要 `gd`、`mbstring`、`openssl`、`session`、`zip`；计划任务执行环境（Linux `cron`、Windows 任务计划程序或等效服务） |

## 三、上手 A：Docker Compose 起站

**第 1 步 · 拉取代码**

```bash
git clone https://gitee.com/yuchen0x1/baidu-tieba-yunqiandao.git
cd baidu-tieba-yunqiandao
```

**第 2 步 · 准备配置文件**

```bash
cd docker
cp .env.example .env
```

**第 3 步 · 编辑 `docker/.env`**，至少修改数据库密码：

```dotenv
DB_PASSWD=change-me-please
WEB_PORT=8080
CSRF=true
TC_AUTO_CREATE_INSTALL_LOCK=false
```

`DB_PASSWD` 不能留空；不要把真实密码提交到 Git。

**第 4 步 · 在仓库根目录启动**

```bash
docker compose -f docker/docker-compose.yml up -d --build
```

**第 5 步 · 完成安装**：访问 <http://127.0.0.1:8080>，按安装向导导入数据库并创建管理员。

Docker Compose 默认连接参数：

| 配置项 | 默认值 |
| --- | --- |
| 数据库主机 | `db` |
| 数据库用户 | `root` |
| 数据库密码 | `docker/.env` 中的 `DB_PASSWD` |
| 数据库名 | `tiebacloud` |
| 表前缀 | `tc_` |
| Web 端口 | `8080` |

安装完成后确认 `setup/install.lock` 存在。`TC_AUTO_CREATE_INSTALL_LOCK=true` 可在容器启动时自动创建安装锁；首次安装阶段应保持为 `false`，安装完成后再按部署策略决定是否启用。

日常查看状态或日志：

```bash
docker compose -f docker/docker-compose.yml ps
docker compose -f docker/docker-compose.yml logs -f web
docker compose -f docker/docker-compose.yml logs -f db
```

## 四、上手 B：传统 PHP 环境

1. 安装 PHP、所需扩展、MySQL/MariaDB 和 Web 服务器。
2. 获取代码并将仓库文件部署到 Web 根目录（克隆地址：<https://gitee.com/yuchen0x1/baidu-tieba-yunqiandao.git>），确保 Web 进程可写入安装需要的目录。
3. 可选：安装 Composer 依赖（开发或运行测试需要）：

   ```bash
   composer install
   ```

4. 如需通过环境变量配置数据库和安全选项，设置 `config.php` 支持的变量（见第五章）；否则直接编辑 `config.php`。
5. 访问 `/setup/install.php`，按向导导入 `setup/install.template.sql` 并创建管理员。
6. 安装完成后确认 `setup/install.lock` 存在，防止安装程序重复运行。
7. 在后台设置计划任务密码、站点地址、通知和 API Token，然后配置计划任务。

Windows 部署包可双击 `windows/start-web.bat`，它会检查 PHP 版本、必需扩展和端口后打开安装页或站点首页。指定其他端口可运行：

```powershell
powershell -ExecutionPolicy Bypass -File windows/start-web.ps1 -Port 8081
```

## 五、配置参考

### 5.1 环境变量：数据库连接

`config.php` 与 Docker 均支持以下变量：

| 变量 | 默认值 | 说明 |
| --- | --- | --- |
| `DB_HOST` | `127.0.0.1`（Compose 为 `db`） | 数据库主机 |
| `DB_USER` | `root` | 数据库用户 |
| `DB_PASSWD` | 空（Docker 必填） | 数据库密码 |
| `DB_NAME` | `tiebacloud` | 数据库名 |
| `DB_SSL` | `0` | 设为 `1` 启用 MySQL SSL 连接 |
| `DB_PREFIX` | `tc_` | 数据表前缀 |

### 5.2 环境变量：安全与运行

| 变量 | 默认值 | 说明 |
| --- | --- | --- |
| `ANTI_CSRF` | `true` | 是否启用 CSRF 防御；`CSRF` 是兼容用别名 |
| `CSRF` | `true` | Compose 中传给应用的 CSRF 兼容变量 |
| `SYSTEM_SALT` | 空 | 加密和 CSRF 密钥材料；生产环境建议设置高强度随机值 |
| `WEB_PORT` | `8080` | Compose 对外 Web 端口 |
| `PUID` / `PGID` | `1000` / `1000` | Compose 保留的容器用户配置变量 |
| `TC_AUTO_CREATE_INSTALL_LOCK` | `false` | 容器启动时是否自动创建安装锁 |
| `SSL_CERT_FILE` / `CURL_CA_BUNDLE` | 空 | Windows PHP 未配置 CA 根证书时，指向可信 CA bundle 文件；不要关闭 HTTPS 证书校验 |
| `UPDATE_GITEE_TOKEN` | 空 | Gitee Release 为私有或 API 限流时必需的只读 Token，仅发送给 Gitee API；不要提交到 Git |

### 5.3 后台配置项

以下选项由安装向导或 Web 后台写入数据库，不是系统环境变量：

| 配置项 | 作用 |
| --- | --- |
| `system_url` | 站点公开 URL，计划任务并发回调会使用它 |
| `cron_pw` | `do.php` 的计划任务密码 |
| `api_token` | 开放 API 的 Bearer Token；为空时 API 返回 503 并视为未启用 |
| `notify_enable`、`notify_channel`、`notify_on_fail`、`notify_on_health` 及各通知渠道凭据 | 通知开关、触发条件与各渠道配置 |
| 邮件 SMTP 配置 | 主机、端口、认证、加密方式、账号和密码 |

### 5.4 在线更新机制

- 管理员打开「系统更新」页面时，程序默认通过 HTTPS 请求当前仓库的 Gitee `latest` 正式 Release，并比较 Release tag 与当前程序版本；检测到更高版本后，页面会显示版本说明、Release 页面、Windows 部署包下载地址和「立即更新」按钮，没有更高版本时会明确显示当前已是最新版本。
- 在线更新使用 Gitee Release 的源码 ZIP，不会覆盖当前站点的 `config.php`、`key.php`、`.env`、`docker/.env` 或 `setup/install.lock`，也不会修改数据库。
- 若 Gitee API 返回 `403` 或 `429`，请在部署环境设置只读 `UPDATE_GITEE_TOKEN`；令牌不会写入配置文件，也不会发送到其他域名。
- 更新前会再次获取最新 Release，避免使用过期页面提交；网络不可用或服务器不可写时会保留原程序并提示手动更新。
- 发布新版本时，请在 Gitee 创建正式 Release，并使用数字版本 tag（例如 `v5.09`）。Windows 部署包建议命名为包含 `windows` 的 `.zip` 文件，程序会自动将其作为下载入口展示。现有部署还可以运行 `windows/check-update.ps1` 做独立检查；`tools/publish-gitee.ps1 -Tag v5.10` 默认只生成本地包和 SHA-256，必须额外传入 `-Publish` 才会创建 Gitee Release。

## 六、计划任务：让签到按时跑起来

`do.php` 会执行到期的系统计划任务，建议每分钟调用一次，由应用内部判断任务是否到点。两种调用方式任选：

| 调用方式 | 写法 |
| --- | --- |
| Web | `https://你的域名/do.php?pw=你的计划任务密码` |
| CLI | `php /var/www/do.php --pw=你的计划任务密码` |

Linux `crontab` 示例：

```cron
* * * * * php /var/www/do.php --pw='your-password' >/dev/null 2>&1
```

密码注意事项：如果没有设置 `cron_pw`，Web 方式会拒绝执行；CLI 方式可以不带密码，但生产环境仍建议设置密码。计划任务密码会出现在 URL 或进程参数中，请使用强随机值并限制日志、历史记录和进程信息的访问权限。

## 七、常见问题排查

**问：页面提示数据库连接失败？**

答：确认数据库服务已启动、`DB_HOST` 在 Compose 中为 `db`、`DB_PASSWD` 与 MariaDB 初始化密码一致，并查看 `docker compose ... logs db`。首次修改 MariaDB 密码后，已有命名卷中的旧密码不会自动更新；需要按数据库迁移策略处理，不要直接删除生产卷。

**问：Docker 容器健康检查失败？**

答：先查看 `docker compose ... ps` 和 `logs web`。容器存活检查访问 `/healthz.php?mode=live`；安装完成后再访问 `/healthz.php?mode=ready`，确认数据库和核心表均已就绪。

Windows 可运行：

```powershell
powershell -ExecutionPolicy Bypass -File windows/check-health.ps1 -Mode ready
```

**问：`do.php` 提示密码错误或未设置密码？**

答：在后台设置 `cron_pw`，Web 请求使用 `?pw=...`，CLI 使用 `--pw=...`。确认计划任务调用的是项目中的绝对路径。

**问：API 返回 401 或 503？**

答：503 表示后台尚未配置 `api_token`；401 表示 `Authorization: Bearer ...` 缺失、格式错误或 Token 不匹配。

**问：贴吧签到失败或账号不健康？**

答：先在后台重新检查/更新百度账号凭据，确认服务器可访问百度 HTTPS 接口，再查看签到日志和健康检查结果。不要在公开渠道粘贴 BDUSS/STOKEN。

## 八、开放 API

先在后台设置中心配置 `api_token`，然后在请求头中携带：

```http
Authorization: Bearer <api-token>
```

接口均位于 `/api.php`：

| 接口 | 方法 | 用途 |
| --- | --- | --- |
| `/api.php?action=update` | `GET` | 获取 Gitee 最新正式版本并判断是否需要更新 |
| `/api.php?action=status` | `GET` | 签到状态汇总 |
| `/api.php?action=health` | `GET` | 百度账号健康状态 |
| `/api.php?action=sign` | `POST` | 立即执行全部签到 |
| `/api.php?action=retry` | `POST` | 执行失败复签 |
| `/api.php?action=health_check` | `POST` | 执行账号健康检查 |
| `/api.php?action=logs&limit=50` | `GET` | 获取最近批次日志；`limit` 范围为 1–200 |

写操作必须使用 `POST`，认证失败返回 HTTP 401，API 未启用返回 HTTP 503，未知 action 返回 HTTP 400。

调用示例：

```bash
curl -H @auth-header.txt \
  "http://127.0.0.1:8080/api.php?action=status"

curl -X POST -H @auth-header.txt \
  "http://127.0.0.1:8080/api.php?action=sign"
```

其中 `auth-header.txt` 内容为：

```text
Authorization: Bearer your-api-token
```

请通过 HTTPS 暴露生产 API，不要在命令历史、Issue 或日志中泄露 Token、BDUSS、STOKEN 或通知凭据。

## 九、安全清单

- 安装完成后保留 `setup/install.lock`，并限制安装、调试和管理入口的公网暴露。
- 使用 HTTPS、强数据库密码、强计划任务密码和高强度 `SYSTEM_SALT`。
- BDUSS/STOKEN 属于敏感凭据；不要提交到 Git、日志、Issue 或聊天记录。
- 生产环境关闭调试类插件，及时更新 PHP、MariaDB、Docker 和依赖。
- 发现漏洞请按 [`SECURITY.md`](SECURITY.md) 的私密报告流程提交，不要直接公开利用细节。

## 十、开发者指南：测试、CI 与目录结构

**本地开发与测试**

安装开发依赖后可运行：

```bash
composer install
composer test
composer lint
composer check
```

- `composer test`：运行 `tests/` 下的 PHPUnit 冒烟测试。
- `composer lint`：检查仓库内全部非依赖 PHP 文件的语法。
- `composer check`：依次运行全仓语法检查和 PHPUnit 测试。
- CI 会在 PHP 8.2 与 8.4 上执行 Composer 校验、依赖审计、全仓语法检查和 PHPUnit 测试，并验证 PowerShell 脚本与 Docker Compose 配置。
- 测试不连接真实数据库或百度网络，不代表完整生产环境验收。

**持续集成与镜像发布**

- `.github/workflows/ci.yml`：PHP 语法检查和 Compose 校验。
- `.github/workflows/build.yml`：推送到 `main` 或手动触发多架构 Docker 镜像构建并推送到 Docker Hub，需要配置 `DOCKERHUB_USERNAME` 和 `DOCKERHUB_PASSWORD` Secrets。

**项目结构**

```text
.
├── api.php                 # Bearer Token API
├── do.php                  # 计划任务入口
├── index.php / ajax.php    # Web 入口与异步请求
├── config.php / init.php   # 配置与初始化
├── setting.php             # 后台设置及管理操作
├── lib/                    # 核心类、计划任务、通知和数据库封装
├── templates/              # Web 页面模板
├── plugins/                # 插件及插件说明
├── setup/                  # 安装、升级、检查和 SQL 模板
├── lib/class.updater.php   # Gitee Release 在线检查和安全更新
├── docker/                 # Dockerfile、Compose 和环境变量示例
├── source/                 # CSS、JavaScript、字体和文档素材
├── tests/                  # PHPUnit 测试
├── SECURITY.md             # 漏洞报告与部署安全建议
├── CHANGELOG.md            # 更新记录
└── composer.json           # PHP 版本、扩展、脚本和开发依赖
```

## 十一、致谢与相关资源

本项目使用 AGPL-3.0 授权，详见仓库中的 [`license.html`](license.html)。

- 本仓库（Gitee）：<https://gitee.com/yuchen0x1/baidu-tieba-yunqiandao>
- 原项目：shirakun/Tieba-Cloud-Sign
- QQ 交流群：`1016190748`
- Telegram：<https://t.me/lenmoC>

反馈问题时，请提供部署方式、PHP/Docker/MariaDB 版本、错误信息、相关日志，以及是否修改过插件或模板。
