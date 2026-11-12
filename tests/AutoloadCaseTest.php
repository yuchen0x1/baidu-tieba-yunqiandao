<?php

use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__) . '/lib/class.loader.php';

/**
 * lib 类加载静态回归测试（V7.0）
 * class_autoload 曾按"类名小写拼文件名"查找，在大小写敏感的 Linux 文件系统上
 * 会漏掉 class.SignManager.php 等混合命名文件。加载器现已改为真实目录清单解析，
 * 本测试强制：lib 下每个 class.*.php 声明的类都能被 lib_class_file() 解析回同一个文件，
 * 未登记别名的命名偏差会在 CI 直接失败。
 */
final class AutoloadCaseTest extends TestCase
{
    /** @var array 小写键 => 真实文件名 */
    private $map;

    protected function setUp(): void
    {
        $this->map = lib_class_map();
        $this->assertNotEmpty($this->map, 'lib 目录应存在 class.*.php 文件');
    }

    private function declaredClass($file): ?string
    {
        $source = file_get_contents(SYSTEM_ROOT . '/lib/' . $file);
        $this->assertNotFalse($source, '无法读取 ' . $file);
        if (preg_match('/\b(?:abstract\s+|final\s+)?class\s+([A-Za-z0-9_]+)/', $source, $m) !== 1) {
            return null;
        }
        return $m[1];
    }

    public function testCriticalClassesResolveRegardlessOfCase(): void
    {
        foreach (array('P', 'p', 'E', 'SignManager', 'signmanager', 'SIGNMANAGER', 'OAuth', 'MessageCenter', 'Migrator', 'ProjectUpdater', 'option', 'cron', 'Notify', 'totp', 'SMTP', 'LoginGuard', 'Webhook') as $class) {
            $file = lib_class_file($class);
            $this->assertNotNull($file, '类 ' . $class . ' 应能被加载器解析');
            $this->assertFileExists($file);
        }
    }

    public function testLegacyWmysqlResolvesThroughAlias(): void
    {
        $file = lib_class_file('wmysql');
        $this->assertNotNull($file);
        $this->assertSame(basename($file), lib_class_map()['mysqli'], 'wmysql 应解析到 mysqli 驱动文件');
    }

    public function testEveryClassFileRoundTripsThroughLoader(): void
    {
        // 纯函数文件（无类声明），不参与类名回环校验
        $functionOnly = array('loader');
        foreach ($this->map as $key => $file) {
            if (in_array($key, $functionOnly, true)) {
                continue;
            }
            $declared = $this->declaredClass($file);
            $this->assertNotNull($declared, '文件 ' . $file . ' 未声明类（纯函数文件请登记到 $functionOnly）');
            $resolved = lib_class_file($declared);
            $this->assertNotNull($resolved, '文件 ' . $file . ' 声明的类 ' . $declared . ' 无法解析（需统一命名或在 lib_class_aliases 登记）');
            $this->assertSame(
                strtolower(basename($resolved)),
                strtolower($file),
                '文件 ' . $file . ' 声明类 ' . $declared . ' 但解析到了 ' . basename($resolved)
            );
        }
    }

    public function testUnknownClassStillThrowsThroughAutoload(): void
    {
        // 未定义类保持原行为：由 class_autoload 抛出异常（此处仅验证解析器返回 null）
        $this->assertNull(lib_class_file('NoSuchClass_xyz'));
    }
}
