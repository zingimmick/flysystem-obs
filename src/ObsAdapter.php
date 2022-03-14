<?php

declare(strict_types=1);

namespace Zing\Flysystem\Obs;

use DateTimeInterface;
use GuzzleHttp\Psr7\Uri;
use League\Flysystem\Config;
use League\Flysystem\DirectoryAttributes;
use League\Flysystem\FileAttributes;
use League\Flysystem\FilesystemAdapter;
use League\Flysystem\FilesystemOperationFailed;
use League\Flysystem\PathPrefixer;
use League\Flysystem\StorageAttributes;
use League\Flysystem\UnableToCheckDirectoryExistence;
use League\Flysystem\UnableToCopyFile;
use League\Flysystem\UnableToCreateDirectory;
use League\Flysystem\UnableToDeleteDirectory;
use League\Flysystem\UnableToDeleteFile;
use League\Flysystem\UnableToMoveFile;
use League\Flysystem\UnableToReadFile;
use League\Flysystem\UnableToRetrieveMetadata;
use League\Flysystem\UnableToSetVisibility;
use League\Flysystem\UnableToWriteFile;
use League\MimeTypeDetection\FinfoMimeTypeDetector;
use League\MimeTypeDetection\MimeTypeDetector;
use Obs\ObsClient;
use Obs\ObsException;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\UriInterface;

class ObsAdapter implements FilesystemAdapter
{
    /**
     * @var string[]
     */
    private const EXTRA_METADATA_FIELDS = ['Metadata', 'StorageClass', 'ETag', 'VersionId'];

    /**
     * @var string
     */
    private const DELIMITER = '/';

    /**
     * @var int
     */
    private const MAX_KEYS = 1000;

    /**
     * @var string[]
     */
    private const AVAILABLE_OPTIONS = [
        'ACL',
        'StorageClass',
        'ContentType',
        'ContentLength',
        'Metadata',
        'WebsiteRedirectLocation',
        'SseKms',
        'SseKmsKey',
        'SseC',
        'SseCKey',
        'Expires',
        'SuccessRedirect',
    ];

    /**
     * @var string
     */
    protected $bucket;

    /**
     * @var mixed[]|array<string, bool>|array<string, string>
     * @phpstan-var array{url?: string, temporary_url?: string, endpoint?: string, bucket_endpoint?: bool}
     */
    protected $options = [];

    /**
     * @var \Obs\ObsClient
     */
    protected $client;

    /**
     * @var \League\Flysystem\PathPrefixer
     */
    private $pathPrefixer;

    /**
     * @var \Zing\Flysystem\Obs\PortableVisibilityConverter|\Zing\Flysystem\Obs\VisibilityConverter
     */
    private $visibilityConverter;

    /**
     * @var \League\MimeTypeDetection\FinfoMimeTypeDetector|\League\MimeTypeDetection\MimeTypeDetector
     */
    private $mimeTypeDetector;

    /**
     * @param array{url?: string, temporary_url?: string, endpoint?: string, bucket_endpoint?: bool} $options
     */
    public function __construct(
        ObsClient $client,
        string $bucket,
        string $prefix = '',
        ?VisibilityConverter $visibility = null,
        ?MimeTypeDetector $mimeTypeDetector = null,
        array $options = []
    ) {
        $this->client = $client;
        $this->bucket = $bucket;
        $this->pathPrefixer = new PathPrefixer($prefix);
        $this->visibilityConverter = $visibility ?: new PortableVisibilityConverter();
        $this->mimeTypeDetector = $mimeTypeDetector ?: new FinfoMimeTypeDetector();
        $this->options = $options;
    }

    public function getBucket(): string
    {
        return $this->bucket;
    }

    public function getClient(): ObsClient
    {
        return $this->client;
    }

    public function write(string $path, string $contents, Config $config): void
    {
        $this->upload($path, $contents, $config);
    }

    public function kernel(): ObsClient
    {
        return $this->getClient();
    }

    public function setBucket(string $bucket): void
    {
        $this->bucket = $bucket;
    }

    /**
     * @param resource $contents
     */
    public function writeStream(string $path, $contents, Config $config): void
    {
        $this->upload($path, $contents, $config);
    }

