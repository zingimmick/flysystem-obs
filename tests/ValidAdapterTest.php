<?php

declare(strict_types=1);

namespace Zing\Flysystem\Obs\Tests;

use League\Flysystem\AdapterInterface;
use League\Flysystem\Config;
use Obs\ObsClient;
use Zing\Flysystem\Obs\ObsAdapter;

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
        if ((string) getenv('MOCK') !== 'false') {
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

    protected function tearDown(): void
    {
        parent::tearDown();

        $this->adapter->deleteDir('fixture');
    }

    public function testUpdate(): void
    {
        $this->adapter->update('fixture/file.txt', 'update', new Config());
        self::assertSame('update', $this->adapter->read('fixture/file.txt')['contents']);
    }

    public function testUpdateStream(): void
    {
        $this->adapter->write('fixture/file.txt', 'write', new Config());
        $this->adapter->updateStream('fixture/file.txt', $this->streamFor('update')->detach(), new Config());
        self::assertSame('update', $this->adapter->read('fixture/file.txt')['contents']);
    }

    public function testCopy(): void
    {
        $this->adapter->write('fixture/file.txt', 'write', new Config());
        $this->adapter->copy('fixture/file.txt', 'fixture/copy.txt');
        self::assertSame('write', $this->adapter->read('fixture/copy.txt')['contents']);
    }

    public function testCreateDir(): void
    {
        $this->adapter->createDir('fixture/path', new Config());
        self::assertSame([], $this->adapter->listContents('fixture/path'));
    }

    public function testSetVisibility(): void
    {
        $this->adapter->write('fixture/file.txt', 'write', new Config());
        self::assertSame(
            AdapterInterface::VISIBILITY_PRIVATE,
            $this->adapter->getVisibility('fixture/file.txt')['visibility']
        );
        $this->adapter->setVisibility('fixture/file.txt', AdapterInterface::VISIBILITY_PUBLIC);
        self::assertSame(
            AdapterInterface::VISIBILITY_PUBLIC,
            $this->adapter->getVisibility('fixture/file.txt')['visibility']
        );
    }

    public function testRename(): void
    {
        $this->adapter->write('fixture/from.txt', 'write', new Config());
        self::assertTrue((bool) $this->adapter->has('fixture/from.txt'));
        self::assertFalse((bool) $this->adapter->has('fixture/to.txt'));
        $this->adapter->rename('fixture/from.txt', 'fixture/to.txt');
        self::assertFalse((bool) $this->adapter->has('fixture/from.txt'));
        self::assertSame('write', $this->adapter->read('fixture/to.txt')['contents']);
        $this->adapter->delete('fixture/to.txt');
    }

    public function testDeleteDir(): void
    {
        self::assertTrue($this->adapter->deleteDir('fixture'));
        self::assertEmpty($this->adapter->listContents('fixture'));
    }

    public function testWriteStream(): void
    {
        $this->adapter->writeStream('fixture/file.txt', $this->streamFor('write')->detach(), new Config());
        self::assertSame('write', $this->adapter->read('fixture/file.txt')['contents']);
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
        self::assertSame($visibility, $this->adapter->getVisibility('fixture/file.txt')['visibility']);
    }

    public function testWriteStreamWithExpires(): void
    {
        $this->adapter->writeStream('fixture/file.txt', $this->streamFor('write')->detach(), new Config([
            'Expires' => 20,
        ]));
        self::assertSame('write', $this->adapter->read('fixture/file.txt')['contents']);
    }

    public function testWriteStreamWithMimetype(): void
    {
        $this->adapter->writeStream('fixture/file.txt', $this->streamFor('write')->detach(), new Config([
            'mimetype' => 'image/png',
        ]));
        self::assertSame('image/png', $this->adapter->getMimetype('fixture/file.txt')['mimetype']);
    }

    public function testDelete(): void
    {
        $this->adapter->writeStream('fixture/file.txt', $this->streamFor('test')->detach(), new Config());
        self::assertTrue((bool) $this->adapter->has('fixture/file.txt'));
        $this->adapter->delete('fixture/file.txt');
        self::assertFalse((bool) $this->adapter->has('fixture/file.txt'));
    }

    public function testWrite(): void
    {
        $this->adapter->write('fixture/file.txt', 'write', new Config());
        self::assertSame('write', $this->adapter->read('fixture/file.txt')['contents']);
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
        self::assertNotEmpty($this->adapter->listContents('fixture'));
        self::assertEmpty($this->adapter->listContents('path1'));
        $this->adapter->write('fixture/path/file.txt', 'test', new Config());
        $this->adapter->listContents('a', true);
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
        self::assertSame(200, $info[0]);
        self::assertSame(100, $info[1]);
    }
}
