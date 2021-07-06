<?php

declare(strict_types=1);

namespace Zing\Flysystem\Obs\Tests;

use League\Flysystem\AdapterInterface;
use League\Flysystem\Config;
use League\Flysystem\Filesystem;
use Obs\ObsClient;
use Obs\ObsException;
use Zing\Flysystem\Obs\ObsAdapter;
use Zing\Flysystem\Obs\Plugins\FileUrl;
use Zing\Flysystem\Obs\Plugins\TemporaryUrl;

class InvalidAdapterTest extends TestCase
{
    private $adapter;
private $client;
    protected function setUp(): void
    {
        parent::setUp();

        $config = [
            'key' => 'aW52YWxpZC1rZXk=',
            'secret' => 'aW52YWxpZC1zZWNyZXQ=',
            'bucket' => 'test',
            'endpoint' => 'obs.cn-east-3.myhuaweicloud.com',
            'path_style' => '',
            'region' => '',
        ];
        $this->client = new ObsClient($config);
        $this->adapter = new ObsAdapter($this->client, $config['endpoint'], $config['bucket']);
    }

    public function testUpdate(): void
    {
        self::assertFalse($this->adapter->update('file.txt', 'test', new Config()));
    }

    public function testUpdateStream(): void
    {
        self::assertFalse($this->adapter->updateStream('file.txt', $this->streamFor('test')->detach(), new Config()));
    }

    public function testCopy(): void
    {
        self::assertFalse($this->adapter->copy('file.txt', 'copy.txt'));
    }

    public function testCreateDir(): void
    {
        self::assertFalse($this->adapter->createDir('path', new Config()));
    }

    public function testSetVisibility(): void
    {
        self::assertFalse($this->adapter->setVisibility('file.txt', AdapterInterface::VISIBILITY_PUBLIC));
    }

    public function testRename(): void
    {
        self::assertFalse($this->adapter->rename('from.txt', 'to.txt'));
    }

    public function testDeleteDir(): void
    {
        $this->expectException(ObsException::class);
        self::assertFalse($this->adapter->deleteDir('path'));
    }

    public function testWriteStream(): void
    {
        self::assertFalse($this->adapter->writeStream('file.txt', $this->streamFor('test')->detach(), new Config()));
    }

    public function testDelete(): void
    {
        self::assertFalse($this->adapter->delete('file.txt'));
    }

    public function testWrite(): void
    {
        self::assertFalse($this->adapter->write('file.txt', 'test', new Config()));
    }

    public function testRead(): void
    {
        self::assertFalse($this->adapter->read('file.txt'));
    }

    public function testReadStream(): void
    {
        self::assertFalse($this->adapter->readStream('file.txt'));
    }

    public function testGetVisibility(): void
    {
        self::assertFalse($this->adapter->getVisibility('file.txt'));
    }

    public function testGetMetadata(): void
    {
        self::assertFalse($this->adapter->getMetadata('file.txt'));
    }

    public function testListContents(): void
    {
        $this->expectException(ObsException::class);
        self::assertEmpty($this->adapter->listContents());
    }

    public function testGetSize(): void
    {
        self::assertFalse($this->adapter->getSize('file.txt'));
    }

    public function testGetTimestamp(): void
    {
        self::assertFalse($this->adapter->getTimestamp('file.txt'));
    }

    public function testGetMimetype(): void
    {
        self::assertFalse($this->adapter->getMimetype('file.txt'));
    }

    public function testHas(): void
    {
        self::assertFalse($this->adapter->has('file.txt'));
    }

    public function testGetUrl(): void
    {
        self::assertSame('https://test.obs.cn-east-3.myhuaweicloud.com/file.txt', $this->adapter->getUrl('file.txt'));
    }

    public function testSignUrl(): void
    {
        self::assertFalse($this->adapter->signUrl('file.txt', 10, [], null));
    }

    public function testGetTemporaryUrl(): void
    {
        self::assertFalse($this->adapter->getTemporaryUrl('file.txt', 10, [], null));
    }

    public function testSetBucket(): void
    {
        self::assertSame('test', $this->adapter->getBucket());
        $this->adapter->setBucket('bucket');
        self::assertSame('bucket', $this->adapter->getBucket());
    }

    public function testGetClient(): void
    {
        self::assertInstanceOf(ObsClient::class, $this->adapter->getClient());
    }

    public function testGetUrlWithUrl(): void
    {
        $client = \Mockery::mock(ObsClient::class);
        $obsAdapter = new ObsAdapter($client, '', '', '', [
            'url' => 'https://oss.cdn.com',
        ]);
        $filesystem = new Filesystem($obsAdapter);
        $filesystem->addPlugin(new FileUrl());
        self::assertSame('https://oss.cdn.com/test', $filesystem->getUrl('test'));
    }

    public function testGetUrlWithBucketEndpoint(): void
    {
        $client = \Mockery::mock(ObsClient::class);
        $obsAdapter = new ObsAdapter($client, 'https://oss.cdn.com', '', '', [
            'bucket_endpoint' => true,
        ]);
        $filesystem = new Filesystem($obsAdapter);
        $filesystem->addPlugin(new FileUrl());
        self::assertSame('https://oss.cdn.com/test', $filesystem->getUrl('test'));
    }

    public function testGetTemporaryUrlWithUrl(): void
    {
        $obsAdapter = new ObsAdapter($this->client, 'https://oss.cdn.com', '', '', [
            'temporary_url' => 'https://oss.cdn.com',
        ]);
        $filesystem = new Filesystem($obsAdapter);
        $filesystem->addPlugin(new TemporaryUrl());
        self::assertStringStartsWith('https://oss.cdn.com/test',(string)$filesystem->getTemporaryUrl('test',10));
    }
}
