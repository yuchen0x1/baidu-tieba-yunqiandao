# 发布手册（V7.0 起）

> 发布仓库为 Gitee（`yuchen0x1/baidu-tieba-yunqiandao`）。发布必须从**真实 git clone** 中执行
> （打包基于 tag 指向的已提交树，要求工作区已提交干净）；本仓库的开发工作区如未初始化 git，请先在正式 clone 中操作。

## 一、发布前检查

在 clone 目录执行一键门禁（有条件时配置 `TCS_IT_DB_*` 指向可用的 MySQL/MariaDB，让四条集成链路真实运行）：

```bash
php tools/release-gate.php
```

9 项检查（语法扫描、单元测试、版本一致性、迁移规范、SQL 注入面守卫、迁移/调度/API/备份恢复集成）全部 PASS 才允许发布。

## 二、发布步骤

```powershell
# 1. 打版本标签并推送
git tag v7.0
git push origin v7.0

# 2. 配置 Gitee 私人令牌（需要该仓库的管理权限）
$env:GITEE_ACCESS_TOKEN = "<你的 Gitee 私人令牌>"

# 3. 发布（上传 ZIP + SHA-256），随后只保留最近 3 个 Release 并删除更旧版本的远端标签
tools/publish-gitee.ps1 -Tag v7.0 `
  -ReleaseNotesFile release-notes/v7.0.md `
  -Publish -CleanOldReleases 3 -DeleteOldTags -Confirm:$false
```

说明：

- `-CleanOldReleases <N>`：发布成功后按创建时间倒序仅保留最近 N 个 Release（刚发布的版本永远保留），其余删除。
  不传该参数则不删除任何历史版本。
- `-DeleteOldTags`：删除被清理 Release 对应的**远端标签**。标签删除后旧版本的源码归档地址将失效——
  在线更新器只读取 `releases/latest`，不受影响；但如有人依赖旧 tag 的源码地址请慎用。删除 Release 不会自动删标签，
  因此清理标签必须显式传此开关。
- 不带 `-Publish` 时仅本地打包（ZIP + SHA-256），不做任何远端变更，可用于演练。
- 脚本在发布与清理全程要求 `GITEE_ACCESS_TOKEN`；令牌只发送给 gitee.com。

## 三、发布后验证

1. 打开 `https://gitee.com/yuchen0x1/baidu-tieba-yunqiandao/releases/latest`，确认新版本、附件（ZIP + `.sha256`）与说明正确。
2. 在一台测试环境用旧版本触发「系统更新」，确认能检测到新版本、下载校验通过并完成升级（`core_version` 变为 7.0）。
3. 抽查 `UPDATE_GITEE_TOKEN` 与 Docker Compose 部署的更新检查是否正常。

## 四、常见问题

- **发布脚本报"工作区存在未提交改动"**：脚本只打包已提交内容，请先提交或还原。
- **清理时某条 Release 删除失败**：脚本会跳过该项并告警（对应标签也不会删除），修复权限后重跑清理即可——
  重复执行安全：已删除的不会匹配，保留数量内的不会被动。
- **回滚**：发布后发现问题，删除新 Release 附件并将上一个版本重新设为 latest（Gitee 网页操作）；
  代码回滚到上一 tag 重新发布为更新版本号（例如 v7.0.1），不要复用已发布的版本号。
