# 更新日志

## 未发布（架构安全批次）

- **安全修复**：CSRF 令牌的回退盐由硬编码常量改为按需自动生成的 `crypto_key`（已配置 `SYSTEM_SALT` 或已有密钥的实例行为不变）；此前全新实例在密钥未生成时可被推算出合法令牌。
- **安全修复**：发布脚本 `tools/publish-gitee.ps1` 清理旧版本时，Gitee 令牌从 URL 查询串改为 Authorization 头（查询串会落入反代/访问日志）。
- **架构**：确立「开源实现 vs 官方服务能力」边界（`docs/architecture-boundary.md`）。新增官方服务网关客户端 `lib/class.official.php`（HMAC-SHA256 请求签名 + 时间戳/nonce 防重放、短期 Access Token 缓存、License RSA-2048 非对称验签）；零密钥参考网关 `official-service/`（实例认证/防重放/配额限流/Provider 代理，全部凭据走 env，生产部署须迁入私有仓库）。
- **AI 插件**：Provider 形态可选「直连（自带 Key）」（默认，行为不变）或「官方 AI 服务」（实例不持有 Provider Key，模型路由/配额/风控由官方网关裁决）；管理页新增官方服务接入配置（实例 Secret 加密存库）。
- **工具链**：新增 `tools/secret-scan.php` 仓库 Secret 扫描（接入 `composer check`、发布门禁与 CI）；新增 `tools/official-keygen.php` 授权密钥对生成器（私钥仅输出到官方服务器，不入仓库）；`.gitignore` 排除私钥/网关数据目录。

## 2026-09-07 V7.1.1：修复 7.0 升级被版本门禁阻断

- **修复**：从 7.0 升级到 7.1 时，站点会被版本门禁以「未找到对应的升级脚本」阻断（7.1 无数据库结构变更，本应直升）。现新增通用升级入口 `setup/upgrade.php`：无专用逐版本脚本时，6.6 及以上任意版本均可一键直升当前版本（同一迁移器、同一状态记录，失败可恢复）；命令行 `php tools/migrate.php up` 不受影响。
- 已发布 v7.1 的站点：直接访问 `setup/upgrade.php` 即可完成升级；或按本页说明在线更新到 v7.1.1 后按引导操作。

## 2026-09-06 V7.1 发布：全站页面体验重设计

### 全站统一骨架
- 用户侧与管理侧全部页面统一「页头横幅」：小字眉题 + 标题 + 状态标签 + 常用操作按钮；此前插件页/老页面是裸标题或简易页头，观感不一致，现全部拉齐。
- 页面提示、空数据状态、表格工具条、表单分段编号（①②③…）按统一规范收敛。

### 目标贴吧勾选（自动回帖 / 云点赞 / 帖子监控）
- 三个插件的「目标贴吧」从纯手填改为**从已同步的贴吧列表勾选**：带搜索过滤、全选筛选结果、清空与已选计数；勾选自动写入文本框，也可继续手动输入未同步的吧名，两边双向同步。数据格式不变，老配置无需迁移。

### 重点页面
- **工作台**：按时段问候（早上好/晚上好 + 昵称），新增「连续签到」统计卡；管理员「系统健康/服务器信息」折叠为次级区域。
- **AI 发帖/回帖**：运行状态、配置来源、账号数收进页头标签，**AI 总开关在页头即可切换保存**，不用再滚到表单里找。
- **个人设置**：单张长表单拆为「基本信息 / 签到策略 / 通知与报告 / API 与安全」四卡两栏，修改密码独立成卡。
- **账号中心**：近 7 天签到结果改为紧凑表格（一行一天）。
- **贴吧与签到**：账号切换由横向标签改为下拉，贴吧列表新增搜索与分页（每页 50 条），保存按钮常驻底部。
- **计划任务 / 插件管理**：运行顺序与加载顺序支持**拖拽排序**（拖完自动重排顺序值，仍需保存）。
- **用户管理**：勾选用户后底部浮现批量操作条，新增用户搜索框。
- **成就勋章**：新增已解锁/待解锁筛选；**签到排行**：我的排名进页头，前三名显示奖牌。
- **帖子监控**：设置拆为「监控规则 / 推送与通知渠道」两卡，通知渠道字段按所选渠道展开。
- **公告栏管理**：挂载点从多选列表改为可读性更好的复选按钮组，新增效果预览。
- **签到看板（管理）**：三个立即执行任务按钮提升到页头；**邀请码 / 双因素认证 / 系统更新 / 工具箱**等管理页统一套新骨架。

### 侧边栏
- 分组重排：核心页（WORKSPACE）→ 插件功能（PLUGINS）→ 个人设置与致谢（PREFERENCES）→ 管理区；AI 发帖/回帖与自动回帖相邻，不再被设置页隔开。

### 修复
- 修复「近 7 天签到结果」等列表条目被拆成逐行竖排的排版错误（致谢页名单同步修复）。

## 2026-09-06 V7.0 发布准备

- **修复 Windows 脚本在 PowerShell 5.1 下解析失败**：`windows/*.ps1` 为无 BOM 的 UTF-8，5.1 按 GBK 误读中文导致 `start-web.ps1`（README 推荐的 `powershell -File` 调用方式）直接解析报错；已为全部含中文的 PS1 补 UTF-8 BOM，CI 新增 5.1 解析守卫防回归。
- 发布脚本 `tools/publish-gitee.ps1` 新增 `-CleanOldReleases <N>`（发布成功后按创建时间倒序仅保留最近 N 个 Release）与 `-DeleteOldTags`（连同被清理版本的远端标签一并删除，显式开关）；本地打包模式不做任何远端变更。
- 新增 `release-notes/v7.0.md`（V7.0 发布说明）与 `docs/RELEASE.md`（发布手册：门禁 → 打 tag → 发布 → 清旧 → 发布后验证与回滚）。

## 2026-09-06 V7.0 批次8：架构演进评估 + 常驻调度模式

> 架构评估为文档交付（docs/architecture-evolution.md）；唯一代码变更为 do.php 的可选常驻模式，默认 cron 路径零改动。

