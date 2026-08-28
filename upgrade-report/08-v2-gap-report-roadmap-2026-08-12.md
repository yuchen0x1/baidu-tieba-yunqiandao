# 08 V2.0 差距报告、升级路线图与 AI 增强方案

- 编制日期：2026-08-12（本轮：竞品联网调研 + 安全/架构深度审计 + 实际代码执行）
- 数据来源：GitHub API 实测竞品、仓库内既有 01~07 报告、全仓 PHP 代码审计、113 个 PHP 文件语法核验
- 本轮已落地代码：V5.06（通知中心 / 登录防护 / 签到日志闭环 / InnoDB 迁移 / 6 项安全修复）

---

## 一《项目升级差距报告》

### A. 当前项目优势

1. **部署现代化已经领先同类**：PHP 8.4 + Caddy(zstd/gzip/安全头/强缓存) + MariaDB 11.4 + Docker Compose V2 + 健康检查 + OPcache，Docker 侧完成度高于上游 MoeNetwork/Tieba-Cloud-Sign（1968★）。
2. **基础安全已在上一轮补齐**：CSRF Token（HMAC）、bcrypt 密码哈希、install.lock、环境变量配置、SECURITY.md、CI 语法检查。
3. **功能完整度在“贴吧专用”赛道排前**：多账号、扫码登录、贴吧增删、多模式签到、插件体系（灌水/点赞/封禁/删帖/查楼）、控制台模式、自动更新。
4. **UI 已升级为现代风格**：玻璃拟态布局、暗色模式、响应式侧边栏（上一轮完成，摆脱纯 Bootstrap 3 观感）。
5. **已有半成品现代化组件**：SignManager（日志/健康度）、baiduid_health、cron_log_batch 表。

### B. 当前项目不足（本轮实测确认）

| 问题 | 位置 | 说明 |
|------|------|------|
| 出站 TLS 校验关闭 | `lib/class.wcurl.php:381` | BDUSS/密码可被中间人截获 —— **本轮已修复** |
| 百度接口走明文 http | `lib/class.misc.php` 多处 | 同上 —— **本轮已修复** |
| 异常处理器泄露 SQL/栈 | `lib/class.E.php`、`class.mysqli.php:138` | 未登录访客可见完整 SQL 与绝对路径 —— **本轮已修复** |
| 复签任务死代码 | `lib/class.misc.php:378` DoSign_retry | `date('Y-m-d')` 与 `latest`(日号) 类型不匹配，复签永不执行 —— **本轮已修复** |
| 登录无限流/无审计 | `lib/globals.php` | 可撞库 —— **本轮已修复（LoginGuard）** |
| 旧 md5 链密码不升级 | `lib/globals.php:139` | 登录成功不 rehash —— **本轮已修复** |
| `wmzz_debug` 默认启用 | `setup/install.template.sql:119` | phpinfo 泄露环境变量 —— **本轮已修复（默认关闭）** |
| 无通知渠道 | 全库 | 签到失败、BDUSS 失效静默 —— **本轮已新增 Notify** |
| 签到日志未闭环 | cron 任务 | SignManager 日志未接入主链路 —— **本轮已接入** |
| 核心表仍为 MyISAM | 7 张表 | 无行锁/无事务 —— **本轮已提供升级脚本** |
| `latest` 存日号 | `tieba.latest tinyint` | 月初跨月边界会误判“已签到”，建议改 DATE 列 |
| BDUSS/STOKEN 明文落库 | `baiduid` | DB 泄露=账号全泄露，建议 openssl 加密 |
| 无服务端会话 | Cookie-only 认证 | 登出不可剥销，建议 auth_version 机制 |
| 插件即 include | `plugins.php` | 无沙箱/签名，上传插件=执行代码（管理员面） |

### C. 与行业领先项目差距（竞品调研 2026-08-12 实测）

| 差距 | 参考竞品 | 状态 |
|------|----------|------|
| 多渠道通知（Bark/Server酱/Telegram/PushPlus/钉钉/企微） | Sitoi/dailycheckin(8.7k★)、qd(5.6k★)、龙青(19.8k★) | 本轮已补 Server酱/Telegram/Webhook/邮件，Bark/PushPlus 待加 |
| 贴吧验证码自动识别/打码 | Aruelius/tieba_sign(167★) 唯一带打码 | 缺失（ddddocr / 打码平台 API） |
| 可视化任务中心（手动/暂停/重试/历史） | 龙青、qd、n8n(200k★) | 有雏形（admin:cron + 日志页），无重试按钮/暂停粒度 |
| 签到统计报表/趋势图 | 龙青、ECharts 生态 | 缺失 |
| 风控韧性（UA 池/随机时间/退避） | dailycheckin | UA 池已有(SignManager)，随机时间/退避缺失 |
| 开放 API + Token | qd、healthchecks(10.2k★) | 缺失 |
| GitHub Actions 零成本托管 | LuoSue/TiebaSignIn-1(273★) | 缺失（可作为轻量变体） |
| Serverless cron | 同上 + CF Workers | 缺失 |

