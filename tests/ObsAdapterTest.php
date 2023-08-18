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

        return new ObsAdapter(new ObsClient($config), (string) getenv(
            'HUAWEI_CLOUD_BUCKET'
        ) ?: '', 'github-test', null, null, [
            'endpoint' => $config['endpoint'],
        ]);
    }

    private \League\Flysystem\FilesystemAdapter $filesystemAdapter;

    protected function setUp(): void
    {
        if ((string) getenv('MOCK') !== 'false') {
            $this->markTestSkipped('Mock tests enabled');
        }

        $this->filesystemAdapter = self::createFilesystemAdapter();

        parent::setUp();
    }

    public function adapter(): FilesystemAdapter
    {
        return $this->filesystemAdapter;
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        $adapter = $this->adapter();
        $adapter->deleteDirectory('/');
    }

    public function testFetching_unknown_mime_type_of_a_file(): void
    {
        $this->adapter()
            ->write('unknown-mime-type.md5', '', new Config());

        $this->runScenario(function (): void {
            $this->assertSame('binary/octet-stream', $this->adapter()->mimeType('unknown-mime-type.md5')->mimeType());
        });
    }
}