### 调度：新增可选常驻模式
- `php do.php --pw=密码 --loop[=间隔秒]`（默认 30 秒，上限 600）：CLI 常驻循环调度，复用 runall 的防重入锁/nextrun/失败退避全部机制，与 cron 触发天然互斥不重复执行；断线自动重建数据库连接，单轮异常不中断守护。适合无 crond 的 Windows 部署或不便配置计划任务的场景。
- 常驻进程输出即时落盘（关闭控制台缓冲转换），便于重定向到日志文件。

### 演进评估（docs/architecture-evolution.md）
- **Worker/队列**：对比 cron+do.php、常驻 Worker、消息队列三方案的多节点去重/延迟/依赖/失败语义；结论为队列暂不引入（零 Composer 运行时依赖的架构原则不变），给出三条重新评估触发条件，且引入时应走 cron_1/cron_2 插件钩子。
- **动态分表合并**：盘点约 10 个消费方文件与既有白名单防线，结论暂不合并（跨用户统计已由 sign_history 规避，插件 ABI 风险最大），给出单表 500 万行等重新评估触发条件与 expand/contract 合并路径。

### 测试
- 调度集成测试新增场景 G（真实进程级验证）：常驻循环 6 秒内执行到期任务、日志即时落盘、按 PID 干净终止。全量 49 项断言通过。

## 2026-09-06 V7.0 批次7：发布流程收口

> 测试与流程建设，无功能变更；其中 dataBak 与安装模板为真实缺陷修复。

### 备份恢复
- **修复 dataBak 导出丢失 NULL 值**：可空列（error_message、stoken、last_error 等）此前导出为空字符串，恢复后 NULL→'' 静默失真；现导出为 SQL NULL。
- **修复安装模板缺 `api_rate_limits` 表**：全新安装此前靠 API 限流器懒建该表，模板已补齐与迁移一致的定义。
- 新增 `tools/backup-restore-drill.php`（15 项断言，真实数据库）：用应用自身的备份逻辑执行「全新安装 → 播种数据（含引号/反斜杠/序列化值）→ 备份 → 空库恢复 → 15 张表逐行指纹比对 → 序列化配置语义校验 → keep 轮换」完整演练，每次 CI 自动执行一次。

### 发布门禁
- 新增一键发布门禁 `tools/release-gate.php`（`composer gate`）：语法扫描、单元测试、版本元数据一致性、迁移文件规范、入口 SQL 注入面守卫、四条数据库集成链路，共 9 项检查一次跑完，任一失败即阻断发布；数据库不可达时集成项标记 SKIP（CI 中保证可达）。
- CI 新增 Windows `release-packaging` 作业：对临时 tag 执行发布脚本本地打包模式，验证 ZIP 生成、SHA-256 校验文件一致性、核心入口文件齐全——发布包生产线本身进入回归范围。

### 测试基建
- `composer integration` 现包含四条链路（迁移/调度/API/备份恢复）；API 集成测试的内置服务器改为文件日志并按真实 PID 强杀，修复 Windows 下进程残留导致父进程永久等待的问题。

## 2026-09-06 V7.0 批次6：API 合同与插件 ABI

> API 响应新增 api_version 字段与稳定错误码（纯增量，旧字段不变）；HTTP 方法现在严格按矩阵强制（此前 sign 等写接口用 GET 访问会执行成功，现在返回 405）；插件启用前强制兼容检查。

### 开放 API 合同（lib/api-contract.php）
- **版本标识**：所有 JSON 响应携带 `api_version`（当前 v1），破坏性变更时递增。
- **统一错误格式**：错误响应固定为 `{error, code, api_version, ...}`，新增稳定错误码 `missing_token/auth_failed/rate_limited/method_not_allowed/forbidden_user_token/forbidden_global_token/unknown_action/upstream_unavailable`；文本可优化，code 面向程序判断。
- **权限矩阵单一事实源**：`api_actions()` 同时驱动三件事——HTTP 方法与 Token 类型门禁（update/health_check/webhook_test 仅全局 Token，monitor 仅个人 Token）、openapi 文档自动生成（修复此前文档缺 streak/ranking/failures/monitor 等条目的漂移）、README 表格。新增合同守卫测试：README 记录的 action 与方法必须与矩阵一致，双向防漂移。
- **方法严格化**：方法不符统一 405（此前仅 sign/retry/health_check 校验，其余 action 任何方法都放行）。

### 插件 ABI
- **启用前兼容检查**：新增 `plugin_compatibility_check()`（manifest 完整性 + 云签到最低版本 `for` + PHP 最低版本 `forphp`），不满足即拒绝启用并给出明确原因；`activePlugin()` 内部同门禁兜底。此前插件启用完全不校验版本兼容。
- 兼容判定为纯函数 `plugin_compatibility_errors()`，11 项单元测试覆盖边界（all/数值版本/未满足/空清单）。

### 全新安装修复
- 修复安装模板缺少 007000 基线迁移所播种选项的问题（`api_rate_limit`/`metrics_token`/`webhook_*`/`cleanup_*`）——该缺失导致全新安装的 API 限流静默失效、数据保留清理默认关闭，与升级安装行为不一致；迁移集成测试新增 6 项「模板选项与基线迁移同步」断言防复发。

### 集成测试
- 新增 `tools/api-integration-test.php`（20 项断言，PHP 内置服务器 + 真实 MySQL 端到端）：认证、响应信封、方法门禁、全局/个人 Token 权限边界、unknown_action、openapi 完整性、限流 429 全链路验证；`composer integration` 与 CI 同步运行三条集成链路（迁移/调度/API）。

## 2026-09-06 V7.0 批次5：数据库访问收敛

> 纯代码安全收敛，无数据库结构变更、无升级步骤；对既有数据行为无影响（参数化绑定原始值，消除历史双重转义隐患）。

