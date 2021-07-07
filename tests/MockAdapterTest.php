<?php

declare(strict_types=1);

namespace Zing\Flysystem\Obs\Tests;

use League\Flysystem\Config;
use League\Flysystem\DirectoryAttributes;
use League\Flysystem\FileAttributes;
use League\Flysystem\Visibility;
use Mockery;
use Obs\Internal\Common\Model;
use Obs\ObsClient;
use Obs\ObsException;
use Zing\Flysystem\Obs\ObsAdapter;

class MockAdapterTest extends TestCase
{
    private $client;

    private $adapter;

    protected function setUp(): void
    {
        parent::setUp();

        $this->client = Mockery::mock(ObsClient::class);
        $this->adapter = new ObsAdapter($this->client, 'test');
        $this->mockPutObject('fixture/read.txt', 'read-test');
        $this->adapter->write('fixture/read.txt', 'read-test', new Config());
    }

    private function mockPutObject($path, $body): void
    {
        $this->client->shouldReceive('putObject')
            ->withArgs([[
                'ContentType' => 'text/plain',
                'Bucket' => 'test',
                'Key' => $path,
                'Body' => $body,
            ],
            ])->andReturn(new Model());
    }

    public function testCopy(): void
    {
        $this->mockPutObject('file.txt', 'write');
        $this->adapter->write('file.txt', 'write', new Config());
        $this->client->shouldReceive('copyObject')
            ->withArgs([[
                'Bucket' => 'test',
                'Key' => 'copy.txt',
                'CopySource' => 'test/file.txt',
                'MetadataDirective' => 'COPY',
            ],
            ])->andReturn(new Model());
        $this->adapter->copy('file.txt', 'copy.txt', new Config());
        $this->mockGetObject('copy.txt', 'write');
        self::assertSame('write', $this->adapter->read('copy.txt'));
    }

    private function mockGetObject($path, $body): void
    {
        $this->client->shouldReceive('getObject')
            ->withArgs([[
                'Bucket' => 'test',
                'Key' => $path,
            ],
            ])->andReturn(new Model([
                'Body' => $this->streamFor($body),
            ]));
    }

    public function testCreateDir(): void
    {
        $this->client->shouldReceive('putObject')
            ->withArgs([[
                'Bucket' => 'test',
                'Key' => 'path/',
                'Body' => null,
            ],
            ])->andReturn(new Model());
        $this->adapter->createDirectory('path', new Config());
        $this->client->shouldReceive('listObjects')
            ->withArgs([[
                'Bucket' => 'test',
                'Delimiter' => '/',
                'Prefix' => 'path/',
                'MaxKeys' => 1000,
                'Marker' => '',
            ],
            ])->andReturn(new Model([
                'NextMarker' => '',
                'Contents' => [[
                    'Key' => 'path/',
                    'LastModified' => '2021-05-31T06:52:31.942Z',
                    'ETag' => 'd41d8cd98f00b204e9800998ecf8427e',
                    'Size' => 0,
                    'StorageClass' => 'STANDARD_IA',
                    'Owner' => [
                        'DisplayName' => 'zingimmick',
                        'ID' => '0c85ae1126000f380f21c00e77706640',
                    ],
                ],
                ],
            ]));
        $this->client->shouldReceive('getObjectMetadata')
            ->withArgs([[
                'Bucket' => 'test',
                'Key' => 'path/',
            ],
            ])->andReturn(new Model(
                [
                    'ContentLength' => 0,
                    'Date' => 'Mon, 31 May 2021 06:52:32 GMT',
                    'RequestId' => '00000179C13207EF9217A7F5589D2DC6',
                    'Id2' => '32AAAQAAEAABAAAQAAEAABAAAQAAEAABCSvXM+dHYwFYYJv2m9y5LibcMVibe3QN',
                    'Reserved' => 'amazon, aws and amazon web services are trademarks or registered trademarks of Amazon Technologies, Inc',
                    'Expiration' => '',
                    'LastModified' => 'Mon, 31 May 2021 06:52:31 GMT',
                    'ContentType' => 'binary/octet-stream',
                    'ETag' => 'd41d8cd98f00b204e9800998ecf8427e',
                    'VersionId' => '',
                    'WebsiteRedirectLocation' => '',
                    'StorageClass' => 'STANDARD_IA',
                    'AllowOrigin' => '',
                    'MaxAgeSeconds' => '',
                    'ExposeHeader' => '',
                    'AllowMethod' => '',
                    'AllowHeader' => '',
                    'Restore' => '',
                    'SseKms' => '',
                    'SseKmsKey' => '',
                    'SseC' => '',
                    'SseCKeyMd5' => '',
                    'Metadata' => [],
                    'HttpStatusCode' => 200,
                    'Reason' => 'OK',
                ]
            ));
        self::assertEquals(
            [new DirectoryAttributes('path')],
            iterator_to_array($this->adapter->listContents('path', false))
        );
    }

