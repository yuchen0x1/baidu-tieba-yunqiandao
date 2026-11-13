# 发布手册

> GitHub 是本项目的源码与发布仓库：<https://github.com/yuchen0x1/baidu-tieba-yunqiandao>。
> 发布操作应在真实且干净的 git clone 中执行。发布包取自现有版本 tag 指向的已提交树；工作区有已跟踪文件改动时会阻止打包。

本项目仅发布网页版源码部署包，不制作桌面或移动 App，也不提供单独的 Windows 发行包。Docker 与传统 PHP 是同一网页版的部署方式。历史发布记录保留原样。

源码仓库与部署包的边界见[仓库维护说明](REPOSITORY_HYGIENE.md)。`git archive --worktree-attributes` 应用维护中的 `.gitattributes`，排除测试、审计报告、截图、网关参考实现和开发工具；保留 `tools/migrate.php`、安装 SQL 模板、访问保护文件及无凭据的 `config.php` 模板。不得直接把个人工作目录打成 ZIP 发布。

## 一、发布前检查

在 clone 目录执行发布门禁（有条件时配置 `TCS_IT_DB_*` 指向可用的 MySQL/MariaDB，让集成链路真实运行而不是跳过）：

```bash
php tools/release-gate.php
```

所有检查通过后再发布。选定 tag 前，请核实 `version.php`、`composer.json`、迁移文件和对应发布说明中的版本信息。本手册不替项目选择版本，也不创建发布说明。

## 二、发布流程

确认项目元数据和发布说明无误后，创建并推送版本 tag：

```bash
tag="v<版本号>"
git tag "$tag"
git push origin "$tag"
```

推送 `v*` tag 会触发 `.github/workflows/release.yml`：先运行语法/安全扫描、Secret 扫描、PHPUnit 以及基于 MariaDB 的迁移、调度、API、备份恢复与通知集成检查；全部通过后，再将 tag 中的源码打包为通用 Web ZIP（`tieba-cloud-sign-web-vX.Y.Z.zip`）、生成 SHA-256 校验文件和更新清单 JSON，并创建 GitHub Release。工作流还会确认 tag 的提交已经位于 `origin/main` 历史中。工作流使用仓库 `GITHUB_TOKEN` 及 `contents: write` 权限，无需个人访问令牌或仓库变量。

如需本地打包或手动发布，请安装并登录有目标仓库 Release 写权限的 [GitHub CLI](https://cli.github.com/)，然后运行：

```powershell
$tag = "v<版本号>"
$arguments = @{
  Tag = $tag
  Publish = $true
  Confirm = $false
}
# 仅当该 tag 对应的发布说明文件已审核并存在时才传入。
$notes = "release-notes/$tag.md"
if (Test-Path -LiteralPath $notes) {
  $arguments.ReleaseNotesFile = $notes
}
tools/publish-github.ps1 @arguments
```

如已有受控的 RSA 私钥文件，可额外传入 `-SigningPrivateKeyFile <path>`；脚本会生成 base64 编码的 `.sig` 资产并把算法写入更新清单。私钥路径只应指向发布机或临时 CI Secret 文件，绝不能提交仓库。未提供私钥时只发布 SHA-256 校验文件，不会伪造签名。

省略 `ReleaseNotesFile` 时由 GitHub 自动生成发布说明。不带 `-Publish` 时，脚本只在本地生成 ZIP 和 `.sha256` 文件，不执行远端操作。脚本要求数字版本 tag 已存在，且工作区没有已跟踪文件改动。

以下清理参数不属于常规发布步骤，使用前应先审核保留策略：

- `-CleanOldReleases <N>`：发布成功后删除最新 N 个以外的 Release；刚发布的 Release 会保留。
- `-DeleteOldTags`：同时删除成功清理的 Release 对应远端 tag。tag 删除后，其源码归档将不再可用。

发布脚本通过 `gh` 使用 `github.com` 的登录凭据，不读取其他平台的发布凭据。

## 三、发布后验证

1. 打开 <https://github.com/yuchen0x1/baidu-tieba-yunqiandao/releases>，核对 tag、发布说明、Web ZIP 和 `.sha256` 附件。
2. 下载 ZIP、`.sha256` 和 `tieba-cloud-sign-update-*.json`，验证 SHA-256，并在临时测试环境验证网页版安装与浏览器访问。
3. 在测试环境运行旧版本，从后台「系统更新」确认能检测到 GitHub `latest` Release。执行在线更新后，更新器应下载 Release 资产，校验 GitHub Release API 的 `digest` 或同一 Release 的 `.sha256` 摘要，校验通过后完成覆盖安装，并保留站点配置。
4. 确认缺少可信 SHA-256 摘要、摘要不一致或只有源码归档时，更新器会拒绝在线覆盖安装，原程序保持不变；配置 `UPDATE_RELEASE_PUBLIC_KEY` 后还应验证 `.sig` 签名，`UPDATE_REQUIRE_SIGNATURE=true` 时缺少签名也必须拒绝；此时可从 Release 页面手动下载安装。
5. 在需要提高 GitHub API 限额的环境中，可设置只读 `UPDATE_GITHUB_TOKEN` 后重试。令牌仅发送给 `api.github.com`，不要写入配置文件或提交到 Git。

## 四、常见问题与回滚

- **打包脚本提示存在已跟踪文件改动**：提交或还原这些改动，然后从 tag 对应的干净 clone 打包。未跟踪文件不会进入包，脚本使用 `git archive` 从 tag 树取文件。
- **GitHub CLI 未登录或权限不足**：运行 `gh auth status --hostname github.com`，并确认本地账户或工作流令牌对目标仓库具有 Release 写权限。
- **回滚**：发现问题后，将有问题的 GitHub Release 删除或标记为预发布；修正并审核项目元数据后，使用新的版本 tag 重新发布。不要复用已发布的版本号。
