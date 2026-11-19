# 平台与兼容性

签到、复签、凭据检查和通知都由服务器及 MariaDB 执行。浏览器关闭后任务继续运行。前端以响应式 PHP 模板提供工作台，CLI 复用同一套调度和迁移核心。版本源为 `version.php`，部署形态共用同一产品版本。

| 平台 | 结论 | 核心能力、适配和维护 |
| --- | --- | --- |
| 桌面 Web | A | 支持管理、统计、更新、插件。键鼠使用现有导航和表格。以 HTTPS 站点持续交付。 |
| 移动 Web、响应式 Web | A | 保留账号、配置和查询。窄屏使用折叠菜单与横向表格。移动浏览器退出后服务端继续签到。维护同一 Web 版本。 |
| Docker | A | Compose 构建 Linux 应用镜像并配置 MariaDB；依赖 Docker Engine 和卷权限。版本随 Git 标签分发、备份后重建。 |
| NAS | A | 支持能够运行兼容 Linux 容器、MariaDB 及 Compose 的设备。架构和卷权限由设备决定，使用相同更新流程。 |
| 私有化 / 云服务器 | A | PHP 8.2+、mysqli、curl、mbstring、OpenSSL、zip、MariaDB/MySQL，及分钟级调度权限。传统 PHP 使用 Web ZIP，容器使用 Compose。 |
| CLI | A | `do.php`、`do.php --loop`、`tools/migrate.php`；需 PHP CLI 与站点配置，随源码或 Web 包升级，供服务器运维使用。 |
| Windows 10、Windows 11 | C（原生） | 浏览器可管理站点。Windows 环境可在兼容的 Docker Desktop/WSL 上运行 Linux 容器。原生打包还需单独管理 PHP、数据库、调度、安装和签名。 |
| Linux、Ubuntu、Debian、Fedora、Arch Linux | A（服务端）/ C（桌面） | 原生 PHP 或 Linux Docker 适合长期运行。DEB/RPM/AppImage 各维护一套安装、数据恢复和更新体系，桌面用途与 Web 重合。 |
| macOS Intel、macOS Apple Silicon | C（原生） | 浏览器可管理。兼容的 Docker Desktop 可运行 Linux 容器，需匹配架构。原生长期服务还需签名、公证与 Intel/ARM 分发。 |
| Android 手机、平板、折叠屏 | C（原生） | 用响应式 Web 管理。服务端仍需常驻以完成定时任务。原生端需维护后台和电量策略、签名与独立发布，现阶段收益有限。 |
| iOS、iPadOS | C（原生） | Safari 可管理站点。移动端后台调度与签名、分发规则限制独立签到服务。独立客户端仍依赖原服务端。 |
| PWA | C | 安装入口可实现；离线时仍无法查询或触发服务端任务。缓存已登录页面增加隐私和过期状态成本。 |
| Chrome、Edge、Firefox 扩展 | C | 扩展可以打开控制台，但持续调度仍由服务器提供。跨商店审核、凭据权限和更新增加成本。 |

| 分发目标 | 资产 | 更新与回滚 |
| --- | --- | --- |
| 传统 PHP / 私有服务器 | Web ZIP、SHA-256、更新清单、GitHub Release 证明 | 后台主动检查或手动部署。迁移前备份，保留配置与数据库恢复点。 |
| Docker / NAS | Git 标签中的 Compose、Dockerfile；镜像工作流构建 Linux amd64、arm64、arm、s390x | 按标签构建并切换，复用命名卷。数据库与镜像按同一恢复点回滚。Docker Hub 推送由维护者显式触发。 |
| 桌面/移动浏览器 | 服务器提供页面，无端侧安装包 | 升级在服务器完成，页面重新请求资源。 |

详见[安装](../README.md#-快速开始)、[更新与恢复](AUTO_UPDATE.md)、[发布](RELEASE.md)和[调研](reports/ecosystem-2026-11-18.md)。架构构建通过与物理设备运行验收分别记录。
