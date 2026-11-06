<?php

use PHPUnit\Framework\TestCase;

/**
 * 版本元数据与发布配置回归测试（V7.0）
 * 防止再次出现安装模板、Docker、Windows 脚本与运行版本漂移：
 * 全部版本与令牌命名只有一个来源（version.php / UPDATE_GITHUB_TOKEN）。
 */
final class ReleaseConfigTest extends TestCase
{
    private function read(string $relative): string
    {
        $content = file_get_contents(dirname(__DIR__) . '/' . $relative);
        $this->assertNotFalse($content, '无法读取文件：' . $relative);
        return $content;
    }

    /** 从 version.php 解析当前版本号（版本唯一的合法来源） */
    private function currentVersion(): string
    {
        $content = $this->read('version.php');
        $this->assertMatchesRegularExpression("/define\('SYSTEM_VER', '([^']+)'\)/", $content, 'version.php 缺少合法的 SYSTEM_VER 定义');
        preg_match("/define\('SYSTEM_VER', '([^']+)'\)/", $content, $m);
        return $m[1];
    }

    public function testVersionPhpIsTheSingleSourceOfTruth(): void
    {
        $content = $this->read('version.php');
        $version = $this->currentVersion();
        $this->assertMatchesRegularExpression("/define\('SYSTEM_VER', '" . preg_quote($version, '/') . "'\)/", $content);
        $this->assertMatchesRegularExpression("/define\('SYSTEM_FN', '[^']+'\)/", $content);
        $this->assertMatchesRegularExpression("/define\('SYSTEM_VER_NOTE', '[^']+'\)/", $content);
    }

    public function testEntryPointsUseSharedVersionSource(): void
    {
        $init = $this->read('init.php');
        $this->assertStringContainsString("require SYSTEM_ROOT . '/version.php'", $init);
        $this->assertStringNotContainsString("define('SYSTEM_VER'", $init);

        foreach (array('setup/install.php', 'setup/check.php') as $file) {
            $content = $this->read($file);
            $this->assertStringContainsString('version.php', $content, $file . ' 应使用统一版本源');
            $this->assertStringNotContainsString("define('SYSTEM_VER'", $content, $file . ' 不得自带版本常量');
        }
    }

    public function testInstallTemplateInstallsCurrentVersionWithBaseline(): void
    {
        $sql = $this->read('setup/install.template.sql');
        $this->assertStringContainsString("('core_version', '" . $this->currentVersion() . "')", $sql);
        $this->assertStringContainsString('CREATE TABLE `{VAR-PREFIX}schema_migrations`', $sql);
        $this->assertStringContainsString("'001001'", $sql);
        $this->assertStringContainsString("'007000'", $sql);
        $this->assertStringNotContainsString("'5.12'", $sql);
    }

    public function testDockerUsesOptionalGitHubUpdateToken(): void
    {
        foreach (array('docker/docker-compose.yml', 'docker/.env.example') as $file) {
            $content = $this->read($file);
            $this->assertStringContainsString('UPDATE_GITHUB_TOKEN', $content, $file . ' 应使用 GitHub 令牌');
            $this->assertStringNotContainsString('UPDATE_GITEE_TOKEN', $content, $file . ' 不得残留 Gitee 令牌命名');
        }
    }

    public function testLoopGracefulReloadIsOptInAndDisabledByDefault(): void
    {
        // do.php --loop 优雅重载必须默认关闭：只有部署方显式设置 LOOP_RELOAD_FILE 才启用
        $compose = $this->read('docker/docker-compose.yml');
        $this->assertStringContainsString('LOOP_RELOAD_FILE', $compose);
        $this->assertStringContainsString('LOOP_RELOAD_FILE:-}', $compose, 'compose 默认值必须为空（默认关闭）');
        $env = $this->read('docker/.env.example');
        $this->assertStringContainsString('# LOOP_RELOAD_FILE=', $env, '.env 示例中应注释掉（默认关闭）');
        $loop = $this->read('do.php');
        $this->assertStringContainsString("getenv('LOOP_RELOAD_FILE')", $loop);
        $this->assertStringContainsString('exit(75)', $loop, '常驻进程收到标记后应优雅退出（75）');
        $upgrade = $this->read('upgrade.php');
        $this->assertStringContainsString("getenv('LOOP_RELOAD_FILE')", $upgrade, '升级入口应支持写入重载标记');
    }

