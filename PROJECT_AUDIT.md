# 项目现状审计

审计日期：2026-11-16
审计对象：`main` 分支，版本 `V7.8.0`
审计方式：源代码、迁移、配置、测试、发布脚本和已有报告交叉检查；没有把 README 的宣传文字当作实现证据。

## 1. 产品定位与运行边界

贴吧云签到是一个自托管 PHP Web 应用。用户在浏览器中绑定百度账号、同步关注贴吧，系统由计划任务或 `do.php --loop` 负责签到、失败复签、健康检查、通知、统计和插件任务。运行时依赖 PHP 8.2+、mysqli/cURL/OpenSSL/Session/ZIP 等扩展，以及 MySQL/MariaDB；官方部署形态是 Docker Compose 或传统 PHP 站点。

项目只维护网页版，不提供桌面或移动客户端。数据库表使用配置前缀，敏感选项由 `lib/class.SecretBox.php` 加密落库。在线更新位于 `lib/class.updater.php`，发布包只允许 Web 部署包，并校验 SHA-256；数字签名是可选的生产强化配置。

## 2. 实际能力清单

| 模块 | 已验证的实现 | 主要证据 |
|---|---|---|
| 认证与账号 | 密码登录、登录审计、登录失败守卫、TOTP 2FA、GitHub OAuth、邀请码注册 | `lib/globals.php`、`lib/class.loginguard.php`、`lib/class.totp.php`、`lib/class.OAuth.php`、`tests/OAuthLoginTest.php` |
| 签到引擎 | 多种签到方式、客户端轮换、账号代理、签到历史、失败复签、贴吧健康统计 | `lib/class.misc.php`、`lib/class.SignManager.php`、`lib/cron_system_sign*.php`、`tests/SignRetrySmokeTest.php` |
| 调度 | cron/CLI/Web 触发、常驻循环、到期判断、失败退避、任务级运行锁、运行历史、失败告警去重 | `do.php`、`lib/class.cron.php`、`templates/admin-cron.php`、`tests/CronSchedulerTest.php` |
| 数据与运维 | 备份/恢复演练、迁移器、保留策略、健康探针、Prometheus 指标 | `lib/cron_system_dbbackup.php`、`lib/class.migrator.php`、`lib/cron_system_cleanup.php`、`healthz.php`、`metrics.php` |
| 通知 | Server 酱、Telegram、Webhook、Bark、PushPlus、邮件及多个国内渠道；接受/失败状态分开 | `lib/class.notify.php`、`lib/class.webhook.php`、`tests/NotificationDeliveryTest.php` |
| API | Bearer 全局/个人 Token、权限矩阵、稳定错误码、OpenAPI 输出、限流、导出和统计 | `api.php`、`lib/api-contract.php`、`tests/ApiContractTest.php` |
| 插件 | 插件安装/启停、兼容性检查、发帖/回帖/点赞/监控/QQ 等插件 | `lib/plugins.php`、`plugins/`、`plugins/README.md` |
| 发布 | GitHub Release 检查、可信摘要校验、保护配置、数据库备份、迁移、回滚、Web 包门禁 | `lib/class.updater.php`、`docs/AUTO_UPDATE.md`、`tools/release-gate.php` |

## 3. 架构概览

```text
浏览器 / API / do.php
        │
        ├─ init.php → config.php → wmysql(mysqli)
        ├─ globals.php / setting.php / ajax.php / api.php
        ├─ lib/class.cron.php → cron_* 任务 → SignManager / Notify / 插件
        ├─ lib/class.migrator.php → setup/migrations/*.php
        └─ templates + source/css + source/js
```

应用是传统 PHP 请求模型，没有 Composer 运行时框架。核心扩展点是 cron 文件约定、插件目录约定、`doAction` 钩子和 API 合同矩阵。该结构适合单机或少量节点自托管，暂不适合未经重新设计就引入分布式队列。

