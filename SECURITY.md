# 安全政策

## 报告漏洞

请不要在公开 Issue 中提交未修复的安全漏洞。请通过 GitHub 的 Security Advisory 功能私密提交：

仓库页面 -> Security -> Report a vulnerability

## 部署安全建议

- 安装完成后确认 `setup/install.lock` 已存在，避免安装程序被再次访问。
- Docker 部署请使用强随机数据库密码，密码只放在 `docker/.env` 中，不要提交到代码仓库。
- 修改 `config.php` 时请使用 UTF-8 无 BOM 编码，避免中文注释导致解析失败。
- 请使用仍在安全支持期内的 PHP 版本，推荐 PHP 8.4；不再支持已停止维护的 PHP 旧版本。
- 不要让 `do.php?pw=...` 的计划任务密码使用弱密码，该地址可以绕过登录触发计划任务。
- 停止使用并移除已废弃的 `safe_mode`、`mysql_*` 扩展相关配置；它们已从现代 PHP 中移除。
