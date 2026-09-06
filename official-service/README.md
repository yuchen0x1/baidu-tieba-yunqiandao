# Official Service Gateway（参考实现）

> **边界声明**：本目录是「官方服务网关」的**参考实现**，属于协议样例与开发脚手架。
> 官方生产部署（含真实 Provider Key、License 签发私钥、实例数据库、风控规则）
> 必须放在**私有仓库与私有基础设施**中，遵循 `docs/architecture-boundary.md` 的分离原则。
> 本目录可以公开，因为其中**不含、也不允许含**任何密钥——所有凭据来自环境变量。

## 它解决什么问题

开源仓库可以被完整复制，但下列能力只存在于运行中的官方基础设施：

| 能力 | 落点 | Fork 后能否获得 |
|---|---|---|
| AI Provider Key | 网关侧 env / Secret Manager | 否 |
| 模型路由策略 | 网关侧 `PROVIDER_MODEL` / `GW_ALLOW_CLIENT_MODEL` | 否（官方路由） |
| 每实例配额 / 限流 | 网关 `gw_usage` / `gw_rate` | 自建版只有自己的 |
| License 签发私钥 | `GW_LICENSE_PRIVATE_KEY`（绝不入库） | 否 |
| 官方风控 / 滥用检测 | 网关扩展点 | 否 |

Fork 者得到的是**社区版**：完全自托管（自带 Provider Key 或不使用 AI）。
只有接入官方网关的实例，才获得官方 AI 配额、官方模型与商业授权。

## 协议摘要（与开源侧 `lib/class.official.php` 对齐）

- **实例认证**：`instance_id` + `instance_secret`（官方控制台签发，实例侧加密存库）。
- **请求签名（防重放/防篡改）**：
  `string_to_sign = timestamp \n nonce \n path \n sha256_hex(body)`，
  签名 = `HMAC-SHA256(instance_secret, string_to_sign)`，hex 编码。
  时间窗 ±300 秒，nonce 一次性（按实例隔离）。
- **Access Token**：`TCS1.<b64url(payload)>.<b64url(hmac)>`，
  payload 含 `sub/iat/exp/scope/jti`，签名密钥 = `sha256("tcs-official-token-v1:" + instance_secret)`。
  短 TTL（默认 600 秒），客户端到期前 30 秒自动刷新。
- **License**：payload 为规范化 JSON，`RSA-2048 + SHA-256` 签名。
  公钥随开源仓库分发（`lib/official-config.php`），私钥只在官方授权服务器。

## 环境变量

| 变量 | 必填 | 说明 |
|---|---|---|
| `GW_ADMIN_TOKEN` | 管理端点必填 | 运维令牌；未设置时管理端点直接 503 |
| `GW_TOKEN_TTL` | 否 | Access Token 有效期秒数（默认 600） |
| `GW_LICENSE_PRIVATE_KEY` | License 签发必填 | PEM 私钥（推荐用 `_FILE` 变体挂载文件） |
| `GW_LICENSE_PRIVATE_KEY_FILE` | 否 | 从文件读私钥（Secret Manager 挂载场景） |
| `PROVIDER_BASE_URL` | AI 代理必填 | OpenAI 兼容上游基址 |
| `PROVIDER_API_KEY` | AI 代理必填 | 上游 Key。**生产必须用 Secret Manager 注入** |
| `PROVIDER_MODEL` | AI 代理必填 | 服务端路由的默认模型 |
| `GW_ALLOW_CLIENT_MODEL` | 否 | `1` 才允许客户端指定模型（默认锁死服务端路由） |
| `GW_DAILY_QUOTA` | 否 | 每实例每日调用上限（默认 200，可按实例覆盖） |
| `GW_RATE_PER_MIN` | 否 | 每实例每分钟上限（默认 10） |
| `GW_DATA_DIR` | 否 | SQLite 数据目录（默认 `./data`，已 gitignore） |

## 快速验证（本机）

```bash
# 1. 初始化存储
GW_DATA_DIR=/tmp/gw-data php index.php initdb

# 2. 启动（本机测试可用 http；生产必须置于 HTTPS 反代之后）
GW_ADMIN_TOKEN=dev-admin \
PROVIDER_BASE_URL=https://open.bigmodel.cn/api/paas/v4 \
PROVIDER_API_KEY=$MY_PROVIDER_KEY \
PROVIDER_MODEL=glm-5.3-flash \
php -S 127.0.0.1:9000 index.php

# 3. 注册实例（返回一次性 secret）
curl -s -X POST http://127.0.0.1:9000/v1/admin/instance \
  -H "Authorization: Bearer dev-admin" \
  -d '{"instance_id":"inst-demo"}'

# 4. 签发 License（需先完成 tools/official-keygen.php 的密钥对部署）
curl -s -X POST http://127.0.0.1:9000/v1/license/issue \
  -H "Authorization: Bearer dev-admin" \
  -d '{"license_id":"L-001","plan":"pro","features":["ai:shared"],"expires_at":1798761600}'
```

实例侧接入：贴吧云签到后台 → AI 插件设置 → 「官方 AI 服务」，填网关地址与实例凭据。

## 生产化清单（超出本参考实现的部分）

- [ ] HTTPS 终结（Nginx/Caddy 反代 + 证书），网关本身不监听公网明文端口
- [ ] Provider Key 与签发私钥改由 Secret Manager（Vault/KMS/云密钥）注入，禁用进程 env 明文
- [ ] SQLite → 独立数据库；nonce/配额表迁移到 Redis 等共享存储以支持多副本
- [ ] 风控扩展点：异常调用模式告警 → 限流/降级/暂停 → 重新验证（不执行破坏性反制）
- [ ] 迁入私有仓库 + 独立 CI/CD；Fork PR 永远接触不到生产 Secret
- [ ] Release 产物签名与 Artifact 校验
