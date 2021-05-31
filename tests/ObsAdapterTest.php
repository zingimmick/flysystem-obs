<?php

declare(strict_types=1);

namespace Zing\Flysystem\Obs\Tests;

use League\Flysystem\AdapterInterface;
use League\Flysystem\Config;
use League\Flysystem\Filesystem;
use Obs\ObsClient;
use Zing\Flysystem\Obs\ObsAdapter;
use Zing\Flysystem\Obs\Plugins\FileUrl;
use Zing\Flysystem\Obs\Plugins\Kernel;
use Zing\Flysystem\Obs\Plugins\SetBucket;
use Zing\Flysystem\Obs\Plugins\SignatureConfig;
use Zing\Flysystem\Obs\Plugins\SignUrl;
use Zing\Flysystem\Obs\Plugins\TemporaryUrl;

class ObsAdapterTest extends TestCase
{
    public function testInvalid(): void
    {
        $config = [
            'key' => 'aW52YWxpZC1rZXk=',
            'secret' => 'aW52YWxpZC1zZWNyZXQ=',
            'bucket' => 'test',
            'endpoint' => 'obs.cn-east-3.myhuaweicloud.com',
            'path_style' => '',
            'region' => '',
        ];
        $obsClient = new ObsClient($config);
        $obsAdapter = new ObsAdapter($obsClient, $config['endpoint'], $config['bucket']);
        $config = new Config();
        self::assertFalse($obsAdapter->write('11', 'test', $config));
        self::assertFalse($obsAdapter->read('11'));
        self::assertFalse($obsAdapter->readStream('11'));
        self::assertFalse($obsAdapter->has('11'));
        self::assertFalse($obsAdapter->setVisibility('11', AdapterInterface::VISIBILITY_PUBLIC));
        self::assertFalse($obsAdapter->getVisibility('11'));
        self::assertFalse($obsAdapter->getSize('11'));
        self::assertFalse($obsAdapter->signUrl('11', 10, [], null));
        self::assertFalse($obsAdapter->getMimetype('11'));
        self::assertFalse($obsAdapter->getTimestamp('11'));
        self::assertFalse($obsAdapter->delete('11'));
        self::assertFalse($obsAdapter->rename('11', '22'));
        self::assertFalse($obsAdapter->copy('11', '22'));
        self::assertFalse($obsAdapter->update('11', 'test', $config));
        $filesystem = new Filesystem($obsAdapter);
        $filesystem->addPlugin(new FileUrl());
        $filesystem->addPlugin(new Kernel());
        $filesystem->addPlugin(new SetBucket());
        $filesystem->addPlugin(new SignatureConfig());
        $filesystem->addPlugin(new SignUrl());
        $filesystem->addPlugin(new TemporaryUrl());
        self::assertInstanceOf(ObsClient::class, $filesystem->kernel());
        self::assertIsArray($filesystem->signatureConfig());
        self::assertIsArray($filesystem->signatureConfig('/'));
    }

    private function getKey()
    {
        return getenv('HUAWEI_CLOUD_KEY') ?: '';
    }

    private function getSecret()
    {
        return getenv('HUAWEI_CLOUD_SECRET') ?: '';
    }

    private function getBucket()
    {
        return getenv('HUAWEI_CLOUD_BUCKET') ?: '';
    }

    private function getEndpoint()
    {
        return getenv('HUAWEI_CLOUD_ENDPOINT') ?: 'obs.cn-east-3.myhuaweicloud.com';
    }

    public function testValid(): void
    {
        $config = [
            'key' => $this->getKey(),
            'secret' => $this->getSecret(),
            'bucket' => $this->getBucket(),
            'endpoint' => $this->getEndpoint(),
            'path_style' => '',
            'region' => '',
        ];
        print base64_encode(json_encode($config));
        $obsClient = new ObsClient($config);
        $obsAdapter = new ObsAdapter($obsClient, $config['endpoint'], $config['bucket']);
        $filesystem = new Filesystem($obsAdapter);
        $filesystem->addPlugin(new FileUrl());
        $filesystem->addPlugin(new Kernel());
        $filesystem->addPlugin(new SetBucket());
        $filesystem->addPlugin(new SignatureConfig());
        $filesystem->addPlugin(new SignUrl());
        $filesystem->addPlugin(new TemporaryUrl());
        self::assertInstanceOf(ObsClient::class, $filesystem->kernel());
        if ($filesystem->has('11')) {
            $filesystem->delete('11');
        }
        if ($filesystem->has('22')) {
            $filesystem->delete('22');
        }
        if ($filesystem->has('33')) {
            $filesystem->delete('33');
        }

        self::assertFalse($filesystem->has('11'));
        $filesystem->put('11', 'test');
        self::assertTrue($filesystem->has('11'));
        self::assertSame('test', $filesystem->read('11'));
        $stream = $filesystem->readStream('11');
        self::assertSame('test', stream_get_contents($stream));
        self::assertIsArray($filesystem->getMetadata('11'));
        self::assertSame(4, $filesystem->getSize('11'));
        self::assertSame('binary/octet-stream', $filesystem->getMimetype('11'));
        self::assertGreaterThan(time() - 10, $filesystem->getTimestamp('11'));
        self::assertSame('test', file_get_contents($filesystem->signUrl('11', 20)));
        self::assertSame('test', file_get_contents($filesystem->getTemporaryUrl('11', 20)));
        self::assertSame(AdapterInterface::VISIBILITY_PRIVATE, $filesystem->getVisibility('11'));
        $filesystem->setVisibility('11', AdapterInterface::VISIBILITY_PUBLIC);
        self::assertSame(AdapterInterface::VISIBILITY_PUBLIC, $filesystem->getVisibility('11'));
        self::assertSame('test', file_get_contents($filesystem->getUrl('11')));
        $filesystem->put('11', 'update');
        self::assertSame('update', $filesystem->read('11'));
        $filesystem->putStream('11', $stream);
        self::assertSame('test', $filesystem->read('11'));
        $filesystem->copy('11', '22');
        self::assertTrue($filesystem->has('22'));
        $filesystem->rename('22', '33');
        self::assertFalse($filesystem->has('22'));
        self::assertTrue($filesystem->has('33'));
        $filesystem->createDir('test-dir');
        $filesystem->put('test-dir/11', 'test');
        self::assertCount(1, $filesystem->listContents('test-dir', true));
        self::assertTrue($filesystem->has('test-dir/11'));
        $filesystem->deleteDir('test-dir');
        self::assertFalse($filesystem->has('test-dir/11'));
        $filesystem->bucket('test');
        self::assertFalse($filesystem->has('11'));
        $filesystem->bucket('zing-test');
        self::assertIsArray($filesystem->signatureConfig());
        $filesystem->delete('11');
    }

    public function testGetUrlWithCdn(): void
    {
        $client = \Mockery::mock(ObsClient::class);
        $obsAdapter = new ObsAdapter($client, '', '', '', [
            'cdn' => 'https://oss.cdn.com',
        ]);
        $filesystem = new Filesystem($obsAdapter);
        $filesystem->addPlugin(new FileUrl());
        self::assertSame('https://oss.cdn.com/test', $filesystem->getUrl('test'));
    }

    public function testGetUrlWithCName(): void
    {
        $client = \Mockery::mock(ObsClient::class);
        $obsAdapter = new ObsAdapter($client, 'https://oss.cdn.com', '', '', [
            'isCName' => true,
        ]);
        $filesystem = new Filesystem($obsAdapter);
        $filesystem->addPlugin(new FileUrl());
        self::assertSame('https://oss.cdn.com/test', $filesystem->getUrl('test'));
    }
}
