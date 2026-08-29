<?php

use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__) . '/lib/class.updater.php';

final class UpdaterTest extends TestCase
{
    private function payload(string $tag = 'v5.08'): array
    {
        return array(
            'tag_name' => $tag,
            'name' => '百度贴吧云签到 ' . $tag,
            'body' => '测试版本说明',
            'html_url' => 'https://github.com/yuchen0x1/baidu-tieba-yunqiandao/releases/tag/' . $tag,
            'zipball_url' => 'https://api.github.com/repos/yuchen0x1/baidu-tieba-yunqiandao/zipball/' . $tag,
            'published_at' => '2026-08-29T00:00:00Z',
            'prerelease' => false,
            'assets' => array(array(
                'name' => 'tieba-cloud-sign-windows-' . $tag . '.zip',
                'browser_download_url' => 'https://github.com/yuchen0x1/baidu-tieba-yunqiandao/releases/download/' . $tag . '/package.zip',
                'size' => 123,
                'digest' => 'sha256:test'
            ))
        );
    }

    public function testReleaseIsParsedAndWindowsAssetIsSelected(): void
    {
        $release = ProjectUpdater::parseRelease($this->payload());
        $this->assertSame('5.08', $release['version']);
        $this->assertSame('tieba-cloud-sign-windows-v5.08.zip', $release['asset_name']);
        $this->assertSame(123, $release['asset_size']);
        $this->assertSame('sha256:test', $release['asset_digest']);
    }

    public function testNewerReleaseIsDetected(): void
    {
        $status = ProjectUpdater::buildStatus('5.07', ProjectUpdater::parseRelease($this->payload('v5.08')));
        $this->assertTrue($status['update_available']);
        $this->assertSame('5.07', $status['current_version']);
        $this->assertSame('5.08', $status['latest_version']);
    }

    public function testCurrentReleaseIsNotReportedAsUpdate(): void
    {
        $status = ProjectUpdater::buildStatus('5.07', ProjectUpdater::parseRelease($this->payload('v5.07')));
        $this->assertFalse($status['update_available']);
    }

    public function testUntrustedReleaseUrlIsRejected(): void
    {
        $payload = $this->payload();
        $payload['html_url'] = 'https://example.com/release';
        $this->expectException(InvalidArgumentException::class);
        ProjectUpdater::parseRelease($payload);
    }
}
