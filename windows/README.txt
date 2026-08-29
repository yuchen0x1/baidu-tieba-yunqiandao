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
4. 双击 start-web.bat，或在命令行运行它。
5. 浏览器打开 http://127.0.0.1:8080/setup/install.php，按向导完成安装。
6. 安装完成后确认上级目录 setup\\install.lock 存在。
7. 使用 Windows 任务计划程序每分钟运行 run-cron.bat。

日常使用
- start-web.bat：启动本地 Web 服务。
- run-cron.bat：执行一次到期的计划任务。
- 默认服务地址：http://127.0.0.1:8080
- 管理员进入“系统更新”即可在线检查 GitHub 最新正式 Release；检测到新版本后可保留配置地更新源码。
- 若需只检查 Windows 部署包，可运行 `powershell -ExecutionPolicy Bypass -File windows\check-update.ps1`。

安全提示
- 不要把 BDUSS、STOKEN、数据库密码或通知凭据提交到 Git 或公开渠道。
- 生产部署请使用 HTTPS，并限制管理入口访问。
- 本包已包含 Composer 运行依赖，不需要再次执行 composer install。