### 注入面收敛（ajax.php / setting.php / api.php）
- **账号绑定全链路参数化**：Cookie 绑定、扫码登录（qrlogin）、账号密码获取（getverify）、批量导入中所有「拼接 + sqladds/escape_string 预转义」的 SQL 全部改为 `pquery` 参数绑定原始值——既消除注入面，又避免转义值入库的历史隐患；批量导入的 STOKEN 空值经 NULL 绑定落库。
- **二阶注入防线**：新增 `safe_table_name()` 单表名白名单助手（小写字母/数字/下划线 ≤64 位，非法回退 tieba），接入 `TABLE`/`CON_TABLE` 常量定义（lib/globals.php）、账号解绑分表、复签控制表与 API 用户表选择——来自数据库 `users.t` 的值在拼入 SQL 标识符前统一过白名单。
- **API 收尾**：令牌哈希查找、限流计数读取、health 账号过滤全部参数化；经真实数据库验证 NULL 绑定、含引号/反斜杠值原样存储。
- **回归守卫**：新增断言「三个入口禁止出现插值拼接 SQL（`{$var}` 进 query/once_fetch_array）」，后续引入即 CI 失败；`safe_table_name` 白名单行为覆盖 10 项边界。

## 2026-09-06 V7.0 批次4：调度可靠性加固

> 随 V7.0 持续集成，无额外升级步骤（新选项 cron_notify_interval 由迁移 007100 自动写入）。

### 调度引擎（do.php / cron）
- **排程写入持锁完成**：`cron::runOnce` 调整为「执行 → 记录运行历史 → 排程 → 释放锁（finally 保证）」，消除并发进程在解锁与排程之间交错读写 retry/nextrun 的窗口。
- **失败通知去重**：渠道告警（Webhook/Notify）新增节流窗口 `cron_notify_interval`（默认 3600 秒，0 = 每次耗尽都告警）；重试阶段的失败本就不告警，同一任务持续失败时渠道告警最多每窗口一次；结构化错误日志不受节流影响，保证可观测性。
- 新增迁移 007100 写入默认选项；全新安装模板同步预置。

### 集成测试
- 新增 `tools/scheduler-integration-test.php`（43 项断言，真实数据库）：到期任务执行与排程、锁冲突跳过、过期锁接管恢复、失败指数退避、耗尽告警去重（窗口抑制/关闭）、一次性任务清理、`do.php` 只跑到期任务与持锁并发保护。
- 迁移与调度集成测试共用 `tools/lib-integration.php`；`composer integration` 与 CI 的 MariaDB 11.4 作业同时运行两者。

## 2026-09-06 V7.0 升级治理：可恢复迁移、统一版本源与发布校验

> 数据库版本 6.6 → 7.0（无破坏性结构变更）。升级入口：管理员访问 `/setup/update6.6to7.0.php`，或服务器执行 `php tools/migrate.php up`（同一迁移器）。升级前请备份数据库。

### 升级与迁移
- **统一迁移器**：新增 `lib/class.migrator.php`，`tools/migrate.php` 改为薄 CLI 入口。`schema_migrations` 升级为完整状态模型（description/checksum/status/started_at/finished_at/batch_id/error_message），旧记录按成功兼容；每次执行前通过 `GET_LOCK` 取得库级迁移锁，防止 Web/CLI/容器并发迁移。
- **失败可恢复**：迁移失败写入 failed 状态与错误信息后中止，修复后重跑即可继续；任一迁移未全部成功时绝不更新 `core_version`；已应用迁移文件被改写时 checksum 校验阻断。
- **版本门禁**：7.0 迁移仅允许从 `core_version=6.6` 进入，5.13 及更早版本明确阻断并提示先走旧升级链；已在新版本时幂等退出。`setup/update6.6to7.0.php` 为受管理员登录保护的 Web 升级入口，版本门禁页会自动引导。
- **7.0 基线迁移**（007000）：校验 6.6 必备核心表齐全（缺失即失败，不猜不跳），幂等补齐调度结构与既有系统选项/任务，兼容经旧脚本链升级、迁移记录为空的存量库。

### 版本元数据统一
- 新增 `version.php` 作为唯一版本源，`init.php`、安装向导、环境检查页共用；修复安装页标记 5.12、环境检查页标记 1.0 的漂移。
- 新安装模板 `core_version` 直接写入 7.0，并预置 `schema_migrations` 基线记录，全新安装不再显示待应用迁移。
- `windows/check-update.ps1` 默认当前版本由 5.09 修正为 7.0。
- Composer 平台要求补齐 `ext-zip`/`ext-openssl`/`ext-session`（与 Docker、安装检查一致）；OpenSSL 与 ZipArchive 在环境检查中升级为“必须”。

### 更新器安全
- 在线更新安装前强制校验发布包 SHA-256：优先 Release `digest` 字段，其次随包发布的 `.sha256` 附件；拿不到可信摘要或哈希不一致时拒绝覆盖安装（ZIP 魔数不再作为完整性依据）。
- Docker 部署的更新令牌命名由 `UPDATE_GITHUB_TOKEN` 统一为 `UPDATE_GITEE_TOKEN`，与更新器、README、Windows 脚本一致。

### Linux 兼容与 CI
- **类加载大小写加固**：`class_autoload` 原按"类名小写拼文件名"查找，在大小写敏感的 Linux 容器上会漏掉 `class.SignManager.php`、`class.P.php` 等混合命名文件；现改为基于真实目录清单的解析器（`lib/class.loader.php`，含 `wmysql`/`ProjectUpdater` 命名别名），新增静态回归测试强制 lib 下每个类文件都能解析回自身。
- **CI 新增 Docker 镜像冒烟**：构建镜像并 `compose up --wait` 启动完整栈，验证 `healthz.php?mode=live` 存活检查返回 ok、安装向导与首页路由经 Caddy + PHP-FPM 正常响应（HTTP 200）。

### 测试
- 新增 `MigratorTest`（14 项：建表升级、legacy 兼容、锁冲突、状态流转、失败重试、checksum 漂移、版本门禁、007000 基线校验）与 `ReleaseConfigTest`（7 项：版本源唯一性、安装模板基线、Docker/Windows/Composer 配置防漂移）；`UpdaterTest` 新增 5 项摘要校验测试。全量 105 项测试通过。
- 新增真实数据库迁移集成测试 `tools/migrate-integration-test.php`（`composer integration`）：自动构建 6.6 存量库夹具，完整演练失败恢复 → 升级 → 幂等 → checksum 漂移阻断 → 迁移锁 → down 回滚 → 全新安装基线共 35 项断言；CI 新增 MariaDB 11.4 服务容器作业运行该测试。

## 2026-09-05 V6.6 移动端适配深化 + 视觉细节统一 + 工作台快捷操作

