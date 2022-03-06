<?php

declare(strict_types=1);

namespace Zing\Flysystem\Obs\Tests;

use League\Flysystem\Config;
use League\Flysystem\DirectoryAttributes;
use League\Flysystem\FileAttributes;
use League\Flysystem\StorageAttributes;
use League\Flysystem\UnableToCopyFile;
use League\Flysystem\UnableToRetrieveMetadata;
use League\Flysystem\Visibility;
use Mockery;
use Obs\Internal\Common\Model;
use Obs\ObsClient;
use Obs\ObsException;
use Zing\Flysystem\Obs\ObsAdapter;

/**
 * @internal
 */
final class MockAdapterTest extends TestCase
{
    /**
     * @var \Mockery\LegacyMockInterface
     */
    private $legacyMock;

    /**
     * @var \Zing\Flysystem\Obs\ObsAdapter
     */
    private $obsAdapter;

    protected function setUp(): void
    {
        parent::setUp();

        $this->legacyMock = Mockery::mock(ObsClient::class);
        $this->obsAdapter = new ObsAdapter($this->legacyMock, 'test');
        $this->mockPutObject('fixture/read.txt', 'read-test');
        $this->obsAdapter->write('fixture/read.txt', 'read-test', new Config());
    }

    /**
     * @param resource|string $body
     */
    private function mockPutObject(string $path, $body, ?string $visibility = null): void
    {
        $arg = [
            'ContentType' => 'text/plain',
            'Bucket' => 'test',
            'Key' => $path,
            'Body' => $body,
        ];
        if ($visibility !== null) {
            $arg = array_merge($arg, [
                'ACL' => $visibility === Visibility::PUBLIC ? 'public-read' : 'private',
            ]);
        }

        $this->legacyMock->shouldReceive('putObject')
            ->withArgs([$arg])->andReturn(new Model());
    }

    public function testCopy(): void
    {
        $this->mockPutObject('file.txt', 'write');
        $this->obsAdapter->write('file.txt', 'write', new Config());
        $this->legacyMock->shouldReceive('copyObject')
            ->withArgs([
                [
                    'Bucket' => 'test',
                    'Key' => 'copy.txt',
                    'CopySource' => 'test/file.txt',
                    'MetadataDirective' => 'COPY',
                    'ACL' => 'public-read',
                ],
            ])->andReturn(new Model());
        $this->mockGetVisibility('file.txt', Visibility::PUBLIC);
        $this->obsAdapter->copy('file.txt', 'copy.txt', new Config());
        $this->mockGetObject('copy.txt', 'write');
        self::assertSame('write', $this->obsAdapter->read('copy.txt'));
    }

    public function testCopyFailed(): void
    {
        $this->mockPutObject('file.txt', 'write');
        $this->obsAdapter->write('file.txt', 'write', new Config());
        $this->legacyMock->shouldReceive('copyObject')
            ->withArgs([
                [
                    'Bucket' => 'test',
                    'Key' => 'copy.txt',
                    'CopySource' => 'test/file.txt',
                    'MetadataDirective' => 'COPY',
                    'ACL' => 'public-read',
                ],
            ])->andThrow(new ObsException());
        $this->mockGetVisibility('file.txt', Visibility::PUBLIC);
        $this->expectException(UnableToCopyFile::class);
        $this->obsAdapter->copy('file.txt', 'copy.txt', new Config());
        $this->mockGetObject('copy.txt', 'write');
        self::assertSame('write', $this->obsAdapter->read('copy.txt'));
    }

    private function mockGetObject(string $path, string $body): void
    {
        $this->legacyMock->shouldReceive('getObject')
            ->withArgs([
                [
                    'Bucket' => 'test',
                    'Key' => $path,
                ],
            ])->andReturn(new Model([
                'Body' => $this->streamFor($body),
            ]));
    }