### D. 用户体验差距

1. 移动端仍有 Bootstrap 3 痕迹（表格溢出、导航密度高），部分页面（设置中心）长表单无分组折叠。
2. 无加载/空态/错误态统一组件；AJAX 返回 HTML 片段直接插入（XSS 面与体验都不佳）。
3. 首次使用引导弱：BDUSS 获取教程在 Wiki，站内无引导卡片。
4. 通知/报表缺失导致“签没签、挂没挂”要主动进后台看。

### E. 技术架构差距

1. 无 Composer/PSR-4/命名空间/PDO，字符串拼接 SQL + 手工转义贯穿全库（SQL 注入面收敛成本高）。
2. 无单元/集成测试，CI 仅 `php -l`；无 PHPStan/Psalm。
3. cron 每分钟全表扫描 + `fsockopen` 伪异步；`SignManager::log` 每次执行 DDL（`CREATE TABLE IF NOT EXISTS` 每 cron 跑一次）。
4. `options` KV 表无类型/无 updated_at；`users` 无 created_at/last_login/auth_version。
5. 前段无构建工具、无组件化、无包管理。

### F. 商业价值差距

1. 上游明确“无商业版”，本项目定位是自托管工具，无变现路径；真正的护城河应转向“个人账号自动化平台”（签到只是第一个插件）。
2. 合规风险：贴吧接口逆向 + BDUSS 托管，是灰色地带，不能作为商业承诺；可借鉴 qd 的 HAR 驱动模式降低逆向依赖。
3. 社区运营缺失：无官方演示站、无插件市场、无赞助/捐赠入口（GitHub Sponsors）。

### 优先级排序

- ★★★★★ 必须升级：通知闭环（已做）、登录限流+审计（已做）、出站 TLS/HTTPS（已做）、异常脱敏（已做）、复签 bug（已做）、MyISAM→InnoDB（脚本已提供，待执行）
- ★★★★ 强烈建议：BDUSS 加密存储、`latest` 改 DATE、密码 rehash 全覆盖（已做）、CSRF 收紧（随机盐+去 Referer 回退）、任务中心补齐重试/暂停
- ★★★ 可以优化：统计报表(ECharts)、打码接入(ddddocr)、开放 API、GitHub Actions 变体、移动端表格卡片化
- ★★ 暂不考虑：多平台扩展（B站/微博等）、插件沙箱、队列化、前端框架重写

---

## 二《项目2.0升级路线图》

### 阶段 1：快速提升（1-7 天） —— 已完成 ✅

| 功能名称 | 升级原因 | 参考案例 | 技术方案 | 难度 | 价值 |
|----------|----------|----------|----------|------|------|
| 通知中心 | 失败静默→主动告警 | dailycheckin/qd/龙青 | `class.notify.php` + 设置页 + cron 钩子 | 低 | ★★★★★ |
| 登录限流+审计 | 防撞库、可溯源 | healthchecks | `class.loginguard.php` + 审计表 | 低 | ★★★★★ |
| 出站 TLS/HTTPS | BDUSS 防截获 | 行业基线 | wcurl 证书校验 + http→https | 低 | ★★★★★ |
| 异常脱敏 | 防信息泄露 | 通用安全基线 | E::display 门控 + error_log | 低 | ★★★★ |
| 复签 bug 修复 | 重试功能实际生效 | 上游 bug 修复 | `date('d')` 日号对齐 | 低 | ★★★★ |
| 密码自动 rehash | 弱哈希下线 | OWASP | login 钩子 + password_needs_rehash | 低 | ★★★★ |
| InnoDB 迁移脚本 | 行锁/崩溃恢复 | MariaDB 官方 | update5.05to5.06.php | 低 | ★★★★ |

### 阶段 2：核心升级（7-30 天）

| 功能名称 | 升级原因 | 参考案例 | 技术方案 | 难度 | 预计价值 |
|----------|----------|----------|----------|------|----------|
| 任务中心补齐（手动重试/暂停/调度表达式） | 管理员需要掌控执行 | 龙青、xxl-job、Cronicle | cron 表加 enable/nextrun；admin:cron 页加重试/暂停按钮 | 中 | ★★★★★ |
| BDUSS/STOKEN 加密落库 | DB 泄露即账号泄露 | 通用密钥管理 | openssl_encrypt + 独立密钥文件(SYSTEM_SALT/新密钥) | 中 | ★★★★★ |
| `tieba.latest` 改 DATE | 月初漏签 bug | 数据模型修正 | 迁移脚本 + 查询统一 | 中 | ★★★★ |
| 打码接入（ddddocr sidecar 或打码平台） | 贴吧验证码刚需 | Aruelius/tieba_sign | ddddocr-fastapi 容器 + `misc::getVerifyCode` 对接 | 中 | ★★★★ |
| CSRF 收紧（安装时生成随机盐 + 写操作强制 Token） | 当前盐可为空/Referer 回退 | OWASP CSRF | install.php 生成 64 位 hex；csrf() 去回退 | 中 | ★★★★ |
| 静态资源合并与首屏优化 | 减少请求数 | Lighthouse 基线 | 合并/精简 css/js，移除空引用 | 低 | ★★★ |
| 签到统计报表（7日/30日趋势+成功率） | 用户价值可视化 | 龙青/ECharts | cron_log_batch 聚合 + ECharts CDN | 中 | ★★★ |