> 数据库版本 6.5 → 6.6（无任何表结构变更，纯界面版本）；CSS 缓存戳随版本号自动更新，浏览器将强制拉取新样式。

### 移动端与 UI（批 C）
- **移动端深度适配**：10 个主要页面在 375px 视口下横向溢出清零（程序化审计驱动）。AI 发帖/回帖设置表格（原 55px 溢出）新增 `form-table` 响应式布局——手机上逐行堆叠（标签在上、控件在下）；「最近执行记录」「发帖草稿箱」「内容审查拦截」表格统一接入 `cs-table-wrap/cs-table-scroll` 玻璃滚动容器；移动端内联定宽输入控件自动转全宽。
- **视觉细节统一**：全仓 22 处遗留 `<font color>` 标签清零，统一为 `cs-badge` 状态徽章与新增的 `cs-text-success/danger/warning/info` 文本色工具类（覆盖 AI 用户页/插件管理/计划任务/双因素认证/账号中心/贴吧封禁日志）；管理页插件状态、计划任务运行状态徽章与新版页面风格一致。
- **工作台首屏快捷操作**：Hero 区新增常用操作行——「立即签到」（一键为我的全部账号签到，实时显示已签/失败/等待统计与 toast 反馈，未绑定账号时自动禁用）、「绑定百度账号」「签到记录」「数据分析」直达入口。

## 2026-09-05 V6.5 数据分析增强（吧明细导出 / 监控统计入报表 / 账号健康度趋势）

> 数据库版本 6.4 → 6.5（无核心表结构变更），请访问 `/setup/update6.4to6.5.php` 完成升级。账号健康度快照表随健康检查自动创建并积累，无需手工操作。

### 数据与报表
- **吧维度明细导出**：数据分析页新增「导出吧明细 CSV」（`export=csv&type=forum`），按当前统计区间输出每个贴吧的成功/失败/总计/成功率/最近状态/最近签到时间/最近消息；同时修复原「导出报表 CSV」未跟随管理员控制模式（CON_UID）视角的问题——管理员查看某用户数据时导出的也是该用户的数据。
- **监控统计纳入报表**：数据分析页新增「监控命中趋势」区块（每日命中数面积图）与每吧命中数/联动 AI 回帖数 Top 表（近 7/30/90/365 天跟随区间筛选）；帖子监控插件未安装时自动隐藏，不影响页面。
- **账号健康度趋势**：新增 `tc_baiduid_health_snapshot` 每日快照表（每账号每天一条，取当天最后一次健康检查结果，随既有健康检查计划任务自动积累，无需新增计划任务）；数据分析页新增「账号健康度趋势」图（每日正常账号数柱状 + 正常率折线，空白天自动补齐）。

### 测试
- 新增 `ReportStatsTest` 4 项（监控表缺失容错、Top/趋势聚合、健康趋势空白天填充与参数绑定、健康快照 REPLACE 参数绑定），全量 77 项测试通过。

## 2026-09-05 V6.4 安全加固 + AI 体验完善（人设模板/半自动发帖/审查拦截）

> 数据库版本 6.3 → 6.4（无核心表结构变更），请访问 `/setup/update6.3to6.4.php` 完成升级。站长建议：升级后在「设置中心 → 系统」重新保存一次开放 API Token，即可从旧明文存储自动转为哈希存储（新旧两种存储形态在线上均正常认证，不强制）。

### 安全（6 处高危 + 多项收敛）
- **邮箱验证接口未认证 SQL 注入**：`index.php?mod=verify&token=` 的 token 未做格式校验直接拼 SQL，访客可盲注；现按生成格式（40 位十六进制）严格校验后放行。
- **「重签出错吧」跨用户数据重置 + 万级 N+1**：共享贴吧表中按他人 id 区间逐行循环且 UPDATE 缺失 uid 条件，单次点击可能清掉其他用户的签到状态；现改为单条带 uid 的参数化 UPDATE（仅重置本人出错贴吧）。
- **CSV 导出静默截断**：签到日志导出被分页上限钳制为 200 行且无任何提示；上限提升至 5000 行，UI 分页不受影响。
- **排行榜隐私开关完全失效**：`option::uget` 参数顺序颠倒导致查询恒为空，勾选「隐藏昵称」后昵称仍公开；已修正。
- **百度接口错误消息二阶注入**：签到失败的 `error_msg` 含引号时状态落库静默失败、贴吧无法进入复签队列；fid/状态/错误消息全部参数化。
- **管理员侧注入面**：用户管理的 `NOT IN (...)` 列表与数据库备份表名拼接强制 int 转换 + 表名白名单（`[A-Za-z0-9_]{1,64}`）。
- **CSRF 硬化**：请求携带 token 但校验失败时直接拒绝，不再静默降级到 Referer 校验（未携带 token 的旧链接仍走原 Referer 规则，兼容不变）。
- **全局 API Token 与 metrics Token 哈希存储**：保存后仅落库 SHA-256（`api_token_storable`），认证端 `api_token_matches` 同时兼容哈希与升级前旧站明文形态；`metrics.php` 与 `api.php` 双消费点同步。
- **性能与信息泄露**：签到日志/健康检查的高频建表 DDL 加进程级守卫（含表缺失自愈重试）；「立即签到」响应不再把全站统计回给普通用户（改本人数据）；管理后台状态面板全表扫描改 SQL 聚合；`option` 类 `plugin_` 分支与用户设置 uid 全部参数化/强转；开放 API `update` 动作对个人 Token 返回 403；账号绑定缺参从空白页改为 JSON 错误；日志筛选 LIKE 通配符转义。

### AI 体验完善（ver4_ai 插件 2.1 → 2.2）
- **自定义人设模板**：个人面板可把调好的人设另存为「我的模板」（上限 20 个、同名覆盖），人设下拉新增「我的模板」分组，支持选用与删除。
- **半自动发帖模式**：发帖设置新增开关；开启后计划任务到点不再自动发布，而是为每个目标吧一次生成 3 条候选（逐条过敏感词 + 近 7 天去重），存入「发帖草稿箱」由用户挑选发布或丢弃。发布前校验账号归属，失败保留草稿可重试；候选 7 天未处理自动清理、每人上限 15 条；与全自动模式并存互不影响。
- **内容审查拦截记录**：用户页新增敏感词/重复跳过（status=5）单独列表（最近 20 条，仅本人账号），便于排查人设与敏感词配置。
- 插件内发帖/回帖/日志/建表函数上移至 `ver4_ai_client.php`，计划任务与用户页共用一份实现。