    public function testCreateDir(): void
    {
        $this->legacyMock->shouldReceive('putObject')
            ->withArgs([
                [
                    'ACL' => 'public-read',
                    'Bucket' => 'test',
                    'Key' => 'path/',
                    'Body' => null,
                ],
            ])->andReturn(new Model());
        $this->legacyMock->shouldReceive('listObjects')
            ->withArgs([
                [
                    'Bucket' => 'test',
                    'Prefix' => 'path/',
                    'Delimiter' => '/',
                    'MaxKeys' => 1,
                ],
            ])->andReturn(
                new Model([
                    'NextMarker' => '',
                    'Contents' => [
                        [
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
                ]),
                new Model([
                    'NextMarker' => '',
                    'Contents' => [],
                ])
            );
        $this->legacyMock->shouldReceive('listObjects')
            ->withArgs([
                [
                    'Bucket' => 'test',
                    'Prefix' => 'path/',
                    'MaxKeys' => 1000,
                    'Delimiter' => '/',
                    'Marker' => '',
                ],
            ])->andReturn(new Model([
                'NextMarker' => '',
                'Contents' => [
                    [
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
        $this->legacyMock->shouldReceive('listObjects')
            ->withArgs([
                [
                    'Bucket' => 'test',
                    'Prefix' => 'path/',
                    'MaxKeys' => 1000,
                    'Marker' => '',
                ],
            ])->andReturn(new Model([
                'NextMarker' => '',
                'Contents' => [
                    [
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
        $this->legacyMock->shouldReceive('getObjectMetadata')
            ->withArgs([
                [
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
        $this->legacyMock->shouldReceive('deleteObject')
            ->withArgs([
                [
                    'Bucket' => 'test',
                    'Key' => 'path/',
                ],
            ]);
        $this->obsAdapter->createDirectory('path', new Config());
        self::assertTrue($this->obsAdapter->directoryExists('path'));
        self::assertSame([], iterator_to_array($this->obsAdapter->listContents('path', false)));
        $this->obsAdapter->deleteDirectory('path');
        self::assertFalse($this->obsAdapter->directoryExists('path'));
    }

    public function testSetVisibility(): void
    {
        $this->mockPutObject('file.txt', 'write');
        $this->obsAdapter->write('file.txt', 'write', new Config());
        $this->legacyMock->shouldReceive('getObjectAcl')
            ->withArgs([
                [
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
                'Grants' => [
                    [
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
        self::assertSame(Visibility::PRIVATE, $this->obsAdapter->visibility('file.txt')->visibility());
        $this->legacyMock->shouldReceive('setObjectAcl')
            ->withArgs([
                [
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
        $this->obsAdapter->setVisibility('file.txt', Visibility::PUBLIC);

        self::assertSame(Visibility::PUBLIC, $this->obsAdapter->visibility('file.txt')['visibility']);
    }

    public function testRename(): void
    {
        $this->mockPutObject('from.txt', 'write');
        $this->obsAdapter->write('from.txt', 'write', new Config());
        $this->mockGetMetadata('from.txt');
        self::assertTrue($this->obsAdapter->fileExists('from.txt'));
        $this->legacyMock->shouldReceive('getObjectMetadata')
            ->withArgs([
                [
                    'Bucket' => 'test',
                    'Key' => 'to.txt',
                ],
            ])->andThrow(new ObsException());
        self::assertFalse($this->obsAdapter->fileExists('to.txt'));
        $this->legacyMock->shouldReceive('copyObject')
            ->withArgs([
                [
                    'Bucket' => 'test',
                    'Key' => 'to.txt',
                    'CopySource' => 'test/from.txt',
                    'MetadataDirective' => 'COPY',
                    'ACL' => 'public-read',
                ],
            ])->andReturn(new Model());
        $this->legacyMock->shouldReceive('deleteObject')
            ->withArgs([
                [
                    'Bucket' => 'test',
                    'Key' => 'from.txt',
                ],
            ])->andReturn(new Model());
        $this->mockGetVisibility('from.txt', Visibility::PUBLIC);
        $this->obsAdapter->move('from.txt', 'to.txt', new Config());
        $this->legacyMock->shouldReceive('getObjectMetadata')
            ->withArgs([
                [
                    'Bucket' => 'test',
                    'Key' => 'from.txt',
                ],
            ])->andThrow(new ObsException());
        self::assertFalse($this->obsAdapter->fileExists('from.txt'));
        $this->mockGetObject('to.txt', 'write');
        self::assertSame('write', $this->obsAdapter->read('to.txt'));
        $this->legacyMock->shouldReceive('deleteObject')
            ->withArgs([
                [
                    'Bucket' => 'test',
                    'Key' => 'to.txt',
                ],
            ])->andReturn(new Model());
        $this->obsAdapter->delete('to.txt');
    }

    public function testDeleteDir(): void
    {
        $this->legacyMock->shouldReceive('listObjects')
            ->withArgs([
                [
                    'Bucket' => 'test',
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
                'Contents' => [
                    [
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
        $this->legacyMock->shouldReceive('getObjectMetadata')
            ->withArgs([
                [
                    'Bucket' => 'test',
                    'Key' => 'path',
                ],
            ])->andThrow(new ObsException());
        $this->legacyMock->shouldReceive('deleteObject')
            ->withArgs([
                [
                    'Bucket' => 'test',
                    'Key' => 'path/',
                ],
            ])->andReturn(new Model());
        $this->legacyMock->shouldReceive('deleteObject')
            ->withArgs([
                [
                    'Bucket' => 'test',
                    'Key' => 'path/file.txt',
                ],
            ])->andReturn(new Model());
        $this->obsAdapter->deleteDirectory('path');
        self::assertTrue(true);
    }

    public function testWriteStream(): void
    {
        $contents = $this->streamForResource('write');
        $this->mockPutObject('file.txt', $contents);
        $this->obsAdapter->writeStream('file.txt', $contents, new Config());
        $this->mockGetObject('file.txt', 'write');
        self::assertSame('write', $this->obsAdapter->read('file.txt'));
    }

    /**
     * @return \Iterator<string[]>
     */
    public function provideVisibilities(): \Iterator
    {
        yield [Visibility::PUBLIC];
        yield [Visibility::PRIVATE];
    }

    private function mockGetVisibility(string $path, string $visibility): void
    {
        $model = new Model([
            'ContentLength' => '508',
            'Date' => 'Mon, 31 May 2021 06:52:31 GMT',
            'RequestId' => '00000179C132050392179DB73EB80FFF',
            'Id2' => '32AAAQAAEAABAAAQAAEAABAAAQAAEAABCS7X7CQo6PJncbE/Rw7pAST9+g4eSFFj',
            'Reserved' => 'amazon, aws and amazon web services are trademarks or registered trademarks of Amazon Technologies, Inc',
            'Owner' => [
                'DisplayName' => 'zingimmick',
                'ID' => '0c85ae1126000f380f21c00e77706640',
            ],
            'Grants' => $visibility === Visibility::PRIVATE ? [
                [
                    'Grantee' => [
                        'DisplayName' => 'zingimmick',
                        'ID' => '0c85ae1126000f380f21c00e77706640',
                        'URI' => '',
                    ],
                    'Permission' => 'FULL_CONTROL',
                ],
            ] : [
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
        ]);

        $this->legacyMock->shouldReceive('getObjectAcl')
            ->withArgs([
                [
                    'Bucket' => 'test',
                    'Key' => $path,
                ],
            ])
            ->andReturn($model);
    }

    /**
     * @dataProvider provideVisibilities
     */
    public function testWriteStreamWithVisibility(string $visibility): void
    {
        $contents = $this->streamForResource('write');
        $this->mockPutObject('file.txt', $contents, $visibility);
        $this->obsAdapter->writeStream('file.txt', $contents, new Config([
            'visibility' => $visibility,
        ]));
        $this->mockGetVisibility('file.txt', $visibility);
        self::assertSame($visibility, $this->obsAdapter->visibility('file.txt')['visibility']);
    }

    public function testWriteStreamWithExpires(): void
    {
        $contents = $this->streamForResource('write');
        $this->legacyMock->shouldReceive('putObject')
            ->withArgs([
                [
                    'ContentType' => 'text/plain',
                    'Expires' => 20,
                    'Bucket' => 'test',
                    'Key' => 'file.txt',
                    'Body' => $contents,
                ],
            ])->andReturn(new Model());
        $this->obsAdapter->writeStream('file.txt', $contents, new Config([
            'Expires' => 20,
        ]));
        $this->mockGetObject('file.txt', 'write');
        self::assertSame('write', $this->obsAdapter->read('file.txt'));
    }

    public function testWriteStreamWithMimetype(): void
    {
        $contents = $this->streamForResource('write');
        $this->legacyMock->shouldReceive('putObject')
            ->withArgs([
                [
                    'ContentType' => 'image/png',
                    'Bucket' => 'test',
                    'Key' => 'file.txt',
                    'Body' => $contents,
                ],
            ])->andReturn(new Model());
        $this->obsAdapter->writeStream('file.txt', $contents, new Config([
            'ContentType' => 'image/png',
        ]));
        $this->legacyMock->shouldReceive('getObjectMetadata')
            ->once()
            ->withArgs([
                [
                    'Bucket' => 'test',
                    'Key' => 'file.txt',
                ],
            ])->andReturn(new Model([
                'ContentLength' => 9,
                'Date' => 'Mon, 31 May 2021 06:52:32 GMT',
                'RequestId' => '00000179C13207FD9217A8324EE5B315',
                'Id2' => '32AAAQAAEAABAAAQAAEAABAAAQAAEAABCSOcy2Ri+ilXxrwc5JIVg6ifOFbyOU/p',
                'Reserved' => 'amazon, aws and amazon web services are trademarks or registered trademarks of Amazon Technologies, Inc',
                'Expiration' => '',
                'LastModified' => 'Mon, 31 May 2021 06:52:32 GMT',
                'ContentType' => 'image/png',
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
        self::assertSame('image/png', $this->obsAdapter->mimeType('file.txt')['mime_type']);
    }

    public function testDelete(): void
    {
        $contents = $this->streamForResource('write');
        $this->mockPutObject('file.txt', $contents);
        $this->obsAdapter->writeStream('file.txt', $contents, new Config());
        $this->mockGetMetadata('file.txt');
        self::assertTrue($this->obsAdapter->fileExists('file.txt'));
        $this->legacyMock->shouldReceive('deleteObject')
            ->withArgs([
                [
                    'Bucket' => 'test',
                    'Key' => 'file.txt',
                ],
            ])->andReturn(new Model());
        $this->obsAdapter->delete('file.txt');
        $this->legacyMock->shouldReceive('getObjectMetadata')
            ->withArgs([
                [
                    'Bucket' => 'test',
                    'Key' => 'file.txt',
                ],
            ])->andThrow(new ObsException());
        self::assertFalse($this->obsAdapter->fileExists('file.txt'));
    }

    public function testWrite(): void
    {
        $this->mockPutObject('file.txt', 'write');
        $this->obsAdapter->write('file.txt', 'write', new Config());
        $this->mockGetObject('file.txt', 'write');
        self::assertSame('write', $this->obsAdapter->read('file.txt'));
    }

    public function testRead(): void
    {
        $this->mockGetObject('fixture/read.txt', 'read-test');
        self::assertSame('read-test', $this->obsAdapter->read('fixture/read.txt'));
    }

    public function testReadStream(): void
    {
        $this->mockGetObject('fixture/read.txt', 'read-test');

        self::assertSame('read-test', stream_get_contents($this->obsAdapter->readStream('fixture/read.txt')));
    }

    public function testGetVisibility(): void
    {
        $this->legacyMock->shouldReceive('getObjectAcl')
            ->withArgs([
                [
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
                'Grants' => [
                    [
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
        self::assertSame(Visibility::PRIVATE, $this->obsAdapter->visibility('fixture/read.txt')['visibility']);
    }

    private function mockGetMetadata(string $path): void
    {
        $this->legacyMock->shouldReceive('getObjectMetadata')
            ->once()
            ->withArgs([
                [
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

    private function mockGetEmptyMetadata(string $path): void
    {
        $this->legacyMock->shouldReceive('getObjectMetadata')
            ->once()
            ->withArgs([
                [
                    'Bucket' => 'test',
                    'Key' => $path,
                ],
            ])->andReturn(new Model([
                'ContentLength' => null,
                'Date' => 'Mon, 31 May 2021 06:52:32 GMT',
                'RequestId' => '00000179C13207FD9217A8324EE5B315',
                'Id2' => '32AAAQAAEAABAAAQAAEAABAAAQAAEAABCSOcy2Ri+ilXxrwc5JIVg6ifOFbyOU/p',
                'Reserved' => 'amazon, aws and amazon web services are trademarks or registered trademarks of Amazon Technologies, Inc',
                'Expiration' => '',
                'LastModified' => null,
                'ContentType' => null,
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
        $this->legacyMock->shouldReceive('listObjects')
            ->withArgs([
                [
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
                    'Contents' => [
                        [
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
        $this->legacyMock->shouldReceive('getObjectMetadata')
            ->withArgs([
                [
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
        self::assertNotEmpty($this->obsAdapter->listContents('path', false));
        $this->legacyMock->shouldReceive('listObjects')
            ->withArgs([
                [
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
        self::assertEmpty(iterator_to_array($this->obsAdapter->listContents('path1', false)));
        $this->mockPutObject('a/b/file.txt', 'test');
        $this->obsAdapter->write('a/b/file.txt', 'test', new Config());
        $this->legacyMock->shouldReceive('listObjects')
            ->withArgs([
                [
                    'Bucket' => 'test',
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
                'Contents' => [
                    [
                        'Key' => 'a/b/file.txt',
                        'LastModified' => '2021-05-31T15:23:24.217Z',
                        'ETag' => 'd41d8cd98f00b204e9800998ecf8427e',
                        'Size' => 9,
                        'StorageClass' => 'STANDARD_IA',
                        'Owner' => [
                            'DisplayName' => 'zingimmick',
                            'ID' => '0c85ae1126000f380f21c00e77706640',
                        ],
                    ],
                ],
                'Name' => 'test',
                'Prefix' => 'a/',
                'Delimiter' => '/',
                'MaxKeys' => 1000,
                'CommonPrefixes' => [
                    [
                        'Prefix' => 'a/b/',
                    ],
                ],
                'Location' => 'cn-east-3',
                'HttpStatusCode' => 200,
                'Reason' => 'OK',
            ]));
        $this->legacyMock->shouldReceive('listObjects')
            ->withArgs([
                [
                    'Bucket' => 'test',
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
                'Contents' => [
                    [
                        'Key' => 'a/b/file.txt',
                        'LastModified' => '2021-05-31T15:23:24.217Z',
                        'ETag' => 'd41d8cd98f00b204e9800998ecf8427e',
                        'Size' => 9,
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
        $contents = iterator_to_array($this->obsAdapter->listContents('a', true));
        self::assertContainsOnlyInstancesOf(StorageAttributes::class, $contents);
        self::assertCount(2, $contents);

        /** @var \League\Flysystem\FileAttributes $file */
        $file = $contents[0];
        self::assertInstanceOf(FileAttributes::class, $file);
        self::assertSame('a/b/file.txt', $file->path());
        self::assertSame(9, $file->fileSize());

        self::assertNull($file->mimeType());
        self::assertSame(1622474604, $file->lastModified());
        self::assertNull($file->visibility());
        self::assertSame([
            'StorageClass' => 'STANDARD_IA',
            'ETag' => 'd41d8cd98f00b204e9800998ecf8427e',
        ], $file->extraMetadata());

        /** @var \League\Flysystem\DirectoryAttributes $directory */
        $directory = $contents[1];
        self::assertInstanceOf(DirectoryAttributes::class, $directory);
        self::assertSame('a/b', $directory->path());
    }

    public function testGetSize(): void
    {
        $this->mockGetMetadata('fixture/read.txt');
        self::assertSame(9, $this->obsAdapter->fileSize('fixture/read.txt')->fileSize());
    }

    public function testGetSizeError(): void
    {
        $this->mockGetEmptyMetadata('fixture/read.txt');
        $this->expectException(UnableToRetrieveMetadata::class);
        self::assertSame(9, $this->obsAdapter->fileSize('fixture/read.txt')->fileSize());
    }

    public function testGetTimestamp(): void
    {
        $this->mockGetMetadata('fixture/read.txt');
        self::assertSame(1622443952, $this->obsAdapter->lastModified('fixture/read.txt')->lastModified());
    }

    public function testGetTimestampError(): void
    {
        $this->mockGetEmptyMetadata('fixture/read.txt');
        $this->expectException(UnableToRetrieveMetadata::class);
        self::assertSame(1622443952, $this->obsAdapter->lastModified('fixture/read.txt')->lastModified());
    }

    public function testGetMimetype(): void
    {
        $this->mockGetMetadata('fixture/read.txt');
        self::assertSame('text/plain', $this->obsAdapter->mimeType('fixture/read.txt')->mimeType());
    }

    public function testGetMimetypeError(): void
    {
        $this->mockGetEmptyMetadata('fixture/read.txt');
        $this->expectException(UnableToRetrieveMetadata::class);
        self::assertSame('text/plain', $this->obsAdapter->mimeType('fixture/read.txt')->mimeType());
    }

    public function testGetMetadataError(): void
    {
        $this->mockGetEmptyMetadata('fixture/');
        $this->expectException(UnableToRetrieveMetadata::class);
        self::assertSame('text/plain', $this->obsAdapter->mimeType('fixture/')->mimeType());
    }

    public function testHas(): void
    {
        $this->mockGetMetadata('fixture/read.txt');
        self::assertTrue($this->obsAdapter->fileExists('fixture/read.txt'));
    }

    public function testGetTemporaryUrl(): void
    {
        $this->legacyMock->shouldReceive('createSignedUrl')
            ->withArgs([
                [
                    'Method' => 'GET',
                    'Bucket' => 'test',
                    'Key' => 'fixture/read.txt',
                    'Expires' => 10,
                    'QueryParams' => [],
                ],
            ])->andReturn(new Model([
                'SignedUrl' => 'signed-url',
            ]));
        self::assertSame('signed-url', $this->obsAdapter->getTemporaryUrl('fixture/read.txt', 10, []));
    }

    public function testDirectoryExists(): void
    {
        $this->legacyMock->shouldReceive('listObjects')
            ->withArgs([
                [
                    'Bucket' => 'test',
                    'Prefix' => 'fixture/exists-directory/',
                    'Delimiter' => '/',
                    'MaxKeys' => 1,
                ],
            ])->andReturn(new Model([
                'ContentLength' => '302',
                'Date' => 'Sun, 16 Jan 2022 09:26:08 GMT',
                'RequestId' => '0000017E6235507D950E8EB369D41D99',
                'Id2' => '32AAAQAAEAABAAAQAAEAABAAAQAAEAABCSZadwpdiOENxOcC8idIIBfPBMDPTNFd',
                'Reserved' => '',
                'IsTruncated' => false,
                'Marker' => '',
                'NextMarker' => '',
                'Contents' => [],
                'Name' => 'zing-test',
                'Prefix' => 'fixture/exists-directory/',
                'Delimiter' => '/',
                'MaxKeys' => 1000,
                'CommonPrefixes' => [],
                'Location' => 'cn-east-3',
                'HttpStatusCode' => 200,
                'Reason' => 'OK',
            ]), new Model([
                'ContentLength' => '302',
                'Date' => 'Sun, 16 Jan 2022 09:26:08 GMT',
                'RequestId' => '0000017E6235507D950E8EB369D41D99',
                'Id2' => '32AAAQAAEAABAAAQAAEAABAAAQAAEAABCSZadwpdiOENxOcC8idIIBfPBMDPTNFd',
                'Reserved' => '',
                'IsTruncated' => false,
                'Marker' => '',
                'NextMarker' => '',
                'Contents' => [
                    [
                        'Key' => 'fixture/exists-directory/',
                        'LastModified' => '2022-01-16T09:29:18.390Z',
                        'ETag' => 'd41d8cd98f00b204e9800998ecf8427e',
                        'Size' => 0,
                        'StorageClass' => 'WARM',
                        'Type' => '',
                        'Owner' => [
                            [
                                'ID' => '0c85ae1126000f380f21c00e77706640',
                            ],
                        ],
                    ],
                ],
                'Name' => 'zing-test',
                'Prefix' => 'fixture/exists-directory/',
                'Delimiter' => '/',
                'MaxKeys' => 1000,
                'CommonPrefixes' => [],
                'Location' => 'cn-east-3',
                'HttpStatusCode' => 200,
                'Reason' => 'OK',
            ]));
        $this->legacyMock->shouldReceive('putObject')
            ->withArgs([
                [
                    'ACL' => 'public-read',
                    'Bucket' => 'test',
                    'Key' => 'fixture/exists-directory/',
                    'Body' => null,
                ],
            ])->andReturn(new Model());
        self::assertFalse($this->obsAdapter->directoryExists('fixture/exists-directory'));
        $this->obsAdapter->createDirectory('fixture/exists-directory', new Config());
        self::assertTrue($this->obsAdapter->directoryExists('fixture/exists-directory'));
    }
}
