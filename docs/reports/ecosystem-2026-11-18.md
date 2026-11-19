# 开发生态、竞品与升级候选（2026-11-18）

通过 GitHub GraphQL 批量读取公开仓库元数据、默认分支提交和根目录、README（若路径存在）、最新 Release 和最近更新的开放 Issue。来源列可复查；Issue 抽样用于定位变化，无社区需求频率统计。发布与备份方向进一步核查了 Nextcloud 更新器、GitHub 不可变 Release、Compose 命名卷及其官方文档。设计取舍独立实现。

| 仓库 | 关系与平台 | 默认分支、证据 | 许可（GitHub SPDX） | Star / Fork | 最近提交 / Release | 选择 |
| --- | --- | --- | --- | ---: | --- | --- |
| [MoeNetwork/Tieba-Cloud-Sign](https://github.com/MoeNetwork/Tieba-Cloud-Sign) | 直接 · PHP/HTML；Web、Docker | [master](https://github.com/MoeNetwork/Tieba-Cloud-Sign/tree/3319222b3ada) · lib/plugins/setup · [开放 Issue](https://github.com/MoeNetwork/Tieba-Cloud-Sign/issues/299) | NOASSERTION | 1978 / 418 | 2026-10-22 · 未返回 | 保留旧插件和 PHP 部署边界 |
| [kookxiang/Tieba_Sign](https://github.com/kookxiang/Tieba_Sign) | 直接 · PHP；Web | [reborn](https://github.com/kookxiang/Tieba_Sign/tree/906f2cf15387) · system/template | 未识别 | 187 / 81 | 2016-07-11 · 未返回 | 对比轻量部署和签名单站模型 |
| [kenvix/Tieba-Cloud-Sign](https://github.com/kenvix/Tieba-Cloud-Sign) | 直接 · 历史 Web 分支 | [master](https://github.com/kenvix/Tieba-Cloud-Sign/tree/32d7ca199a53) · README / 上游链接 | 未识别 | 1 / 0 | 2016-05-29 · 未返回 | 仅保留来源谱系，默认分支缺少实现目录 |
| [qd-today/qd](https://github.com/qd-today/qd) | 相邻 · Python；Web、worker、Docker | [master](https://github.com/qd-today/qd/tree/d4a2bde2fb7c) · worker.py / web · [开放 Issue](https://github.com/qd-today/qd/issues/645) | MIT | 5598 / 646 | 2026-11-07 · [20261108](https://github.com/qd-today/qd/releases/tag/20261108) | 执行状态和任务能力可以分层展示 |
| [whyour/qinglong](https://github.com/whyour/qinglong) | 相邻 · TypeScript；Web、CLI、Docker | [develop](https://github.com/whyour/qinglong/tree/d9e9e083abda) · back/services / src/pages · [开放 Issue](https://github.com/whyour/qinglong/issues/2967) | Apache-2.0 | 20113 / 3232 | 2026-11-17 · [v2.24.0](https://github.com/whyour/qinglong/releases/tag/v2.24.0) | 筛选、任务上下文与执行日志 |
| [jhuckaby/Cronicle](https://github.com/jhuckaby/Cronicle) | 相邻 · Node.js；Web 和常驻 worker | [master](https://github.com/jhuckaby/Cronicle/tree/be4763491bfa) · lib/scheduler.js · [开放 Issue](https://github.com/jhuckaby/Cronicle/issues/993) | NOASSERTION | 5879 / 510 | 2026-11-16 · [v0.9.147](https://github.com/jhuckaby/Cronicle/releases/tag/v0.9.147) | 计划任务租约和历史的边界 |
| [healthchecks/healthchecks](https://github.com/healthchecks/healthchecks) | 相邻 · Django；Web、Docker | [master](https://github.com/healthchecks/healthchecks/tree/6c05a7adf266) · hc/api / docker · [开放 Issue](https://github.com/healthchecks/healthchecks/issues/1368) | BSD-3-Clause | 10532 / 1048 | 2026-11-16 · [v5.0.3](https://github.com/healthchecks/healthchecks/releases/tag/v5.0.3) | 健康与运行结果分层 |
| [symfony/lock](https://github.com/symfony/lock) | 技术 · PHP；服务端锁 | [8.2](https://github.com/symfony/lock/tree/3a8e5fd5118a) · Store | MIT | 516 / 15 | 2026-11-16 · [v8.2.0-BETA2](https://github.com/symfony/lock/releases/tag/v8.2.0-BETA2) | 所有者、存储后端与 TTL |
| [symfony/messenger](https://github.com/symfony/messenger) | 技术 · PHP；服务端队列 | [8.2](https://github.com/symfony/messenger/tree/d5de0f9fe2c8) · Worker.php / Transport | MIT | 1113 / 94 | 2026-11-17 · [v8.2.0-BETA2](https://github.com/symfony/messenger/releases/tag/v8.2.0-BETA2) | 保留有界重试和失败分类 |
| [laravel/horizon](https://github.com/laravel/horizon) | 相邻 · Laravel/Redis；Web | [5.x](https://github.com/laravel/horizon/tree/b86d5aa87ca4) · src / resources · [开放 Issue](https://github.com/laravel/horizon/issues/1845) | MIT | 4182 / 757 | 2026-11-17 · [v5.52.0](https://github.com/laravel/horizon/releases/tag/v5.52.0) | 运维状态分层；当前规模无需 Redis |
| [gotify/server](https://github.com/gotify/server) | 相邻 · Go；Web/API 与客户端 | [master](https://github.com/gotify/server/tree/01086b95a8b6) · server / auth · [开放 Issue](https://github.com/gotify/server/issues/714) | NOASSERTION | 16220 / 902 | 2026-11-09 · [v3.1.1](https://github.com/gotify/server/releases/tag/v3.1.1) | 通知应用与用户凭据分开 |
| [binwiederhier/ntfy](https://github.com/binwiederhier/ntfy) | 相邻 · Go；Web、CLI、Android/iOS 客户端 | [main](https://github.com/binwiederhier/ntfy/tree/a098f52f6b64) · server / client · [开放 Issue](https://github.com/binwiederhier/ntfy/issues/2211) | Apache-2.0 | 35633 / 1723 | 2026-11-16 · [v2.38.1](https://github.com/binwiederhier/ntfy/releases/tag/v2.38.1) | 投递确认和订阅使用不同状态 |
| [louislam/uptime-kuma](https://github.com/louislam/uptime-kuma) | 相邻 · Node.js/Vue；Web、Docker | [master](https://github.com/louislam/uptime-kuma/tree/9ea6750c1971) · server / src · [开放 Issue](https://github.com/louislam/uptime-kuma/issues/8212) | MIT | 94593 / 8869 | 2026-11-18 · [2.5.12](https://github.com/louislam/uptime-kuma/releases/tag/2.5.12) | 健康页、状态提示与空状态 |
| [dgtlmoon/changedetection.io](https://github.com/dgtlmoon/changedetection.io) | 相邻 · Python；Web、Docker | [master](https://github.com/dgtlmoon/changedetection.io/tree/fdce08421c01) · changedetectionio · [开放 Issue](https://github.com/dgtlmoon/changedetection.io/issues/1678) | Apache-2.0 | 35882 / 2261 | 2026-11-16 · [0.70.3](https://github.com/dgtlmoon/changedetection.io/releases/tag/0.70.3) | 监控去重、差异通知 |
| [n8n-io/n8n](https://github.com/n8n-io/n8n) | 相邻 · TypeScript；Web、Docker | [master](https://github.com/n8n-io/n8n/tree/c3b98e6b65e8) · packages · [开放 Issue](https://github.com/n8n-io/n8n/issues/44151) | NOASSERTION | 210883 / 61559 | 2026-11-18 · [n8n@2.48.5](https://github.com/n8n-io/n8n/releases/tag/n8n%402.48.5) | 执行详情与恢复提示；仅参考交互 |
| [huginn/huginn](https://github.com/huginn/huginn) | 相邻 · Ruby on Rails；Web、Docker | [master](https://github.com/huginn/huginn/tree/91c6a928fceb) · app/models · [开放 Issue](https://github.com/huginn/huginn/issues/3379) | MIT | 50572 / 4356 | 2026-11-18 · [v2026.11.18](https://github.com/huginn/huginn/releases/tag/v2026.11.18) | 插件输入输出和审计边界 |
| [nextcloud/updater](https://github.com/nextcloud/updater) | 技术 · PHP；Web 与 CLI | [master](https://github.com/nextcloud/updater/tree/d469d466a756) · lib / tests · [开放 Issue](https://github.com/nextcloud/updater/issues/783) | AGPL-3.0 | 50 / 40 | 2026-09-01 · 未返回 | 更新器先校验和备份，再替换文件 |
| [matomo-org/matomo](https://github.com/matomo-org/matomo) | 相邻 · PHP；Web、CLI | [6.x-dev](https://github.com/matomo-org/matomo/tree/4eba44a15551) · core / plugins · [开放 Issue](https://github.com/matomo-org/matomo/issues/25753) | GPL-3.0 | 22035 / 2940 | 2026-11-17 · [5.14.3](https://github.com/matomo-org/matomo/releases/tag/5.14.3) | 用户有可独立恢复的数据库备份 |
| [WordPress/WordPress](https://github.com/WordPress/WordPress) | 技术 · PHP；Web | [master](https://github.com/WordPress/WordPress/tree/de76770730a5) · wp-admin/includes | NOASSERTION | 21543 / 13039 | 2026-11-18 · 未返回 | 升级状态与恢复入口；保留站点配置 |
| [composer/composer](https://github.com/composer/composer) | 技术 · PHP；CLI | [main](https://github.com/composer/composer/tree/5147ea1a01c1) · src / doc · [开放 Issue](https://github.com/composer/composer/issues/13282) | MIT | 29581 / 4868 | 2026-11-13 · [2.11.2](https://github.com/composer/composer/releases/tag/2.11.2) | 锁定依赖、审计、版本更新完整性 |
| [deployphp/deployer](https://github.com/deployphp/deployer) | 技术 · PHP；CLI 及远程服务器 | [master](https://github.com/deployphp/deployer/tree/c08d1367d8f1) · src / recipe | MIT | 11125 / 1513 | 2026-08-26 · [v8.0.5](https://github.com/deployphp/deployer/releases/tag/v8.0.5) | 版本目录、回滚和显式运维控制 |
| [docker/compose](https://github.com/docker/compose) | 技术 · Go；Linux Docker 与 Desktop | [main](https://github.com/docker/compose/tree/7b8dffe3119c) · pkg / cmd · [开放 Issue](https://github.com/docker/compose/issues/14531) | Apache-2.0 | 39005 / 6042 | 2026-11-18 · [v5.8.0](https://github.com/docker/compose/releases/tag/v5.8.0) | 项目命名和命名卷跨容器保留 |
| [crazy-max/diun](https://github.com/crazy-max/diun) | 相邻 · Go；Docker、CLI | [master](https://github.com/crazy-max/diun/tree/8b678d0badde) · internal / docs · [开放 Issue](https://github.com/crazy-max/diun/issues/1913) | MIT | 5112 / 163 | 2026-11-18 · [v4.33.1](https://github.com/crazy-max/diun/releases/tag/v4.33.1) | 镜像更新提醒与实际切换解耦 |
| [getwud/wud](https://github.com/getwud/wud) | 相邻 · Node.js；Web、Docker | [main](https://github.com/getwud/wud/tree/b1910bb0bc63) · app / ui · [开放 Issue](https://github.com/getwud/wud/issues/640) | MIT | 4243 / 165 | 2026-11-15 · [9.8.0](https://github.com/getwud/wud/releases/tag/9.8.0) | 明确显示标签更新与 digest 变化 |
| [mcuadros/ofelia](https://github.com/mcuadros/ofelia) | 相邻 · Go；Docker 调度 | [master](https://github.com/mcuadros/ofelia/tree/754f92085c7c) · core / integration · [开放 Issue](https://github.com/mcuadros/ofelia/issues/259) | MIT | 4036 / 210 | 2026-11-15 · [v0.3.23](https://github.com/mcuadros/ofelia/releases/tag/v0.3.23) | 容器与任务分开，需控制 Docker 权限 |
| [dkron-io/dkron](https://github.com/dkron-io/dkron) | 相邻 · Go；分布式调度与 Web | [main](https://github.com/dkron-io/dkron/tree/5cca42de0764) · dkron / executor · [开放 Issue](https://github.com/dkron-io/dkron/issues/2087) | LGPL-3.0 | 4743 / 417 | 2026-10-07 · [v4.2.1](https://github.com/dkron-io/dkron/releases/tag/v4.2.1) | 分布式场景才引入多节点选主 |
| [TwiN/gatus](https://github.com/TwiN/gatus) | 相邻 · Go；Web、Docker | [master](https://github.com/TwiN/gatus/tree/5890e28d3bf3) · config / controller · [开放 Issue](https://github.com/TwiN/gatus/issues/1146) | Apache-2.0 | 12828 / 918 | 2026-11-15 · [v5.38.0](https://github.com/TwiN/gatus/releases/tag/v5.38.0) | 可解释的健康探针与状态判定 |
| [caronc/apprise](https://github.com/caronc/apprise) | 技术 · Python；CLI 和通知库 | [master](https://github.com/caronc/apprise/tree/eab0f8bfe669) · apprise / tests · [开放 Issue](https://github.com/caronc/apprise/issues/1897) | BSD-2-Clause | 17791 / 703 | 2026-11-17 · [v2.2.0](https://github.com/caronc/apprise/releases/tag/v2.2.0) | 统一适配器结果与通道文档 |
| [restic/restic](https://github.com/restic/restic) | 技术 · Go；CLI、多操作系统 | [master](https://github.com/restic/restic/tree/74f00280e21a) · internal / doc · [开放 Issue](https://github.com/restic/restic/issues/21929) | BSD-2-Clause | 37293 / 1985 | 2026-11-17 · [v0.19.1](https://github.com/restic/restic/releases/tag/v0.19.1) | 备份校验和恢复演练 |
| [gitleaks/gitleaks](https://github.com/gitleaks/gitleaks) | 技术 · Go；CLI、CI | [master](https://github.com/gitleaks/gitleaks/tree/5f6ec56cfa6f) · detect / config · [开放 Issue](https://github.com/gitleaks/gitleaks/issues/2010) | MIT | 31159 / 2388 | 2026-10-23 · [v8.30.1](https://github.com/gitleaks/gitleaks/releases/tag/v8.30.1) | 敏感信息扫描与有依据的规则例外 |
| [renovatebot/renovate](https://github.com/renovatebot/renovate) | 技术 · TypeScript；CLI、托管平台 | [main](https://github.com/renovatebot/renovate/tree/026bc94a7c9e) · lib / docs · [开放 Issue](https://github.com/renovatebot/renovate/issues/2958) | AGPL-3.0 | 23167 / 3448 | 2026-11-18 · [44.252.1](https://github.com/renovatebot/renovate/releases/tag/44.252.1) | 例行更新 PR 与变动范围审查 |
| [sigstore/cosign](https://github.com/sigstore/cosign) | 技术 · Go；CLI、容器和制品 | [main](https://github.com/sigstore/cosign/tree/0743bfcff5f8) · cmd / pkg · [开放 Issue](https://github.com/sigstore/cosign/issues/5226) | Apache-2.0 | 6448 / 843 | 2026-11-18 · [v3.1.4](https://github.com/sigstore/cosign/releases/tag/v3.1.4) | 以公开验证凭据校验发布证明 |

直接竞品集中在少数同源的贴吧签到项目。相邻项目用于比较调度、通知、升级、安全分发和容器运维，不能直接替代百度账号绑定与复签。GitHub 的未识别许可需要查仓库授权文本后再评估复用。

## 功能差距和实现取舍

| 场景 | 本项目基线 | 对标 | 结果 |
| --- | --- | --- | --- |
| 升级恢复 | 文件写入失败可恢复，数据库备份失败后仍继续 | Nextcloud 单独保留代码与数据、Deployer 版本回退 | 迁移锁内保存独立数据库备份，管理员可按同一备份点恢复 |
| 容器迭代 | DB 卷可复用，应用文件依赖原容器 | Compose 命名卷、Diun 更新提示 | 保留配置、锁和应用数据，旧实例首次迁移显式导出 |
| 发布 | GitHub Release 允许重新上传 | GitHub 不可变 Release、Cosign 验证流程 | 草稿审核资产后发布，通过 GitHub 证明核验资产 |
| 运维体验 | 已有调度日志、健康检查和通知测试 | QingLong、Healthchecks、Gatus | 保持原有信息结构，新增更新页可用性状态，调度 UI 优化留在候选 |
| 原生体验 | 服务端持续执行，浏览器承担管理 | 监控和调度项目普遍以 Web/CLI 管理 | 移动设备使用响应式 Web，主机长期运行由 Web 服务承担 |

## 30 项候选

| # | 候选与依据 | 优先级 / 难度 | 决定 |
| ---: | --- | --- | --- |
| 1 | 草稿上传摘要核对（GitHub Release） | 高 / 中 | 实施 |
| 2 | 稳定资产不可覆盖（GitHub Release） | 高 / 低 | 实施 |
| 3 | 发布证明独立验证（Cosign、GitHub） | 高 / 中 | 实施 |
| 4 | 标签与包内版本一致（Composer） | 高 / 低 | 实施 |
| 5 | Tag 复用完整 PR CI（GitHub） | 高 / 中 | 实施 |
| 6 | 追踪 Dockerfile 和 Compose 依赖（Dependabot） | 高 / 低 | 实施 |
| 7 | 固定第三方 Action 提交（GitHub） | 高 / 低 | 实施 |
| 8 | 上传后校验实际下载（GitHub） | 高 / 低 | 实施 |
| 9 | 确认数据库备份再迁移（Nextcloud） | 高 / 中 | 实施 |
| 10 | 迁移锁覆盖备份阶段（Symfony Lock） | 高 / 中 | 实施 |
| 11 | 备份恢复回放（restic） | 高 / 中 | 实施 |
| 12 | 在线覆盖互斥（Symfony Lock） | 高 / 低 | 实施 |
| 13 | 单独持久化 Web 状态目录（Compose） | 高 / 中 | 实施 |
| 14 | 旧容器显式转移配置、安装锁与数据（Compose） | 高 / 中 | 实施 |
| 15 | 稳定资源 URL 及时重新验证（Web 缓存） | 中 / 低 | 实施 |
| 16 | 更新兼容和故障分类模板（GitHub） | 中 / 低 | 实施 |
| 17 | 每周依赖和测试复核（Renovate、Composer） | 中 / 低 | 实施 |
| 18 | 更新页区分可安装与仅下载（Nextcloud） | 中 / 低 | 实施 |
| 19 | 保护更新状态文件直链（Docker/Caddy 现场审核） | 高 / 低 | 实施 |
| 20 | 发布状态与 README 漂移提示（GitHub） | 中 / 低 | 实施 |
| 21 | 站点专属更新签名托管（Nextcloud） | 高 / 中 | 需要签名密钥生命周期决策 |
| 22 | 镜像摘要及注册表稳定渠道（Diun、Cosign） | 中 / 中 | 先确认渠道和 NAS 架构需求 |
| 23 | 部署端维护模式及后台任务暂停（Nextcloud） | 高 / 高 | 候选，需定义跨进程写入规则 |
| 24 | Web 后台下载任务独立于浏览器页面（qd） | 中 / 高 | 候选，需持久任务与重启恢复 |
| 25 | 签到历史 API 筛选统一（QingLong） | 中 / 中 | 候选，兼容现有客户端 |
| 26 | 计划任务心跳告警（Healthchecks） | 中 / 中 | 候选，先复用现有 Prometheus 指标 |
| 27 | 通知投递状态汇总（Gotify、Apprise） | 中 / 中 | 候选，先稳定跨渠道错误语义 |
| 28 | 不可变的多节点镜像回滚（Deployer） | 中 / 高 | 候选，先验收单机备份恢复 |
| 29 | 移动端安装型 PWA（Uptime Kuma） | 低 / 中 | 暂缓，核心功能仍须联网 |
| 30 | 桌面、平板和手机独立原生端（相邻项目平台支持） | 低 / 高 | 暂缓，响应式 Web 已覆盖管理需求 |
| 31 | 分布式任务容灾（Dkron） | 低 / 高 | 暂缓，单机服务尚无需求 |
| 32 | 容器自动探测后静默升级（Diun、WUD） | 低 / 高 | 暂缓，需配合数据库备份和迁移 |

## 维护复查

每次较大版本重查直接竞品及采用方向的 Release、许可证、维护状态和活跃 Issue。重点关注 30/90/180 天内的新发布、弃用通知及迁移兼容性，从实际使用反馈更新候选。每周 CI 与 Dependabot 负责重复的依赖核查。

[GitHub 不可变 Release](https://docs.github.com/en/code-security/concepts/supply-chain-security/immutable-releases) · [Nextcloud 更新器](https://docs.nextcloud.com/server/stable/admin_manual/maintenance/update.html) · [Compose 应用模型](https://docs.docker.com/compose/intro/compose-application-model/)
