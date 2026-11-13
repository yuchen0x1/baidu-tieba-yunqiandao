# 百度贴吧云签到 · 全功能升级路线图（V5.12）

> 生成与完成日期：2026-09-03
> 目标：对照开源社区（[MoeNetwork/Tieba-Cloud-Sign](https://github.com/MoeNetwork/Tieba-Cloud-Sign) 主程序及其衍生插件/分支），将本项目升级为功能完备、可运营、可维护的贴吧云签到系统，并保持 AGPL-3.0 合规。

---

## 已完成能力

| 领域 | 完成内容 | 主要位置 |
|---|---|---|
| 签到引擎 | 网页/移动/客户端/一键 4 种方式、失败复签、多线程、扫描贴吧、随机间隔、乱序、客户端版本可配置 | `lib/class.misc.php` |
| 账号与凭证 | 多用户、多 BDUSS/STOKEN、AES 加密、全局/账号级 HTTP 或 SOCKS5 代理、扫码绑定、批量导入 | `templates/baiduid.php`、`lib/class.misc.php` |
| 验证码 | 超级鹰自动打码；验证码出现后自动识别并仅重试一次 | `lib/class.dama.php` |
| 账号体验 | 找回密码、可选邮箱验证注册、注册/登录验证码与限流 | `lib/globals.php`、`templates/lostpw.php` |
| 健康运营 | BDUSS 每日体检、连续失效自动停用、通知、管理员/用户恢复签到 | `lib/class.SignManager.php`、`templates/admin-sign-dashboard.php` |
| 通知中心 | Server酱、Telegram、Webhook、Bark、PushPlus、邮件、钉钉、企业微信、飞书、WxPusher、ntfy | `lib/class.notify.php` |
| 插件生态 | 插件钩子、上传 ZIP 安装、仅限 Gitee ZIP 在线安装、云点赞插件 | `lib/plugins.php`、`plugins/ver4_like/` |
| 开放 API | 旧版全局 Bearer Token 兼容；个人 Token 只存 SHA-256 哈希且按用户隔离 status/sign/retry/health/logs/calendar | `api.php`、`templates/set.php` |
| 签到日历 | 单贴吧结果落历史表、个人 90 天热力图、API `calendar` | `lib/class.SignManager.php`、`templates/calendar.php` |
| 合规 | 开源致谢页面、保留上游版权与 AGPL-3.0 责任 | `templates/thanks.php` |
| 运维 | GitHub Release 在线更新、统一迁移器、Docker/传统 PHP 网页部署、PHP 8.2/8.4 CI | `setup/`、`docker/` |

## 版本迁移

1. 旧部署按顺序运行 `setup/update.php` 展示的迁移脚本，最终执行 `setup/update5.11to5.12.php`。
2. V5.12 会给 `users` 表增加 `api_token_hash` 与唯一索引，创建 `sign_history`，并补齐通知/健康设置默认项。
3. 历史签到日历自 V5.12 起开始记录，不会臆造旧版本数据。
4. 升级完成后到“设置中心 → 签到设置”按需配置代理、打码、自动停用；到“个人设置”生成用户 API Token。

## 合规与致谢

- 搬运社区代码时保留原文件头版权声明；功能级借鉴应在致谢页面登记来源。
- 致谢页：`index.php?mod=thanks`。
- 本项目遵守 AGPL-3.0；派生、网络分发与二次开发也应履行对应开源义务。

