# V6.1 功能路线图

> 目标：从"单一云签到"扩展为贴吧账号综合管理工具。按以下顺序分四期开发，每期独立可交付。

## Phase 1：贴吧消息中心

聚合展示百度账号的站内消息，接入现有通知体系。

- 数据源：贴吧 web 接口（私信列表、回复我的、@我的），经 BDUSS 拉取
- 展示：`index.php?mod=messages` 新页面，按类型分栏，未读计数进导航徽章
- 推送：新消息经现有 `Notify` 通道（QQ 推送 / 邮件 / Server酱）可选推送
- 存储：`tc_messages`（uid, type, sender, content, url, is_read, created_at），拉取去重按消息 ID
- 调度：注册 `system_message_pull` 计划任务，默认每小时；失败退避复用 V5.13 调度引擎

## Phase 2：关注/粉丝管理

- 数据源：关注/粉丝列表接口（BDUSS）
- 功能：列表展示（头像、吧龄、等级）、搜索、批量取关/特别关注
- 存储：`tc_baidu_friends`（uid, pid, type[follow/fan], fid, info json, updated_at）
- 风控：分页间隔与请求限速复用 `sign_sleep`/客户端指纹轮换

## Phase 3：签到数据深度分析

- 基于 `tc_sign_history` + `tc_baiduid_health` 现有数据，无需新表
- 页面：日历页扩展"账号分析"页（等级/成长值变化、成功率趋势、吧维度对比、失败原因 TOP）
- 图表：复用 V6.0 `CS.chart`（统计页已用）

## Phase 4：第三方登录

- GitHub OAuth：无需企业资质，可先行落地（`tc_users_oauth`：uid, provider, openid, bound_at）
- QQ 互联 / 微信：需用户自行申请开发者资质后接入，预留 provider 抽象层
- 绑定流程：已有账号绑定 + 新用户自动注册（需开启注册开关）

## 依赖与风险

- Phase 1/2 的贴吧接口依赖 BDUSS 有效性与客户端指纹，失效账号自动跳过
- Phase 4 QQ/微信需用户提供已审核通过的应用凭据