    public function testSetVisibility(): void
    {
        $this->mockPutObject('file.txt', 'write');
        $this->adapter->write('file.txt', 'write', new Config());
        $this->client->shouldReceive('getObjectAcl')
            ->withArgs([[
                'Bucket' => 'test',
                'Key' => 'file.txt',
            ],
            ])
            ->andReturns(new Model([
                'ContentLength' => '508',
                'Date' => 'Mon, 31 May 2021 06:52:31 GMT',
                'RequestId' => '00000179C132050392179DB73EB80FFF',
                'Id2' => '32AAAQAAEAABAAAQAAEAABAAAQAAEAABCS7X7CQo6PJncbE/Rw7pAST9+g4eSFFj',
                'Reserved' => 'amazon, aws and amazon web services are trademarks or registered trademarks of Amazon Technologies, Inc',
                'Owner' => [
                    'DisplayName' => 'zingimmick',
                    'ID' => '0c85ae1126000f380f21c00e77706640',
                ],
                'Grants' => [[
                    'Grantee' => [
                        'DisplayName' => 'zingimmick',
                        'ID' => '0c85ae1126000f380f21c00e77706640',
                        'URI' => '',
                        'Permission' => 'FULL_CONTROL',
                    ],
                    'VersionId' => '',
                    'HttpStatusCode' => 200,
                    'Reason' => 'OK',
                ],
                ],
            ]), new Model([
                'ContentLength' => '700',
                'Date' => 'Mon, 31 May 2021 06:52:31 GMT',
                'RequestId' => '00000179C132055792179EAE74DFD216',
                'Id2' => '32AAAQAAEAABAAAQAAEAABAAAQAAEAABCSdxGpEHY4PlHymn9n5tgYbtJp4AkMer',
                'Reserved' => 'amazon, aws and amazon web services are trademarks or registered trademarks of Amazon Technologies, Inc',
                'Owner' => [
                    'DisplayName' => 'zingimmick',
                    'ID' => '0c85ae1126000f380f21c00e77706640',
                ],
                'Grants' => [
                    [
                        'Grantee' => [
                            'DisplayName' => 'zingimmick',
                            'ID' => '0c85ae1126000f380f21c00e77706640',
                            'URI' => '',
                        ],
                        'Permission' => 'FULL_CONTROL',
                    ],
                    [
                        'Grantee' => [
                            'DisplayName' => '',
                            'ID' => '',
                            'URI' => 'http://acs.amazonaws.com/groups/global/AllUsers',
                        ],
                        'Permission' => 'READ',
                    ],
                ],
                'VersionId' => '',
                'HttpStatusCode' => 200,
                'Reason' => 'OK',
            ]));
        self::assertSame(Visibility::PRIVATE, $this->adapter->visibility('file.txt')->visibility());
        $this->client->shouldReceive('setObjectAcl')
            ->withArgs([[
                'Bucket' => 'test',
                'Key' => 'file.txt',
                'ACL' => 'public-read',
            ],
            ])->andReturn(new Model([
                'ContentLength' => '0',
                'Date' => 'Mon, 31 May 2021 06:52:31 GMT',
                'RequestId' => '00000179C132053492179E666378BF10',
                'Id2' => '32AAAUgAIAABAAAQAAEAABAAAQAAEAABCSFbUsDzX172DxJwfaphYILIunSuoAAR',
                'Reserved' => 'amazon, aws and amazon web services are trademarks or registered trademarks of Amazon Technologies, Inc',
                'HttpStatusCode' => 200,
                'Reason' => 'OK',
            ]));
        $this->adapter->setVisibility('file.txt', Visibility::PUBLIC);

        self::assertSame(Visibility::PUBLIC, $this->adapter->visibility('file.txt')['visibility']);
    }

