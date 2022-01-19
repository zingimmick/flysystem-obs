<?php

declare(strict_types=1);

namespace Zing\Flysystem\Obs\Tests;

use League\Flysystem\AdapterTestUtilities\FilesystemAdapterTestCase;
use League\Flysystem\Config;
use League\Flysystem\FilesystemAdapter;
use Obs\ObsClient;
use Zing\Flysystem\Obs\ObsAdapter;

/**
 * @internal
 */
final class ObsAdapterTest extends FilesystemAdapterTestCase
{
    protected static function createFilesystemAdapter(): FilesystemAdapter
    {
        $config = [
            'key' => (string) getenv('HUAWEI_CLOUD_KEY') ?: '',
            'secret' => (string) getenv('HUAWEI_CLOUD_SECRET') ?: '',
            'bucket' => (string) getenv('HUAWEI_CLOUD_BUCKET') ?: '',
            'endpoint' => (string) getenv('HUAWEI_CLOUD_ENDPOINT') ?: 'obs.cn-east-3.myhuaweicloud.com',
            'path_style' => '',
            'region' => '',
        ];

        return new ObsAdapter(new ObsClient($config), (string) getenv('HUAWEI_CLOUD_BUCKET') ?: '', 'github-test');
    }

    protected function setUp(): void
    {
        if ((string) getenv('MOCK') !== 'false') {
            self::markTestSkipped('Mock tests enabled');
        }

        parent::setUp();
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        $adapter = $this->adapter();
        $adapter->deleteDirectory('/');
    }

    /**
     * @test
     */
    public function fetching_unknown_mime_type_of_a_file(): void
    {
        $this->adapter()
            ->write('unknown-mime-type.md5', '', new Config());

        $this->runScenario(function (): void {
            self::assertSame('binary/octet-stream', $this->adapter()->mimeType('unknown-mime-type.md5')->mimeType());
        });
    }

    public function testMore(): void
    {
        $adapter = $this->adapter();

        $adapter->write('path.txt', 'contents', new Config());
        $this->assertTrue($adapter->fileExists('path.txt'));
        sleep(1);
        $contents = $adapter->read('path.txt');

        $this->assertSame('contents', $contents);
    }
}
