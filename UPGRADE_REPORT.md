# Project Evolution Upgrade Report

日期：2026-11-16
仓库：`Baidu Tieba Cloud Check-in System`  版本：`V7.8.0`  分支：`main`

## A. 竞品研究

- 研究清单：15 个开源项目，见 [`COMPETITOR_ANALYSIS.md`](COMPETITOR_ANALYSIS.md)。
- 5 个项目已有完整仓库元数据基线；另外 10 个项目完成官方仓库关键源码入口核查。
- GitHub REST 公共 API 在补充最新 Release/Issue/PR 元数据时返回 403 rate limit，因此没有把未重新核验的时间写成事实。原始研究 JSON：[`docs/reports/evolution-2026-11-16-research.json`](docs/reports/evolution-2026-11-16-research.json)。
- 最值得借鉴的实现方向：任务租约所有者、失败退避和运行记录、健康探针、通知状态分层、插件/工作流能力边界。
- 本项目的独特优势：百度贴吧协议适配、多账号/分组、BDUSS 健康处置、签到成长统计、Web 部署包和低依赖自托管。
- 主要短板：长任务自动续租和外部副作用幂等、大库流式备份、可信反向代理配置、真实升级/回滚 E2E、插件历史代码清理。

## B. 升级数量与状态

| 状态 | 数量 | 说明 |
|---|---:|---|
| 发现/规划 | 54 | `UPGRADE_ROADMAP.md` 中 EV-001 至 EV-054，按功能、可靠性、安全、性能、运维、API、UX、发布分组 |
| 本轮完成 | 4 | EV-050、EV-051、EV-052、EV-053，均有代码和本地回归证据 |
| 本轮部分完成 | 1 | EV-054：租约所有者和主动续租 API 已实现；自动周期续租和 MariaDB 并发演练未完成 |
| 本轮未完成 | 49 | EV-001 至 EV-049，已写入验收标准和后续顺序；未把建议标成完成 |

已有的签到 claim、任务历史、保留清理、API 合同、通知状态、在线更新等能力属于前序基线，在审计中单独列出，不重复计入本轮新增完成数。

## C. 代码与配置变更

### 新增

- `lib/api-rate-limit.php`：原子 API 限流消费函数。
- `setup/migrations/007600_cron_lease_owner.php`：为 `cron` 表增加 `running_owner` 的幂等迁移。
- `tests/ApiRateLimitTest.php`：限流并发路径、达到上限和存储失败测试。
- `PROJECT_AUDIT.md`、`COMPETITOR_ANALYSIS.md`、`UPGRADE_ROADMAP.md`、`UPGRADE_REPORT.md`。
- `docs/reports/evolution-2026-11-16-research.json`：研究方法与项目清单记录。

### 修改

- `api.php`：替换 SELECT→UPDATE 限流流程，使用原子 UPDATE/UPSERT；限流返回 `Retry-After`；存储失败返回 503。
- `lib/class.cron.php`：任务锁写入所有者令牌，新增 `renewLock()`，释放时按所有者条件更新；`latestRun()` 改为参数绑定。
- `setup/install.template.sql`：新安装包含 `running_owner` 和 007600 迁移记录。
- `init.php`、`healthz.php`：增加默认安全响应头。
- `tests/CronSchedulerTest.php`、`tests/CronHistoryTest.php`：增加租约所有者/续租和参数绑定回归。

### 删除与数据迁移

- 没有删除用户文件、业务数据或配置。
- 没有运行真实生产数据库迁移；迁移文件已加入本地发布门禁和新安装模板，实际站点需由升级器在备份后执行。

## D. 验证结果

| 检查 | 结果 | 证据 |
|---|---|---|
| 变更文件 PHP 语法 | PASS | `php -l` 覆盖本轮 PHP 文件 |
| 聚焦单元测试 | PASS | `php vendor/bin/phpunit tests/CronSchedulerTest.php tests/CronHistoryTest.php tests/ApiRateLimitTest.php`：32 tests，0 failures |
| 全量 PHPUnit | PASS | `php vendor/bin/phpunit`：204 tests，0 failures，3 skipped |
| 全仓 lint/安全规则 | PASS | `php tools/lint.php`：238 文件；保留 10 条历史人工复核警告 |
| Secret 扫描 | PASS | `php tools/secret-scan.php`：368 个追踪文件，无疑似 Secret |
| Composer 配置/依赖审计 | PASS | `composer validate --strict`、`composer audit --locked --no-interaction` |
| Composer platform requirements | BLOCKED/FAIL | `composer check-platform-reqs`：当前 CLI 未加载必需的 `ext-mysqli` |
| 发布门禁 | PASS | `php tools/release-gate.php`：6 通过、1 数据库集成跳过、0 失败 |
| 工作树格式 | PASS | `git diff --check` |
| MariaDB 迁移/调度/API/备份/通知集成 | BLOCKED | 本轮没有配置 `TCS_IT_*` 或启动 MariaDB |
| Docker、多 PHP 版本、真实百度/通知/更新 | BLOCKED/PARTIAL | 没有本轮可用的外部实例或 Docker 服务；不能据此声称生产验证 |

## E. 升级前后变化

### 升级前

- 任务锁依赖任务名、`running` 和时间戳；过期任务被接管后，旧进程仍可能按任务名清理锁。
- API 限流先读计数再写入，并发请求可能在同一窗口超发。
- `latestRun()` 将任务名转义后拼入 SQL，虽然有转义，仍不符合统一参数绑定边界。
- 通用 Web 响应没有在应用入口统一发出浏览器安全响应头。

### 升级后

- 任务锁具备进程级所有者令牌；旧所有者的释放不会匹配新租约；长任务可以调用 `cron::renewLock()`。
- 限流额度由数据库原子条件更新和唯一键 UPSERT 消费，达到上限返回 429，客户端可据 `Retry-After` 重试。
- 任务历史最新记录使用预处理参数；引号和 SQL 片段只作为数据处理。
- Web/healthz 入口默认发出 `nosniff`、点击劫持、引用者和权限策略头。

## F. 遗留风险与下一步

1. 在隔离 MariaDB 中执行 007600 迁移，并用两个并发进程验证旧租约不能释放新租约。
2. 在签到批处理的安全边界接入自动续租；对百度请求设置最大耗时和可恢复的分段状态。
3. 将备份改为流式文件写出，记录峰值内存和恢复校验结果。
4. 配置可信代理 CIDR，拒绝未授权来源伪造 `X-Forwarded-Proto`。
5. 对存量插件的动态 SQL、命令执行和网络目标完成逐文件审计。
6. 完成旧版本客户端的签名 Release 更新、迁移失败和回滚演练后，再决定是否发布新版本。

## G. 交付边界

本次工作已提交为 `2deea59` 并推送到 `main`；`v7.8.0` GitHub Release 已创建并通过发布工作流 `43880255325`。公开 Web ZIP、SHA-256 文件和更新清单已下载复核，摘要一致。没有部署到生产，也未触碰真实百度凭据。所有未完成项目、证据限制和阻碍均保留在本报告、审计和路线图中。

发布后核对：Release 为正式非草稿版本，资产包括 `tieba-cloud-sign-web-v7.8.0.zip`、对应 `.sha256` 和 `tieba-cloud-sign-update-v7.8.0.json`；本地工作树在发布核对后保持干净。
