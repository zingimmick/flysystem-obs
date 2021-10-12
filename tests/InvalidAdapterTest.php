<?php

declare(strict_types=1);

namespace Zing\Flysystem\Obs\Tests;

use League\Flysystem\Config;
use League\Flysystem\UnableToCopyFile;
use League\Flysystem\UnableToCreateDirectory;
use League\Flysystem\UnableToDeleteFile;
use League\Flysystem\UnableToMoveFile;
use League\Flysystem\UnableToReadFile;
use League\Flysystem\UnableToRetrieveMetadata;
use League\Flysystem\UnableToSetVisibility;
use League\Flysystem\UnableToWriteFile;
use League\Flysystem\Visibility;
use Obs\ObsClient;
use Obs\ObsException;
use Zing\Flysystem\Obs\ObsAdapter;

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

    private $adapter;

    private $client;

    protected function setUp(): void
    {
        parent::setUp();

        $this->client = new ObsClient(self::CONFIG);
        $this->adapter = new ObsAdapter($this->client, self::CONFIG['endpoint'], self::CONFIG['bucket']);
    }

    public function testCopy(): void
    {
        $this->expectException(UnableToCopyFile::class);
        $this->adapter->copy('file.txt', 'copy.txt', new Config());
    }

    public function testCreateDir(): void
    {
        $this->expectException(UnableToCreateDirectory::class);
        $this->adapter->createDirectory('path', new Config());
    }

    public function testSetVisibility(): void
    {
        $this->expectException(UnableToSetVisibility::class);
        $this->adapter->setVisibility('file.txt', Visibility::PUBLIC);
    }

    public function testRename(): void
    {
        $this->expectException(UnableToMoveFile::class);
        $this->adapter->move('from.txt', 'to.txt', new Config());
    }

    public function testDeleteDir(): void
    {
        $this->expectException(ObsException::class);
        $this->adapter->deleteDirectory('path');
    }

    public function testWriteStream(): void
    {
        $this->expectException(UnableToWriteFile::class);
        $this->adapter->writeStream('file.txt', $this->streamFor('test')->detach(), new Config());
    }

    public function testDelete(): void
    {
        $this->expectException(UnableToDeleteFile::class);
        $this->adapter->delete('file.txt');
    }

    public function testWrite(): void
    {
        $this->expectException(UnableToWriteFile::class);
        $this->adapter->write('file.txt', 'test', new Config());
    }

    public function testRead(): void
    {
        $this->expectException(UnableToReadFile::class);
        $this->adapter->read('file.txt');
    }

    public function testReadStream(): void
    {
        $this->expectException(UnableToReadFile::class);
        $this->adapter->readStream('file.txt');
    }

    public function testGetVisibility(): void
    {
        $this->expectException(UnableToRetrieveMetadata::class);
        $this->adapter->visibility('file.txt')
            ->visibility();
    }

    public function testListContents(): void
    {
        $this->expectException(ObsException::class);
        self::assertEmpty(iterator_to_array($this->adapter->listContents('/', false)));
    }

    public function testGetSize(): void
    {
        $this->expectException(UnableToRetrieveMetadata::class);
        $this->adapter->fileSize('file.txt')
            ->fileSize();
    }

    public function testGetTimestamp(): void
    {
        $this->expectException(UnableToRetrieveMetadata::class);
        $this->adapter->lastModified('file.txt')
            ->lastModified();
    }

    public function testGetMimetype(): void
    {
        $this->expectException(UnableToRetrieveMetadata::class);
        $this->adapter->mimeType('file.txt')
            ->mimeType();
    }

    public function testHas(): void
    {
        self::assertFalse($this->adapter->fileExists('file.txt'));
    }
}
