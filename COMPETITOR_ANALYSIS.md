# 开源竞品与相邻实现对标

最新批次见 [2026-11-18 的 32 个项目与升级候选](docs/reports/ecosystem-2026-11-18.md)。

研究日期：2026-11-16
方法：优先查看官方 GitHub 仓库、README、许可证和关键源码文件；已有 2026-11-13 研究记录继续作为基线。本轮没有复制任何竞品代码。GitHub REST 公共 API 在补充元数据时返回 403 rate limit，因此 5 个项目有完整的仓库元数据基线，另外 10 个项目完成了源码入口核查，但本轮没有把它们的最新 Release/Issue/PR 时间写成已验证事实。

## 1. 研究清单

| # | 项目 | 类型与可借鉴点 | 关键源码/证据 | 许可证信息 | 对本项目的结论 |
|---:|---|---|---|---|---|
| 1 | [MoeNetwork/Tieba-Cloud-Sign](https://github.com/MoeNetwork/Tieba-Cloud-Sign) | 直接竞品；贴吧签到、插件、cron | [`lib/class.cron.php`](https://github.com/MoeNetwork/Tieba-Cloud-Sign/blob/master/lib/class.cron.php) | 仓库 API 报告 `NOASSERTION`；复用前必须人工确认 | 保留兼容性和插件 ABI；不直接复制实现 |
| 2 | [kookxiang/Tieba_Sign](https://github.com/kookxiang/Tieba_Sign) | 早期贴吧签到流程、轻量 PHP 入口 | [`index.php`](https://github.com/kookxiang/Tieba_Sign/blob/reborn/index.php) | 未在本轮确认 SPDX；只作历史实现参考 | 继续保持简单部署路径，避免引入重框架 |
| 3 | [qd-today/qd](https://github.com/qd-today/qd) | 多任务调度、异步 worker、任务状态 | [`worker.py`](https://github.com/qd-today/qd/blob/master/worker.py) | MIT（既有研究记录） | 借鉴任务状态与 worker 边界；不引入 Python 运行时 |
| 4 | [whyour/qinglong](https://github.com/whyour/qinglong) | 任务管理 UI、脚本调度、执行日志 | [`back/services/cron.ts`](https://github.com/whyour/qinglong/blob/develop/back/services/cron.ts)、[`src/pages/crontab/index.tsx`](https://github.com/whyour/qinglong/blob/develop/src/pages/crontab/index.tsx) | Apache-2.0（既有研究记录） | 借鉴筛选/状态信息层次；保留 PHP 模板架构 |
| 5 | [jhuckaby/Cronicle](https://github.com/jhuckaby/Cronicle) | 分布式调度、锁、执行记录 | [`lib/scheduler.js`](https://github.com/jhuckaby/Cronicle/blob/master/lib/scheduler.js) | MIT（源码头注释及既有研究记录） | 参考租约和运行记录设计；当前规模不引入分布式队列 |
| 6 | [healthchecks/healthchecks](https://github.com/healthchecks/healthchecks) | cron 心跳、失败提醒、可观测性 | [`hc/api/models.py`](https://github.com/healthchecks/healthchecks/blob/master/hc/api/models.py) | BSD-3-Clause（既有研究记录） | 强化 scheduler freshness、失败通知和恢复指引 |
| 7 | [symfony/lock](https://github.com/symfony/lock) | 抽象锁、TTL、存储后端 | [`Lock.php`](https://github.com/symfony/lock/blob/7.3/Lock.php) | MIT（官方 Symfony 组件） | 借鉴“所有者 + TTL”语义；本项目采用自有 mysqli 轻量实现 |
| 8 | [symfony/messenger](https://github.com/symfony/messenger) | worker、重试策略、失败传输 | [`Worker.php`](https://github.com/symfony/messenger/blob/7.3/Worker.php) | MIT（官方 Symfony 组件） | 参考失败分类、重试和死信概念；不增加常驻消息队列依赖 |
| 9 | [laravel/horizon](https://github.com/laravel/horizon) | worker supervisor、进程状态与队列监控 | [`RedisSupervisorRepository.php`](https://github.com/laravel/horizon/blob/5.x/src/Repositories/RedisSupervisorRepository.php) | MIT | 参考管理员运行态视图；当前项目以 task_runs + Prometheus 满足单机运维 |
| 10 | [gotify/server](https://github.com/gotify/server) | 自托管推送、应用 token、消息状态 | [`LICENSE`](https://github.com/gotify/server/blob/master/LICENSE)、[server](https://github.com/gotify/server/tree/master/server) | MIT（仓库 LICENSE） | 参考渠道状态和 token 分离；继续维护现有多渠道而不绑定单一服务 |
| 11 | [binwiederhier/ntfy](https://github.com/binwiederhier/ntfy) | HTTP 推送、主题、重试和服务端路由 | [`server/server.go`](https://github.com/binwiederhier/ntfy/blob/main/server/server.go) | 需在集成前复核当前仓库 LICENSE | 参考 webhook 事件签名、重试和隐私边界 |
| 12 | [louislam/uptime-kuma](https://github.com/louislam/uptime-kuma) | 多探针、状态页、通知渠道 | [`server/model/monitor.js`](https://github.com/louislam/uptime-kuma/blob/master/server/model/monitor.js) | MIT（仓库常见声明，集成前仍复核） | 参考 health/readiness 分层和可见失败原因 |
| 13 | [dgtlmoon/changedetection.io](https://github.com/dgtlmoon/changedetection.io) | 监控目标、变更事件、通知与去重 | [`README.md`](https://github.com/dgtlmoon/changedetection.io/blob/master/README.md) | 集成前复核当前 LICENSE | 参考监控命中去重、事件历史和用户可解释反馈 |
| 14 | [n8n-io/n8n](https://github.com/n8n-io/n8n) | 工作流节点、执行记录、失败恢复 | [`workflow-runner.ts`](https://github.com/n8n-io/n8n/blob/master/packages/cli/src/workflow-runner.ts) | Sustainable Use License；不是本项目可直接复制的 MIT/Apache 代码 | 只借鉴执行记录和节点边界思想，不引入其许可证或依赖 |
| 15 | [huginn/huginn](https://github.com/huginn/huginn) | 事件驱动 agent、计划与记忆 | [`agent.rb`](https://github.com/huginn/huginn/blob/master/app/models/agent.rb) | MIT（仓库 LICENSE） | 参考插件能力声明、事件输入输出和审计记录 |

## 2. 对比结论

### 对方普遍更强的地方

- 调度类项目更早把“任务定义、运行记录、重试、失败传输、租约”拆成独立模型；`qd`、QingLong、Cronicle、Symfony Messenger 和 Horizon 的源码都把执行过程视为可观察对象。
- Healthchecks、Uptime Kuma、Gotify、ntfy 将“服务接受、任务成功、终端收到”分成不同状态，本项目的通知可靠性改造已经沿用了这条边界，但还需要统一状态 API。
- QingLong、Uptime Kuma 和 n8n 的管理页在筛选、运行状态、空状态和错误解释上更系统；本项目目前主要覆盖计划任务页和通知设置页。
- Symfony Lock 与 Cronicle 都明确处理锁所有权和过期；本轮新增 `running_owner` 正是针对旧实现的同一类风险。

### 本项目已经领先或具有独特价值的地方

- 直接针对百度贴吧的多账号、BDUSS/STOKEN 健康度、贴吧分表、签到成长统计和失败复签，通用调度器不能替代这些领域逻辑。
- Web 部署包、Docker Compose、传统 PHP 环境和零 Composer 运行时依赖让低配自托管更容易；引入 Redis、Python worker 或 Node 队列会提高运维门槛。
- 现有插件 ABI、个人/全局 API 权限、加密选项、在线更新保护文件和迁移器已经形成完整产品边界，不能为了追随竞品而重写。

### 采用、重新实现与拒绝

| 方向 | 决定 | 原因 |
|---|---|---|
| 任务租约所有者 | 重新实现 | 直接解决本项目旧锁误释放风险，无新运行时依赖 |
| API 限流 | 重新实现为原子 SQL | MariaDB 已经存在，避免引入 Redis；保留旧选项和表结构 |
| 失败退避/运行历史 | 保持并继续增强 | 现有 `task_runs`、`last_status`、退避和告警去重已覆盖核心路径 |
| 推送渠道 | 保持多渠道 | Gotify/ntfy 的单一服务模式不适合所有贴吧用户；可借鉴状态模型 |
| 分布式队列 | 暂不采用 | 当前应用为单机/少量节点，消息队列会扩大部署、迁移和幂等成本 |
| n8n 等工作流代码 | 不直接集成 | 许可证、运行时和架构边界不匹配；只吸收执行记录思想 |
| UI 重框架 | 暂不采用 | 当前模板/Bootstrap 兼容层已有大量插件页面，整体替换风险高 |

## 3. 研究限制

- 2026-11-16 的 GitHub REST 公共 API 补充调用受到 403 rate limit；因此没有把本轮未能重新读取的最新 Release、Issue、PR 时间写成事实。
- 关键源码链接来自项目默认分支或稳定分支；分支移动、许可证变更和上游协议变化在真正集成前必须重新核查。
- 研究只产生设计输入，不构成代码复制授权。AGPL、GPL、Sustainable Use License 或未声明许可证的仓库不能直接移植实现。

## 4. 对本仓库的直接输入

本轮落地的改进对应 `UPGRADE_ROADMAP.md` 的 `EV-050`（原子 API 限流）、`EV-051`（运行记录参数绑定）、`EV-052`（默认安全响应头）、`EV-053`（Retry-After）和 `EV-054`（租约所有者）。其余差距保留为可验收的路线图项目，没有把研究结论伪装成已完成代码。
