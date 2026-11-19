# 参与开发

日常开发从 `main` 建分支，以 Pull Request 合并。修复分支和功能分支使用 `fix/`、`feature/` 或 `codex/` 前缀。紧急修复走相同流程，检查通过后发布新的补丁版本。

使用 PHP 8.2+、Composer 和隔离的 MariaDB 测试库。先执行 `composer install` 和 `composer check`，涉及数据库时配置 `TCS_IT_DB_*` 后执行 `composer integration`。Docker 部署改动还需要验证安装页、健康探针和数据持久化。修改 SQL 迁移时新增文件，保留历史迁移的校验和。

若 PR 与 `main` 冲突，在开发分支解决并重跑检查。检查与复核通过后合并。发布版本、包校验和恢复流程见[发布手册](docs/RELEASE.md)，私密报告见[安全政策](SECURITY.md)。
