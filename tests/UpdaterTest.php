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
                'name' => 'tieba-cloud-sign-web-' . $tag . '.zip',
                'browser_download_url' => 'https://github.com/yuchen0x1/baidu-tieba-yunqiandao/releases/download/' . $tag . '/package.zip',
                'size' => 123,
                'digest' => 'sha256:test'
            ), array(
                'name' => 'tieba-cloud-sign-web-' . $tag . '.zip.sha256',
                'browser_download_url' => 'https://github.com/yuchen0x1/baidu-tieba-yunqiandao/releases/download/' . $tag . '/package.zip.sha256',
                'size' => 100,
                'digest' => ''
            ))
        );
    }

    public function testReleaseIsParsedAndWebAssetIsSelected(): void
    {
        $release = ProjectUpdater::parseRelease($this->payload());
        $this->assertSame('5.08', $release['version']);
        $this->assertSame('tieba-cloud-sign-web-v5.08.zip', $release['asset_name']);
        $this->assertSame(123, $release['asset_size']);
        $this->assertSame('sha256:test', $release['asset_digest']);
    }

    public function testWebPackageIsPreferredAndSidecarsMatchSelectedPackage(): void
    {
        $payload = $this->payload();
        $base = 'https://github.com/yuchen0x1/baidu-tieba-yunqiandao/releases/download/v5.08/';
        $legacy = 'tieba-cloud-sign-windows-v5.08.zip';
        array_unshift($payload['assets'], array('name' => $legacy, 'browser_download_url' => $base . $legacy),
            array('name' => $legacy . '.sha256', 'browser_download_url' => $base . $legacy . '.sha256'),
            array('name' => $legacy . '.sig', 'browser_download_url' => $base . $legacy . '.sig'));
        $release = ProjectUpdater::parseRelease($payload);
        $this->assertSame('tieba-cloud-sign-web-v5.08.zip', $release['asset_name']);
        $this->assertSame($base . 'package.zip.sha256', $release['asset_checksum_url']);
        $this->assertSame('', $release['asset_signature_url'], '不可串用其他附件的签名');
    }

    public function testHistoricalWebArchiveNameRemainsReadable(): void
    {
        $payload = $this->payload();
        foreach ($payload['assets'] as &$asset) { $asset['name'] = str_replace('-web-', '-windows-', $asset['name']); }
        unset($asset);
        $release = ProjectUpdater::parseRelease($payload);
        $this->assertSame('tieba-cloud-sign-windows-v5.08.zip', $release['asset_name']);
        $this->assertNotEmpty($release['asset_checksum_url']);
    }

    public function testUnrelatedApplicationArchivesAreNotWebUpdates(): void
    {
        $payload = $this->payload();
        $payload['assets'][0]['name'] = 'desktop-client.zip';
        $release = ProjectUpdater::parseRelease($payload);
        $this->assertSame('', $release['asset_download_url']);
        $this->assertSame('', $release['asset_checksum_url']);
    }

    public function testPageAssetsAreLimitedToActualFilesInSameRepositoryAndTag(): void
    {
        $base = '/yuchen0x1/baidu-tieba-yunqiandao/releases/download/v7.6.0/';
        $name = 'tieba-cloud-sign-web-v7.6.0.zip';
        $html = '<a href="' . $base . $name . '">Web</a><a href="' . $base . $name . '.sha256">SHA</a>'
            . '<a href="//evil.example/' . $name . '">bad</a>'
            . '<a href="/attacker/repo/releases/download/v7.6.0/' . $name . '">bad</a>'
            . '<a href="/yuchen0x1/baidu-tieba-yunqiandao/releases/download/v7.5.0/' . $name . '">bad</a>';
        $assets = self::method('extractReleaseAssets')->invoke(null, $html, 'v7.6.0');
        $this->assertCount(2, $assets);
        $release = ProjectUpdater::parseRelease(array('tag_name' => 'v7.6.0', 'assets' => $assets));
        $this->assertSame($name, $release['asset_name']);
        $this->assertSame('https://github.com' . $base . $name . '.sha256', $release['asset_checksum_url']);
        $this->assertSame('', $release['asset_signature_url'], '未发现附件时不得虚构签名可用');
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

    public function testSignatureAssetAndVerificationSummaryAreExposed(): void
    {
        $payload = $this->payload();
        $payload['assets'][] = array(
            'name' => 'tieba-cloud-sign-web-v5.08.zip.sig',
            'browser_download_url' => 'https://github.com/yuchen0x1/baidu-tieba-yunqiandao/releases/download/v5.08/package.zip.sig'
        );
        $release = ProjectUpdater::parseRelease($payload);
        $this->assertSame(
            'https://github.com/yuchen0x1/baidu-tieba-yunqiandao/releases/download/v5.08/package.zip.sig',
            $release['asset_signature_url']
        );
        $summary = ProjectUpdater::verificationSummary($release);
        $this->assertTrue($summary['sha256_available']);
        $this->assertTrue($summary['signature_available']);
    }

    public function testRequiredSignatureWithoutPublicKeyIsRejected(): void
    {
        $previousKey = getenv('UPDATE_RELEASE_PUBLIC_KEY');
        $previousRequired = getenv('UPDATE_REQUIRE_SIGNATURE');
        putenv('UPDATE_RELEASE_PUBLIC_KEY');
        putenv('UPDATE_REQUIRE_SIGNATURE=true');
        try {
            $path = $this->tempPackage('signed-package');
            try {
                $this->expectException(RuntimeException::class);
                $this->expectExceptionMessage('未配置 UPDATE_RELEASE_PUBLIC_KEY');
                ProjectUpdater::verifyPackageSignature($path, array());
            } finally {
                @unlink($path);
            }
        } finally {
            $previousKey === false ? putenv('UPDATE_RELEASE_PUBLIC_KEY') : putenv('UPDATE_RELEASE_PUBLIC_KEY=' . $previousKey);
            $previousRequired === false ? putenv('UPDATE_REQUIRE_SIGNATURE') : putenv('UPDATE_REQUIRE_SIGNATURE=' . $previousRequired);
        }
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

    // ---------- GitHub 匿名限流的分类与安全回退（V7.3） ----------

    private static function method(string $name): ReflectionMethod
    {
        $method = new ReflectionMethod('ProjectUpdater', $name);
        $method->setAccessible(true);
        return $method;
    }

    public function testRateLimitResponseIsClassifiedFromHeaders(): void
    {
        $check = self::method('isRateLimitedResponse');
        $github429 = array('status' => 429, 'headers' => array(), 'body' => '');
        $this->assertTrue($check->invoke(null, $github429, true));
        $this->assertTrue($check->invoke(null, $github429, false));
        // GitHub 403 只有带限流特征才算限流，普通 403 保持通用错误
        $plain403 = array('status' => 403, 'headers' => array(), 'body' => 'forbidden');
        $this->assertFalse($check->invoke(null, $plain403, true));
        $quota403 = array('status' => 403, 'headers' => array('x-ratelimit-remaining' => '0'), 'body' => '');
        $this->assertTrue($check->invoke(null, $quota403, true));
        $body403 = array('status' => 403, 'headers' => array(), 'body' => '{"message":"API rate limit exceeded for 1.2.3.4"}');
        $this->assertTrue($check->invoke(null, $body403, true));
        $this->assertFalse($check->invoke(null, $body403, false));
    }

    public function testRateLimitExceptionCarriesSanitizedMetadataOnly(): void
    {
        $create = self::method('createRateLimitException');
        $exception = $create->invoke(null, array(
            'status' => 403,
            'headers' => array(
                'x-ratelimit-limit' => '60',
                'x-ratelimit-remaining' => '0',
                'x-ratelimit-reset' => (string) (time() + 600),
                'retry-after' => '120'
            ),
            'body' => '{"message":"API rate limit exceeded for 1.2.3.4"}'
        ));
        $this->assertInstanceOf(ProjectUpdaterRateLimitException::class, $exception);
        $this->assertSame(403, $exception->getStatusCode());
        $this->assertSame(120, $exception->getRetryAfter());
        $this->assertSame(60, $exception->getLimit());
        $this->assertSame(0, $exception->getRemaining());
        $this->assertTrue($exception->getResetAt() > time());
        // 异常消息绝不携带上游响应正文，避免把限流 IP 等信息带进页面与日志
        $this->assertStringNotContainsString('rate limit exceeded', $exception->getMessage());
        $this->assertStringNotContainsString('1.2.3.4', $exception->getMessage());
    }

    public function testRetryAfterHttpDateIsParsedAndBounded(): void
    {
        $create = self::method('createRateLimitException');
        $exception = $create->invoke(null, array(
            'status' => 429,
            'headers' => array('retry-after' => gmdate('D, d M Y H:i:s', time() + 90) . ' GMT'),
            'body' => ''
        ));
        $this->assertGreaterThan(0, $exception->getRetryAfter());
        $this->assertLessThanOrEqual(604800, $exception->getRetryAfter());
    }

    public function testReleasePageTagExtraction(): void
    {
        $extract = self::method('extractReleaseTag');
        $this->assertSame('v7.4.0', $extract->invoke(null, array(
            'url' => 'https://github.com/yuchen0x1/baidu-tieba-yunqiandao/releases/tag/v7.4.0',
            'body' => '<html>anything</html>'
        )));
        $this->assertSame('v7.4.0', $extract->invoke(null, array(
            'url' => '',
            'body' => '<a href="/yuchen0x1/baidu-tieba-yunqiandao/releases/tag/v7.4.0">V7.4.0</a>'
        )));
        $this->assertSame('', $extract->invoke(null, array(
            'url' => '',
            'body' => '<a href="/attacker/other-repo/releases/tag/v9.9.9">bad</a>'
        )));
        $this->assertSame('', $extract->invoke(null, array(
            'url' => '',
            'body' => '<a href="/yuchen0x1/baidu-tieba-yunqiandao/releases/tag/not-a-version">bad</a>'
        )));
    }

    public function testWebReleaseKeepsMatchingChecksum(): void
    {
        // Web 包使用发布脚本命名，安装前仍强制校验配套 .sha256
        $release = ProjectUpdater::parseRelease(array(
            'tag_name' => 'v7.4.0',
            'name' => '百度贴吧云签到 V7.4.0',
            'body' => 'GitHub API 当前达到匿名请求限额，已通过官方 Release 页面发现此版本。',
            'html_url' => 'https://github.com/yuchen0x1/baidu-tieba-yunqiandao/releases/tag/v7.4.0',
            'zipball_url' => 'https://api.github.com/repos/yuchen0x1/baidu-tieba-yunqiandao/zipball/v7.4.0',
            'published_at' => '',
            'prerelease' => false,
            'assets' => array(
                array('name' => 'tieba-cloud-sign-web-v7.4.0.zip', 'browser_download_url' => 'https://github.com/yuchen0x1/baidu-tieba-yunqiandao/releases/download/v7.4.0/tieba-cloud-sign-web-v7.4.0.zip'),
                array('name' => 'tieba-cloud-sign-web-v7.4.0.zip.sha256', 'browser_download_url' => 'https://github.com/yuchen0x1/baidu-tieba-yunqiandao/releases/download/v7.4.0/tieba-cloud-sign-web-v7.4.0.zip.sha256')
            )
        ));
        $this->assertSame('tieba-cloud-sign-web-v7.4.0.zip', $release['asset_name']);
        $this->assertSame(
            'https://github.com/yuchen0x1/baidu-tieba-yunqiandao/releases/download/v7.4.0/tieba-cloud-sign-web-v7.4.0.zip.sha256',
            $release['asset_checksum_url']
        );
        // 回退通道没有 API digest 字段，只能依赖 .sha256 附件；安装前仍强制校验
        $this->assertSame('', $release['asset_digest']);
    }
}
