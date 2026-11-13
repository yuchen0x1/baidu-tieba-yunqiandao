# 架构边界：开源版与官方服务版（Architecture Boundary）

> 状态：随 7.1.x 引入。本文是「开源实现」与「官方服务能力」的权威分界说明。
>
> 核心原则：**开源的是实现基础，不是官方服务能力。**
> 源码可以复制、审计、Fork、自建；但官方 AI 配额、Provider 凭据、License 授权、
> 配额风控等能力只存在于官方基础设施，无法凭源码复制。

## 1. 能力三分类（A 可开源 / B 实例服务端 / C 官方服务端）

### A. 可开源（随仓库分发，允许任何人运行）

| 范围 | 内容 |
|---|---|
| UI | `templates/`、`source/`、`lib/ui.php`、`lib/forum_picker.php` |
| 业务框架 | `init.php`、`lib/`（除明确标注外）、入口 `index.php/ajax.php/setting.php/api.php/do.php` |
| 插件 | `plugins/`（ver4_*、weltolk_*、wmzz_*），含 AI 插件的直连模式 |
| 数据结构 | `setup/`（Schema 模板与迁移）、`lib/api-contract.php` 合同 |
| 开发运维工具 | `tools/`（含 `secret-scan.php`、`official-keygen.php`）、`docker/`、CI 配置 |
| 官方服务协议与参考实现 | `lib/official-config.php`、`lib/class.official.php`（客户端 SDK）、`official-service/`（**零密钥**参考网关） |

### B. 实例服务端（每个自建站点的运营者自有数据与凭据）

本项目的「服务端」就是每一个部署实例。以下内容**早已全部在服务端**，任何一项
都不应下发到浏览器/客户端 JS/公开接口：

| 数据/凭据 | 存储位置 | 保护措施 |
|---|---|---|
| 百度 BDUSS、账号密码 | `tc_baiduid` 等表 | AES-256-GCM 加密（`P::encrypt`，密钥 `SYSTEM_SALT` 或自动生成 `crypto_key`） |
| AI 直连 Provider Key | `tc_options` / `users_options` | 同上密文存储；页面只写不回显；`testconn` 仅在服务端使用 |
| SMTP / OAuth / 打码平台凭据 | `tc_options` | 同上；OAuth client_secret 永不出服务端 |
| 数据库凭据 | `config.php` / env | `config.php` 不入 Git；生产目录权限隔离 |
| API Token（开放 API） | `tc_users.api_token_hash` | 仅存哈希；逐用户隔离；按 Token 限流（`api_rate_limits`） |
| 权限判断 | 服务端 `ROLE` 门控 | `admin:*` 页面/接口在服务端判定，前端只是展示 |

### C. 官方服务端（必须运行在官方私有基础设施，Fork 者天然得不到）

| 能力 | 落点 | Fork 后 |
|---|---|---|
| AI Provider Key 与模型路由 | 官方网关 env / Secret Manager | 得不到；自建版自带 Key（直连模式） |
| 每实例配额、限流、风控 | 网关 `gw_usage/gw_rate` + 扩展点 | 自建版只有自己的配额 |
| License 签发私钥 | 官方授权服务器（`GW_LICENSE_PRIVATE_KEY[_FILE]`） | 得不到；客户端只持公钥验签 |
| 实例注册/吊销 | 官方控制台/网关管理端点 | 自建版不受官方管辖 |
| 官方数据与商业规则 | 官方数据库 | 得不到 |

## 2. 请求链路（已实现）

```text
直连模式（社区版默认，零外部依赖）：
  实例 PHP ──Bearer 自带Key──▶ Provider API            （实例持有 Key，密文存库）

官方模式（接入官方服务后）：
  实例 PHP ──HMAC 签名(timestamp/nonce/body)──▶ 官方网关
                 ├── 实例认证 + 签名/防重放校验
                 ├── Bearer 短期 Token（sub/iat/exp/scope/jti）
                 ├── 配额 / 每分钟限流
                 └── 服务端模型路由 ──▶ Provider（Key 永不出网关）

License（可选商业授权）：
  官方授权服务器 ──RSA-2048 签名──▶ License {license_id, plan, features, expires_at, ...}
  实例持公钥（lib/official-config.php）本地验签；私钥绝不进 Git/实例/镜像/发布包
```

协议细节见 `official-service/README.md`；签名/Token/License 的纯函数覆盖见
`tests/OfficialGatewayTest.php`（含防篡改、防过期、跨实例拒绝）。

## 3. 密钥与 Secret 的存放铁律

| 密钥 | 允许位置 | 禁止位置 |
|---|---|---|
| License 签发私钥 | 官方服务器 Secret Manager | Git、实例、客户端、Docker 镜像、发布包 |
| Provider API Key（官方池） | 官方网关 Secret Manager | Git、实例、浏览器、日志 |
| 实例 instance_secret | 实例 `tc_options`（密文） | Git、前端、日志 |
| BDUSS / SMTP / OAuth Secret | 实例数据库（密文） | Git、前端、日志 |
| 验签公钥 | 开源仓库（`official-config.php`） | —（公钥可以公开） |

- CI/CD：所有工作流只用平台 Secret（`${{ secrets.* }}`、内置 token），无 YAML 硬编码；
  Fork PR 不接触任何生产 Secret（CI 仅跑 lint/test/scan）。
- `tools/secret-scan.php` 已接入 `composer check`、`composer gate` 与 CI，
  高信号凭据模式命中即失败；误报用行尾 `secret-scan:allow` 显式豁免（当前仅 RFC 6238 测试向量一处）。
- `.gitignore` 已排除 `official-service/keys/`、`official-service/data/`、`*.pem`、`*.key`。

## 4. 明确不做的（防止伪安全）

- 不依赖代码混淆、Base64、字符串隐藏、客户端布尔开关做安全边界。
- 不自造密码学：只使用 HMAC-SHA256、AES-256-GCM、RSA-2048/SHA-256 标准原语。
- 不在客户端做授权「判断」：客户端只保存/展示，授权结果由服务端最终裁决。
- 不破坏 Fork 后的社区版：未接入官方服务时，所有开源功能完整可用（默认 `direct` 模式零行为变化）。

## 5. 尚需真实基础设施才能落地的部分（诚实清单）

以下能力本仓库只有**接口与参考实现**，真正运行需要官方基础设施投入：

1. **官方网关生产部署**：HTTPS 终结、多副本共享存储（nonce/配额迁 Redis）、
   Secret Manager 注入 Provider Key（当前参考实现从 env 读取，仅适用于开发）。
2. **实例控制台**：注册实例、签发/吊销凭据、License 订单与计费联动（当前用管理端点手工完成）。
3. **风控与滥用检测**：异常调用画像、降级/暂停/重新验证流水线（参考实现仅有限流/配额）。
4. **多环境密钥隔离**：development/staging/production 各自独立的 Provider 池、Token、数据库。
5. **Release 产物签名**：发布包二进制签名与校验链（当前仅有 SHA256 校验）。
