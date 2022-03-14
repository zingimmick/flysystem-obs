<?php

declare(strict_types=1);

namespace Zing\Flysystem\Obs\Tests;

use League\Flysystem\AdapterInterface;
use League\Flysystem\Config;
use Obs\Internal\Common\Model;
use Obs\ObsClient;
use Obs\ObsException;
use Rector\Core\ValueObject\Visibility;
use Zing\Flysystem\Obs\ObsAdapter;

class MockAdapterTest extends TestCase
{
    /**
     * @var \Mockery\LegacyMockInterface|\Mockery\MockInterface|\Obs\ObsClient
     */
    private $client;

    /**
     * @var \Zing\Flysystem\Obs\ObsAdapter
     */
    private $obsAdapter;

    protected function setUp(): void
    {
        parent::setUp();

        $this->client = \Mockery::mock(ObsClient::class);
        $this->obsAdapter = new ObsAdapter($this->client, 'obs.cn-east-3.myhuaweicloud.com', 'test');
        $this->client->shouldReceive('putObject')
            ->withArgs([
                [
                    'Bucket' => 'test',
                    'Key' => 'fixture/read.txt',
                    'Body' => 'read-test',
                    'ContentType' => 'text/plain',
                ],
            ])->andReturn(new Model([]));
        $this->obsAdapter->write('fixture/read.txt', 'read-test', new Config());
    }

    public function testUpdate(): void
    {
        $this->client->shouldReceive('putObject')
            ->withArgs([
                [
                    'Bucket' => 'test',
                    'Key' => 'file.txt',
                    'Body' => 'update',
                    'ContentType' => 'text/plain',
                ],
            ])->andReturn(new Model());
        $this->obsAdapter->update('file.txt', 'update', new Config());
        $this->client->shouldReceive('getObject')
            ->withArgs([
                [
                    'Bucket' => 'test',
                    'Key' => 'file.txt',
                ],
            ])->andReturn(new Model([
                'Body' => $this->streamFor('update'),
            ]));
        static::assertSame('update', $this->obsAdapter->read('file.txt')['contents']);
    }

    public function testUpdateStream(): void
    {
        $this->client->shouldReceive('putObject')
            ->withArgs([
                [
                    'Bucket' => 'test',
                    'Key' => 'file.txt',
                    'Body' => 'write',
                    'ContentType' => 'text/plain',
                ],
            ])->andReturn(new Model());
        $this->obsAdapter->write('file.txt', 'write', new Config());
        $this->client->shouldReceive('putObject')
            ->withArgs([
                [
                    'Bucket' => 'test',
                    'Key' => 'file.txt',
                    'Body' => 'update',
                    'ContentType' => 'text/plain',
                ],
            ])->andReturn(new Model());
        $this->obsAdapter->updateStream('file.txt', $this->streamFor('update')->detach(), new Config());
        $this->client->shouldReceive('getObject')
            ->withArgs([
                [
                    'Bucket' => 'test',
                    'Key' => 'file.txt',
                ],
            ])->andReturn(new Model([
                'Body' => $this->streamFor('update'),
            ]));
        static::assertSame('update', $this->obsAdapter->read('file.txt')['contents']);
    }

    private function mockPutObject($path, $body, $visibility = null): void
    {
        $arg = [
            'Bucket' => 'test',
            'Key' => $path,
            'Body' => $body,
            'ContentType' => 'text/plain',
        ];
        if ($visibility !== null) {
            $arg = array_merge($arg, [
                'visibility' => $visibility === AdapterInterface::VISIBILITY_PUBLIC ? 'public' : 'private',
                'ACL' => $visibility === AdapterInterface::VISIBILITY_PUBLIC ? 'public-read' : 'private',
            ]);
        }

        $this->client->shouldReceive('putObject')
            ->withArgs([$arg])->andReturn(new Model());
    }