### 界面
- 「重签出错吧」增加确认弹窗（说明只重置出错项、不影响已签成功项）；贴吧绑定缺参提交返回明确 JSON 错误提示。

### 测试
- 新增 `SecurityHardeningTest`（8 项：注入面收敛、Token 哈希兼容、DDL 守卫、LIKE 转义）与 `Ver4AiPersonaTest`（6 项：模板解析/上限/覆盖/删除、账号过滤 `strlen('0')` 回归），全量 73 项测试通过；关键修复均经本地浏览器 + curl 双通道 E2E 验证。

## 2026-09-04 V6.3 帖子监控与 AI 联动 + 数据运营 + Glass v5 玻璃拟态

> 本版本起恢复「每批次同步：版本号 + 更新日志 + Gitee Release」的发版节奏；数据库版本 5.13 → 6.3（无核心表结构变更），请访问 `/setup/update5.13to6.3.php` 完成升级（或使用 `php tools/migrate.php` 流程）。

### 新功能
- **帖子监控插件（ver4_monitor 1.0 → 1.4）**：用户个人面板配置目标贴吧与关键词，定时扫描最新主题帖并推送命中提醒——支持排除关键词、监控全部新帖模式、实时推送或每日汇总（附各吧命中统计）、个人通知渠道（Server酱/TG/Bark/PushPlus/Webhook/ntfy，未配置回退站点渠道）、命中帖首楼页内预览、命中历史记录。
- **监控命中联动 AI 自动回帖（1.4）**：命中后由 AI 读取首楼上下文生成回复并自动发布（用户开关+每日上限 1-20），完整复用 AI 配置/人设/内容风格与站长敏感词、内容去重防护，命中表展示回帖状态（已回帖/失败/跳过）。
- **AI 面板 2.0**：发帖/回帖下沉为用户个人面板功能（个人 Key AES 加密存储，可选站点共享配置回退）；内容安全双重防护（站长敏感词全站预检+近 7 天同账号内容去重）；AI 内容风格定制（emoji 允许/禁止、字数偏好）与话题标签自动附加。
- **百度账号页增强套件**：「我的贴吧」关注吧列表（等级/经验/今日签到状态+30 分钟缓存）、今日签到进度条、账号分组管理与筛选、聚合操作（全部/选中账号立即签到、一键刷新贴吧列表）、统一代理入口、最高吧等级 Top3 摘要、「关注 N 吧 · 已签 M」徽章、我的贴吧 CSV 导出。
- **签到引擎**：签到时段窗口（支持跨零点）、失败智能重试退避（按 2 的幂间隔）、客户端指纹轮换默认开启。
- **数据保留与自动清理**：工具箱可配签到历史/任务运行历史/批量签到日志/插件执行记录/API 限流记录保留天数（0=永久），新增 `system_cleanup` 每日清理计划任务（升级脚本自动注册，工具箱可手动「立即清理一次」）。
- **每周签到报告**：个人设置开启并选择推送日，`system_weekreport` 经用户个人通知渠道推送近 7 天签到汇总/成功率/失败原因 Top3（6 天防重、无记录不打扰）。
- **数据分析页**：7/30/90/365 天多维分析（成功率趋势/吧维度对比/失败原因 TOP/时段分布/账号健康对比）+ 报表 CSV 导出。

### 界面
- **Glass v5 玻璃拟态深化**：环境光斑升级为品牌紫罗兰光谱（深浅双主题）；cs-* 提示条/列表/折叠/表格容器玻璃化；深色主题残留旧青绿清理；修复 BS5 变量导致深色表格白底白字、alert 实色刺眼的问题；下拉菜单玻璃化；不支持 backdrop-filter 时自动回退高不透明玻璃；CSS 版本戳 g5 强制刷新。

### 修复
- 帖子监控排除关键词失效（cron 使用未定义变量 `$excludeWords`，选项从未被读取）。
- 双因素认证（admin:totp）页面空白（control.php 缺渲染分支）。
- 健康度列窄屏被挤成竖排；监控页个人通知渠道输入框窄屏裁切；贴吧列表序号列改为面板内行号。
- 数据中心 IP 风控提示、AI 账号过滤空配置误产出 `[0]` 导致全部账号被跳过（`strlen('0')` 陷阱）。

### 安全
- 插件与核心 40 处 SQL 汇聚点参数化加固（pquery 预处理）；weltolk 插件设置页存储型 XSS 回显转义；用户选项写入迁移参数化。

## 2026-09-04 V6.2 AI 大模型模块 + 空白页修复

### 新功能
- **AI 大模型发帖/回帖插件（ver4_ai 1.0）**：接入任意 OpenAI 兼容大模型接口（智谱/DeepSeek/中转站等，默认预配置智谱 GLM）。
  - AI 定时发主题帖：按「每日定时（到点发一帖）」或「每 N 小时」模式向目标贴吧自动发布大模型生成的主题帖（标题+正文），人设（System Prompt）与主题方向可配置，每账号每日上限保护。
  - AI 智能回帖：定时拉取目标贴吧最新主题帖，可读取首楼内容作为上下文，大模型生成贴合主题的口语化回帖；轮间隔节流、楼中楼比例可调、AI 失败可走兜底内容库、同帖同日去重。
  - 设置中心插件页：API 地址/Key（AES-256-GCM 加密存储）/模型/温度/Token 上限/全局人设、回帖与发帖全部参数、免发帖的「生成测试」按钮；可指定账号 ID 子集，账号级代理沿用。
  - 计划任务 `ver4_ai_do`：日志表 `tc_ver4_ai_log`（回帖/发帖记录、状态、错误），与调度引擎运行历史打通；接口请求全程走公网校验（协议/主机/DNS 解析均拒绝私有保留地址）。

### 修复
- **双因素认证页面空白**：`admin:totp` 在 `templates/control.php` 缺少对应渲染分支，GET 访问只输出 footer（V5.13 引入）；补齐 case 并将 `admin-totp` 模板改为由调用方统一渲染头尾（`setting.php` POST 路径同步补齐）。
- 侧边栏「反馈与文档」链接由 GitHub（仓库已冻结）切换为 Gitee 仓库地址。