    public function testRename(): void
    {
        $this->mockPutObject('from.txt', 'write');
        $this->adapter->write('from.txt', 'write', new Config());
        $this->mockGetMetadata('from.txt');
        self::assertTrue((bool) $this->adapter->fileExists('from.txt'));
        $this->client->shouldReceive('getObjectMetadata')
            ->withArgs([[
                'Bucket' => 'test',
                'Key' => 'to.txt',
            ],
            ])->andThrow(new ObsException());
        self::assertFalse((bool) $this->adapter->fileExists('to.txt'));
        $this->client->shouldReceive('copyObject')
            ->withArgs([[
                'Bucket' => 'test',
                'Key' => 'to.txt',
                'CopySource' => 'test/from.txt',
                'MetadataDirective' => 'COPY',
            ],
            ])->andReturn(new Model());
        $this->client->shouldReceive('deleteObject')
            ->withArgs([[
                'Bucket' => 'test',
                'Key' => 'from.txt',
            ],
            ])->andReturn(new Model());
        $this->adapter->move('from.txt', 'to.txt', new Config());
        $this->client->shouldReceive('getObjectMetadata')
            ->withArgs([[
                'Bucket' => 'test',
                'Key' => 'from.txt',
            ],
            ])->andThrow(new ObsException());
        self::assertFalse((bool) $this->adapter->fileExists('from.txt'));
        $this->mockGetObject('to.txt', 'write');
        self::assertSame('write', $this->adapter->read('to.txt'));
        $this->client->shouldReceive('deleteObject')
            ->withArgs([[
                'Bucket' => 'test',
                'Key' => 'to.txt',
            ],
            ])->andReturn(new Model());
        $this->adapter->delete('to.txt');
    }

    public function testDeleteDir(): void
    {
        $this->client->shouldReceive('listObjects')
            ->withArgs([[
                'Bucket' => 'test',
                'Delimiter' => '/',
                'Prefix' => 'path/',
                'MaxKeys' => 1000,
                'Marker' => '',
            ],
            ])->andReturn(new Model([
                'ContentLength' => '864',
                'Date' => 'Mon, 31 May 2021 06:52:32 GMT',
                'RequestId' => '00000179C13207949217A6C3460097BF',
                'Id2' => '32AAAQAAEAABAAAQAAEAABAAAQAAEAABCSeDHUY9dqA1oi7BKX+IbcUaoAQHmnMG',
                'Reserved' => 'amazon, aws and amazon web services are trademarks or registered trademarks of Amazon Technologies, Inc',
                'IsTruncated' => false,
                'Marker' => '',
                'NextMarker' => '',
                'Contents' => [[
                    'Key' => 'path/',
                    'LastModified' => '2021-05-31T06:52:31.942Z',
                    'ETag' => '"d41d8cd98f00b204e9800998ecf8427e"',
                    'Size' => 0,
                    'StorageClass' => 'STANDARD_IA',
                    'Owner' => [
                        'DisplayName' => 'zingimmick',
                        'ID' => '0c85ae1126000f380f21c00e77706640',
                    ],
                ], [
                    'Key' => 'path/file.txt',
                    'LastModified' => '2021-05-31T06:52:32.001Z',
                    'ETag' => '"098f6bcd4621d373cade4e832627b4f6"',
                    'Size' => 4,
                    'StorageClass' => 'STANDARD_IA',
                    'Owner' => [
                        'DisplayName' => 'zingimmick',
                        'ID' => '0c85ae1126000f380f21c00e77706640',
                    ],
                ],
                ],
                'Name' => 'test',
                'Prefix' => 'path/',
                'Delimiter' => '/',
                'MaxKeys' => 1000,
                'CommonPrefixes' => [],
                'Location' => 'cn-east-3',
                'HttpStatusCode' => 200,
                'Reason' => 'OK',
            ]));
        $this->mockGetMetadata('path/');
        $this->mockGetMetadata('path/file.txt');
        $this->client->shouldReceive('getObjectMetadata')
            ->withArgs([[
                'Bucket' => 'test',
                'Key' => 'path',
            ],
            ])->andThrow(new ObsException());
        $this->client->shouldReceive('deleteObject')
            ->withArgs([[
                'Bucket' => 'test',
                'Key' => 'path',
            ],
            ])->andReturn(new Model());
        $this->client->shouldReceive('deleteObject')
            ->withArgs([[
                'Bucket' => 'test',
                'Key' => 'path/file.txt',
            ],
            ])->andReturn(new Model());
        $this->adapter->deleteDirectory('path');
        self::assertTrue(true);
    }

