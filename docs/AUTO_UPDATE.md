# 在线自动更新实施说明

## 适用范围

本项目只维护 PHP 网页版，不提供桌面或移动 App。Docker 与传统 PHP 部署共用同一代码和发布包；更新对象是站点源码和数据库迁移。

## 方案选型

实施前对照了三个成熟方向：

1. [GitHub Releases/Actions](https://docs.github.com/en/repositories/releasing-projects-on-github/about-releases)：源码和部署包由 Release 保存，Actions 使用 `GITHUB_TOKEN` 发布，适合本项目已有的 GitHub 仓库和 PHP 部署包。
2. [Composer self-update / Composer 安装器](https://getcomposer.org/doc/03-cli.md#self-update)：适合 Composer CLI 本身或纯依赖包，不适合直接替换本项目站点文件和执行数据库迁移。
3. [Deployer](https://deployer.org/docs/7.x/installation)：通过发布目录、原子切换和历史版本回滚实现服务器部署，适合有 SSH/进程管理权限的集中式服务器；普通共享主机通常没有这些权限。

最终采用 **GitHub Release + 管理员主动触发的站点内更新器**，并吸收 Deployer 的“先备份、失败恢复”原则。该方案不增加运行时依赖，也保留手动下载和传统部署路径。

## 工作流程

1. 管理后台调用 GitHub `releases/latest`，只接受正式 Release、固定仓库路径和 HTTPS 白名单主机。
2. GitHub API 匿名限流时，仅回退到同一仓库的官方 Release 页面；不会切换到 Gitee 或任意第三方地址。
3. 管理员确认后下载 ZIP。下载最多重试 3 次，限制 512 MiB，并校验 Release 资产大小和 ZIP 魔数。
4. 在线覆盖前强制校验 SHA-256：优先使用 GitHub `digest`，否则读取同一 Release 的 `.sha256` 资产。源码归档没有可信摘要时拒绝安装。
5. 如配置 `UPDATE_RELEASE_PUBLIC_KEY`，再校验同一 Release 的 detached `.sig`（RSA/ECDSA + SHA-256）；配置 `UPDATE_REQUIRE_SIGNATURE=true` 时没有签名或签名失败会拒绝安装。
6. 覆盖前将当前代码（不含 `data/`、配置、锁文件、`vendor/` 和更新缓存）保存为 `data/backups/pre-update-files-*.zip`，保留最近 3 份。写入失败会自动移除本次新增文件并恢复旧代码。
7. 文件覆盖后跳转 `upgrade.php`。新版在数据库迁移锁内先完整写入备份，再运行迁移器和清理 opcache。备份出错时停止迁移并显示原因。迁移失败时保留代码和数据库备份，维护者可修复后重试，或按下文恢复。
8. GitHub 不可变 Release 的证明可以用 `gh release verify vX.Y.Z` 和 `gh release verify-asset vX.Y.Z tieba-cloud-sign-web-vX.Y.Z.zip` 独立验证。在 PHP 后台有现成公钥的部署仍按 detached `.sig` 策略验证。

发布包名称统一使用 `tieba-cloud-sign-web-<tag>.zip`。更新器优先选择 Web 包，校验文件与签名必须匹配同一包名。历史 `windows` 命名的 ZIP 实际也是 PHP 网页源码，只保留读取兼容；不再生成这类发行包。API 限流回退通过同一仓库的 Release 页面和附件列表读取真实名称，不猜测资产或签名存在。

## 配置

| 变量 | 必需 | 说明 |
| --- | --- | --- |
| `UPDATE_GITHUB_TOKEN` | 否 | 只读 GitHub Token，仅发往 `api.github.com`，用于提高 API 限额；不写入数据库。 |
| `UPDATE_RELEASE_PUBLIC_KEY` | 否 | 发布签名验证公钥（PEM 文本）。配置后，Release 必须带 `.sig` 才能通过签名校验。 |
| `UPDATE_REQUIRE_SIGNATURE` | 否 | `true` 时强制签名；未配置公钥或 Release 没有 `.sig` 都拒绝安装。 |
| `SSL_CERT_FILE` / `CURL_CA_BUNDLE` | 否 | Windows PHP 没有系统 CA bundle 时指定可信 CA 文件，禁止关闭 TLS 校验。 |
| `LOOP_RELOAD_FILE` | 否 | `do.php --loop` 的优雅重载标记文件。 |

发布私钥只应保存在受控的 CI Secret 或发布机，不得提交仓库。网页部署包使用 SHA-256 和可选 Release 签名验证，不涉及桌面安装器或 Authenticode 签名。

## GitHub Secrets

正式 Release 使用工作流内置的 `GITHUB_TOKEN`，仓库无需额外配置个人访问令牌；`release.yml` 只申请 `contents: write`。如果要推送 Docker Hub 镜像，在仓库 `Settings → Secrets and variables → Actions` 配置 `DOCKERHUB_USERNAME` 和 `DOCKERHUB_PASSWORD`。如果要启用包签名，应在受控发布机/CI 中把私钥写入临时文件，再传给 `-SigningPrivateKeyFile`；不要把私钥内容、路径或公钥秘密写进源码。

## 发布

版本唯一来源是 `version.php`，稳定标签统一使用 `vMAJOR.MINOR.PATCH`。所有产品更新必须先合并到 `main`，Release 工作流会拒绝不在 `origin/main` 历史中的标签。

```bash
composer gate
git tag v<版本号>
git push origin v<版本号>
```

`.github/workflows/release.yml` 会重新安装依赖、运行测试和 MariaDB 集成检查，然后调用 `tools/publish-github.ps1` 生成：

- `tieba-cloud-sign-web-vX.Y.Z.zip`
- `.sha256` 校验文件
- `tieba-cloud-sign-update-vX.Y.Z.json` 更新清单

如果发布机提供受控 RSA 私钥，可给 `tools/publish-github.ps1` 传入 `-SigningPrivateKeyFile` 生成 base64 `.sig`；客户端通过 `UPDATE_RELEASE_PUBLIC_KEY` 验证。没有私钥时不生成签名，系统不会把 SHA-256 冒充数字签名。

推送 `main` 会运行 CI 和 Docker 构建；普通 push 不会创建正式 Release。

## 测试与故障处理

- 本地：`composer check`、`composer integration`（需要 MariaDB）、`composer gate`。
- CI：PHP 8.2/8.4、Secret 扫描、迁移/调度/API/备份恢复、Docker 冒烟和 通用 Web 发布包校验。
- 更新包下载中断会重试；大小、SHA-256、签名或 ZIP 安全检查失败时不覆盖现有文件。
- 覆盖写入中途失败会自动回滚代码；数据库迁移失败时以 `data/backups/pre-upgrade_*.sql` 为恢复依据，修复后可再次运行 `upgrade.php`。
- GitHub 不可用时应用仍可正常运行，管理员可以从 Release 页面手动下载并按常规部署流程更新。

## 当前限制

- PHP Web 请求无法像桌面安装器一样在浏览器中静默重启 PHP-FPM；更新期间页面会等待服务器完成下载和校验，常驻 `do.php --loop` 仅通过 `LOOP_RELOAD_FILE` 通知进程管理器重启。
- 没有配置签名公钥时，系统仍强制 SHA-256，但不会声称完成数字签名验证。
- GitHub Release、仓库写权限和 CI Secret属于外部资源；本地代码和工作流可以验证，实际线上发布需在拥有权限的仓库中运行。


## Docker Compose 升级与回滚

升级前暂停外部调度和站点写入。保留数据库导出、当前源代码或镜像、`docker/.env`、整个 `data/`、原始 `config.php` 和 `setup/install.lock`。配置可能保存站点加密盐，恢复时需和数据库配套。

从 v7.8.1 及更早的 Compose 版本首次迁移时，先从旧 Web 容器复制文件。以下命令假定在仓库根目录，且一直使用默认 Compose 项目名。如部署使用 `-p`，每条命令都传入同一项目名。

```bash
docker compose -f docker/docker-compose.yml stop web
umask 077
mkdir -p ../tieba-instance-backup/data
docker compose -f docker/docker-compose.yml cp web:/var/www/data/. ../tieba-instance-backup/data/
docker compose -f docker/docker-compose.yml cp -L web:/var/www/config.php ../tieba-instance-backup/data/config.php
docker compose -f docker/docker-compose.yml cp -L web:/var/www/setup/install.lock ../tieba-instance-backup/data/install.lock
docker compose -f docker/docker-compose.yml exec -T db sh -ec 'exec mariadb-dump --single-transaction -uroot --password="$MYSQL_ROOT_PASSWORD" "$MYSQL_DATABASE"' > ../tieba-instance-backup/database.sql
```

安装锁若不存在，请先确认旧站点是否完成了安装。下载并核验新发布版本、切到对应源码，保留原 `docker/.env`。旧容器状态导出完成后，创建新版 Web 容器并导入备份目录，再启动。使用卷后直接通过同项目名执行 `up -d --build`，即可延续 Web 和数据库卷。

```bash
docker compose -f docker/docker-compose.yml create --build web
docker compose -f docker/docker-compose.yml cp ../tieba-instance-backup/data/. web:/var/www/data/
docker compose -f docker/docker-compose.yml up -d --wait
```

管理员登录后访问 `upgrade.php`，确认迁移成功。检查 `healthz.php?mode=ready`、登录、账号数量和计划任务，再恢复正常写入。备份应存放于站点目录之外，确认升级正常后由维护者按保留策略整理。

回滚时，暂停写入，将旧代码或镜像、配置和运行文件一起恢复到上一个已知版本。若迁移已执行，先将升级前数据库备份导入隔离数据库并验证，再切换应用连接。期间发生的新写入需要单独保留。升级失败时避免清除卷，优先保全旧数据与失败现场。

传统 PHP 部署同样适用上述恢复原则。文件覆盖失败时更新器会尝试自动恢复旧代码。数据库回滚由维护者显式执行。