## 2026-09-03 V5.13 全面升级

### 新功能
- **调度引擎**：任务级运行历史（`task_runs`）、跨进程防重入锁、按 `nextrun` 调度、失败指数退避重试（`cron_max_retry`/`cron_retry_backoff`）、任务中心增强（状态徽标/下次执行/耗时/一键重试）。
- **Webhook 事件推送**：新增 `lib/class.webhook.php`，任务失败等事件推送自定义 URL（HMAC-SHA256 签名 `X-Webhook-Signature`），curl 优先、stream 回退；选项 `webhook_enable/url/secret/events`。
- **开放 API 增强**：`api.php` 新增按 Token 的滑动窗口限流（`api_rate_limit`，超限 429）、`webhook_test`（管理员测试推送）、`openapi`（接口文档）。
- **可观测性**：新增结构化 JSON 日志（`lib/class.logger.php` → `data/log/system.log`）、Prometheus 端点（`metrics.php`，Token 认证）、深度健康检查（`healthz.php?mode=deep`：cron 新鲜度/错误堆积）。
- **数据库迁移体系**：新增 `tools/migrate.php status|up|down` 与 `schema_migrations` 表；首个幂等迁移 `setup/migrations/001001_v513_scheduler.php` 覆盖 V5.13 全部结构。
- **插件规范**：manifest 清单校验（`plugin_manifest_validate`，缺失 name/version/author 标记不完整）；覆盖安装自动备份到 `plugins/.rollback` 且失败自动回滚，`plugin_restore_plugin` 支持手动恢复。
- **管理员双因素认证（TOTP）**：新增 `lib/class.totp.php`（RFC 6238，兼容 Google/Microsoft Authenticator），管理员登录二次验证；`admin:totp` 页生成密钥/链接、启用/关闭。
- **工程收尾**：`tools/lint.php` 新增高危模式扫描（eval/废弃 mysql_* 硬失败，addslashes/命令调用警告，`@lint-ignore` 豁免），随 CI 自动执行。
- **国际化骨架**：新增 `lib/i18n.php` 与 `lang/zh-CN.php`，`I18N::t()` 词条化调用（登录页示例接入）。

### 升级
- 数据库版本 5.12 → 5.13：`cron` 表新增 `enable/nextrun/retry/max_retry/last_status/last_duration/running/running_at`，新建 `task_runs`、`api_rate_limits` 表，补齐调度/API/可观测性/Webhook 选项。
- 升级路径：访问 `/setup/update5.12to5.13.php`（自动迁移+自删），或使用 `php tools/migrate.php up` 统一迁移（幂等，可重复执行）。

## 2026-09-03 V5.12 多项目调研落地批次

### 新功能
- **自动发主题帖**：`ver4_reply` 插件升级至 1.1，新增发主题帖模式（目标贴吧 + 标题库 + 内容库随机选取、每吧限量、每账号每日发帖上限），接口 `c/c/thread/add`，与自动回帖共用间隔与代理；回帖/发帖日志表新增 `type` 列区分（旧表自动补列）。
- **楼中楼回复**：回帖可按可调比例（0-100%）随机进入主题帖指定楼层回复，楼层经 `c/f/pb/page` 获取、`quote_id` 参数提交（协议参考 96dl/Tieba-Cloud-Sign-Plugins 的 ver4_post 与 FirefoxBar Tieba_Client）。
- **用户自主修改密码**：个人设置页新增修改密码表单（验证当前密码、6-64 位校验），改密成功后旧会话自动失效需重新登录。
- **多邀请码管理**：新增 `admin:invite` 页面（侧边栏"邀请码管理"）：批量生成（数量/可用次数/有效期/备注）、启用停用、删除、用量统计；注册流程支持多邀请码模式（`invites` 表原子核销，启用后单一邀请码设置失效）。
- **邮件群发**：用户管理页新增群发表单，向全部留有邮箱的用户发送 HTML 邮件，支持 `{name}`/`{site}` 变量。
- **定时数据库备份**：新增计划任务 `lib/cron_system_dbbackup.php`，每日将全部数据表导出到 `data/backups` 目录（Apache 由 .htaccess 拦截直接下载），保留份数可配、可选邮件通知站长；工具箱可一键注册任务、立即备份、保存设置。
- 客户端指纹轮换：签到设置新增开关（默认开启），开启后每次请求随机 `_client_type`（1-4）与 `_client_id`/`_phone_imei`，降低风控特征。
- 多项目横向调研：`docs/tieba-feature-survey.md` 对照 10 个同类开源项目 + 上游 48 插件 + 客户端 API 差距矩阵，给出采用/暂缓决策。

## 2026-09-03 V5.12 通知、插件生态与现代化

### 新功能
- 新增**账号中心**页面（`index.php?mod=account`，侧边栏可见）：集中展示每个绑定账号的健康状态、贴吧数量、当日签到结果、代理配置与失效恢复入口，附近 7 天签到趋势。
- 新增**云端自动回帖**插件 `ver4_reply`：目标贴吧 + 回帖内容库（随机选取）+ 每吧限量 + 每账号每日上限 + 随机间隔 + 按天去重（独立日志表）；接口 `c/c/post/add`（tiebaclient 签名，继承账号级代理，触发频率限制自动终止本轮）。参数集参考 kookxiang/Tieba_Sign 的 xxx_post 插件。
- 通知渠道扩展至 11 个：新增钉钉群机器人（支持加签）、企业微信、飞书、WxPusher、ntfy（自托管 HTTPS 实例）。
- 新增云点赞插件 `ver4_like`：按贴吧配置、多账号、每吧限量、随机间隔，走贴吧客户端接口并继承账号级代理。
- 失效账号自动停用：BDUSS 健康检查连续失败达到阈值（可配 1-20 次）后自动停止该账号全部贴吧签到并推送提醒；账号所有者与管理员均可一键恢复。
- 插件在线安装：仅允许 `https://gitee.com` 的 ZIP 下载链接，禁止跨域重定向、限制 50MB；上传安装与在线安装统一走新的安全提取器（单根目录、Zip Slip 防护、拒绝覆盖、兼容 `仓库-main` 归档结构）。
- 逐用户 API Token：个人设置生成/撤销，数据库只存 SHA-256 哈希；旧全局 Token 完全兼容；用户 Token 严格隔离 status/sign/retry/health/logs/calendar。
- 签到历史与日历：新增 `sign_history` 表记录每个贴吧每次签到的结果，新增个人 90 天热力图页与 `api.php?action=calendar` 接口。

