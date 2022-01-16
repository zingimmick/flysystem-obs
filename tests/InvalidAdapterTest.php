<?php

declare(strict_types=1);

namespace Zing\Flysystem\Obs\Tests;

use League\Flysystem\Config;
use League\Flysystem\UnableToCheckDirectoryExistence;
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
use Zing\Flysystem\Obs\UnableToGetUrl;

/**
 * @internal
 */
final class InvalidAdapterTest extends TestCase
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
    private $obsAdapter;

    /**
     * @var \Obs\ObsClient
     */
    private $obsClient;

    protected function setUp(): void
    {
        parent::setUp();

        $this->obsClient = new ObsClient(self::CONFIG);
        $this->obsAdapter = new ObsAdapter($this->obsClient, self::CONFIG['bucket'], '');
    }

    public function testCopy(): void
    {
        $this->expectException(UnableToCopyFile::class);
        $this->obsAdapter->copy('file.txt', 'copy.txt', new Config());
    }

    public function testCreateDir(): void
    {
        $this->expectException(UnableToCreateDirectory::class);
        $this->obsAdapter->createDirectory('path', new Config());
    }

    public function testSetVisibility(): void
    {
        $this->expectException(UnableToSetVisibility::class);
        $this->obsAdapter->setVisibility('file.txt', Visibility::PUBLIC);
    }

    public function testRename(): void
    {
        $this->expectException(UnableToMoveFile::class);
        $this->obsAdapter->move('from.txt', 'to.txt', new Config());
    }

    public function testDeleteDir(): void
    {
        $this->expectException(ObsException::class);
        $this->obsAdapter->deleteDirectory('path');
    }

    public function testWriteStream(): void
    {
        $this->expectException(UnableToWriteFile::class);
        $this->obsAdapter->writeStream('file.txt', $this->streamForResource('test'), new Config());
    }

    public function testDelete(): void
    {
        $this->expectException(UnableToDeleteFile::class);
        $this->obsAdapter->delete('file.txt');
    }

    public function testWrite(): void
    {
        $this->expectException(UnableToWriteFile::class);
        $this->obsAdapter->write('file.txt', 'test', new Config());
    }

    public function testRead(): void
    {
        $this->expectException(UnableToReadFile::class);
        $this->obsAdapter->read('file.txt');
    }

    public function testReadStream(): void
    {
        $this->expectException(UnableToReadFile::class);
        $this->obsAdapter->readStream('file.txt');
    }

    public function testGetVisibility(): void
    {
        $this->expectException(UnableToRetrieveMetadata::class);
        $this->obsAdapter->visibility('file.txt')
            ->visibility();
    }

    public function testListContents(): void
    {
        $this->expectException(ObsException::class);
        self::assertEmpty(iterator_to_array($this->obsAdapter->listContents('/', false)));
    }

    public function testGetSize(): void
    {
        $this->expectException(UnableToRetrieveMetadata::class);
        $this->obsAdapter->fileSize('file.txt')
            ->fileSize();
    }

    public function testGetTimestamp(): void
    {
        $this->expectException(UnableToRetrieveMetadata::class);
        $this->obsAdapter->lastModified('file.txt')
            ->lastModified();
    }

    public function testGetMimetype(): void
    {
        $this->expectException(UnableToRetrieveMetadata::class);
        $this->obsAdapter->mimeType('file.txt')
            ->mimeType();
    }

    public function testHas(): void
    {
        self::assertFalse($this->obsAdapter->fileExists('file.txt'));
    }

    public function testBucket(): void
    {
        $obsAdapter = new ObsAdapter($this->obsClient, self::CONFIG['bucket'], '', null, null, [
            'endpoint' => 'http://obs.cdn.com',
        ]);
        self::assertSame('test', $obsAdapter->getBucket());
    }

    public function testSetBucket(): void
    {
        $obsAdapter = new ObsAdapter($this->obsClient, self::CONFIG['bucket'], '', null, null, [
            'endpoint' => 'http://obs.cdn.com',
        ]);
        $obsAdapter->setBucket('new-bucket');
        self::assertSame('new-bucket', $obsAdapter->getBucket());
    }

    public function testGetUrl(): void
    {
        $obsAdapter = new ObsAdapter($this->obsClient, self::CONFIG['bucket'], '', null, null, [
            'endpoint' => 'http://obs.cdn.com',
        ]);
        self::assertSame('http://test.obs.cdn.com/test', $obsAdapter->getUrl('test'));
    }

    public function testGetClient(): void
    {
        $obsAdapter = new ObsAdapter($this->obsClient, self::CONFIG['bucket'], '', null, null, [
            'endpoint' => 'http://obs.cdn.com',
        ]);
        self::assertSame($this->obsClient, $obsAdapter->getClient());
        self::assertSame($this->obsClient, $obsAdapter->kernel());
    }

    public function testGetUrlWithoutSchema(): void
    {
        $obsAdapter = new ObsAdapter($this->obsClient, self::CONFIG['bucket'], '', null, null, [
            'endpoint' => 'obs.cdn.com',
        ]);
        self::assertSame('https://test.obs.cdn.com/test', $obsAdapter->getUrl('test'));
    }

    public function testGetUrlWithoutEndpoint(): void
    {
        $obsAdapter = new ObsAdapter($this->obsClient, self::CONFIG['bucket'], '');
        $this->expectException(UnableToGetUrl::class);
        $this->expectExceptionMessage('Unable to get url with option endpoint missing.');
        $obsAdapter->getUrl('test');
    }

    public function testGetUrlWithUrl(): void
    {
        $obsAdapter = new ObsAdapter($this->obsClient, self::CONFIG['bucket'], '', null, null, [
            'endpoint' => 'https://obs.cdn.com',
            'url' => 'https://obs.cdn.com',
        ]);
        self::assertSame('https://obs.cdn.com/test', $obsAdapter->getUrl('test'));
    }

    public function testGetUrlWithBucketEndpoint(): void
    {
        $obsAdapter = new ObsAdapter($this->obsClient, self::CONFIG['bucket'], '', null, null, [
            'endpoint' => 'https://obs.cdn.com',
            'bucket_endpoint' => true,
        ]);
        self::assertSame('https://obs.cdn.com/test', $obsAdapter->getUrl('test'));
    }

    public function testGetTemporaryUrlWithUrl(): void
    {
        $obsAdapter = new ObsAdapter($this->obsClient, self::CONFIG['bucket'], '', null, null, [
            'endpoint' => 'https://obs.cdn.com',
            'temporary_url' => 'https://obs.cdn.com',
        ]);
        self::assertStringStartsWith('https://obs.cdn.com/test', $obsAdapter->getTemporaryUrl('test', 10));
    }

    public function testDirectoryExists(): void
    {
        $this->expectException(UnableToCheckDirectoryExistence::class);
        $this->obsAdapter->directoryExists('path');
    }
}
