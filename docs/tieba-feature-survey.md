# 贴吧同类开源项目横向调研与差距矩阵

> 更新：2026-09-03。方法：按 GitHub star 检索贴吧相关仓库（137 个匹配），逐个核对功能面；结合上游插件库（48 项）与客户端 API 逆向参考（aiotieba / libsgh/tieba-api / kookxiang/Tieba_Sign）。
> 结论先行：**签到核心、多账号、通知、代理、打码、账号健康、批量导入、找回密码、邮箱验证、云点赞、自动回帖、账号中心** 已覆盖社区主流能力；真正的剩余差距集中在"客户端指纹轮换、自动发主题帖、楼中楼回复、消息中心、粉丝/关注管理、站点运营类插件"。

## 一、对照项目清单（同类项目）

| 项目 | Star | 形态 | 与本项目关系 |
|---|---|---|---|
| [Sitoi/dailycheckin](https://github.com/Sitoi/dailycheckin) | 8.7k | Python 多平台签到聚合（青龙/Docker） | 贴吧只是其一个模块；其"多平台聚合+统一通知"思路本项目已用 11 渠道通知实现 |
| [MoeNetwork/Tieba-Cloud-Sign](https://github.com/MoeNetwork/Tieba-Cloud-Sign) | 2k | PHP 云签到（本项目上游） | 核心同源；差异见插件库对照 |
| [LuoSue/TiebaSignIn-1](https://github.com/LuoSue/TiebaSignIn-1) | 281 | GitHub Actions 每日签到（8 点经验） | 运行形态不同（无站点）；sign_hour 已覆盖其"定时签到"诉求 |
| [ghosx/TiebaProject](https://github.com/ghosx/TiebaProject) | 185 | Django 云签到+云回复（已归档，推荐用其 Action 版） | 架构参考；云回复能力本项目 ver4_reply 已落地 |
| [kookxiang/Tieba_Sign](https://github.com/kookxiang/Tieba_Sign) | 189 | PHP 签到工具（2016） | **本轮借鉴**：xxx_post 回帖参数集 + 客户端指纹随机化（client_type 1-4 轮换、_client_id/_imei 随机） |
| [Aruelius/tieba_sign](https://github.com/Aruelius/tieba_sign) | 167 | Python 扫码登录+自动签到+自动打码 | 扫码登录本项目已有；打码已有（超级鹰） |
| [gwtak/TieBaSign](https://github.com/gwtak/TieBaSign) | 149 | GitHub Actions 签到 | 运行形态不同 |
| [libsgh/tieba-api](https://github.com/libsgh/tieba-api) | 104 | Java 贴吧客户端 API 封装 | **API 面最全的对照**：楼中楼回复、发主题帖、上传图片、关注/取关吧、粉丝管理/移除粉丝、消息中心（回复我/@我）、举报、封禁原因查询 |
| [y1yang0/TiebaCloud](https://github.com/y1yang0/TiebaCloud) | 79 | PHP 云签到（2018，停更） | 老旧，无新增能力 |
| [Starry-OvO/aiotieba](https://github.com/Starry-OvO/aiotieba) | 674 | Python 贴吧客户端（最完整协议参考） | API 协议字典；后续功能（消息中心、赞同/反对等）以它为协议依据 |

## 二、上游插件库 48 项对照（有 ✓ / 无 ✗ / 等价 ≈）

| 上游插件 | 本项目 | 备注 |
|---|---|---|
| 云灌水（自动回帖/发帖） | ✓ ver4_reply（本轮，回帖） | **自动发主题帖暂缺**，见差距表 |
| 云点赞 | ✓ ver4_like | |
| 黑名单/性别吧龄限制注册 | ✗ | 站点运营增强，暂缓 |
| 邮件签到通知 | ✓ 通知中心 | |
| 邮件群发（wmzz_mailer） | ✗ | 站长运营工具，可低成本低成本补齐 |
| 数据库自动备份到邮箱 | ✗ | 已有手动备份下载；"定时备份+发送"暂缓 |
| 找回密码 | ✓ | |
| 批量导入 BDUSS | ✓ | |
| BDUSS 有效性检测 | ✓ 健康检查 | |
| 更换密码 | ✗ | 个人设置缺"修改密码"——**可低成本补齐** |
| 公告 / GZip / 自定义页面 / 背景 / 下雪 / 一言 / 评论 | ≈/✗ | UI 美化类，低优先级 |
| 循环封禁 / 自动删帖 | ✓ ver4_ban / ver4_review | |
| 多邀请码 | ✗（现为单邀请码） | 小改动 |
| 第三方登录（微博 OAuth） | ✗ | 大特性，暂缓 |
| 滑块/reCAPTCHA/hCAPTCHA/Vaptcha | ≈ 自绘验证码 | 等价实现 |
| 防搜索引擎收录 | ≈ robots.txt | 可加 noindex meta |
| BDUSS 登录站点（花式登录） | ✗ | 小众玩法，暂缓 |
| PushPlus/Bark/HTTP 自定义推送 | ✓ 11 渠道 | |
| Adminer / 密码重置工具 | ✗ | 运维工具，暂缓 |

## 三、客户端 API 对照（以 libsgh/tieba-api、aiotieba、kookxiang 为参照）

| 能力 | 本项目 | 差距结论 |
|---|---|---|
| 签到（四模式+复签） | ✓ | — |
| 回帖（主题帖下回复） | ✓ ver4_reply | — |
| **自动发主题帖** | ✗ | `c/c/thread/add`，参数集与回帖近似（+title）——**下一批实现** |
| **楼中楼回复（指定楼层）** | ✗ | 需 z/pid 参数，协议已明 |
| **客户端指纹轮换** | 部分（版本号可配、type 固定 4） | **本轮实现**：type 1-4 随机 + _client_id/_imei 每请求随机 |
| 贴吧扫描/一键签到 | ✓ | — |
| 关注/取关贴吧 | ✗ | web 接口 focus2/unfocus2（可操作异常吧），暂缓 |
| 粉丝管理/移除粉丝 | ✗ | 暂缓 |
| 消息中心（回复我/@我） | ✗ | 高价值：轮询+通知中心联动，暂缓（需协议验证） |
| 用户资料（吧龄/粉丝/等级） | 部分（健康+签到） | 暂缓：账号中心二期增强 |
| 上传图片发帖 | ✗ | 暂缓（上传接口复杂） |
| 打码平台 | ✓ 超级鹰 | — |
| 代理 | ✓ 全局+账号级 | — |

## 四、落地批次

- **本轮已实现**：客户端指纹轮换（防特征固化）。
- **下一批（建议顺序）**：① 自动发主题帖（ver4_reply 增加模式）；② 修改密码；③ 多邀请码；④ 邮件群发+定时备份到邮箱；⑤ 楼中楼回复。
- **暂缓池**：消息中心、关注/粉丝管理、第三方登录、图片发帖、注册风控增强。
