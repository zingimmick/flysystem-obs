<?php

declare(strict_types=1);

namespace Zing\Flysystem\Obs\Tests;

use League\Flysystem\Config;
use League\Flysystem\DirectoryAttributes;
use League\Flysystem\Visibility;
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

        $this->adapter = new ObsAdapter(new ObsClient($config), $this->getBucket());
        $this->adapter->write('fixture/read.txt', 'read-test', new Config());
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        $this->adapter->deleteDirectory('fixture');
    }

    public function testCopy(): void
    {
        $this->adapter->write('fixture/file.txt', 'write', new Config());
        $this->adapter->copy('fixture/file.txt', 'fixture/copy.txt', new Config());
        self::assertSame('write', $this->adapter->read('fixture/copy.txt'));
    }

    public function testCreateDir(): void
    {
        $this->adapter->createDirectory('fixture/path', new Config());
        self::assertEquals([new DirectoryAttributes('fixture/path'),
        ], iterator_to_array($this->adapter->listContents('fixture/path', false)));
    }

    public function testSetVisibility(): void
    {
        $this->adapter->write('fixture/file.txt', 'write', new Config());
        self::assertSame(Visibility::PRIVATE, $this->adapter->visibility('fixture/file.txt')['visibility']);
        $this->adapter->setVisibility('fixture/file.txt', Visibility::PUBLIC);
        self::assertSame(Visibility::PUBLIC, $this->adapter->visibility('fixture/file.txt')['visibility']);
    }

    public function testRename(): void
    {
        $this->adapter->write('fixture/from.txt', 'write', new Config());
        self::assertTrue((bool) $this->adapter->fileExists('fixture/from.txt'));
        self::assertFalse((bool) $this->adapter->fileExists('fixture/to.txt'));
        $this->adapter->move('fixture/from.txt', 'fixture/to.txt', new Config());
        self::assertFalse((bool) $this->adapter->fileExists('fixture/from.txt'));
        self::assertSame('write', $this->adapter->read('fixture/to.txt'));
        $this->adapter->delete('fixture/to.txt');
    }

    public function testDeleteDir(): void
    {
        $this->adapter->deleteDirectory('fixture');
        self::assertEmpty(iterator_to_array($this->adapter->listContents('fixture', false)));
    }

    public function testWriteStream(): void
    {
        $this->adapter->writeStream('fixture/file.txt', $this->streamFor('write')->detach(), new Config());
        self::assertSame('write', $this->adapter->read('fixture/file.txt'));
    }

    public function testDelete(): void
    {
        $this->adapter->writeStream('fixture/file.txt', $this->streamFor('test')->detach(), new Config());
        self::assertTrue((bool) $this->adapter->fileExists('fixture/file.txt'));
        $this->adapter->delete('fixture/file.txt');
        self::assertFalse((bool) $this->adapter->fileExists('fixture/file.txt'));
    }

    public function testWrite(): void
    {
        $this->adapter->write('fixture/file.txt', 'write', new Config());
        self::assertSame('write', $this->adapter->read('fixture/file.txt'));
    }

    public function testRead(): void
    {
        self::assertSame('read-test', $this->adapter->read('fixture/read.txt'));
    }

    public function testReadStream(): void
    {
        self::assertSame('read-test', stream_get_contents($this->adapter->readStream('fixture/read.txt')));
    }

    public function testGetVisibility(): void
    {
        self::assertSame(Visibility::PRIVATE, $this->adapter->visibility('fixture/read.txt')->visibility());
    }

    public function testListContents(): void
    {
        self::assertNotEmpty(iterator_to_array($this->adapter->listContents('fixture', false)));
        self::assertEmpty(iterator_to_array($this->adapter->listContents('path1', false)));
        $this->adapter->write('fixture/path/file.txt', 'test', new Config());
        $this->adapter->listContents('a', true);
    }

    public function testGetSize(): void
    {
        self::assertSame(9, $this->adapter->fileSize('fixture/read.txt')->fileSize());
    }

    public function testGetTimestamp(): void
    {
        self::assertGreaterThan(time() - 10, $this->adapter->lastModified('fixture/read.txt')->lastModified());
    }

    public function testGetMimetype(): void
    {
        self::assertSame('text/plain', $this->adapter->mimeType('fixture/read.txt')->mimeType());
    }

    public function testHas(): void
    {
        self::assertTrue((bool) $this->adapter->fileExists('fixture/read.txt'));
    }
}
