# 项目进化交付报告

- 项目：百度贴吧云签到
- 根目录：`E:\移动云盘同步盘\Baidu Tieba Cloud Check-in System`
- 分支：`upgrade/v7.5-fortify`
- 日期：2026-11-12
- 交付状态：PARTIAL（本地改造与静态验证完成，数据库/容器/浏览器验证待补）

## 1. 原始情况

这是一个 PHP 8.2+、MySQL/MariaDB、自托管的贴吧自动签到 Web 应用，已有多账号、调度、失败复签、健康检查、通知、开放 API、插件、Docker/Windows 部署与 v7.5 安全加固。开始时工作树包含用户已有的未提交改动，本次未重置、覆盖或推送。

## 2. 竞品研究

初筛 16 个开源项目，深读 5 个项目的仓库元数据与 README/source。研究重点是调度、并发控制、运行历史、失败通知、指标仪表盘和 PHP 兼容性。完整清单见 `research.json`；本批没有复制外部代码。

## 3. 实际实现

### P1：签到并发一致性

- 新增 `claim_owner` / `claimed_at` 字段与 `claim_expiry` 索引。
- 新增迁移 `007500_sign_claim.php`，覆盖基础表、动态分表、安装模板和回滚路径。
- `misc::DoSign()` 与 `DoSign_retry()` 在访问百度前用原子 UPDATE 抢占行。
- 成功、明确失败和不可识别结果均释放 claim；进程崩溃由 15 分钟 TTL 自动回收。
- 结果更新带 claim owner 条件，避免过期旧进程覆盖新进程结果。

### P2：配置值一致性

- `option::set()` 改为预处理 upsert。
- 内存缓存保留调用者原始值，修复含引号/反斜杠配置在同一请求中读回变形的问题。

## 4. 修改文件

- `lib/class.option.php`
- `lib/class.misc.php`
- `setup/migrations/007500_sign_claim.php`
- `setup/install.template.sql`
- `setup/template.table.sql`
- `tests/SecurityHardeningTest.php`
- `tests/SignClaimTest.php`
- `.evolution/*`

## 5. 依赖与架构

没有新增 Composer、前端或运行时依赖。实现复用现有 MySQLi `pquery`、动态分表白名单和调度入口。

## 6. 测试结果

- PHPUnit：167 tests / 839 assertions，0 failures，3 skipped。
- PHP 语法扫描：通过。
- Secret scan：通过。
- Release gate：6 pass、1 skip、0 fail；跳过原因是数据库不可达。
- 未执行：数据库集成、Docker 冒烟、浏览器 UI 回归。

## 7. 进化评分（证据化）

| 维度 | 改造前 | 当前 | 证据 |
|---|---:|---:|---|
| 功能完整度 | 20/25 | 22/25 | claim 与配置一致性已落地，DAG 等非领域功能未引入 |
| UI/体验 | 15/20 | 15/20 | 已有双主题/图表；本批未做 UI 结构修改 |
| 稳定性 | 14/20 | 17/20 | 167 项 PHPUnit 全绿；DB 集成待补 |
| 架构/代码质量 | 11/15 | 13/15 | 参数化、迁移、动态表白名单复用 |
| 性能/安全 | 7/10 | 9/10 | claim 索引、TTL 与 owner 条件；需真实并发压测 |
| 文档/工程交付 | 7/10 | 9/10 | `.evolution` 状态、研究、差距和测试报告齐备 |
| **总分** | **74/100** | **85/100** | 仅代表当前可验证证据，不替代生产验收 |

## 8. 未完成事项与下一步

1. 在隔离 MariaDB 上跑 007500 migration、并发 cron/API 演练，确认动态分表全部具备列与索引。
2. 补 claim 失效接管、异常退出、重复调用抑制的集成测试。
3. 收敛 `option::add/uset/uadd` 等旧式转义 API。
4. 执行 Docker 冒烟和浏览器工作台回归。
5. release notes 与升级 backlog 已同步；本次仍未执行 Git 提交、推送或正式发布。
