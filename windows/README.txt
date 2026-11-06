百度贴吧云签到 Windows 部署包

本包是 Windows 部署包，不是独立的 .exe 桌面客户端。程序启动后请使用浏览器访问 Web 页面。

运行环境
- Windows 10/11
- PHP 8.2 或更高版本，并加入 PATH
- PHP 扩展：curl、json、mbstring、mysqli、gd、openssl、zip
- MySQL 或 MariaDB 数据库

安装步骤
1. 将本目录解压到不含中文和空格的目录，例如 C:\\tieba-cloud-sign。
2. 准备 MySQL/MariaDB 数据库；默认数据库名为 tiebacloud，也可以通过环境变量 DB_NAME 修改。
3. 按需设置 DB_HOST、DB_USER、DB_PASSWD、DB_NAME、DB_PREFIX、SYSTEM_SALT 环境变量，或编辑上级目录的 config.php。
4. 双击 start-web.bat，或运行 start-web.ps1；启动器会检查 PHP 版本、扩展和端口。
5. 浏览器打开 http://127.0.0.1:8080/setup/install.php，按向导完成安装。
6. 安装完成后确认上级目录 setup\\install.lock 存在。
7. 使用 Windows 任务计划程序每分钟运行 run-cron.bat。

日常使用
- start-web.bat：启动本地 Web 服务。
- start-web.ps1 -Port 8081：使用指定端口启动，并自动打开安装页或首页。
- run-cron.bat：执行一次到期的计划任务。
- check-health.ps1 -Mode live：检查 PHP 服务是否存活。
- check-health.ps1 -Mode ready：检查安装锁、数据库和核心表是否就绪。
- 默认服务地址：http://127.0.0.1:8080
- 管理员进入“系统更新”即可在线检查 GitHub 最新正式 Release；检测到新版本后可保留站点配置地更新程序。
- 在线覆盖安装只接受带可信 SHA-256 摘要的 Release 资产。更新器会校验 Release API 的 digest 或同一 Release 的 .sha256 文件；缺少摘要、摘要不一致或下载的是源码归档时会拒绝在线覆盖安装，原程序保持不变。
- 若需只检查 Windows 部署包，可运行 `powershell -ExecutionPolicy Bypass -File windows\check-update.ps1`。
- Windows 部署包及 SHA-256 校验文件通过本项目 GitHub Releases 发布：<https://github.com/yuchen0x1/baidu-tieba-yunqiandao/releases>。

安全提示
- 不要把 BDUSS、STOKEN、数据库密码或通知凭据提交到 Git 或公开渠道。
- 生产部署请使用 HTTPS，并限制管理入口访问。
- 本包已包含 Composer 运行依赖，不需要再次执行 composer install。
