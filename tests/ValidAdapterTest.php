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
    private $obsAdapter;

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

        $this->obsAdapter = new ObsAdapter(new ObsClient($config), $this->getEndpoint(), $this->getBucket());
        $this->obsAdapter->write('fixture/read.txt', 'read-test', new Config());
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        $this->obsAdapter->deleteDir('fixture');
    }

    public function testUpdate(): void
    {
        $this->obsAdapter->update('fixture/file.txt', 'update', new Config());
        static::assertSame('update', $this->obsAdapter->read('fixture/file.txt')['contents']);
    }

    public function testUpdateStream(): void
    {
        $this->obsAdapter->write('fixture/file.txt', 'write', new Config());
        $this->obsAdapter->updateStream('fixture/file.txt', $this->streamFor('update')->detach(), new Config());
        static::assertSame('update', $this->obsAdapter->read('fixture/file.txt')['contents']);
    }

    public function testCopy(): void
    {
        $this->obsAdapter->write('fixture/file.txt', 'write', new Config());
        $this->obsAdapter->copy('fixture/file.txt', 'fixture/copy.txt');
        static::assertSame('write', $this->obsAdapter->read('fixture/copy.txt')['contents']);
    }

    public function testCreateDir(): void
    {
        $this->obsAdapter->createDir('fixture/path', new Config());
        static::assertFalse($this->obsAdapter->has('fixture/path'));
    }

    public function testSetVisibility(): void
    {
        $this->obsAdapter->write('fixture/file.txt', 'write', new Config());
        static::assertSame(
            AdapterInterface::VISIBILITY_PRIVATE,
            $this->obsAdapter->getVisibility('fixture/file.txt')['visibility']
        );
        $this->obsAdapter->setVisibility('fixture/file.txt', AdapterInterface::VISIBILITY_PUBLIC);
        static::assertSame(
            AdapterInterface::VISIBILITY_PUBLIC,
            $this->obsAdapter->getVisibility('fixture/file.txt')['visibility']
        );
    }

    public function testRename(): void
    {
        $this->obsAdapter->write('fixture/from.txt', 'write', new Config());
        static::assertTrue((bool) $this->obsAdapter->has('fixture/from.txt'));
        static::assertFalse((bool) $this->obsAdapter->has('fixture/to.txt'));
        $this->obsAdapter->rename('fixture/from.txt', 'fixture/to.txt');
        static::assertFalse((bool) $this->obsAdapter->has('fixture/from.txt'));
        static::assertSame('write', $this->obsAdapter->read('fixture/to.txt')['contents']);
        $this->obsAdapter->delete('fixture/to.txt');
    }

    public function testDeleteDir(): void
    {
        static::assertTrue($this->obsAdapter->deleteDir('fixture'));
        static::assertEmpty($this->obsAdapter->listContents('fixture'));
        static::assertSame([], $this->obsAdapter->listContents('fixture/path/'));
        $this->obsAdapter->write('fixture/path1/file.txt', 'test', new Config());
        $contents = $this->obsAdapter->listContents('fixture/path1');
        static::assertCount(1, $contents);
        $file = $contents[0];
        static::assertSame('fixture/path1/file.txt', $file['path']);
    }

    public function testWriteStream(): void
    {
        $this->obsAdapter->writeStream('fixture/file.txt', $this->streamFor('write')->detach(), new Config());
        static::assertSame('write', $this->obsAdapter->read('fixture/file.txt')['contents']);
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
        $this->obsAdapter->writeStream('fixture/file.txt', $this->streamFor('write')->detach(), new Config([
            'visibility' => $visibility,
        ]));
        static::assertSame($visibility, $this->obsAdapter->getVisibility('fixture/file.txt')['visibility']);
    }

    public function testWriteStreamWithExpires(): void
    {
        $this->obsAdapter->writeStream('fixture/file.txt', $this->streamFor('write')->detach(), new Config([
            'Expires' => 20,
        ]));
        static::assertSame('write', $this->obsAdapter->read('fixture/file.txt')['contents']);
    }

    public function testWriteStreamWithMimetype(): void
    {
        $this->obsAdapter->writeStream('fixture/file.txt', $this->streamFor('write')->detach(), new Config([
            'mimetype' => 'image/png',
        ]));
        static::assertSame('image/png', $this->obsAdapter->getMimetype('fixture/file.txt')['mimetype']);
    }

    public function testDelete(): void
    {
        $this->obsAdapter->writeStream('fixture/file.txt', $this->streamFor('test')->detach(), new Config());
        static::assertTrue((bool) $this->obsAdapter->has('fixture/file.txt'));
        $this->obsAdapter->delete('fixture/file.txt');
        static::assertFalse((bool) $this->obsAdapter->has('fixture/file.txt'));
    }

    public function testWrite(): void
    {
        $this->obsAdapter->write('fixture/file.txt', 'write', new Config());
        static::assertSame('write', $this->obsAdapter->read('fixture/file.txt')['contents']);
    }

    public function testRead(): void
    {
        static::assertSame('read-test', $this->obsAdapter->read('fixture/read.txt')['contents']);
    }

    public function testReadStream(): void
    {
        static::assertSame(
            'read-test',
            stream_get_contents($this->obsAdapter->readStream('fixture/read.txt')['stream'])
        );
    }

    public function testGetVisibility(): void
    {
        static::assertSame(
            AdapterInterface::VISIBILITY_PRIVATE,
            $this->obsAdapter->getVisibility('fixture/read.txt')['visibility']
        );
    }

    public function testGetMetadata(): void
    {
        static::assertIsArray($this->obsAdapter->getMetadata('fixture/read.txt'));
    }

    public function testListContents(): void
    {
        static::assertNotEmpty($this->obsAdapter->listContents('fixture'));
        static::assertEmpty($this->obsAdapter->listContents('path1'));
        $this->obsAdapter->write('fixture/path/file.txt', 'test', new Config());
        $this->obsAdapter->listContents('a', true);
    }

    public function testGetSize(): void
    {
        static::assertSame(9, $this->obsAdapter->getSize('fixture/read.txt')['size']);
    }

    public function testGetTimestamp(): void
    {
        static::assertGreaterThan(time() - 10, $this->obsAdapter->getTimestamp('fixture/read.txt')['timestamp']);
    }

    public function testGetMimetype(): void
    {
        static::assertSame('text/plain', $this->obsAdapter->getMimetype('fixture/read.txt')['mimetype']);
    }

    public function testHas(): void
    {
        static::assertTrue((bool) $this->obsAdapter->has('fixture/read.txt'));
    }

    public function testSignUrl(): void
    {
        static::assertSame('read-test', file_get_contents($this->obsAdapter->signUrl('fixture/read.txt', 10, [])));
    }

    public function testGetTemporaryUrl(): void
    {
        static::assertSame(
            'read-test',
            file_get_contents($this->obsAdapter->getTemporaryUrl('fixture/read.txt', 10, []))
        );
    }

    public function testImage(): void
    {
        $this->obsAdapter->write(
            'fixture/image.png',
            file_get_contents('https://via.placeholder.com/640x480.png'),
            new Config()
        );
        $info = getimagesize($this->obsAdapter->signUrl('fixture/image.png', 10, [
            'x-image-process' => 'image/crop,w_200,h_100',
        ]));
        static::assertSame(200, $info[0]);
        static::assertSame(100, $info[1]);
    }
}