    /**
     * @param resource|string $contents
     */
    private function upload(string $path, $contents, Config $config): void
    {
        $options = $this->createOptionsFromConfig($config);
        if (! isset($options['ACL'])) {
            /** @var string|null $visibility */
            $visibility = $config->get(Config::OPTION_VISIBILITY);
            if ($visibility !== null) {
                $options['ACL'] = $options['ACL'] ?? $this->visibilityConverter->visibilityToAcl($visibility);
            }
        }

        $shouldDetermineMimetype = $contents !== '' && ! \array_key_exists('ContentType', $options);

        if ($shouldDetermineMimetype) {
            $mimeType = $this->mimeTypeDetector->detectMimeType($path, $contents);
            if ($mimeType) {
                $options['ContentType'] = $mimeType;
            }
        }

        try {
            $this->client->putObject(array_merge($options, [
                'Bucket' => $this->bucket,
                'Key' => $this->pathPrefixer->prefixPath($path),
                'Body' => $contents,
            ]));
        } catch (ObsException $obsException) {
            throw UnableToWriteFile::atLocation($path, '', $obsException);
        }
    }

    public function move(string $source, string $destination, Config $config): void
    {
        try {
            $this->copy($source, $destination, $config);
            $this->delete($source);
        } catch (FilesystemOperationFailed $filesystemOperationFailed) {
            throw UnableToMoveFile::fromLocationTo($source, $destination);
        }
    }

    public function copy(string $source, string $destination, Config $config): void
    {
        try {
            /** @var string $visibility */
            $visibility = $this->visibility($source)
                ->visibility();
        } catch (FilesystemOperationFailed $filesystemOperationFailed) {
            throw UnableToCopyFile::fromLocationTo($source, $destination, $filesystemOperationFailed);
        }

        try {
            $this->client->copyObject(array_merge($this->createOptionsFromConfig($config), [
                'Bucket' => $this->bucket,
                'Key' => $this->pathPrefixer->prefixPath($destination),
                'CopySource' => $this->bucket . '/' . $this->pathPrefixer->prefixPath($source),
                'MetadataDirective' => ObsClient::CopyMetadata,
                'ACL' => $this->visibilityConverter->visibilityToAcl($visibility),
            ]));
        } catch (ObsException $obsException) {
            throw UnableToCopyFile::fromLocationTo($source, $destination, $obsException);
        }
    }

    public function delete(string $path): void
    {
        try {
            $this->client->deleteObject([
                'Bucket' => $this->bucket,
                'Key' => $this->pathPrefixer->prefixPath($path),
            ]);
        } catch (ObsException $obsException) {
            throw UnableToDeleteFile::atLocation($path, '', $obsException);
        }
    }

    public function deleteDirectory(string $path): void
    {
        $result = $this->listDirObjects($path, true);
        $keys = array_column($result['objects'], 'Key');
        if ($keys === []) {
            return;
        }

        try {
            $this->client->deleteObjects([
                'Bucket' => $this->bucket,
                'Objects' => array_map(function ($key): array {
                    return [
                        'Key' => $key,
                    ];
                }, $keys),
            ]);
        } catch (ObsException $obsException) {
            throw UnableToDeleteDirectory::atLocation($path, '', $obsException);
        }
    }

    public function createDirectory(string $path, Config $config): void
    {
        $defaultVisibility = $config->get('directory_visibility', $this->visibilityConverter->defaultForDirectories());
        $config = $config->withDefaults([
            'visibility' => $defaultVisibility,
        ]);

        try {
            $this->write(trim($path, '/') . '/', '', $config);
        } catch (FilesystemOperationFailed $filesystemOperationFailed) {
            throw UnableToCreateDirectory::dueToFailure($path, $filesystemOperationFailed);
        }
    }

    public function setVisibility(string $path, string $visibility): void
    {
        try {
            $this->client->setObjectAcl([
                'Bucket' => $this->bucket,
                'Key' => $this->pathPrefixer->prefixPath($path),
                'ACL' => $this->visibilityConverter->visibilityToAcl($visibility),
            ]);
        } catch (ObsException $obsException) {
            throw UnableToSetVisibility::atLocation($path, '', $obsException);
        }
    }