    public function testCopy(): void
    {
        $this->mockPutObject('file.txt', 'write');
        $this->obsAdapter->write('file.txt', 'write', new Config());
        $this->client->shouldReceive('copyObject')
            ->withArgs([
                [
                    'Bucket' => 'test',
                    'Key' => 'copy.txt',
                    'CopySource' => 'test/file.txt',
                    'MetadataDirective' => 'COPY',
                    'ACL' => 'public',
                ],
            ])->andReturn(new Model());
        $this->mockGetVisibility('file.txt', Visibility::PUBLIC);
        $this->obsAdapter->copy('file.txt', 'copy.txt');
        $this->mockGetObject('copy.txt', 'write');
        static::assertSame('write', $this->obsAdapter->read('copy.txt')['contents']);
    }

    private function mockGetObject($path, $body): void
    {
        $this->client->shouldReceive('getObject')
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
        $this->client->shouldReceive('putObject')
            ->withArgs([
                [
                    'Bucket' => 'test',
                    'Key' => 'path/',
                    'Body' => null,
                    'ContentType' => 'text/plain',
                ],
            ])->andReturn(new Model());
        $this->obsAdapter->createDir('path', new Config());
        $this->client->shouldReceive('listObjects')
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
        $this->client->shouldReceive('getObjectMetadata')
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
        static::assertSame([], $this->obsAdapter->listContents('path'));
    }

    public function testSetVisibility(): void
    {
        $this->client->shouldReceive('putObject')
            ->withArgs([
                [
                    'Bucket' => 'test',
                    'Key' => 'file.txt',
                    'Body' => 'write',
                    'ContentType' => 'text/plain',
                ],
            ])->andReturn(new Model());
        $this->obsAdapter->write('file.txt', 'write', new Config());
        $this->client->shouldReceive('getObjectAcl')
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
        static::assertSame(
            AdapterInterface::VISIBILITY_PRIVATE,
            $this->obsAdapter->getVisibility('file.txt')['visibility']
        );
        $this->client->shouldReceive('setObjectAcl')
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
        $this->obsAdapter->setVisibility('file.txt', AdapterInterface::VISIBILITY_PUBLIC);

        static::assertSame(
            AdapterInterface::VISIBILITY_PUBLIC,
            $this->obsAdapter->getVisibility('file.txt')['visibility']
        );
    }

    public function testRename(): void
    {
        $this->mockPutObject('from.txt', 'write');
        $this->obsAdapter->write('from.txt', 'write', new Config());
        $this->mockGetMetadata('from.txt');
        static::assertTrue((bool) $this->obsAdapter->has('from.txt'));
        $this->client->shouldReceive('getObjectMetadata')
            ->withArgs([
                [
                    'Bucket' => 'test',
                    'Key' => 'to.txt',
                ],
            ])->andThrow(new ObsException());
        static::assertFalse((bool) $this->obsAdapter->has('to.txt'));
        $this->client->shouldReceive('copyObject')
            ->withArgs([
                [
                    'Bucket' => 'test',
                    'Key' => 'to.txt',
                    'CopySource' => 'test/from.txt',
                    'MetadataDirective' => 'COPY',
                    'ACL' => 'public',
                ],
            ])->andReturn(new Model());
        $this->client->shouldReceive('deleteObject')
            ->withArgs([
                [
                    'Bucket' => 'test',
                    'Key' => 'from.txt',
                ],
            ])->andReturn(new Model());
        $this->mockGetVisibility('from.txt', Visibility::PUBLIC);
        $this->obsAdapter->rename('from.txt', 'to.txt');
        $this->client->shouldReceive('getObjectMetadata')
            ->withArgs([
                [
                    'Bucket' => 'test',
                    'Key' => 'from.txt',
                ],
            ])->andThrow(new ObsException());
        static::assertFalse((bool) $this->obsAdapter->has('from.txt'));
        $this->mockGetObject('to.txt', 'write');
        static::assertSame('write', $this->obsAdapter->read('to.txt')['contents']);
        $this->client->shouldReceive('deleteObject')
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
        $this->client->shouldReceive('listObjects')
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
        $this->client->shouldReceive('getObjectMetadata')
            ->withArgs([
                [
                    'Bucket' => 'test',
                    'Key' => 'path',
                ],
            ])->andThrow(new ObsException());
        $this->client->shouldReceive('deleteObjects')
            ->withArgs([
                [
                    'Bucket' => 'test',
                    'Objects' => [
                        [
                            'Key' => 'path/',
                        ], [
                            'Key' => 'path/file.txt',
                        ],
                    ],
                ],
            ])->andReturn(new Model());
        static::assertTrue($this->obsAdapter->deleteDir('path'));
    }

