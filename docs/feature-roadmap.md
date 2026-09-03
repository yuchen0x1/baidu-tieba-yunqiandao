# V6.1 功能路线图

> 目标：从"单一云签到"扩展为贴吧账号综合管理工具。
> 2026-09-04 修订：Phase 1（百度站内消息拉取）经实测被百度接口封锁，调整为暂缓；开发顺序改为 3 → 2 → 1 → 4。

## Phase 1（暂缓）：贴吧消息中心 —— 数据源被百度封锁

- 基础设施已落地并保留：消息页（`index.php?mod=messages`）、`tc_messages` 表、`MessageCenter` 类、`system_message_pull` 计划任务（当前 enable=0）
- 实测结论（2026-09-04，数据中心 IP + 纯 BDUSS）：
  - `c/s/sync` 可用但现代版本仅返回客户端配置，无消息计数/条目
  - `c/u/feed/replyme`、`c/u/feed/atme`、`c/u/feed/sync` 全部要求登录（needlogin=1），带 STOKEN（cookie/参数）无效，`feed/sync` 返回拦截 HTML
  - web 端 `tbnotice/getnotice`、`hg/allreplylist`、`mo/q/newmsgcount` 均返回拦截 HTML
  - `c/s/login` 用 BDUSS 换 stoken 被拒（error_code=2）
- 解除条件：获得可用的消息数据源（如新版客户端协议签名、OAuth 体系或抓包出的新端点）
- 注意：消息拉取计划任务当前禁用，重新启用后需将 `tc_cron.system_message_pull.enable` 置 1

## Phase 2：关注/粉丝管理

- 数据源：关注/粉丝列表接口（BDUSS）——与 Phase 1 同类风险，开发前需先探针验证可用性
- 功能：列表展示（头像、吧龄、等级）、搜索、批量取关/特别关注
- 存储：`tc_baidu_friends`（uid, pid, type[follow/fan], fid, info json, updated_at）
- 风控：分页间隔与请求限速复用 `sign_sleep`/客户端指纹轮换

## Phase 3（当前开发）：签到数据深度分析

- 基于 `tc_sign_history` + `tc_baiduid_health` 现有数据，无需新表、无百度接口依赖（不受 Phase 1 影响）
- 页面：账号分析页（等级/成长值变化、成功率趋势、吧维度对比、失败原因 TOP）
- 图表：复用 V6.0 `CS.chart`（统计页已用）

## Phase 4：第三方登录

- GitHub OAuth：无需企业资质，可先行落地（`tc_users_oauth`：uid, provider, openid, bound_at）
- QQ 互联 / 微信：需用户自行申请开发者资质后接入，预留 provider 抽象层
- 绑定流程：已有账号绑定 + 新用户自动注册（需开启注册开关）
