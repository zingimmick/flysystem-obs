<?php

declare(strict_types=1);

namespace Zing\Flysystem\Obs\Tests;

use League\Flysystem\AdapterInterface;
use League\Flysystem\Config;
use Obs\ObsClient;
use Zing\Flysystem\Obs\ObsAdapter;

class ValidAdapterTest extends TestCase
{
    /**
     * @var \Zing\Flysystem\Obs\ObsAdapter
     */
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
        if ((string) getenv('MOCK') !== 'false') {
            static::markTestSkipped('Mock tests enabled');
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

    protected function tearDown(): void
    {
        parent::tearDown();

        $this->adapter->deleteDir('fixture');
    }

    public function testUpdate(): void
    {
        $this->adapter->update('fixture/file.txt', 'update', new Config());
        static::assertSame('update', $this->adapter->read('fixture/file.txt')['contents']);
    }

    public function testUpdateStream(): void
    {
        $this->adapter->write('fixture/file.txt', 'write', new Config());
        $this->adapter->updateStream('fixture/file.txt', $this->streamFor('update')->detach(), new Config());
        static::assertSame('update', $this->adapter->read('fixture/file.txt')['contents']);
    }

    public function testCopy(): void
    {
        $this->adapter->write('fixture/file.txt', 'write', new Config());
        $this->adapter->copy('fixture/file.txt', 'fixture/copy.txt');
        static::assertSame('write', $this->adapter->read('fixture/copy.txt')['contents']);
    }

    public function testCreateDir(): void
    {
        $this->adapter->createDir('fixture/path', new Config());
        static::assertSame([], $this->adapter->listContents('fixture/path'));
    }

    public function testSetVisibility(): void
    {
        $this->adapter->write('fixture/file.txt', 'write', new Config());
        static::assertSame(
            AdapterInterface::VISIBILITY_PRIVATE,
            $this->adapter->getVisibility('fixture/file.txt')['visibility']
        );
        $this->adapter->setVisibility('fixture/file.txt', AdapterInterface::VISIBILITY_PUBLIC);
        static::assertSame(
            AdapterInterface::VISIBILITY_PUBLIC,
            $this->adapter->getVisibility('fixture/file.txt')['visibility']
        );
    }

    public function testRename(): void
    {
        $this->adapter->write('fixture/from.txt', 'write', new Config());
        static::assertTrue((bool) $this->adapter->has('fixture/from.txt'));
        static::assertFalse((bool) $this->adapter->has('fixture/to.txt'));
        $this->adapter->rename('fixture/from.txt', 'fixture/to.txt');
        static::assertFalse((bool) $this->adapter->has('fixture/from.txt'));
        static::assertSame('write', $this->adapter->read('fixture/to.txt')['contents']);
        $this->adapter->delete('fixture/to.txt');
    }

    public function testDeleteDir(): void
    {
        static::assertTrue($this->adapter->deleteDir('fixture'));
        static::assertEmpty($this->adapter->listContents('fixture'));
    }

    public function testWriteStream(): void
    {
        $this->adapter->writeStream('fixture/file.txt', $this->streamFor('write')->detach(), new Config());
        static::assertSame('write', $this->adapter->read('fixture/file.txt')['contents']);
    }

    /**
     * @return \Iterator<string[]>
     */
    public function provideVisibilities(): \Iterator
    {
        yield [AdapterInterface::VISIBILITY_PUBLIC];
        yield [AdapterInterface::VISIBILITY_PRIVATE];
    }

    /**
     * @dataProvider provideVisibilities
     *
     * @param $visibility
     */
    public function testWriteStreamWithVisibility($visibility): void
    {
        $this->adapter->writeStream('fixture/file.txt', $this->streamFor('write')->detach(), new Config([
            'visibility' => $visibility,
        ]));
        static::assertSame($visibility, $this->adapter->getVisibility('fixture/file.txt')['visibility']);
    }

    public function testWriteStreamWithExpires(): void
    {
        $this->adapter->writeStream('fixture/file.txt', $this->streamFor('write')->detach(), new Config([
            'Expires' => 20,
        ]));
        static::assertSame('write', $this->adapter->read('fixture/file.txt')['contents']);
    }

    public function testWriteStreamWithMimetype(): void
    {
        $this->adapter->writeStream('fixture/file.txt', $this->streamFor('write')->detach(), new Config([
            'mimetype' => 'image/png',
        ]));
        static::assertSame('image/png', $this->adapter->getMimetype('fixture/file.txt')['mimetype']);
    }

    public function testDelete(): void
    {
        $this->adapter->writeStream('fixture/file.txt', $this->streamFor('test')->detach(), new Config());
        static::assertTrue((bool) $this->adapter->has('fixture/file.txt'));
        $this->adapter->delete('fixture/file.txt');
        static::assertFalse((bool) $this->adapter->has('fixture/file.txt'));
    }

    public function testWrite(): void
    {
        $this->adapter->write('fixture/file.txt', 'write', new Config());
        static::assertSame('write', $this->adapter->read('fixture/file.txt')['contents']);
    }

    public function testRead(): void
    {
        static::assertSame('read-test', $this->adapter->read('fixture/read.txt')['contents']);
    }

    public function testReadStream(): void
    {
        static::assertSame('read-test', stream_get_contents($this->adapter->readStream('fixture/read.txt')['stream']));
    }

    public function testGetVisibility(): void
    {
        static::assertSame(
            AdapterInterface::VISIBILITY_PRIVATE,
            $this->adapter->getVisibility('fixture/read.txt')['visibility']
        );
    }

    public function testGetMetadata(): void
    {
        static::assertIsArray($this->adapter->getMetadata('fixture/read.txt'));
    }

    public function testListContents(): void
    {
        static::assertNotEmpty($this->adapter->listContents('fixture'));
        static::assertEmpty($this->adapter->listContents('path1'));
        $this->adapter->write('fixture/path/file.txt', 'test', new Config());
        $this->adapter->listContents('a', true);
    }

    public function testGetSize(): void
    {
        static::assertSame(9, $this->adapter->getSize('fixture/read.txt')['size']);
    }

    public function testGetTimestamp(): void
    {
        static::assertGreaterThan(time() - 10, $this->adapter->getTimestamp('fixture/read.txt')['timestamp']);
    }

    public function testGetMimetype(): void
    {
        static::assertSame('text/plain', $this->adapter->getMimetype('fixture/read.txt')['mimetype']);
    }

    public function testHas(): void
    {
        static::assertTrue((bool) $this->adapter->has('fixture/read.txt'));
    }

    public function testSignUrl(): void
    {
        static::assertSame('read-test', file_get_contents($this->adapter->signUrl('fixture/read.txt', 10, [])));
    }

    public function testGetTemporaryUrl(): void
    {
        static::assertSame('read-test', file_get_contents($this->adapter->getTemporaryUrl('fixture/read.txt', 10, [])));
    }

    public function testImage(): void
    {
        $this->adapter->write(
            'fixture/image.png',
            file_get_contents('https://via.placeholder.com/640x480.png'),
            new Config()
        );
        $info = getimagesize($this->adapter->signUrl('fixture/image.png', 10, [
            'x-image-process' => 'image/crop,w_200,h_100',
        ]));
        static::assertSame(200, $info[0]);
        static::assertSame(100, $info[1]);
    }
}
