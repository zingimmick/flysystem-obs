<?php

declare(strict_types=1);

namespace Zing\Flysystem\Obs\Tests;

use League\Flysystem\AdapterInterface;
use League\Flysystem\Config;
use Obs\ObsClient;
use Zing\Flysystem\Obs\ObsAdapter;
use function GuzzleHttp\Psr7\stream_for;

class ValidAdapterTest extends TestCase
{
    private $adapter;

    private function getKey(): string
    {
        return (string) getenv('HUAWEI_CLOUD_KEY') ?: '';
    }

    private function getSecret(): string
    {
        return (string) getenv('HUAWEI_CLOUD_SECRET') ?: '';
    }

    private function getBucket(): string
    {
        return (string) getenv('HUAWEI_CLOUD_BUCKET') ?: '';
    }

    private function getEndpoint(): string
    {
        return (string) getenv('HUAWEI_CLOUD_ENDPOINT') ?: 'obs.cn-east-3.myhuaweicloud.com';
    }

    protected function setUp(): void
    {
        if ((string) getenv('MOCK') === 'true') {
            self::markTestSkipped('Mock tests enabled');
        }

        parent::setUp();

        $config = [
            'key' => $this->getKey(),
            'secret' => $this->getSecret(),
            'bucket' => $this->getBucket(),
            'endpoint' => $this->getEndpoint(),
            'path_style' => '',
            'region' => '',
        ];

        $this->adapter = new ObsAdapter(new ObsClient($config), $this->getEndpoint(), $this->getBucket());
        $this->adapter->write('fixture/read.txt', 'read-test', new Config());
    }

    public function testUpdate(): void
    {
        $this->adapter->update('file.txt', 'update', new Config());
        self::assertSame('update', $this->adapter->read('file.txt')['contents']);
    }

    public function testUpdateStream(): void
    {
        $this->adapter->write('file.txt', 'write', new Config());
        $this->adapter->updateStream('file.txt', stream_for('update')->detach(), new Config());
        self::assertSame('update', $this->adapter->read('file.txt')['contents']);
    }

    public function testCopy(): void
    {
        $this->adapter->write('file.txt', 'write', new Config());
        $this->adapter->copy('file.txt', 'copy.txt');
        self::assertSame('write', $this->adapter->read('copy.txt')['contents']);
    }

    public function testCreateDir(): void
    {
        $this->adapter->createDir('path', new Config());
        self::assertSame([[
            'type' => 'dir',
            'path' => 'path',
        ],
        ], $this->adapter->listContents('path'));
    }

    public function testSetVisibility(): void
    {
        $this->adapter->write('file.txt', 'write', new Config());
        self::assertSame(
            AdapterInterface::VISIBILITY_PRIVATE,
            $this->adapter->getVisibility('file.txt')['visibility']
        );
        $this->adapter->setVisibility('file.txt', AdapterInterface::VISIBILITY_PUBLIC);
        self::assertSame(
            AdapterInterface::VISIBILITY_PUBLIC,
            $this->adapter->getVisibility('file.txt')['visibility']
        );
    }

    public function testRename(): void
    {
        $this->adapter->write('from.txt', 'write', new Config());
        self::assertTrue((bool) $this->adapter->has('from.txt'));
        self::assertFalse((bool) $this->adapter->has('to.txt'));
        $this->adapter->rename('from.txt', 'to.txt');
        self::assertFalse((bool) $this->adapter->has('from.txt'));
        self::assertSame('write', $this->adapter->read('to.txt')['contents']);
        $this->adapter->delete('to.txt');
    }

    public function testDeleteDir(): void
    {
        self::assertTrue($this->adapter->deleteDir('fixture'));
        self::assertEmpty($this->adapter->listContents('fixture'));
    }

    public function testWriteStream(): void
    {
        $this->adapter->writeStream('file.txt', stream_for('write')->detach(), new Config());
        self::assertSame('write', $this->adapter->read('file.txt')['contents']);
    }

    public function testDelete(): void
    {
        $this->adapter->writeStream('file.txt', stream_for('test')->detach(), new Config());
        self::assertTrue((bool) $this->adapter->has('file.txt'));
        $this->adapter->delete('file.txt');
        self::assertFalse((bool) $this->adapter->has('file.txt'));
    }

    public function testWrite(): void
    {
        $this->adapter->write('file.txt', 'write', new Config());
        self::assertSame('write', $this->adapter->read('file.txt')['contents']);
    }

    public function testRead(): void
    {
        self::assertSame('read-test', $this->adapter->read('fixture/read.txt')['contents']);
    }

    public function testReadStream(): void
    {
        self::assertSame('read-test', stream_get_contents($this->adapter->readStream('fixture/read.txt')['stream']));
    }

    public function testGetVisibility(): void
    {
        self::assertSame(
            AdapterInterface::VISIBILITY_PRIVATE,
            $this->adapter->getVisibility('fixture/read.txt')['visibility']
        );
    }

    public function testGetMetadata(): void
    {
        self::assertIsArray($this->adapter->getMetadata('fixture/read.txt'));
    }

    public function testListContents(): void
    {
        self::assertNotEmpty($this->adapter->listContents('path'));
        self::assertEmpty($this->adapter->listContents('path1'));
    }

    public function testGetSize(): void
    {
        self::assertSame(9, $this->adapter->getSize('fixture/read.txt')['size']);
    }

    public function testGetTimestamp(): void
    {
        self::assertGreaterThan(time() - 10, $this->adapter->getTimestamp('fixture/read.txt')['timestamp']);
    }

    public function testGetMimetype(): void
    {
        self::assertSame('text/plain', $this->adapter->getMimetype('fixture/read.txt')['mimetype']);
    }

    public function testHas(): void
    {
        self::assertTrue((bool) $this->adapter->has('fixture/read.txt'));
    }

    public function testSignUrl(): void
    {
        self::assertSame('read-test', file_get_contents($this->adapter->signUrl('fixture/read.txt', 10, [])));
    }

    public function testGetTemporaryUrl(): void
    {
        self::assertSame('read-test', file_get_contents($this->adapter->getTemporaryUrl('fixture/read.txt', 10, [])));
    }
}