    public function testWriteStream(): void
    {
        $this->mockPutObject('file.txt', 'write');
        $this->obsAdapter->writeStream('file.txt', $this->streamFor('write')->detach(), new Config());
        $this->mockGetObject('file.txt', 'write');
        static::assertSame('write', $this->obsAdapter->read('file.txt')['contents']);
    }

    /**
     * @return \Iterator<string[]>
     */
    public function provideVisibilities(): \Iterator
    {
        yield [AdapterInterface::VISIBILITY_PUBLIC];
        yield [AdapterInterface::VISIBILITY_PRIVATE];
    }

    private function mockGetVisibility($path, $visibility): void
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
            'Grants' => $visibility === AdapterInterface::VISIBILITY_PRIVATE ? [
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

        $this->client->shouldReceive('getObjectAcl')
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
     *
     * @param $visibility
     */
    public function testWriteStreamWithVisibility($visibility): void
    {
        $this->mockPutObject('file.txt', 'write', $visibility);
        $this->obsAdapter->writeStream('file.txt', $this->streamFor('write')->detach(), new Config([
            'visibility' => $visibility,
        ]));
        $this->mockGetVisibility('file.txt', $visibility);
        static::assertSame($visibility, $this->obsAdapter->getVisibility('file.txt')['visibility']);
    }

    public function testWriteStreamWithExpires(): void
    {
        $this->client->shouldReceive('putObject')
            ->withArgs([
                [
                    'Expires' => 20,
                    'Bucket' => 'test',
                    'Key' => 'file.txt',
                    'Body' => 'write',
                    'ContentType' => 'text/plain',
                ],
            ])->andReturn(new Model());
        $this->obsAdapter->writeStream('file.txt', $this->streamFor('write')->detach(), new Config([
            'Expires' => 20,
        ]));
        $this->mockGetObject('file.txt', 'write');
        static::assertSame('write', $this->obsAdapter->read('file.txt')['contents']);
    }

    public function testWriteStreamWithMimetype(): void
    {
        $this->client->shouldReceive('putObject')
            ->withArgs([
                [
                    'mimetype' => 'image/png',
                    'ContentType' => 'image/png',
                    'Bucket' => 'test',
                    'Key' => 'file.txt',
                    'Body' => 'write',
                ],
            ])->andReturn(new Model());
        $this->obsAdapter->writeStream('file.txt', $this->streamFor('write')->detach(), new Config([
            'mimetype' => 'image/png',
        ]));
        $this->client->shouldReceive('getObjectMetadata')
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
        static::assertSame('image/png', $this->obsAdapter->getMimetype('file.txt')['mimetype']);
    }

    public function testDelete(): void
    {
        $this->mockPutObject('file.txt', 'write');
        $this->obsAdapter->writeStream('file.txt', $this->streamFor('write')->detach(), new Config());
        $this->mockGetMetadata('file.txt');
        static::assertTrue((bool) $this->obsAdapter->has('file.txt'));
        $this->client->shouldReceive('deleteObject')
            ->withArgs([
                [
                    'Bucket' => 'test',
                    'Key' => 'file.txt',
                ],
            ])->andReturn(new Model());
        $this->obsAdapter->delete('file.txt');
        $this->client->shouldReceive('getObjectMetadata')
            ->withArgs([
                [
                    'Bucket' => 'test',
                    'Key' => 'file.txt',
                ],
            ])->andThrow(new ObsException());
        static::assertFalse((bool) $this->obsAdapter->has('file.txt'));
    }

    public function testWrite(): void
    {
        $this->mockPutObject('file.txt', 'write');
        $this->obsAdapter->write('file.txt', 'write', new Config());
        $this->mockGetObject('file.txt', 'write');
        static::assertSame('write', $this->obsAdapter->read('file.txt')['contents']);
    }

    public function testRead(): void
    {
        $this->client->shouldReceive('getObject')
            ->withArgs([
                [
                    'Bucket' => 'test',
                    'Key' => 'fixture/read.txt',
                ],
            ])->andReturn(new Model([
                'Body' => $this->streamFor('read-test'),
            ]));
        static::assertSame('read-test', $this->obsAdapter->read('fixture/read.txt')['contents']);
    }

    public function testReadStream(): void
    {
        $this->client->shouldReceive('getObject')
            ->withArgs([
                [
                    'Bucket' => 'test',
                    'Key' => 'fixture/read.txt',
                ],
            ])->andReturn(new Model([
                'Body' => $this->streamFor('read-test'),
            ]));
        static::assertSame(
            'read-test',
            stream_get_contents($this->obsAdapter->readStream('fixture/read.txt')['stream'])
        );
    }

    public function testGetVisibility(): void
    {
        $this->client->shouldReceive('getObjectAcl')
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
        static::assertSame(
            AdapterInterface::VISIBILITY_PRIVATE,
            $this->obsAdapter->getVisibility('fixture/read.txt')['visibility']
        );
    }

    public function testGetMetadata(): void
    {
        $this->mockGetMetadata('fixture/read.txt');
        static::assertIsArray($this->obsAdapter->getMetadata('fixture/read.txt'));
    }

    private function mockGetMetadata($path): void
    {
        $this->client->shouldReceive('getObjectMetadata')
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

    public function testListContents(): void
    {
        $this->client->shouldReceive('listObjects')
            ->withArgs([
                [
                    'Bucket' => 'test',
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
                            'Key' => 'path/file.txt',
                            'LastModified' => 'Mon, 31 May 2021 06:52:32 GMT',
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
        static::assertNotEmpty($this->obsAdapter->listContents('path'));
        $this->client->shouldReceive('listObjects')
            ->withArgs([
                [
                    'Bucket' => 'test',
                    'Prefix' => 'path1/',
                    'MaxKeys' => 1000,
                    'Marker' => '',
                ],
            ])->andReturn(new Model([
                'NextMarker' => '',
                'Contents' => [],
            ]));
        static::assertEmpty($this->obsAdapter->listContents('path1'));
        $this->mockPutObject('a/b/file.txt', 'test');
        $this->obsAdapter->write('a/b/file.txt', 'test', new Config());
        $this->client->shouldReceive('listObjects')
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
                        'LastModified' => 'Mon, 31 May 2021 06:52:32 GMT',
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

        $this->mockGetMetadata('a/b/file.txt');
        static::assertSame([
            [
                'type' => 'file',
                'mimetype' => null,
                'path' => 'a/b/file.txt',
                'timestamp' => 1622443952,
                'size' => 9,
            ], [
                'type' => 'dir',
                'path' => 'a/b',
            ],
        ], $this->obsAdapter->listContents('a', true));
    }

    public function testGetSize(): void
    {
        $this->mockGetMetadata('fixture/read.txt');
        static::assertSame(9, $this->obsAdapter->getSize('fixture/read.txt')['size']);
    }

    public function testGetTimestamp(): void
    {
        $this->mockGetMetadata('fixture/read.txt');
        static::assertSame(1622443952, $this->obsAdapter->getTimestamp('fixture/read.txt')['timestamp']);
    }

    public function testGetMimetype(): void
    {
        $this->mockGetMetadata('fixture/read.txt');
        static::assertSame('text/plain', $this->obsAdapter->getMimetype('fixture/read.txt')['mimetype']);
    }

    public function testHas(): void
    {
        $this->mockGetMetadata('fixture/read.txt');
        static::assertTrue((bool) $this->obsAdapter->has('fixture/read.txt'));
    }

    public function testSignUrl(): void
    {
        $this->client->shouldReceive('createSignedUrl')
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
        static::assertSame('signed-url', $this->obsAdapter->signUrl('fixture/read.txt', 10, []));
    }

    public function testGetTemporaryUrl(): void
    {
        $this->client->shouldReceive('createSignedUrl')
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
        static::assertSame('signed-url', $this->obsAdapter->getTemporaryUrl('fixture/read.txt', 10, []));
    }
}
