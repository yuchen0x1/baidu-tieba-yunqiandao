# 仅维护网页版

> 阶段记录：本文保留整理/验收当时的状态（包括“未提交、未发布”等字样）；相关成果已纳入 V7.7.0。正式发布状态以 [V7.7.0 Release](https://github.com/yuchen0x1/baidu-tieba-yunqiandao/releases/tag/v7.7.0) 为准。

本项目只提供 PHP 网页版。Docker 和传统 PHP 环境部署同一份程序，用户通过浏览器使用；不制作桌面客户端、Android/iOS App 或单独的 Windows 发行版。

## 本轮检查与清理（2026-11-13）

- 源码中没有发现 Electron/Tauri、Android/iOS 工程或原生应用安装包。
- 原 `windows/` 中的 6 个文件只是本地 PHP 服务的启动、健康检查、任务触发和更新检查工具，已经移除。
- 发布流程和产物统一命名为 `tieba-cloud-sign-web-<tag>.zip`，附带 SHA-256 与更新清单；可选附带签名。打包时显式排除旧标签里的 `windows/` 文件。
- 删除 Windows 专用 CI 作业；通用 Web 打包在 Linux runner 上检查，PowerShell 仍只是发布工具。
- 网页更新器优先选 Web 包，并按所选包名匹配摘要和签名。为已有网页站点保留读取历史 `windows` 命名 ZIP 的兼容性；不再构建这类发行包。
- API 限流时读取本仓库对应标签的真实附件列表，兼容改名前后的 Web 部署包，不虚构签名附件。
- README、发布说明和后台下载提示已经改为网页版。历史日志、过往交付报告和远端 Release 保留历史原貌；本轮没有修改远程发布记录。

## 验证

- **PASS**：PHPUnit 196 tests、1052 assertions，零失败、零跳过；包括 Web 包选择、历史名称兼容、配套摘要/签名、附件来源与标签范围、禁止选择其他应用 ZIP。
- **PASS**：235 个 PHP 文件语法与硬安全检查；保留既有提示性警告。
- **PASS**：发布 PowerShell 语法检查。
- **PASS**：CI 与发布工作流 YAML 解析；Secret 扫描。
- **PASS**：在项目内的独立 Git 测试仓库生成 `tieba-cloud-sign-web-v0.0.0.zip`（仅本地测试，不是产品版本）。432 个条目，核心网页入口齐全，SHA-256 与清单一致，没有 `windows/` 启动器；夹具标签刻意包含旧启动器，以验证排除规则实际生效。
- **PASS**：保存真实 GitHub Release 附件 HTML 后，使用更新器解析出历史 Web 包和配套 SHA-256，并正确确认无签名附件；域名/标签范围与新旧包名选择通过单元测试。
- **BLOCKED**：本机 PHP/cURL 的联网回退请求连接超时。浏览工具与 Python 能读取 GitHub，但不据此把更新器的远程发现或实际在线安装标记为通过。
- **PASS**：`git diff --check`。

保留此前通知可靠性升级的未提交改动。本轮没有改数据库、提交、推送或发布新版本；正式发布后远端才会出现新的 Web 包名。旧的已部署更新器如果在 API 限流时仍硬编码旧资产名，应先完成更新再验收新命名的在线更新链路；本地测试不等于旧站点在线安装已验证。

六个 Windows 脚本已删除。后续用户明确授权清理后，遗留空目录也已移除；清理结果和当前验证见[仓库维护说明](REPOSITORY_HYGIENE.md)。