    public function testWriteStream(): void
    {
        $this->mockPutObject('file.txt', 'write');
        $this->adapter->writeStream('file.txt', $this->streamFor('write')->detach(), new Config());
        $this->mockGetObject('file.txt', 'write');
        self::assertSame('write', $this->adapter->read('file.txt'));
    }

    public function testDelete(): void
    {
        $this->mockPutObject('file.txt', 'write');
        $this->adapter->writeStream('file.txt', $this->streamFor('write')->detach(), new Config());
        $this->mockGetMetadata('file.txt');
        self::assertTrue((bool) $this->adapter->fileExists('file.txt'));
        $this->client->shouldReceive('deleteObject')
            ->withArgs([[
                'Bucket' => 'test',
                'Key' => 'file.txt',
            ],
            ])->andReturn(new Model());
        $this->adapter->delete('file.txt');
        $this->client->shouldReceive('getObjectMetadata')
            ->withArgs([[
                'Bucket' => 'test',
                'Key' => 'file.txt',
            ],
            ])->andThrow(new ObsException());
        self::assertFalse((bool) $this->adapter->fileExists('file.txt'));
    }

    public function testWrite(): void
    {
        $this->mockPutObject('file.txt', 'write');
        $this->adapter->write('file.txt', 'write', new Config());
        $this->mockGetObject('file.txt', 'write');
        self::assertSame('write', $this->adapter->read('file.txt'));
    }

    public function testRead(): void
    {
        $this->mockGetObject('fixture/read.txt', 'read-test');
        self::assertSame('read-test', $this->adapter->read('fixture/read.txt'));
    }

    public function testReadStream(): void
    {
        $this->mockGetObject('fixture/read.txt', 'read-test');

        self::assertSame('read-test', stream_get_contents($this->adapter->readStream('fixture/read.txt')));
    }

    public function testGetVisibility(): void
    {
        $this->client->shouldReceive('getObjectAcl')
            ->withArgs([[
                'Bucket' => 'test',
                'Key' => 'fixture/read.txt',
            ],
            ])
            ->andReturn(new Model([
                'ContentLength' => '508',
                'Date' => 'Mon, 31 May 2021 06:52:31 GMT',
                'RequestId' => '00000179C132050392179DB73EB80FFF',
                'Id2' => '32AAAQAAEAABAAAQAAEAABAAAQAAEAABCS7X7CQo6PJncbE/Rw7pAST9+g4eSFFj',
                'Reserved' => 'amazon, aws and amazon web services are trademarks or registered trademarks of Amazon Technologies, Inc',
                'Owner' => [
                    'DisplayName' => 'zingimmick',
                    'ID' => '0c85ae1126000f380f21c00e77706640',
                ],
                'Grants' => [[
                    'Grantee' => [
                        'DisplayName' => 'zingimmick',
                        'ID' => '0c85ae1126000f380f21c00e77706640',
                        'URI' => '',
                        'Permission' => 'FULL_CONTROL',
                    ],
                    'VersionId' => '',
                    'HttpStatusCode' => 200,
                    'Reason' => 'OK',
                ],
                ],
            ]));
        self::assertSame(Visibility::PRIVATE, $this->adapter->visibility('fixture/read.txt')['visibility']);
    }

    private function mockGetMetadata($path): void
    {
        $this->client->shouldReceive('getObjectMetadata')
            ->once()
            ->withArgs([[
                'Bucket' => 'test',
                'Key' => $path,
            ],
            ])->andReturn(new Model([
                'ContentLength' => 9,
                'Date' => 'Mon, 31 May 2021 06:52:32 GMT',
                'RequestId' => '00000179C13207FD9217A8324EE5B315',
                'Id2' => '32AAAQAAEAABAAAQAAEAABAAAQAAEAABCSOcy2Ri+ilXxrwc5JIVg6ifOFbyOU/p',
                'Reserved' => 'amazon, aws and amazon web services are trademarks or registered trademarks of Amazon Technologies, Inc',
                'Expiration' => '',
                'LastModified' => 'Mon, 31 May 2021 06:52:32 GMT',
                'ContentType' => 'text/plain',
                'ETag' => '"098f6bcd4621d373cade4e832627b4f6"',
                'VersionId' => '',
                'WebsiteRedirectLocation' => '',
                'StorageClass' => 'STANDARD_IA',
                'AllowOrigin' => '',
                'MaxAgeSeconds' => '',
                'ExposeHeader' => '',
                'AllowMethod' => '',
                'AllowHeader' => '',
                'Restore' => '',
                'SseKms' => '',
                'SseKmsKey' => '',
                'SseC' => '',
                'SseCKeyMd5' => '',
                'Metadata' => [],
                'HttpStatusCode' => 200,
                'Reason' => 'OK',
            ]));
    }