    public function visibility(string $path): FileAttributes
    {
        try {
            $result = $this->client->getObjectAcl(
                [
                    'Bucket' => $this->bucket,
                    'Key' => $this->pathPrefixer->prefixPath($path),
                ]
            );
        } catch (ObsException $obsException) {
            throw UnableToRetrieveMetadata::visibility($path, '', $obsException);
        }

        $visibility = $this->visibilityConverter->aclToVisibility((array) $result->get('Grants'));

        return new FileAttributes($path, null, $visibility);
    }

    public function fileExists(string $path): bool
    {
        try {
            $this->getMetadata($path, FileAttributes::ATTRIBUTE_PATH);
        } catch (FilesystemOperationFailed $filesystemOperationFailed) {
            return false;
        }

        return true;
    }

    public function directoryExists(string $path): bool
    {
        try {
            $prefix = $this->pathPrefixer->prefixDirectoryPath($path);
            $options = [
                'Bucket' => $this->bucket,
                'Prefix' => $prefix,
                'Delimiter' => '/',
                'MaxKeys' => 1,
            ];
            $model = $this->client->listObjects($options);

            return $model['Contents'] !== [];
        } catch (ObsException $obsException) {
            throw UnableToCheckDirectoryExistence::forLocation($path, $obsException);
        }
    }

    public function read(string $path): string
    {
        return $this->getObject($path)
            ->getContents();
    }

    /**
     * @return resource
     */
    public function readStream(string $path)
    {
        /** @var resource $resource */
        $resource = $this->getObject($path)
            ->detach();

        return $resource;
    }

    /**
     * @return \Traversable<\League\Flysystem\StorageAttributes>
     */
    public function listContents(string $path, bool $deep): iterable
    {
        $directory = rtrim($path, '/');
        $result = $this->listDirObjects($directory, $deep);

        foreach ($result['objects'] as $files) {
            $path = $this->pathPrefixer->stripDirectoryPrefix((string) ($files['Key'] ?? $files['Prefix']));
            if ($path === $directory) {
                continue;
            }

            yield $this->mapObjectMetadata($files);
        }

        foreach ($result['prefix'] as $dir) {
            yield new DirectoryAttributes($this->pathPrefixer->stripDirectoryPrefix($dir));
        }
    }

    /**
     * Get the metadata of a file.
     */
    private function getMetadata(string $path, string $type): FileAttributes
    {
        try {
            $metadata = $this->client->getObjectMetadata([
                'Bucket' => $this->bucket,
                'Key' => $this->pathPrefixer->prefixPath($path),
            ]);
        } catch (ObsException $obsException) {
            throw UnableToRetrieveMetadata::create($path, $type, '', $obsException);
        }

        $attributes = $this->mapObjectMetadata($metadata->toArray(), $path);

        if (! $attributes instanceof FileAttributes) {
            throw UnableToRetrieveMetadata::create($path, $type);
        }

        return $attributes;
    }

    /**
     * @param array{Key: string|null, Prefix: ?string, ContentLength?: int, Size?: int, LastModified?: string, ContentType?: string} $metadata
     */
    private function mapObjectMetadata(array $metadata, ?string $path = null): StorageAttributes
    {
        if ($path === null) {
            $path = $this->pathPrefixer->stripPrefix((string) ($metadata['Key'] ?? $metadata['Prefix']));
        }

        if (substr($path, -1) === '/') {
            return new DirectoryAttributes(rtrim($path, '/'));
        }

        $dateTime = isset($metadata['LastModified']) ? strtotime($metadata['LastModified']) : null;
        $lastModified = $dateTime ?: null;

        return new FileAttributes(
            $path,
            $metadata['ContentLength'] ?? $metadata['Size'] ?? null,
            null,
            $lastModified,
            $metadata['ContentType'] ?? null,
            $this->extractExtraMetadata($metadata)
        );
    }