### 安全加固
- 移除工具箱"运行 SQL"功能（任意 SQL 执行面）；同时移除 `truntab` 后遗留的不可达死代码（裸 `$_POST` 拼接 TRUNCATE）。
- 新增 `wmysql::pquery` 参数化查询支撑，插件管理、计划任务、用户管理、找回密码、账号代理、个人设置、开放 API 日历/日志、登录审计、健康探针等全部后台 SQL 汇聚点迁移至预处理语句。
- 插件全部 GET 动作（启用/禁用/更新/卸载/重置/安装）增加 `valid_plugin_id` 白名单边界校验。
- 自定义分表名在进入建表语句前强制 `^[a-z0-9_]{1,64}$` 白名单校验。
- 批量修改用户组对 ID 强制 int 转换；后台添加用户与站内注册统一用户名/邮箱白名单校验。
- 计划任务动作名（启用/停用/删除/立即运行/排序）统一白名单校验。
- `tools/lint.php` 弃用 shell 拼接改为 `token_get_all` 进程内语法检查；`LoginGuard::auditLogin` 移除函数内 `$_POST` 直读；账号恢复参数增加数字白名单。

### 升级
- 数据库版本 5.11 → 5.12：`users` 表新增 `api_token_hash`（唯一索引），新建 `sign_history` 表，补齐通知与健康设置默认项；请访问 `/setup/update5.11to5.12.php` 完成升级。

## 2026-09-03 V5.11 账号体验

### 新功能
- 新增批量导入百度账号：账号页支持多行粘贴 BDUSS（可带 STOKEN，兼容 `BDUSS=xxx;STOKEN=yyy` 等格式），自动校验 Cookie、识别账号名并尊重配额与重复添加策略，单次最多 50 行。
- 新找回密码：登录页新增"忘记密码"入口，访客可通过注册邮箱自助重置（一次性令牌 30 分钟有效，5 分钟请求频率限制）。
- 新增注册邮箱验证（可选开关）：开启后新注册用户需点击邮件中的验证链接才能登录，验证页支持 5 分钟一次的重发；未开启时老账户登录不受影响。
- 新增访客可访问的 `index.php?mod=lostpw` 找回密码页与 `index.php?mod=verify` 邮箱验证页。

### 升级
- 数据库版本 5.10 → 5.11：`options` 表新增 `reg_mail_verify` 默认项；请访问 `/setup/update5.10to5.11.php` 完成升级。

## 2026-09-03 V5.10 签到引擎强化

### 新功能
- 新增全局网络代理：支持 HTTP(S) 与 SOCKS5（远程 DNS 解析），签到、扫描、TBS、扫码绑定等请求统一走代理工厂。
- 新增账号级代理覆盖：百度账号列表页可为单个账号配置独立代理（`http://host:port` 或 `socks5://user:pass@host:port`），留空跟随全局，`off` 单独禁用。
- 新增打码平台接入：目前支持超级鹰；绑定账号遇到验证码时自动识别并重试一次，识别失败自动退还题分。
- 模拟客户端版本号 options 化（签到设置页可改），百度更新客户端后无需改代码即可切换版本号。
- 新增签到随机抖动（sign_jitter）：在签到间隔基础上随机追加等待，随机化请求节奏降低风控特征。
- 新增签到乱序开关（sign_shuffle）：默认随机乱序执行签到队列。
- 新增"开源致谢"板块（index.php?mod=thanks），登记本项目所依赖的全部开源项目与作者。

### 升级
- 数据库版本 5.09 → 5.10：`baiduid` 表新增 `proxy` 列，`options` 表新增 11 项默认设置；请访问 `/setup/update5.09to5.10.php` 完成升级。

## 未发布

### 可靠性与运维
- 更新通道调整为仅使用 Gitee 国内源，禁用 GitHub、海外 GeoIP 探测和海外回退。
- Gitee Release 查询与下载仅使用 `UPDATE_GITEE_TOKEN`；受限仓库或 API 限流时需要在部署环境配置该只读令牌。
- 新增 `tools/publish-gitee.ps1`，仅从干净的版本 Tag 生成 Windows ZIP 和 SHA-256 文件；必须显式传入 `-Publish` 才会发布到 Gitee。
- 修复登录失败审计未保存用户名、导致同账号限流规则无法命中的问题。
- 修复数组形式请求参数在核心入口触发类型错误或转换警告的问题。
- 修复 MySQLi 兼容层 `pquery()` 对 INSERT/UPDATE/DELETE 等无结果集语句返回 `false`，导致调度锁 `acquireLock` 误判失败、任务被反复跳过且锁不释放的问题；`affected_rows()` 改为读取预处理语句实际受影响行数，并补充 `ping()` 方法（本地真实数据库运行验证时暴露）。
- 修复 `tools/migrate.php` 迁移后未同步 `core_version` 导致 init 版本校验拦截访问的问题；`migrate.php up` 现会自动同步 `core_version`。
- 固定 bcrypt cost 并保证自动重哈希后的登录 Cookie 使用最新密码哈希。
- 新增 `/healthz.php` 存活与就绪检查，以及 Windows 环境检查启动器和健康检查脚本。
- CI 扩展为 PHP 8.2/8.4 测试矩阵、依赖审计、全仓 PHP 语法和 PowerShell 脚本检查。
- Release 同时发布 Windows ZIP 的 SHA-256 校验文件。
- Windows 更新检查补齐 Gitee 失败后的 GitHub 回退，并严格隔离两个平台的访问 Token。

## 2026-08-30 V5.09 安装向导 UI 升级

