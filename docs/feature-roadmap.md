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

## Phase 2（暂缓）：关注/粉丝管理 —— 数据源被百度封锁

- 探针结论（2026-09-04，本机住宅 IP + 有效 BDUSS，只读测试）：
  - 客户端 `c/f/follow/followList`、`c/f/fan/fanList` 返回 HTTP 200 空 body（带 STOKEN 相同）
  - `c/u/user/getsocialdata` 返回 302；web 端 `i/sys/user_json` 返回 HTML 页（原 JSON 接口已死）；`home/main` 个人主页 403
  - 阳性对照：`c/s/sync` 同一 BDUSS 正常返回 228KB JSON——协议通道本身健康，是关注/粉丝接口被针对性封锁
- 解除条件：获得可用的关注/粉丝列表数据源（同 Phase 1）
- 已完成的存储设计保留：`tc_users_oauth` 类似的懒建表模式可复用（`tc_baidu_friends`：uid, pid, type[follow/fan], fid, info json, updated_at）

## Phase 3（已完成，2026-09-04）：签到数据深度分析

- 基于 `tc_sign_history` + `tc_baiduid_health` 现有数据，无新表、无百度接口依赖（不受 Phase 1 影响）
- 页面：`index.php?mod=analysis`（导航「数据分析」），支持 7/30/90/365 天区间切换
- 内容：概览指标卡（总量/成功失败/成功率/活跃贴吧/连签）、成功率趋势图（柱线双轴）、吧维度对比（Top15 横向堆叠图 + 明细表）、失败原因 TOP（图 + 占比表）、签到时段分布（24 小时聚合）、账号对比与 BDUSS 健康状态表
- 查询层：`SignManager` 新增 `overview` / `hourDistribution` / `accountCompare` / `accountHealth`，`failureBreakdown` 支持自定义天数
- 附带修复 V6.0 遗留缺陷：`components.js` 为 defer 加载，此前所有页面（工作台/日历/排行/统计）的 `CS.chart` 图表初始化时 `CS` 尚不存在导致图表从不渲染；统一改为 DOMContentLoaded 后初始化，浏览器实测全部恢复
- 说明：路线图原列的「等级/成长值变化」本地无数据源（百度接口封锁期间无法获取），暂不实现，待数据源可用后再评估

## Phase 4（已完成，2026-09-04）：第三方登录

- GitHub OAuth 已落地：`lib/class.OAuth.php`（Provider 抽象层 + `tc_users_oauth` 绑定表：uid, provider, openid, info, bound_at，provider+openid 唯一）
- 流程：`index.php?mod=oauth:start`（生成一次性 state 存会话）→ GitHub 授权 → `index.php?mod=oauth:callback`（state 校验 → 换 Token → 拉取用户）
- 已登录用户绑定（账号中心「第三方账号绑定」面板，`setting.php?mod=oauth:unbind` 解绑）；访客按绑定直接登录；未绑定时若开启「自动注册」（需同时开启站点注册，跳过邀请码）自动创建账号（用户名取 GitHub login 清洗+去重，随机密码）
- 安全：state 防重放（hash_equals）、openid 占用检测、解绑走 CSRF 表单；授权跳转经硬编码主机 + client_id/state 白名单校验、0 秒 meta-refresh 中转（不带 redirect_uri，GitHub 强制使用注册回调，杜绝开放重定向）；OAuth 回调路由豁免常规 Referer CSRF（跨站跳回必然携带外部 Referer，该流程由 state 防护）——E2E 实测带 github.com Referer 的回调不再被误杀
- 配置：设置中心新增「第三方登录（GitHub OAuth）」区块（启用开关、Client ID/Secret、自动注册开关、回调地址展示）
- QQ 互联 / 微信：按 `OAuth::providers()` 抽象层扩展，需用户自行申请开发者资质后接入
- 已知边界：完整授权回环需真实 GitHub OAuth App（本地 E2E 已验证 start/state/错误路径/绑定数据层 11 项；真实 GitHub 登录需管理员在 GitHub 创建应用并填入凭据后自测）