### 阶段 3：未来规划（1-6 个月）

| 功能名称 | 升级原因 | 参考案例 | 技术方案 | 难度 | 预计价值 |
|----------|----------|----------|----------|------|----------|
| 开放 API + Token | 生态接入（龙青/n8n/HA） | qd、healthchecks | 独立 `api.php` + Bearer Token + 限流 | 中 | ★★★★ |
| 插件市场与签名 | 生态良性发展 | WordPress | manifest + minisign 签名 + 仓库索引 | 高 | ★★★★ |
| GitHub Actions/Serverless 变体 | 零成本个人签到 | TiebaSignIn-1 | 抽取签到核心为独立 CLI 脚本 + workflow | 中 | ★★★ |
| 多平台适配层 | 从“贴吧工具”变“账号自动化平台” | dailycheckin | 抽象 Task 接口，签到为第一个 Task | 高 | ★★★★ |
| 队列化调度（Redis） | 替代 fsockopen 伪异步 | PowerJob/gocron | 简单队列 worker 或旁路容器 | 高 | ★★★ |
| 前端渐进重写（Vue3 + Vite，服务端模板渐进替换） | 组件化与可维护 | CoreUI/AdminLTE | 先做控制台/看板页，再逐步迁移 | 高 | ★★★ |

---

## 三、AI 增强方案

| AI 能力 | 为什么加入 | 如何实现 | 用什么模型/方案 | 用户价值 |
|---------|-----------|----------|-----------------|----------|
| 验证码自动识别 | 贴吧签到最痛点：验证码卡住自动流程 | 旁路 `ddddocr-fastapi` 容器，PHP 通过 HTTP 调用；或接入超级鹰/图鉴/若快 API | ddddocr(14.6k★, ONNX 本地推理) / dddd_trainer 微调 | 全自动签到率显著提升 |
| 失败原因智能分类 | 错误码(340011/110001等)含义对用户不透明 | 后台 cron 汇总 `cron_log_batch` 后，LLM 分类：风控/封禁/网络/参数 | 轻量 LLM（Ollama/Qwen 或 OpenAI 兼容 API） | 用户秒懂“为什么失败、怎么办” |
| 账号风险预警 | BDUSS 失效/风控前兆（异常频率） | 健康度表 + 失败序列分析（规则+可选 ML） | 规则引擎优先，LLM 辅助解释 | 提前更换 BDUSS，避免连续失败 |
| 签到排程优化 | 贴吧活跃时段不同，固定时间易触发风控 | 按历史成功率学习每吧最优时段，随机化执行 | 简单统计学习即可，无需 LLM | 降低风控概率 |
| 自然语言运营指令（远期） | “帮我给某吧发个早安帖” 式交互 | Agent 层调用现有插件钩子（灌水/点赞） | 函数调用型 LLM（Qwen/GLM/OpenAI） | 运营成本下降 |
| AI 日报/周报 | 不想天天看后台 | 汇总签到+插件数据生成简报，经通知中心推送 | LLM 摘要 + Notify 渠道 | 零门槛掌握全局 |

**落地顺序建议**：打码（ddddocr）→ 失败分类（LLM）→ 风险预警 → 排程优化；全部以 sidecar/API 形式接入，不侵入签到主链路，失败不影响核心功能。

---

## 四、本轮执行清单（V5.06）

| 类别 | 变更 |
|------|------|
| 新增 | `lib/class.notify.php`、`lib/class.loginguard.php`、`setup/update5.05to5.06.php` |
| 修改 | `init.php`(V5.06)、`lib/globals.php`(限流/审计/rehash)、3 个 cron 任务(日志+通知)、`lib/class.SignManager.php`(signSummary)、`lib/class.misc.php`(https+retry 修复)、`lib/class.wcurl.php`(TLS)、`lib/class.E.php`+`class.mysqli.php`(脱敏)、`lib/sfc.functions.php`(https)、`lib/ui.php`(资源版本化)、`setting.php`(保存+测试通知)、`templates/admin-set.php`(通知面板)、`templates/admin-tools.php`(登录审计)、`setup/install.template.sql`(5.06+审计表+通知默认值+wmzz_debug 关闭)、`CHANGELOG.md` |
| 验证 | 113 个 PHP 文件 `php -l` 全部通过；`docker compose config` 未改动容器结构 |

**升级操作（存量部署）**：访问 `http://<站点>/setup/update5.05to5.06.php` 执行一次（自动转 InnoDB、建审计表、写通知默认配置、删除自身）；随后到“设置中心”配置通知渠道。