## 4. 本轮发现的具体问题与处理

### 已修复

1. **计划任务锁缺少所有者语义。** 旧流程只有 `running/running_at`，过期后被新进程接管时，旧进程的 `finally` 可能清掉新锁。本轮增加 `running_owner`、当前进程令牌、`renewLock()` 和所有者条件释放；迁移为 `007600_cron_lease_owner.php`。
2. **API 限流存在并发超发窗口。** 旧流程先 SELECT 再 INSERT/UPDATE，多个并发请求可同时通过检查。本轮改为带上限的原子 UPDATE + 唯一键 UPSERT，达到上限返回 429 并附 `Retry-After: 60`；存储失败 fail closed 为 503。
3. **任务最新运行记录使用字符串拼接。** `cron::latestRun()` 改为参数绑定，并加入带引号输入回归测试。
4. **默认 Web 响应缺少统一安全头。** `init.php` 增加 `X-Content-Type-Options`、`X-Frame-Options`、`Referrer-Policy`、`Permissions-Policy`；`healthz.php` 增加无引用者策略和 `nosniff`。

### 仍然存在

- `dataBak()` 仍把整张表拼成一个 PHP 字符串，大库备份的内存上限没有基准证明。
- 多个历史插件/工具仍触发 lint 的“建议参数化”或命令执行人工复核警告；它们不等同于已确认漏洞，但应继续清理。
- 任务租约现在能防止旧进程误释放新锁，并提供主动续租 API；核心任务执行器尚未自动插入周期续租，也没有 exactly-once 外部副作用保证。
- `request_is_https()` 仍接受任意 `X-Forwarded-Proto`，可信反向代理 CIDR 尚未配置化。
- 真实百度账号、外部代理、生产通知、旧客户端在线升级、长时间运行和 Docker/多 PHP 版本矩阵未在本机完成。

## 5. 基线验证

本轮实际环境：PHP 8.3.36、Composer 2.11.2、PHPUnit 11.5.56。数据库集成变量未配置，Docker 引擎未作为本轮证据使用。

| 检查 | 结果 |
|---|---|
| `php vendor/bin/phpunit` | PASS：204 tests，0 failures，3 skipped（完整单元集，含本轮新增测试） |
| `php tools/lint.php` | PASS：238 个 PHP 文件；10 条历史人工复核警告 |
| `php tools/secret-scan.php` | PASS：扫描 368 个 Git 追踪文件，未发现疑似 Secret |
| Composer platform requirements | BLOCKED/FAIL：当前 CLI PHP 8.3.36 未加载 `ext-mysqli` |
| `php tools/release-gate.php` | PASS：6 项通过、数据库集成 1 项跳过、0 失败 |
| `git diff --check` | PASS |
| MariaDB 迁移/调度/API/备份/通知集成 | BLOCKED：`TCS_IT_*` 未配置或服务未启动；CLI 同时缺少 `ext-mysqli` |
| Docker 构建、多 PHP 版本、真实百度链路 | BLOCKED/PARTIAL：本机没有本轮可用的生产实例或 Docker 服务 |

## 6. 成熟度判断

当前项目适合自托管部署、代码审查和发版准备：核心业务、运维工具、插件和测试门禁完整，已有多轮可靠性加固。它还不能被本地证据描述为生产级或 exactly-once 系统，主要上限来自外部百度协议、长时间任务租约、大库备份、真实升级安装和多环境矩阵。

## 7. 后续优先级

1. 把任务续租接入签到批处理边界，并补 MariaDB 并发演练。
2. 将数据库备份改为流式写出，补大数据量内存和恢复校验。
3. 配置可信反向代理边界，补 HTTPS/session cookie 浏览器验收。
4. 对历史插件做逐文件参数化和网络目标审计。
5. 建立旧版本客户端 → 签名 Release → 迁移 → 回滚的隔离 E2E 演练。
