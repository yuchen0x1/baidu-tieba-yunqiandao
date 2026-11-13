# 通知可靠性与个人设置升级交付 · 2026-11-13

> 阶段记录：本文保留整理/验收当时的状态（包括“未提交、未发布”等字样）；相关成果已纳入 V7.7.0。正式发布状态以 [V7.7.0 Release](https://github.com/yuchen0x1/baidu-tieba-yunqiandao/releases/tag/v7.7.0) 为准。

## 交付边界与执行清单

基线是本仓库 `main`、`a58b5c4`（V7.6.0），开始时工作区干净。本轮改进通知发送、重试与批次确认链路，全部保存在本地工作区，**没有提交、推送、发布或部署**。保留版本 7.6.0，更新日志使用“未发布”。

没有新增生产依赖、数据库表或迁移；使用已有用户选项存储最近结果，不迁移或恢复用户数据库。测试库使用独立 MariaDB 11.4.9 进程的 13376 端口，HTTP 与浏览器测试只访问本机；未向真实百度账号、邮箱或推送渠道发送消息。

- [x] 识别项目、检查 Git 和开发规范，阅读路由、配置、调度、签到统计、通知与插件核心实现。
- [x] 联网阅读 5 个项目的 README、核心源文件与许可证，记录来源和 SHA-256。
- [x] 对发现的通知错误判定、失败重试、批次确认问题实施修复。
- [x] 增加个人推送状态，修正手机表单与状态对比度，验证深浅主题。
- [x] 运行单元、真实 HTTP、MariaDB 集成、发布门禁和浏览器验证。
- [x] 同步 README、更新历史、变更日志、研究记录与运行截图。

这是一轮可审查、可验证的完整增量，不代表全站插件、第三方服务或线上部署已全面验收。

## 1. 项目认知与功能地图

这是自托管的 PHP Web 应用，核心用途是多用户、多百度账号的贴吧自动签到、复签和运维。技术栈为 PHP >=8.2、MySQL/MariaDB、MySQLi、jQuery、Bootstrap 与自有 CSS；支持 Docker、传统 PHP 和 Windows 启动包。没有前端编译环节，Windows 包本质是 Web 运行环境。

| 模块 | 入口与真实实现 | 核查结论 |
| --- | --- | --- |
| 登录、权限与配置 | `init.php`、`lib/globals.php`、`reg.php`、`setting.php`、`class.option.php` | 会话、CSRF、角色、个人选项、加密设置已有实现；本轮沿用 |
| 签到与数据 | `class.misc.php`、`class.SignManager.php`、签到/复签任务 | 已有行级领取、历史、健康检查、统计；日报数据是尝试记录，不能当作去重贴吧数 |
| 调度与运维 | `do.php`、`class.cron.php`、管理模板 | 已有任务锁、失败退避、搜索与历史；没有重复开发 V7.6 的调度功能 |
| 通知与提醒 | `class.notify.php`、`class.Reminder.php`、日报和周报任务 | 多渠道真实实现存在，但“跳过/失败/接受”混用，去重标记提前写入 |
| 帖子监控 | `plugins/ver4_monitor/` | 摘要查询最多 30 条，但旧更新语句确认所有当天未发送记录；存在漏报风险 |
| 用户界面 | `templates/set.php`、`admin-set.php`、CSS tokens/components | 双主题与响应式框架已有；个人设置缺少最近发送结果，小屏双列表单较拥挤 |
| API、更新、迁移与备份 | `api.php`、updater、migrator、测试工具 | 既有实现保留；本轮运行已有合同、迁移、备份恢复集成检查 |
| 扩展 | 插件钩子和用户选项 | 保留协议，没有引入外部队列、通知库或新基础设施 |

最有价值的改进是让已有通知流程可信且可恢复，而非继续堆叠渠道、AI 或入口。审计包含静态代码和隔离运行；没有逐一调用所有插件的外部服务。

## 2. 开源项目研究与对比

2026-11-13 实际通过浏览工具和 raw 源文件读取以下项目的 README、目录信息、核心实现及许可证。GitHub 匿名元数据 API 返回 403 限流，因此没有把维护时间、星数或旧报告数据当作本次已核实事实。观察为分支快照，文件哈希见[研究索引](notification-reliability-research-2026-11-13.json)。没有复制、执行或引入竞品源码。

| 项目及关联性 | 实际研究的核心文件与实践 | 许可证观察与适配 |
| --- | --- | --- |
| [MoeNetwork/Tieba-Cloud-Sign](https://github.com/MoeNetwork/Tieba-Cloud-Sign)，直接同类与历史上游 | [cron 实现](https://github.com/MoeNetwork/Tieba-Cloud-Sign/blob/master/lib/class.cron.php)：PHP 文件任务和插件调度契约 | [自定义许可](https://github.com/MoeNetwork/Tieba-Cloud-Sign/blob/master/license.html)，不假定可直接移植；沿用本项目现有接口 |
| [qd-today/qd](https://github.com/qd-today/qd)，定时 HTTP 签到框架 | [任务处理器](https://github.com/qd-today/qd/blob/master/web/handlers/task.py)：用户范围、成功/失败日志与推送过程 | [MIT](https://github.com/qd-today/qd/blob/master/LICENSE)；借鉴用户隔离和按结果反馈，不迁移其 Tornado 架构 |
| [healthchecks/healthchecks](https://github.com/healthchecks/healthchecks)，后台任务监控 | [传输层](https://github.com/healthchecks/healthchecks/blob/master/hc/api/transports.py)：显式区分不执行、成功及传输错误 | [BSD-3-Clause](https://github.com/healthchecks/healthchecks/blob/master/LICENSE)；落地为显式未发送结果和成功后确认 |
| [caronc/apprise](https://github.com/caronc/apprise)，通知聚合库 | [JSON 通知](https://github.com/caronc/apprise/blob/master/apprise/plugins/custom_json.py)：非 2xx 失败、异常返回、传输结果 | [BSD-2-Clause](https://github.com/caronc/apprise/blob/master/LICENSE)；独立实现 HTTP 状态门禁，不加入 Python 服务 |
| [binwiederhier/ntfy](https://github.com/binwiederhier/ntfy)，相邻通知服务 | [客户端](https://github.com/binwiederhier/ntfy/blob/main/client/client.go)：检查 HTTP 状态，再解析消息事件 | [Apache-2.0/GPL-2.0 双许可](https://github.com/binwiederhier/ntfy#license)；参考结果判定，没有移植依赖或代码 |

| 对比维度 | 原项目情况 | 可借鉴实践 | 本轮成果 |
| --- | --- | --- | --- |
| 核心功能 | 渠道多，但失败和跳过可能算成功 | Apprise、ntfy 校验 HTTP/业务结果 | 全部 HTTP 渠道加状态门禁，拒绝畸形业务成功码 |
| UI | 开关齐全，缺少最近发送结果 | 把后台任务结果变成用户可读状态 | 个人设置显示结果和时间；服务接受与终端收件分开说明 |
| 交互 | 测试可能跳过却显示成功；手机控件拥挤 | 按真实结果给反馈 | 测试未发送给明确提示；手机表单纵向排列 |
| 架构 | `ok` 被用于兼容性和送达两种含义 | Healthchecks 区分 noop 与成功 | 兼容旧 `ok`，新增 `delivered` 与统一判定助手 |
| 自动化 | 失败先写去重标记，阻断恢复 | 成功后才确认工作 | 失败提醒、日报、周报保留下一轮机会，摘要按批确认 |
| 扩展 | 插件与渠道已有成熟接口 | 保持可扩展边界 | 不更换框架，增加可替换的传输工厂和长期回归门禁 |

## 3. 问题优先级、收益与实施结果

| 优先级 | 问题/升级项 | 收益与实现风险 | 状态 |
| --- | --- | --- | --- |
| P0（数据确认） | 摘要只发送前 30 条却确认全部待发记录；发送失败也确认 | 防止静默漏报；低至中风险，真实数据库核验批次和用户范围 | 已修复 |
| P1（功能失效） | 失败提醒发送失败仍当日去重；周报失败仍锁六天 | 恢复配置后可再次发送；沿用原任务周期 | 已修复 |
| P1（错误反馈） | Webhook 的 HTTP 错误、其他渠道成功形状的错误响应可被接受 | 401/429/5xx 不再伪成功；兼容 2xx 和通用 Webhook 204 | 已修复 |
| P1（回退） | 未选渠道 `ok=true` 导致日报不回退邮箱 | 修复个人日报的回退链路；保留旧调用者兼容性 | 已修复 |
| P1（安全） | 部分群机器人绕过公开地址校验；异常/响应可能泄露信息；测试通知走 GET | 复用现有 DNS 固定和 SSRF 保护，错误不回显正文；旧 GET 测试链接变 405 | 已修复，有兼容影响 |
| P1（统计） | 日报把尝试人数算成功人数；零点时间被覆盖；最近失败原因取到成功记录 | 运维信息更准确；不改变原始历史数据和 API | 已修复 |
| P2（体验） | 缺少最近结果，手机表单拥挤、状态配色与标题对比不足 | 可在个人设置检查结果，双主题、小屏可用 | 已实现 |
| P2（统计措辞） | 重试次数被描述为不同贴吧数 | 日报明确是签到尝试次数，避免误读 | 已修复 |
| P3 | 持久通知 outbox、跨任务原子去重、失败退避与死信、跨日补发 | 需要数据模型和投递语义设计，外部服务也可能重复收件 | 后续规划，不以当前标记机制承诺 exactly-once |

## 4. 本次实现与兼容变化

- 传输层先验证 HTTP 2xx；业务码必须是预期整数/数字字符串，Telegram 需布尔 `true`。不把字符串 `"false"`、非法数字或成功形状的 503 响应当成功。
- `Notify::send()` 主动跳过时继续返回 `ok=true`，同时返回 `delivered=false`，减少旧插件兼容冲击；新消费者使用 `wasDelivered()`。该名字表示服务接受，**不证明设备收到、邮箱入箱或用户阅读**。
- 失败提醒只有邮件接受才写当天标记；日报对失败及未发送回退个人邮箱；周报仅在接受后写周期标记。最近结果只存状态与时间，不存消息正文、邮箱、URL 或密钥。
- 监控摘要只按本次选出的 ID 加用户条件确认。未发送的后续批次保留；仍沿用摘要时间窗口，未新增跨日补发机制。
- 钉钉、企业微信、飞书 Webhook 现在同样拒绝内网/非公开地址，且禁止重定向绕过。依赖内网机器人代理的部署会受到影响，需改为满足现有安全策略的公开目标。
- 管理员通知测试使用 POST 表单及既有 CSRF 令牌，旧 `setting.php?mod=admin:testnotify` GET 返回 405；请通过管理页按钮操作。开放 API 合同不变。
- 没有结构迁移。首次尝试后才产生新的个人结果字段，旧记录显示“尚无记录”；没有重置旧去重标记或回放历史通知。

## 5. 重要文件清单

| 文件 | 修改目的 |
| --- | --- |
| `lib/class.notify.php` | 传输工厂、HTTP/业务结果、显式跳过、SSRF、防敏感响应回显 |
| `lib/class.Reminder.php` | 失败后可重试、日报回退、最近结果、统计措辞 |
| `lib/cron_system_daily_report.php`、`lib/cron_system_weekreport.php` | 正确计数、零点配置、成功后去重 |
| `plugins/ver4_monitor/cron/monitor.php` | 按实际接受批次确认，限制用户范围 |
| `plugins/ver4_monitor/ver4_monitor_show.php`、`setting.php`、`templates/admin-set.php` | 测试通知反馈和 POST 操作 |
| `templates/set.php`、`source/css/my.css` | 最近结果、手机表单、深浅主题与对比度 |
| `tests/NotificationDeliveryTest.php`、`tests/fixtures/notify-router.php`（新增） | 本机 HTTP 传输、非法响应、SSRF、空 204 与断连测试 |
| `tools/notification-integration-test.php`（新增） | 真实 MariaDB 重试、回退、去重与批次隔离验收 |
| `composer.json`、`.github/workflows/ci.yml`、`tools/release-gate.php` | 纳入既有长期检查链路 |
| README、CHANGELOG、`docs/releases/release-notes.md` | “未发布”用户说明与开发说明 |
| 本报告、研究/验证 JSON、4 张截图（新增） | 来源、证据与交付边界 |

没有删除文件。`.evolution/runtime/notification-upgrade/` 是 Git 忽略的本轮本机运行资料，未纳入产品发布文件。

## 6. 测试与验收

本机 PHP 8.3.36、MariaDB 11.4.9、Chromium/Playwright。数据库和站点使用独立测试实例与副本。PHP 默认没有加载 mysqli，命令使用运行参数或本轮专用扫描目录启用；没有改系统 php.ini。

机器可读验收摘要见[验证 JSON](notification-reliability-validation-2026-11-13.json)。

| 状态 | 检查与实际结果 |
| --- | --- |
| PASS | `php -d extension=mysqli vendor/bin/phpunit --no-progress`（设置本轮 `OPENSSL_CONF`）：192 tests、1037 assertions，0 跳过、0 失败。新增 14 个参数化/传输测试；此前 3 个签名用例的 OpenSSL 本机配置问题已通过指定现有配置解决 |
| PASS | 新通知集成脚本：22 项，包含失败后重试、成功后去重、最近失败原因、日报回退、周报跳过、30/35 条边界与其他用户保护 |
| PASS | `php tools/release-gate.php`：11 个门禁项目通过；迁移 46、调度 61、API 20、备份恢复 15、通知 22 项通过 |
| PASS | `php tools/lint.php`：235 个 PHP 文件语法与硬安全检查通过。旧 addslashes 及测试子进程调用仍有人工审查提示，未把警告抹去；新增子进程只启动本机受控 HTTP 夹具 |
| PASS | `php tools/secret-scan.php`：追踪文件未发现既有扫描器定义的凭据模式；该扫描不是所有形式泄露的证明 |
| PASS | `composer validate --strict --no-check-publish`，Composer 清单有效；未新增生产依赖 |
| PASS | 本机浏览器登录、三项最近结果、桌面 1440 / 平板 768 / 手机 390 像素布局、深色切换、个人设置保存并读回、旧 GET 405、POST 关闭渠道不显示成功；无页面脚本异常 |
| PASS | `git diff --check`，最终差异无空白错误 |
| BLOCKED | `docker info`：Docker daemon 管道不存在，因此没有运行本轮 Docker 镜像构建与容器冒烟 |
| PARTIAL | 已验证新安装模板导入和本机 PHP 启动；没有重新安装生产站点，也没有运行所有插件外部网络动作 |
| NOT APPLICABLE | 前端打包/类型编译：此项目没有前端构建链；以 PHP 语法、HTTP 和浏览器检查验收 |
| 未执行 | 真实百度签到、真实渠道收件、SMTP 收件、线上 CI、发布、部署、在线更新安装与跨版本回滚 |

本地传输测试包含成功、302、401、429、503、业务错误、非法 JSON/码、连接失败和空 204。其作用是验证客户端判断，不是第三方服务可用性或真实送达率。

Python Playwright 不在当前 Python 环境，安装遇到 PyPI TLS 错误；浏览器验收改用项目运行目录已有的 Node Playwright 与 Chromium，没有关闭证书验证，也没有因此改动生产依赖。

### 实际浏览器截图

以下来自隔离站点，展示的是验收夹具中的状态，用于检查渲染与交互，不能当作真实第三方送达记录。

- [桌面浅色](../screenshots/notification-settings-desktop.png)
- [平板浅色](../screenshots/notification-settings-tablet.png)
- [手机浅色](../screenshots/notification-settings-mobile.png)
- [桌面深色](../screenshots/notification-settings-dark.png)

## 7. 后续优先级与剩余风险

1. **P1**：通知 outbox 与原子领取。当前依赖任务锁和成功标记；进程在服务接受后、数据库确认前退出仍可能重发，不承诺 exactly-once。
2. **P1**：帖子监控实时模式的发送重试与跨日摘要积压处理。目前本轮修复摘要确认，不把实时模式也宣称为持久投递队列。
3. **P1**：在部署方授权的真实渠道和测试百度账号上验收收件、限流恢复与生产任务周期；本轮未消耗用户真实账号执行外部操作。
4. **P2**：个人通知配置从监控插件独立出来，统一凭据掩码、渠道预检与配置入口；应保留旧选项和插件兼容。
5. **P2**：按实测瓶颈渐进收敛运行时 DDL、插件查询与统计查询；本轮没有性能基准，不能声称全站提速。
6. **P3**：逐步清理内联事件/样式和旧依赖，保持现有插件可用，再考虑更严格 CSP、国际化及大范围 UI 迁移。

## 8. 完成度评估

以下为**本轮触及范围的证据等级**，不是全项目的精确质量百分比。1=不完整，2=有实现/静态证据，3=本机单层验证，4=多层本地回归，5=包括生产/真实外部端到端证据。没有以缺少证据的项目给高分。

| 维度 | 等级 / 5 | 依据与限制 |
| --- | --- | --- |
| 功能完整度 | 4 | 通知失败、回退、去重与批次确认闭环通过；真实收件与持久队列未验收 |
| UI/UX | 4 | 三断点、双主题、保存与测试反馈实际操作；未对全站无障碍逐页审计 |
| 代码质量 | 4 | 定向传输回归、全套单元与语法通过；既有重复和旧接口仍存在 |
| 性能 | 2 | 批次保持有界，无新增重型依赖；没有负载/查询基准，不能给出性能提升比例 |
| 安全性 | 3 | 外发地址校验、凭据回显收敛、POST 和现有权限合同本地验证；不是全站渗透测试 |
| 稳定性 | 4 | 多种 HTTP 失败和真实数据库恢复验证；外部服务及进程崩溃投递仍有限制 |
| 可维护性 | 4 | 测试已纳入 Composer、CI 配置、门禁和文档；远程 CI 尚未触发 |
| 扩展性 | 3 | 保留插件和响应兼容并提供传输工厂；outbox 等未来架构尚未实现 |

最终结果：本地已有可审查的代码、回归检查、文档和截图；发布、部署及真实渠道验收仍是独立步骤。
