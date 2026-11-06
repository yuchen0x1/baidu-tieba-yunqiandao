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
            'zipball_url' => 'https://api.github.com/repos/yuchen0x1/baidu-tieba-yunqiandao/zipball/' . $tag . '.zip',
            'published_at' => '2026-08-29T00:00:00Z',
            'prerelease' => false,
            'assets' => array(array(
                'name' => 'tieba-cloud-sign-windows-' . $tag . '.zip',
                'browser_download_url' => 'https://github.com/yuchen0x1/baidu-tieba-yunqiandao/releases/download/' . $tag . '/package.zip',
                'size' => 123,
                'digest' => 'sha256:test'
            ), array(
                'name' => 'tieba-cloud-sign-windows-' . $tag . '.zip.sha256',
                'browser_download_url' => 'https://github.com/yuchen0x1/baidu-tieba-yunqiandao/releases/download/' . $tag . '/package.zip.sha256',
                'size' => 100,
                'digest' => ''
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

    public function testHostileGitHubLookalikeAndAssetHostsAreRejected(): void
    {
        foreach (array('https://github.com.evil.example/release', 'https://evil.example/file.zip', 'https://user@github.com/release', 'https://github.com:444/release') as $url) {
            $payload = $this->payload();
            $payload['html_url'] = $url;
            try {
                ProjectUpdater::parseRelease($payload);
                $this->fail('Expected hostile host to be rejected: ' . $url);
            } catch (InvalidArgumentException $e) {
                $this->assertSame('Release 下载地址不受信任', $e->getMessage());
            }
        }
        $payload = $this->payload();
        $payload['assets'][0]['browser_download_url'] = 'https://evil.example/file.zip';
        $this->assertSame('', ProjectUpdater::parseRelease($payload)['asset_download_url']);
    }

    public function testOfficialHostsWithUnrelatedRepositoryPathsAreRejected(): void
    {
        foreach (array(
            'https://github.com/attacker/other-repository/releases/tag/v5.08',
            'https://github.com/yuchen0x1/baidu-tieba-yunqiandao-malicious/releases/tag/v5.08',
            'https://api.github.com/repos/attacker/other-repository/releases/latest'
        ) as $url) {
            $payload = $this->payload();
            $payload['html_url'] = $url;
            try {
                ProjectUpdater::parseRelease($payload);
                $this->fail('Expected unrelated repository path to be rejected: ' . $url);
            } catch (InvalidArgumentException $e) {
                $this->assertSame('Release 下载地址不受信任', $e->getMessage());
            }
        }

        $payload = $this->payload();
        $payload['assets'][0]['browser_download_url'] = 'https://github.com/attacker/other-repository/releases/download/v5.08/package.zip';
        $this->assertSame('', ProjectUpdater::parseRelease($payload)['asset_download_url']);
    }

    public function testMissingGitHubFieldsAreDerivedFromTag(): void
    {
        $payload = array('tag_name' => 'v5.08', 'name' => null, 'body' => null, 'html_url' => null, 'zipball_url' => null, 'assets' => null, 'published_at' => null, 'prerelease' => null);
        $release = ProjectUpdater::parseRelease($payload);
        $this->assertSame('https://github.com/yuchen0x1/baidu-tieba-yunqiandao/releases/tag/v5.08', $release['release_url']);
        $this->assertSame('https://api.github.com/repos/yuchen0x1/baidu-tieba-yunqiandao/zipball/v5.08', $release['source_download_url']);
        $this->assertSame('', $release['body']);
        $this->assertSame('', $release['published_at']);
        $this->assertFalse($release['is_prerelease']);
    }

    public function testTrustedGitHubReleaseAndAssetHostsAreAccepted(): void
    {
        $payload = $this->payload();
        $payload['zipball_url'] = 'https://api.github.com/repos/yuchen0x1/baidu-tieba-yunqiandao/zipball/v5.08';
        $payload['assets'][0]['browser_download_url'] = 'https://release-assets.githubusercontent.com/package.zip';
        $payload['assets'][1]['browser_download_url'] = 'https://objects.githubusercontent.com/package.zip.sha256';
        $release = ProjectUpdater::parseRelease($payload);
        $this->assertSame('https://release-assets.githubusercontent.com/package.zip', $release['asset_download_url']);
        $this->assertSame('https://objects.githubusercontent.com/package.zip.sha256', $release['asset_checksum_url']);
    }

    public function testGitHubChannelIsTheDefault(): void
    {
        $previous = getenv('UPDATE_PRIMARY_CHANNEL');
        putenv('UPDATE_PRIMARY_CHANNEL');
        try {
            $this->assertSame('github', ProjectUpdater::primaryChannel());
            $this->assertFalse(ProjectUpdater::foreignFallbackEnabled());
        } finally {
            if ($previous === false) {
                putenv('UPDATE_PRIMARY_CHANNEL');
            } else {
                putenv('UPDATE_PRIMARY_CHANNEL=' . $previous);
            }
        }
    }

    public function testLegacyChannelConfigurationCannotChangeGitHubDefault(): void
    {
        $previous = getenv('UPDATE_PRIMARY_CHANNEL');
        putenv('UPDATE_PRIMARY_CHANNEL=github');
        try {
            $this->assertSame('github', ProjectUpdater::primaryChannel());
        } finally {
            if ($previous === false) {
                putenv('UPDATE_PRIMARY_CHANNEL');
            } else {
                putenv('UPDATE_PRIMARY_CHANNEL=' . $previous);
            }
        }
    }

    // ---------- 安装前摘要校验（V7.0） ----------

    private function tempPackage(string $content): string
    {
        $path = tempnam(sys_get_temp_dir(), 'tcs-pkg-');
        file_put_contents($path, $content);
        return $path;
    }

    private function releaseWithDigest(string $digest, string $checksumUrl = ''): array
    {
        return array(
            'asset_download_url' => 'https://github.com/yuchen0x1/baidu-tieba-yunqiandao/releases/download/v5.08/package.zip',
            'asset_digest' => $digest,
            'asset_checksum_url' => $checksumUrl
        );
    }

    public function testChecksumAssetUrlIsSelectedFromRelease(): void
    {
        $release = ProjectUpdater::parseRelease($this->payload());
        $this->assertSame(
            'https://github.com/yuchen0x1/baidu-tieba-yunqiandao/releases/download/v5.08/package.zip.sha256',
            $release['asset_checksum_url']
        );
    }

    public function testPackageWithMatchingDigestIsAccepted(): void
    {
        $content = 'PK' . str_repeat('package-bytes', 32);
        $path = $this->tempPackage($content);
        try {
            $release = $this->releaseWithDigest('sha256:' . hash('sha256', $content));
            $assetUrl = $release['asset_download_url'];
            ProjectUpdater::verifyPackageDigest($path, $release, $assetUrl);
            $this->assertTrue(true);
        } finally {
            @unlink($path);
        }
    }

    public function testPackageWithMismatchedDigestIsRejected(): void
    {
        $path = $this->tempPackage('tampered-content');
        try {
            $release = $this->releaseWithDigest('sha256:' . str_repeat('0', 64));
            $this->expectException(RuntimeException::class);
            $this->expectExceptionMessage('SHA-256 校验失败');
            ProjectUpdater::verifyPackageDigest($path, $release, $release['asset_download_url']);
        } finally {
            @unlink($path);
        }
    }

    public function testFallbackChannelDownloadIsRejectedWithoutDigest(): void
    {
        $path = $this->tempPackage('source-archive');
        try {
            $release = $this->releaseWithDigest('sha256:' . hash('sha256', 'source-archive'));
            $this->expectException(RuntimeException::class);
            $this->expectExceptionMessage('回退通道');
            ProjectUpdater::verifyPackageDigest($path, $release, 'https://api.github.com/repos/yuchen0x1/baidu-tieba-yunqiandao/zipball/v5.08.zip');
        } finally {
            @unlink($path);
        }
    }

    public function testMissingTrustedDigestBlocksInstall(): void
    {
        $path = $this->tempPackage('package-without-digest');
        try {
            // digest 与 .sha256 附件均缺失：无处取得可信摘要，必须拒绝
            $release = $this->releaseWithDigest('', '');
            $this->expectException(RuntimeException::class);
            $this->expectExceptionMessage('摘要');
            ProjectUpdater::verifyPackageDigest($path, $release, $release['asset_download_url']);
        } finally {
            @unlink($path);
        }
    }
}