### 前端体验
- 安装向导改为响应式卡片布局，新增品牌头部、安全提示和清晰的步骤进度指示器。
- 优化许可协议、环境检查、运行环境选择和站点信息表单的层级、间距与移动端显示。
- 安装页静态资源使用版本号缓存标识，更新后可立即加载最新界面。
- 修复无数据库、空表单和异常步骤参数下的安装页错误，并同步新装数据库版本。
- 新增按版本 tag 自动构建并发布 Windows 部署包的 GitHub Actions 工作流。

## 2026-08-29 V5.08 在线更新

### 新功能
- 系统更新页改为读取当前仓库 GitHub 正式 Release，按版本号检测更新，不再把开发中的 commit 当作稳定版本。
- 检测到更新时显示版本说明、Release 页面和 Windows 部署包下载入口，并支持管理员在线更新源码。
- 新增 `api.php?action=update` 版本检查接口和 `windows/check-update.ps1` 独立检查脚本。

### 安全与兼容
- 在线更新强制 HTTPS 并限制可信 GitHub 下载域名，更新前再次确认最新 tag。
- 更新时保留 `config.php`、`key.php`、环境变量文件、安装锁和数据库；失败时清理临时文件。
- 支持 `UPDATE_GITHUB_TOKEN` 提高 GitHub API 速率限制，支持 `SSL_CERT_FILE` / `CURL_CA_BUNDLE` 修复 Windows PHP CA 配置。

## 2026-08-12 V5.07 安全加固 + 开放 API + 报表

### 新功能
- 开放 API (`api.php`): Bearer Token 认证，支持 status/health/sign/retry/health_check/logs 接口，可供青龙面板、n8n、Home Assistant 等外部系统调用。
- 签到报表页 (`templates/admin-stat-chart.php`): ECharts 折线图展示最近 30 天签到趋势，总执行次数、成功率、最近执行时间卡片。
- 签到看板新增“立即复签”“立即全部签到”按钮。
- 通知渠道新增 Bark (iOS) + PushPlus (微信)，现共支持 6 种渠道。
- BDUSS/STOKEN 加密落库 (AES-256-GCM)，存量数据升级时自动加密，读取时透明解密，兼容旧明文数据。
- 升级脚本 `setup/update5.06to5.07.php`: 加密存量 BDUSS、tieba.latest 扩展为 VARCHAR(10)、users 表加 created_at/last_login/auth_version、生成 crypto_key。

### 安全修复
- CSRF 收紧: POST/PUT/PATCH/DELETE 请求强制要求 Token，不再仅依赖 Referer 回退。
- CSRF Token 密钥: 优先使用 SYSTEM_SALT，其次使用数据库 crypto_key，不再使用硬编码公开字符串。
- 管理员操作参数强转: xorder 插件排序、用户批量操作均强制 int 或正则过滤，阻止 SQL 注入。
- 移除已废弃的 mcrypt 死代码，替换为 openssl AES-256-GCM。

---

## 2026-08-12 V5.06 本地升级执行批次

### 新功能
- 通知中心 (`lib/class.notify.php`): 支持 Server酱 / Telegram Bot / 通用 Webhook / 邮件四种渠道，可在签到失败、百度账号 BDUSS 失效时主动推送，设置页可发送测试通知。
- 登录防护 (`lib/class.loginguard.php`): 同一账号 15 分钟内失败 5 次锁定 15 分钟，登录审计记录 (账号/IP/UA/结果) 可在工具箱查看。
- 签到统计与日志闭环: 每次签到/复签/健康检查后写入 `cron_log_batch`，失败时触发通知。
- 数据库升级脚本 `setup/update5.05to5.06.php`: MyISAM → InnoDB、补充索引、新增登录审计表与通知默认配置。

### 安全修复
- 修复 `wcurl` 默认关闭 TLS 证书校验的问题，现默认校验证书与主机名。
- 百度接口全部由 `http://` 改为 `https://`，避免 BDUSS/密码被中间人截获。
- 异常处理器脱敏: 非管理员/非开发模式下不再输出 SQL 、文件路径与调用栈，改为日志记录+通用提示。
- 登录成功后自动将旧式 md5 链密码升级为 bcrypt (cost 12)。
- 修复 `DoSign_retry` 死代码 bug: `date('Y-m-d')` 与 `latest` (日号) 类型不匹配导致复签任务永远不执行。
- 默认关闭 `wmzz_debug` 插件 (phpinfo 流出)。

### 前端/工程
- 静态资源统一加 `?v=` 版本号，配合 Caddy 强缓存。

## 2026-08-04 本地现代化升级

- 修复 `init.php` 中 `SYSTEM_ISCONSOLE` 使用 `or` 导致 `STDIN` 判断失效的运算符优先级问题。
- `config.php` 支持通过环境变量覆盖数据库、CSRF、加密盐等配置，Docker 不再依赖启动时用 sed 改写配置文件。
- 修复 `lib/class.misc.php` 中 `latest` 查询日期未加引号的隐患。
- `setup/check.php` 更新为 PHP 8.1+ / MySQLi 必选，移除已废弃的 `mysql_*` 与 `safe_mode` 检查，补充 `password_hash`、OpenSSL、`allow_url_fopen` 检查。
- `ajax.php` 服务器信息面板改用 `php_sapi_name()`，移除已废弃的 `safe_mode` 分支。
- Docker Compose 升级为 Compose V2：官方 `mariadb:11.4`、命名卷、健康检查、重启策略、`.env` 示例，移除旧版 `links` 写法。
- Compose 的 web 服务改为从仓库内 Dockerfile 本地构建镜像，默认不再拉取上游旧镜像；并修复 Caddy 配置目录权限告警。
- Dockerfile 增加 `php84-opcache` / `php84-openssl`、OPcache 配置、Caddy 安全响应头、gzip 压缩与容器 `HEALTHCHECK`。
- PHP-FPM 池配置 `clear_env = no`，避免 FPM 清空环境变量后回退到 `127.0.0.1` 数据库连接导致 `Connection refused`。
- Compose 健康检查改为请求静态 `favicon.ico`，避免未登录 302 跳转导致 busybox `wget` 误报失败。
- 修复 `init.php` 对 `TC_AUTO_CREATE_INSTALL_LOCK` 的布尔判断，`false` 字符串不再被误判为真值而自动创建 `install.lock`。
- 新增 `.dockerignore` 与 CI 工作流（PHP 语法检查、Compose 校验）。
- 新增 `SECURITY.md`。
