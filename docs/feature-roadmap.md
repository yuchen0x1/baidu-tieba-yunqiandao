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

## Phase 3（已完成，2026-09-04）：签到数据深度分析

- 基于 `tc_sign_history` + `tc_baiduid_health` 现有数据，无新表、无百度接口依赖（不受 Phase 1 影响）
- 页面：`index.php?mod=analysis`（导航「数据分析」），支持 7/30/90/365 天区间切换
- 内容：概览指标卡（总量/成功失败/成功率/活跃贴吧/连签）、成功率趋势图（柱线双轴）、吧维度对比（Top15 横向堆叠图 + 明细表）、失败原因 TOP（图 + 占比表）、签到时段分布（24 小时聚合）、账号对比与 BDUSS 健康状态表
- 查询层：`SignManager` 新增 `overview` / `hourDistribution` / `accountCompare` / `accountHealth`，`failureBreakdown` 支持自定义天数
- 附带修复 V6.0 遗留缺陷：`components.js` 为 defer 加载，此前所有页面（工作台/日历/排行/统计）的 `CS.chart` 图表初始化时 `CS` 尚不存在导致图表从不渲染；统一改为 DOMContentLoaded 后初始化，浏览器实测全部恢复
- 说明：路线图原列的「等级/成长值变化」本地无数据源（百度接口封锁期间无法获取），暂不实现，待数据源可用后再评估

## Phase 4：第三方登录

- GitHub OAuth：无需企业资质，可先行落地（`tc_users_oauth`：uid, provider, openid, bound_at）
- QQ 互联 / 微信：需用户自行申请开发者资质后接入，预留 provider 抽象层
- 绑定流程：已有账号绑定 + 新用户自动注册（需开启注册开关）