    public function testUpdaterKeepsPageFallbackWithinTrustedHosts(): void
    {
        // 匿名限流回退只允许读取官方仓库 Release 页面，不得放宽域名白名单
        $updater = $this->read('lib/class.updater.php');
        $this->assertStringContainsString('GITHUB_RELEASES_PAGE_URL', $updater);
        $this->assertStringContainsString('releases/latest', $updater);
        $this->assertStringContainsString('ProjectUpdaterRateLimitException', $updater);
    }

    public function testUpdaterIsConfiguredForGitHubReleaseEndpoints(): void
    {
        $updater = $this->read('lib/class.updater.php');
        $this->assertStringContainsString('https://api.github.com/repos/yuchen0x1/baidu-tieba-yunqiandao/releases/latest', $updater);
        $this->assertStringContainsString('https://api.github.com/repos/yuchen0x1/baidu-tieba-yunqiandao/releases?per_page=10', $updater);
        $this->assertStringContainsString("getenv('UPDATE_GITHUB_TOKEN')", $updater);
        $this->assertStringContainsString('release-assets.githubusercontent.com', $updater);
        $this->assertStringNotContainsString('gitee.com', strtolower($updater));
    }

    public function testWindowsUpdateCheckDefaultsToCurrentVersion(): void
    {
        $content = $this->read('windows/check-update.ps1');
        $this->assertMatchesRegularExpression('/CurrentVersion\s*=\s*"' . preg_quote($this->currentVersion(), '/') . '"/', $content);
    }

    public function testComposerPlatformContractCoversRealDependencies(): void
    {
        $composer = json_decode($this->read('composer.json'), true);
        $this->assertIsArray($composer);
        foreach (array('ext-curl', 'ext-json', 'ext-mbstring', 'ext-mysqli', 'ext-openssl', 'ext-session', 'ext-zip') as $ext) {
            $this->assertArrayHasKey($ext, $composer['require'], 'composer.json 缺少平台依赖 ' . $ext);
        }
    }

    public function testV70UpgradePathIsWired(): void
    {
        $this->assertFileExists(dirname(__DIR__) . '/setup/update6.6to7.0.php');
        $this->assertFileExists(dirname(__DIR__) . '/setup/migrations/007000_v70_foundation.php');
        require_once dirname(__DIR__) . '/lib/class.migrator.php';
        $this->assertSame('6.6', Migrator::MIN_UPGRADE_VERSION);
        $this->assertTrue(class_exists('Migrator'));
    }

    public function testGenericUpgradeEntryIsWiredForAnySupportedVersion(): void
    {
        // 无专用 update{旧}to{新}.php 脚本时，6.6+ 版本必须能通过通用升级入口直升当前版本。
        // 首选入口是站点根目录 upgrade.php（/setup/ 常被 Web 服务器安全规则封禁，根目录入口任何部署都可达）；
        // setup/upgrade.php 保留为兼容入口。
        $this->assertFileExists(dirname(__DIR__) . '/upgrade.php');
        $this->assertFileExists(dirname(__DIR__) . '/setup/upgrade.php');
        $reg = $this->read('lib/reg.php');
        $this->assertStringContainsString('upgrade.php', $reg, '版本门禁应引导到通用升级入口');
        foreach (array('upgrade.php', 'setup/upgrade.php') as $entry) {
            $upgrade = $this->read($entry);
            $this->assertStringContainsString('SYSTEM_NO_CHECK_VER', $upgrade, '升级入口需绕过版本门禁');
            $this->assertStringContainsString('Migrator::runUp', $upgrade, '升级入口应使用统一迁移器');
            $this->assertStringContainsString("ROLE != 'admin'", $upgrade, '升级入口必须校验管理员身份');
        }
    }
}