    public function testListContents(): void
    {
        $this->client->shouldReceive('listObjects')
            ->withArgs([[
                'Bucket' => 'test',
                'Delimiter' => '/',
                'Prefix' => 'path/',
                'MaxKeys' => 1000,
                'Marker' => '',
            ],
            ])->andReturn(
                new Model([
                    'ContentLength' => '566',
                    'Date' => 'Mon, 31 May 2021 15:23:25 GMT',
                    'RequestId' => '00000179C305C54B920E25B74672EEBF',
                    'Id2' => '32AAAQAAEAABAAAQAAEAABAAAQAAEAABCSfHbGTCJ9SuSxR2hiyYh0eEyU5XfrC0',
                    'Reserved' => 'amazon, aws and amazon web services are trademarks or registered trademarks of Amazon Technologies, Inc',
                    'IsTruncated' => false,
                    'Marker' => '',
                    'NextMarker' => '',
                    'Contents' => [[
                        'Key' => 'path/',
                        'LastModified' => '2021-05-31T15:23:24.217Z',
                        'ETag' => '"d41d8cd98f00b204e9800998ecf8427e"',
                        'Size' => 0,
                        'StorageClass' => 'STANDARD_IA',
                        'Owner' => [
                            'DisplayName' => 'zingimmick',
                            'ID' => '0c85ae1126000f380f21c00e77706640',
                        ],
                    ],
                    ],
                    'Name' => 'test',
                    'Prefix' => 'path/',
                    'Delimiter' => '/',
                    'MaxKeys' => 1000,
                    'CommonPrefixes' => [],
                    'Location' => 'cn-east-3',
                    'HttpStatusCode' => 200,
                    'Reason' => 'OK',
                ])
            );
        $this->client->shouldReceive('getObjectMetadata')
            ->withArgs([[
                'Bucket' => 'test',
                'Key' => 'path/',
            ],
            ])->andReturn(new Model(
                [
                    'ContentLength' => 0,
                    'Date' => 'Mon, 31 May 2021 06:52:32 GMT',
                    'RequestId' => '00000179C13207EF9217A7F5589D2DC6',
                    'Id2' => '32AAAQAAEAABAAAQAAEAABAAAQAAEAABCSvXM+dHYwFYYJv2m9y5LibcMVibe3QN',
                    'Reserved' => 'amazon, aws and amazon web services are trademarks or registered trademarks of Amazon Technologies, Inc',
                    'Expiration' => '',
                    'LastModified' => 'Mon, 31 May 2021 06:52:31 GMT',
                    'ContentType' => 'binary/octet-stream',
                    'ETag' => 'd41d8cd98f00b204e9800998ecf8427e',
                    'VersionId' => '',
                    'WebsiteRedirectLocation' => '',
                    'StorageClass' => 'STANDARD_IA',
                    'AllowOrigin' => '',
                    'MaxAgeSeconds' => '',
                    'ExposeHeader' => '',
                    'AllowMethod' => '',
                    'AllowHeader' => '',
                    'Restore' => '',
                    'SseKms' => '',
                    'SseKmsKey' => '',
                    'SseC' => '',
                    'SseCKeyMd5' => '',
                    'Metadata' => [],
                    'HttpStatusCode' => 200,
                    'Reason' => 'OK',
                ]
            ));
        self::assertNotEmpty($this->adapter->listContents('path', false));
        $this->client->shouldReceive('listObjects')
            ->withArgs([[
                'Bucket' => 'test',
                'Delimiter' => '/',
                'Prefix' => 'path1/',
                'MaxKeys' => 1000,
                'Marker' => '',
            ],
            ])->andReturn(new Model([
                'NextMarker' => '',
                'Contents' => [],
            ]));
        self::assertEmpty(iterator_to_array($this->adapter->listContents('path1', false)));
        $this->mockPutObject('a/b/file.txt', 'test');
        $this->adapter->write('a/b/file.txt', 'test', new Config());
        $this->client->shouldReceive('listObjects')
            ->withArgs([[
                'Bucket' => 'test',
                'Delimiter' => '/',
                'Prefix' => 'a/',
                'MaxKeys' => 1000,
                'Marker' => '',
            ],
            ])->andReturn(new Model([
                'ContentLength' => '333',
                'Date' => 'Mon, 31 May 2021 15:23:25 GMT',
                'RequestId' => '00000179C305C593920E2644AED41021',
                'Id2' => '32AAAQAAEAABAAAQAAEAABAAAQAAEAABCSYw8g3pRtVZNn+ok4GA5fOUfUpb7nSm',
                'Reserved' => 'amazon, aws and amazon web services are trademarks or registered trademarks of Amazon Technologies, Inc',
                'IsTruncated' => false,
                'Marker' => '',
                'NextMarker' => '',
                'Contents' => [],
                'Name' => 'test',
                'Prefix' => 'a/',
                'Delimiter' => '/',
                'MaxKeys' => 1000,
                'CommonPrefixes' => [[
                    'Prefix' => 'a/b/',
                ],
                ],
                'Location' => 'cn-east-3',
                'HttpStatusCode' => 200,
                'Reason' => 'OK',
            ]));
        $this->client->shouldReceive('listObjects')
            ->withArgs([[
                'Bucket' => 'test',
                'Delimiter' => '/',
                'Prefix' => 'a/b/',
                'MaxKeys' => 1000,
                'Marker' => '',
            ],
            ])->andReturn(new Model([
                'ContentLength' => '333',
                'Date' => 'Mon, 31 May 2021 15:23:25 GMT',
                'RequestId' => '00000179C305C593920E2644AED41021',
                'Id2' => '32AAAQAAEAABAAAQAAEAABAAAQAAEAABCSYw8g3pRtVZNn+ok4GA5fOUfUpb7nSm',
                'Reserved' => 'amazon, aws and amazon web services are trademarks or registered trademarks of Amazon Technologies, Inc',
                'IsTruncated' => false,
                'Marker' => '',
                'NextMarker' => '',
                'Contents' => [[
                    'Key' => 'a/b/file.txt',
                    'LastModified' => '2021-05-31T15:23:24.217Z',
                    'ETag' => 'd41d8cd98f00b204e9800998ecf8427e',
                    'Size' => 0,
                    'StorageClass' => 'STANDARD_IA',
                    'Owner' => [
                        'DisplayName' => 'zingimmick',
                        'ID' => '0c85ae1126000f380f21c00e77706640',
                    ],
                ],
                ],
                'Name' => 'test',
                'Prefix' => 'a/b/',
                'Delimiter' => '/',
                'MaxKeys' => 1000,
                'CommonPrefixes' => [],
                'Location' => 'cn-east-3',
                'HttpStatusCode' => 200,
                'Reason' => 'OK',
            ]));
        $this->mockGetMetadata('a/b/file.txt');
        self::assertEquals([new FileAttributes(
            'a/b/file.txt',
            null,
            null,
            1622474604,
            null,
            [
                'StorageClass' => 'STANDARD_IA',
                'ETag' => 'd41d8cd98f00b204e9800998ecf8427e',
            ]
        ), new DirectoryAttributes('a/b/'),
        ], iterator_to_array($this->adapter->listContents('a', true)));
    }

    public function testGetSize(): void
    {
        $this->mockGetMetadata('fixture/read.txt');
        self::assertSame(9, $this->adapter->fileSize('fixture/read.txt')->fileSize());
    }

    public function testGetTimestamp(): void
    {
        $this->mockGetMetadata('fixture/read.txt');
        self::assertSame(1622443952, $this->adapter->lastModified('fixture/read.txt')->lastModified());
    }

    public function testGetMimetype(): void
    {
        $this->mockGetMetadata('fixture/read.txt');
        self::assertSame('text/plain', $this->adapter->mimeType('fixture/read.txt')->mimeType());
    }

    public function testHas(): void
    {
        $this->mockGetMetadata('fixture/read.txt');
        self::assertTrue((bool) $this->adapter->fileExists('fixture/read.txt'));
    }
}
