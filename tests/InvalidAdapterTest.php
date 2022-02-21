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
    /**
     * @var array<string, string>
     */
    private const CONFIG = [
        'key' => 'aW52YWxpZC1rZXk=',
        'secret' => 'aW52YWxpZC1zZWNyZXQ=',
        'bucket' => 'test',
        'endpoint' => 'obs.cn-east-3.myhuaweicloud.com',
        'path_style' => '',
        'region' => '',
    ];

    /**
     * @var \Zing\Flysystem\Obs\ObsAdapter
     */
    private $adapter;

    /**
     * @var \Obs\ObsClient
     */
    private $client;

    protected function setUp(): void
    {
        parent::setUp();

        $this->client = new ObsClient(self::CONFIG);
        $this->adapter = new ObsAdapter($this->client, self::CONFIG['endpoint'], self::CONFIG['bucket']);
    }

    public function testUpdate(): void
    {
        static::assertFalse($this->adapter->update('file.txt', 'test', new Config()));
    }

    public function testUpdateStream(): void
    {
        static::assertFalse($this->adapter->updateStream('file.txt', $this->streamFor('test')->detach(), new Config()));
    }

    public function testCopy(): void
    {
        static::assertFalse($this->adapter->copy('file.txt', 'copy.txt'));
    }

    public function testCreateDir(): void
    {
        static::assertFalse($this->adapter->createDir('path', new Config()));
    }

    public function testSetVisibility(): void
    {
        static::assertFalse($this->adapter->setVisibility('file.txt', AdapterInterface::VISIBILITY_PUBLIC));
    }

    public function testRename(): void
    {
        static::assertFalse($this->adapter->rename('from.txt', 'to.txt'));
    }

    public function testDeleteDir(): void
    {
        $this->expectException(ObsException::class);
        static::assertFalse($this->adapter->deleteDir('path'));
    }

    public function testWriteStream(): void
    {
        static::assertFalse($this->adapter->writeStream('file.txt', $this->streamFor('test')->detach(), new Config()));
    }

    public function testDelete(): void
    {
        static::assertFalse($this->adapter->delete('file.txt'));
    }

    public function testWrite(): void
    {
        static::assertFalse($this->adapter->write('file.txt', 'test', new Config()));
    }

    public function testRead(): void
    {
        static::assertFalse($this->adapter->read('file.txt'));
    }

    public function testReadStream(): void
    {
        static::assertFalse($this->adapter->readStream('file.txt'));
    }

    public function testGetVisibility(): void
    {
        static::assertFalse($this->adapter->getVisibility('file.txt'));
    }

    public function testGetMetadata(): void
    {
        static::assertFalse($this->adapter->getMetadata('file.txt'));
    }

    public function testListContents(): void
    {
        $this->expectException(ObsException::class);
        static::assertEmpty($this->adapter->listContents());
    }

    public function testGetSize(): void
    {
        static::assertFalse($this->adapter->getSize('file.txt'));
    }

    public function testGetTimestamp(): void
    {
        static::assertFalse($this->adapter->getTimestamp('file.txt'));
    }

    public function testGetMimetype(): void
    {
        static::assertFalse($this->adapter->getMimetype('file.txt'));
    }

    public function testHas(): void
    {
        static::assertFalse($this->adapter->has('file.txt'));
    }

    public function testGetUrl(): void
    {
        static::assertSame('https://test.obs.cn-east-3.myhuaweicloud.com/file.txt', $this->adapter->getUrl('file.txt'));
    }

    public function testSignUrl(): void
    {
        static::assertFalse($this->adapter->signUrl('file.txt', 10, [], null));
    }

    public function testGetTemporaryUrl(): void
    {
        static::assertFalse($this->adapter->getTemporaryUrl('file.txt', 10, [], null));
    }

    public function testSetBucket(): void
    {
        static::assertSame('test', $this->adapter->getBucket());
        $this->adapter->setBucket('bucket');
        static::assertSame('bucket', $this->adapter->getBucket());
    }

    public function testGetClient(): void
    {
        static::assertInstanceOf(ObsClient::class, $this->adapter->getClient());
    }

    public function testGetUrlWithUrl(): void
    {
        $client = \Mockery::mock(ObsClient::class);
        $obsAdapter = new ObsAdapter($client, '', '', '', [
            'url' => 'https://oss.cdn.com',
        ]);
        $filesystem = new Filesystem($obsAdapter);
        $filesystem->addPlugin(new FileUrl());
        static::assertSame('https://oss.cdn.com/test', $filesystem->getUrl('test'));
    }

    public function testGetUrlWithBucketEndpoint(): void
    {
        $client = \Mockery::mock(ObsClient::class);
        $obsAdapter = new ObsAdapter($client, 'https://oss.cdn.com', '', '', [
            'bucket_endpoint' => true,
        ]);
        $filesystem = new Filesystem($obsAdapter);
        $filesystem->addPlugin(new FileUrl());
        static::assertSame('https://oss.cdn.com/test', $filesystem->getUrl('test'));
    }

    public function testGetTemporaryUrlWithUrl(): void
    {
        $obsAdapter = new ObsAdapter($this->client, 'https://oss.cdn.com', '', '', [
            'temporary_url' => 'https://oss.cdn.com',
        ]);
        $filesystem = new Filesystem($obsAdapter);
        $filesystem->addPlugin(new TemporaryUrl());
        static::assertStringStartsWith('https://oss.cdn.com/test', (string) $filesystem->getTemporaryUrl('test', 10));
    }
}