    /**
     * @param array<string,mixed> $metadata
     *
     * @return array<string,mixed>
     */
    private function extractExtraMetadata(array $metadata): array
    {
        $extracted = [];

        foreach (self::EXTRA_METADATA_FIELDS as $field) {
            if (isset($metadata[$field]) && $metadata[$field] !== '') {
                $extracted[$field] = $metadata[$field];
            }
        }

        return $extracted;
    }

    public function fileSize(string $path): FileAttributes
    {
        $attributes = $this->getMetadata($path, FileAttributes::ATTRIBUTE_FILE_SIZE);
        if ($attributes->fileSize() === null) {
            throw UnableToRetrieveMetadata::fileSize($path);
        }

        return $attributes;
    }

    public function mimeType(string $path): FileAttributes
    {
        $attributes = $this->getMetadata($path, FileAttributes::ATTRIBUTE_MIME_TYPE);
        if ($attributes->mimeType() === null) {
            throw UnableToRetrieveMetadata::mimeType($path);
        }

        return $attributes;
    }

    public function lastModified(string $path): FileAttributes
    {
        $attributes = $this->getMetadata($path, FileAttributes::ATTRIBUTE_LAST_MODIFIED);
        if ($attributes->lastModified() === null) {
            throw UnableToRetrieveMetadata::lastModified($path);
        }

        return $attributes;
    }

    /**
     * Read an object from the ObsClient.
     */
    protected function getObject(string $path): StreamInterface
    {
        try {
            /** @var array{Body: \Psr\Http\Message\StreamInterface} $model */
            $model = $this->client->getObject([
                'Bucket' => $this->bucket,
                'Key' => $this->pathPrefixer->prefixPath($path),
            ]);

            return $model['Body'];
        } catch (ObsException $obsException) {
            throw UnableToReadFile::fromLocation($path, '', $obsException);
        }
    }

    /**
     * File list core method.
     *
     * @return array{prefix: array<string>, objects: array<array{Key: string|null, Prefix: string|null, ContentLength?: int, Size?: int, LastModified?: string, ContentType?: string}>}
     */
    public function listDirObjects(string $dirname = '', bool $recursive = false): array
    {
        $prefix = trim($this->pathPrefixer->prefixPath($dirname), '/');
        $prefix = empty($prefix) ? '' : $prefix . '/';

        $nextMarker = '';

        $result = [];

        $options = [
            'Bucket' => $this->bucket,
            'Prefix' => $prefix,
            'MaxKeys' => self::MAX_KEYS,
        ];
        if (! $recursive) {
            $options['Delimiter'] = self::DELIMITER;
        }

        while (true) {
            $options['Marker'] = $nextMarker;

            /** @var array{Contents: array<array{Key: string|null, Prefix: string|null, ContentLength?: int, Size?: int, LastModified?: string, ContentType?: string}>|null, CommonPrefixes: array<array<string, string>>|null, NextMarker: ?string} $model */
            $model = $this->client->listObjects($options);

            $nextMarker = $model['NextMarker'];
            $objects = $model['Contents'];
            $prefixes = $model['CommonPrefixes'];
            $result = $this->processObjects($result, $objects, $dirname);
            $result = $this->processPrefixes($result, $prefixes);

            if ($nextMarker === '') {
                break;
            }
        }

        return $result;
    }

    /**
     * @param array{prefix?: array<string>, objects?: array<array{Key: string|null, Prefix: string|null, ContentLength?: int, Size?: int, LastModified?: string, ContentType?: string}>} $result
     * @param array<array{Key: string|null, Prefix: string|null, ContentLength?: int, Size?: int, LastModified?: string, ContentType?: string}>|null $objects
     *
     * @return array{prefix?: array<string>, objects: array<array{Key: string|null, Prefix: string|null, ContentLength?: int, Size?: int, LastModified?: string, ContentType?: string}>}
     */
    private function processObjects(array $result, ?array $objects, string $dirname): array
    {
        $result['objects'] = [];
        if (! empty($objects)) {
            foreach ($objects as $object) {
                $object['Prefix'] = $dirname;
                $result['objects'][] = $object;
            }
        }

        return $result;
    }

