# 贴吧功能调研：开源仓库对照与整合清单

> 更新：2026-09-03。对照 GitHub 生态中贴吧相关仓库的功能面，标注本项目现状与取舍。
> 参考仓库：[MoeNetwork/Tieba-Cloud-Sign](https://github.com/MoeNetwork/Tieba-Cloud-Sign)（上游）、[kookxiang/Tieba_Sign](https://github.com/kookxiang/Tieba_Sign)（xxx_post 自动回帖插件，本轮借鉴其 `c/c/post/add` 参数集）、[Starry-OvO/aiotieba](https://github.com/Starry-OvO/aiotieba)（客户端 API 参考）、社区 Discussion #264。

## 已有（此前版本已实现）

| 功能 | 说明 |
|---|---|
| 全自动签到 | 网页/移动/客户端/一键四模式 + 失败复签 + 多线程 |
| 账号管理 | 扫码/手动/批量导入绑定，健康检查，失效自动停用与恢复 |
| 云封禁 / 云审查 | ver4_ban / ver4_review 插件 |
| 云点赞 | ver4_like 插件（V5.12 新增） |
| 签到排行 / 转盘抽奖 / 公告 | ver4_rank / ver4_lottery / wmzz_anno 插件 |
| 通知中心 | 11 个推送渠道 |

## 本轮新增（V5.12）

| 功能 | 实现方式 | 来源 |
|---|---|---|
| **自动回帖** | 新插件 `ver4_reply`：目标贴吧 + 内容库 + 每吧限量 + 每日上限 + 随机间隔 + 按天去重；接口 `c/c/post/add`（tiebaclient 签名，继承账号级代理） | kookxiang/Tieba_Sign `xxx_post/core.php` |
| **账号中心** | 新页面 `index.php?mod=account`（侧边栏"账号中心"）：账号健康、签到数、贴吧数、代理、恢复入口、近 7 天趋势 | 本项目自有数据 + 既有接口 |

## 暂缓（有可行 API，待后续迭代）

| 功能 | 说明 | 参考 |
|---|---|---|
| 吧等级/经验展示 | frs/page 与 forumGuide 响应含吧等级字段，可在"贴吧与签到"增加等级列 | aiotieba `get_forum_home` |
| 我的收藏 / 我的帖子 | 客户端有对应接口，需按用户维度做分页浏览页 | aiotieba `get_self_saved` 等 |
| 消息中心（回复/@提醒） | 客户端私信/回复提醒接口，需轮询与推送联动 | aiotieba `message` 模块 |
| 自动关注/取关 | 已有 wmzz_gz（页面压缩，功能名与关注无关），可做独立的关注管理插件 | 社区插件 |

## 跳过（与本项目定位不符或重复）

- 非贴吧类仓库（百度网盘/知道/文库等）。
- 各语言重写的"签到器"（青龙脚本、Python 签到框架）——与本项目签到核心重复。
- 已停更且仅支持旧版客户端协议的桥接项目。
