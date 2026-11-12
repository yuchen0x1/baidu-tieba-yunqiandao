# 下一轮升级计划（按优先级排序）

## P1（高优先级，下一轮立即执行）

### 1. 签到行级 claim 防并发重复签到

**状态（2026-11-12）**：已在工作树实现 `007500_sign_claim.php`、安装模板和 `misc::DoSign()`/`DoSign_retry()` 原子领取；仍需隔离 MariaDB 做真实并发演练。

**问题**：`misc::DoSign()` 是"查未签 → 逐条签"，无行级领取；任务锁只保护 `system_sign` 任务，手动"立即签到"走 `ajax.php:238` 直接调 `DoSign()` 完全绕过。`do.php` 的 `sign_multith` 起 N 个内部 HTTP 进程会放大问题，`SYSTEM_CRON_THREAD` 常量全仓无使用——这套"模拟多线程"未闭环。

**已实现**：`UPDATE <table> SET claim_owner=?, claimed_at=? WHERE id=? AND (claim_owner IS NULL OR claim_owner='' OR claimed_at IS NULL OR claimed_at < ?)`，影响行数为 1 才执行；成功/失败释放，异常由 15 分钟 TTL 接管。

**风险**：需改 SQL 领取语义、多进程协调，回归测试复杂。需在隔离 DB 中充分测试。

### 2. DB 集成测试验证迁移

**任务**：在隔离 MariaDB 中执行 `composer integration`，验证迁移 007300~007303 的 up/down/幂等。

**方案**：设置 `TCS_IT_DB_*` 环境变量后运行 `composer integration`；或用 Docker 起 MariaDB 11.4 服务。

## P2（中优先级）

### 3. 插件元数据 N+1 缓存

**问题**：`lib/plugins.php:628` 在 `getPluginInfo()` 里每个插件查一次 `SELECT order`；`lib/plugins.php:684-690` 启动时对每个激活插件再 `include _desc.php` + 查询。16 个插件 = 每请求 16+ 次额外查询。

**方案**：`reg.php:89-97` 已一次性查出 `plugins` 全表，直接把 `order/status/options` 放进 `$i['plugins']['info']`；`getPluginInfo()` 改纯数组组装；`desc` 结果按 `plugin + mtime` 缓存。

### 4. DDL 收敛（消除每请求 DDL）

**问题**：`SignManager::ensureHistoryTable()` 在 12 个方法入口调用 `CREATE TABLE IF NOT EXISTS`；`cron::ensureTables()`、`Notice::ensureTable()`、`OAuth::ensureTable()` 等同理。每请求至少 1-3 次 DDL。

**方案**：新建 `lib/schema.php` 的 `Schema::ensure($key, callable)`，用 `option('schema_ready:<version>')` 做请求间缓存；生产环境默认关闭运行期 DDL。

### 5. 分析查询优化

**问题**：`lib/class.SignManager.php:939-960` 的 `tiebaHealth()` 对 `last_msg/last_exp/last_rank/last_level` 各写一个相关子查询，200 吧时 800 次索引查找。

**方案**：改为 `MAX(id)` join 或 MariaDB 窗口函数 `ROW_NUMBER()`。需基准测试验证。

### 6. option::set 缓存转义不一致

**状态（2026-11-12）**：已修复并新增 `SecurityHardeningTest::testOptionSetUsesRawValuesForDatabaseAndRuntimeCache`。

**问题**：`option::set()` 用 `sqladds()` 转义后既写 SQL 又写内存缓存，含引号的值在同请求内读回是转义后形态。

**后续**：继续收敛 `option::add/uset/uadd` 等旧式转义 API，并在 DB 集成环境补全链路测试。

### 7. Docker 冒烟 + 浏览器 UI 回归

**任务**：Docker daemon 可用后执行容器构建与 Caddy 404 验证；安装 Playwright 做端到端页面验证。

## P3（低优先级，产品/设计决策）

### 8. Bootstrap 3 → 5 迁移
分批迁移（每批 4-5 个模板），先做 `bs5-compat.css`，逐页截图回归。

### 9. 内联样式/事件处理器清理
365 处 `style=` + 51 处 `onclick`，使 CSP 无法启用。按页面分批迁移。

### 10. i18n 完善或删除
`I18N` + `lang/zh-CN.php` 是半成品（模板仅 6 处使用）。要么补全导航/登录/设置页并加 `en-US`，要么删除避免误导。

### 11. 数据模型细节
- `users.email VARCHAR(40)` → `VARCHAR(191)`
- `users.auth_version` 死列（全仓无读取）：删除或用于改密踢下线
- `cron` 表 `no`/`enable` 语义重叠：文档固化优先级，UI 只暴露一个开关
- `baiduid(uid, portrait)` 无唯一约束：去重后加唯一索引
