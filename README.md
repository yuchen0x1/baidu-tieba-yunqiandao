<div align="center">

# 百度贴吧云签到（Tieba Cloud Sign）

> 自托管的百度贴吧自动签到系统：绑定百度账号后，由计划任务自动签到、复签并记录结果。

[![PHP](https://img.shields.io/badge/PHP-%3E%3D8.2-777BB4?logo=php&logoColor=white)](https://www.php.net/)
[![MariaDB](https://img.shields.io/badge/MariaDB-11.4-003545?logo=mariadb&logoColor=white)](https://mariadb.org/)
[![Docker](https://img.shields.io/badge/Docker-Compose%20V2-2496ED?logo=docker&logoColor=white)](https://www.docker.com/)

</div>

## 项目简介

百度贴吧需要每天逐个贴吧签到，人工操作容易漏签。本项目提供一个可自托管的 Web 站点：用户登录后绑定百度账号并刷新关注贴吧，系统通过计划任务执行签到、失败复签、账号健康检查，并保存签到日志。

本仓库持续正常更新，维护内容包括现代 PHP/Docker 运行环境、开放 API、通知、健康检查和安全兼容修复。

## 核心功能

- 自动签到与失败复签：`do.php` 调度内置计划任务，签到与复签分别调用 `misc::DoSign()` 和 `misc::DoSign_retry()`。
- 多用户、多百度账号和多贴吧管理：通过 Web 后台维护账号、贴吧、计划任务和插件。
- 签到看板、统计与日志：记录签到、复签、健康检查批次，并汇总成功、失败和等待数量。
- BDUSS/STOKEN 健康检查：检查百度账号凭据状态，结果保存到 `baiduid_health`。
- 开放 API：`api.php` 提供 Bearer Token 认证的状态、健康、签到、复签、健康检查和日志接口。
- 通知中心：支持 Server 酱、Telegram Bot、通用 Webhook、Bark、PushPlus 和邮件。
- 插件：仓库内提供灌水、点赞、封禁、删帖、审查、QQ 推送、备份等插件。
- Docker 部署：本地构建 PHP 8.4 + Caddy Web 镜像，配套 MariaDB 11.4、健康检查和命名卷。

## 系统要求

### Docker 部署

- Docker Engine
- Docker Compose V2（使用 `docker compose` 命令）
- 能够访问百度贴吧及所选通知服务的网络环境

### 传统 PHP 部署

- PHP `>= 8.2`（`composer.json` 的平台要求；推荐使用仍在安全支持期内的 PHP 8.4）
- MySQL/MariaDB 和可创建数据库表的账号
- Web 服务器（Nginx、Caddy、Apache 等）
- PHP 扩展：`curl`、`json`、`mysqli`；按功能可能还需要 `gd`、`mbstring`、`openssl`、`session`、`zip`
- 计划任务执行环境（Linux `cron`、Windows 任务计划程序或等效服务）

## 快速开始：Docker Compose

1. 准备配置文件：

   ```bash
   cd docker
   cp .env.example .env
   ```

2. 编辑 `docker/.env`。至少修改数据库密码：

   ```dotenv
   DB_PASSWD=change-me-please
   WEB_PORT=8080
   CSRF=true
   TC_AUTO_CREATE_INSTALL_LOCK=false
   ```

   `DB_PASSWD` 不能留空；不要把真实密码提交到 Git。

3. 在仓库根目录启动：

   ```bash
   docker compose -f docker/docker-compose.yml up -d --build
   ```

4. 访问 <http://127.0.0.1:8080>，按安装向导导入数据库并创建管理员。

Docker Compose 默认连接参数：

| 配置 | 默认值 |
| --- | --- |
| 数据库主机 | `db` |
| 数据库用户 | `root` |
| 数据库密码 | `docker/.env` 中的 `DB_PASSWD` |
| 数据库名 | `tiebacloud` |
| 表前缀 | `tc_` |
| Web 端口 | `8080` |

安装完成后确认 `setup/install.lock` 存在。`TC_AUTO_CREATE_INSTALL_LOCK=true` 可在容器启动时自动创建安装锁；首次安装阶段应保持为 `false`，安装完成后再按部署策略决定是否启用。

查看状态或日志：

```bash
docker compose -f docker/docker-compose.yml ps
docker compose -f docker/docker-compose.yml logs -f web
docker compose -f docker/docker-compose.yml logs -f db
```

## 传统 PHP 安装

1. 安装 PHP、所需扩展、MySQL/MariaDB 和 Web 服务器。
2. 将仓库文件部署到 Web 根目录，并确保 Web 进程可写入安装需要的目录。
3. 可选：安装 Composer 依赖（开发或运行测试需要）：

   ```bash
   composer install
   ```

4. 如需通过环境变量配置数据库和安全选项，设置 `config.php` 支持的变量（见下表）。否则直接编辑 `config.php`。
5. 访问 `/setup/install.php`，按向导导入 `setup/install.template.sql` 并创建管理员。
6. 安装完成后确认 `setup/install.lock` 存在，防止安装程序重复运行。
7. 在后台设置计划任务密码、站点地址、通知和 API Token，然后配置计划任务。

## 配置说明

### 环境变量（`config.php` / Docker）

| 变量 | 默认值 | 说明 |
| --- | --- | --- |
| `DB_HOST` | `127.0.0.1`（Compose 为 `db`） | 数据库主机 |
| `DB_USER` | `root` | 数据库用户 |
| `DB_PASSWD` | 空（Docker 必填） | 数据库密码 |
| `DB_NAME` | `tiebacloud` | 数据库名 |
| `DB_SSL` | `0` | 设为 `1` 启用 MySQL SSL 连接 |
| `DB_PREFIX` | `tc_` | 数据表前缀 |
| `ANTI_CSRF` | `true` | 是否启用 CSRF 防御；`CSRF` 是兼容用别名 |
| `SYSTEM_SALT` | 空 | 加密和 CSRF 密钥材料；生产环境建议设置高强度随机值 |
| `WEB_PORT` | `8080` | Compose 对外 Web 端口 |
| `PUID` / `PGID` | `1000` / `1000` | Compose 保留的容器用户配置变量 |
| `CSRF` | `true` | Compose 中传给应用的 CSRF 兼容变量 |
| `TC_AUTO_CREATE_INSTALL_LOCK` | `false` | 容器启动时是否自动创建安装锁 |
| `SSL_CERT_FILE` / `CURL_CA_BUNDLE` | 空 | Windows PHP 未配置 CA 根证书时，指向可信 CA bundle 文件；不要关闭 HTTPS 证书校验 |
| `UPDATE_GITHUB_TOKEN` | 空 | 可选的 GitHub 只读 Token，用于提高 API 速率限制或访问受限仓库；不要提交到 Git |

### 后台配置

以下选项由安装向导或 Web 后台写入数据库，不是系统环境变量：

- `system_url`：站点公开 URL，计划任务并发回调会使用它。
- `cron_pw`：`do.php` 的计划任务密码。
- `api_token`：开放 API 的 Bearer Token；为空时 API 返回 503 并视为未启用。
- `notify_enable`、`notify_channel`、`notify_on_fail`、`notify_on_health` 及各通知渠道凭据。
- 邮件 SMTP 配置（主机、端口、认证、加密方式、账号和密码）。

### 在线更新

管理员打开“系统更新”页面时，程序会通过 HTTPS 请求当前仓库的 GitHub `latest` 正式 Release，并比较 Release tag 与当前程序版本。检测到更高版本后，页面会显示版本说明、Release 页面、Windows 部署包下载地址和“立即更新”按钮；没有更高版本时会明确显示当前已是最新版本。

在线更新使用 GitHub Release 的源码 ZIP，不会覆盖当前站点的 `config.php`、`key.php`、`.env`、`docker/.env` 或 `setup/install.lock`，也不会修改数据库。更新前会再次获取最新 Release，避免使用过期页面提交；网络不可用或服务器不可写时会保留原程序并提示手动更新。

发布新版本时，请在本仓库创建正式 Release，并使用数字版本 tag（例如 `v5.08`）。Windows 部署包建议命名为包含 `windows` 的 `.zip` 文件，程序会自动将其作为下载入口展示。现有部署还可以运行 `windows/check-update.ps1` 做独立检查。

## 使用计划任务

`do.php` 会执行到期的系统计划任务，建议每分钟调用一次，由应用内部判断任务是否到点。

### Web 方式

```text
https://你的域名/do.php?pw=你的计划任务密码
```

### CLI 方式

```bash
php /var/www/do.php --pw=你的计划任务密码
```

Linux `crontab` 示例：

```cron
* * * * * php /var/www/do.php --pw='your-password' >/dev/null 2>&1
```

如果没有设置 `cron_pw`，Web 方式会拒绝执行；CLI 方式可以不带密码，但生产环境仍建议设置密码。计划任务密码会出现在 URL 或进程参数中，请使用强随机值并限制日志、历史记录和进程信息的访问权限。

## 开放 API

先在后台设置中心配置 `api_token`，然后在请求头中发送：

```http
Authorization: Bearer <api-token>
```

接口均位于 `/api.php`：

| 方法 | 路径 | 说明 |
| --- | --- | --- |
| `GET` | `/api.php?action=update` | 获取 GitHub 最新正式版本并判断是否需要更新 |
| `GET` | `/api.php?action=status` | 签到状态汇总 |
| `GET` | `/api.php?action=health` | 百度账号健康状态 |
| `POST` | `/api.php?action=sign` | 立即执行全部签到 |
| `POST` | `/api.php?action=retry` | 执行失败复签 |
| `POST` | `/api.php?action=health_check` | 执行账号健康检查 |
| `GET` | `/api.php?action=logs&limit=50` | 获取最近批次日志；`limit` 范围为 1–200 |

写操作必须使用 `POST`，认证失败返回 HTTP 401，API 未启用返回 HTTP 503，未知 action 返回 HTTP 400。

示例：

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

## 开发与测试

安装开发依赖后可运行：

```bash
composer install
composer test
composer lint
```

- `composer test`：运行 `tests/` 下的 PHPUnit 冒烟测试。
- `composer lint`：检查 `lib/class.misc.php` 的 PHP 语法。
- CI 还会使用 PHP 8.4 容器检查仓库内全部 PHP 文件，并验证 Docker Compose 配置。

测试不连接真实数据库或百度网络，不代表完整生产环境验收。

## 项目结构

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
├── lib/class.updater.php   # GitHub Release 在线检查和安全更新
├── docker/                 # Dockerfile、Compose 和环境变量示例
├── source/                 # CSS、JavaScript、字体和文档素材
├── tests/                  # PHPUnit 测试
├── SECURITY.md             # 漏洞报告与部署安全建议
├── CHANGELOG.md            # 更新记录
└── composer.json           # PHP 版本、扩展、脚本和开发依赖
```

## 故障排查与 FAQ

### 页面提示数据库连接失败

确认数据库服务已启动、`DB_HOST` 在 Compose 中为 `db`、`DB_PASSWD` 与 MariaDB 初始化密码一致，并查看 `docker compose ... logs db`。首次修改 MariaDB 密码后，已有命名卷中的旧密码不会自动更新；需要按数据库迁移策略处理，不要直接删除生产卷。

### Docker 容器健康检查失败

先查看 `docker compose ... ps` 和 `logs web`。Web 健康检查访问容器内的 `http://127.0.0.1:8080/favicon.ico`，安装页的登录跳转不会影响该检查。

### `do.php` 提示密码错误或未设置密码

在后台设置 `cron_pw`，Web 请求使用 `?pw=...`，CLI 使用 `--pw=...`。确认计划任务调用的是项目中的绝对路径。

### API 返回 401 或 503

503 表示后台尚未配置 `api_token`；401 表示 `Authorization: Bearer ...` 缺失、格式错误或 Token 不匹配。

### 贴吧签到失败或账号不健康

先在后台重新检查/更新百度账号凭据，确认服务器可访问百度 HTTPS 接口，再查看签到日志和健康检查结果。不要在公开渠道粘贴 BDUSS/STOKEN。

## 安全与隐私

- 安装完成后保留 `setup/install.lock`，并限制安装、调试和管理入口的公网暴露。
- 使用 HTTPS、强数据库密码、强计划任务密码和高强度 `SYSTEM_SALT`。
- BDUSS/STOKEN 属于敏感凭据；不要提交到 Git、日志、Issue 或聊天记录。
- 生产环境关闭调试类插件，及时更新 PHP、MariaDB、Docker 和依赖。
- 漏洞请按 [`SECURITY.md`](SECURITY.md) 的私密报告流程提交，不要直接公开利用细节。

## Docker 发布

仓库处于正常更新状态，并通过 GitHub Actions 持续检查：

- `.github/workflows/ci.yml`：PHP 语法检查和 Compose 校验。
- `.github/workflows/build.yml`：推送到 `main` 或手动触发多架构 Docker 镜像构建并推送到 Docker Hub，需要配置 `DOCKERHUB_USERNAME` 和 `DOCKERHUB_PASSWORD` Secrets。

## 许可证与相关链接

本项目使用 AGPL-3.0，详见仓库中的 [`license.html`](license.html)。

- 原项目：[`shirakun/Tieba-Cloud-Sign`](https://github.com/shirakun/Tieba-Cloud-Sign)
- 本仓库：[`yuchen0x1/forum-auto-sign`](https://github.com/yuchen0x1/forum-auto-sign)
- QQ 交流群：`1016190748`
- Telegram：<https://t.me/lenmoC>

反馈问题时，请提供部署方式、PHP/Docker/MariaDB 版本、错误信息、相关日志，以及是否修改过插件或模板。