    /**
     * @param array{prefix?: array<string>, objects: array<array{Key: string|null, Prefix: string|null, ContentLength?: int, Size?: int, LastModified?: string, ContentType?: string}>} $result
     * @param array<array<string, string>>|null $prefixes
     *
     * @return array{prefix: array<string>, objects: array<array{Key: string|null, Prefix: string|null, ContentLength?: int, Size?: int, LastModified?: string, ContentType?: string}>}
     */
    private function processPrefixes(array $result, ?array $prefixes): array
    {
        if (! empty($prefixes)) {
            foreach ($prefixes as $prefix) {
                $result['prefix'][] = $prefix['Prefix'];
            }
        } else {
            $result['prefix'] = [];
        }

        return $result;
    }

    /**
     * Get options from the config.
     *
     * @return array<string, mixed>
     */
    protected function createOptionsFromConfig(Config $config): array
    {
        $options = $this->options;
        $mimetype = $config->get('mimetype');
        if ($mimetype) {
            $options['ContentType'] = $mimetype;
        }

        foreach (self::AVAILABLE_OPTIONS as $option) {
            $value = $config->get($option, '__NOT_SET__');

            if ($value !== '__NOT_SET__') {
                $options[$option] = $value;
            }
        }

        return $options;
    }

    /**
     * Get the URL for the file at the given path.
     */
    public function getUrl(string $path): string
    {
        if (isset($this->options['url'])) {
            return $this->concatPathToUrl($this->options['url'], $this->pathPrefixer->prefixPath($path));
        }

        return $this->concatPathToUrl($this->normalizeHost(), $this->pathPrefixer->prefixPath($path));
    }

    protected function normalizeHost(): string
    {
        if (! isset($this->options['endpoint'])) {
            throw UnableToGetUrl::missingOption('endpoint');
        }

        $endpoint = $this->options['endpoint'];
        if (strpos($endpoint, 'http') !== 0) {
            $endpoint = 'https://' . $endpoint;
        }

        /** @var array{scheme: string, host: string} $url */
        $url = parse_url($endpoint);
        $domain = $url['host'];
        if (! ($this->options['bucket_endpoint'] ?? false)) {
            $domain = $this->bucket . '.' . $domain;
        }

        $domain = sprintf('%s://%s', $url['scheme'], $domain);

        return rtrim($domain, '/') . '/';
    }

    /**
     * Get a signed URL for the file at the given path.
     *
     * @param \DateTimeInterface|int $expiration
     * @param array<string, mixed> $options
     */
    public function signUrl(string $path, $expiration, array $options = [], string $method = 'GET'): string
    {
        $expires = $expiration instanceof DateTimeInterface ? $expiration->getTimestamp() - time() : $expiration;

        /** @var array{SignedUrl: string} $model */
        $model = $this->client->createSignedUrl([
            'Method' => $method,
            'Bucket' => $this->bucket,
            'Key' => $this->pathPrefixer->prefixPath($path),
            'Expires' => $expires,
            'QueryParams' => $options,
        ]);

        return $model['SignedUrl'];
    }

    /**
     * Get a temporary URL for the file at the given path.
     *
     * @param \DateTimeInterface|int $expiration
     * @param array<string, mixed> $options
     */
    public function getTemporaryUrl(string $path, $expiration, array $options = [], string $method = 'GET'): string
    {
        $uri = new Uri($this->signUrl($path, $expiration, $options, $method));

        if (isset($this->options['temporary_url'])) {
            $uri = $this->replaceBaseUrl($uri, $this->options['temporary_url']);
        }

        return (string) $uri;
    }

    /**
     * Concatenate a path to a URL.
     */
    protected function concatPathToUrl(string $url, string $path): string
    {
        return rtrim($url, '/') . '/' . ltrim($path, '/');
    }

    /**
     * Replace the scheme, host and port of the given UriInterface with values from the given URL.
     */
    protected function replaceBaseUrl(UriInterface $uri, string $url): UriInterface
    {
        /** @var array{scheme: string, host: string, port?: int} $parsed */
        $parsed = parse_url($url);

        return $uri
            ->withScheme($parsed['scheme'])
            ->withHost($parsed['host'])
            ->withPort($parsed['port'] ?? null);
    }
}
