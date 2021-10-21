<?php

declare(strict_types=1);

namespace Zing\Flysystem\Obs;

use League\Flysystem\Config;
use League\Flysystem\DirectoryAttributes;
use League\Flysystem\FileAttributes;
use League\Flysystem\FilesystemAdapter;
use League\Flysystem\FilesystemOperationFailed;
use League\Flysystem\PathPrefixer;
use League\Flysystem\StorageAttributes;
use League\Flysystem\UnableToCopyFile;
use League\Flysystem\UnableToCreateDirectory;
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
use Throwable;

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
    private const AVAILABLE_OPTIONS = ['ACL', 'Expires', 'StorageClass', 'ContentType'];

    /**
     * @var string
     */
    protected $bucket;

    /**
     * @var array<string,mixed>
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
     * @var \Zing\Flysystem\Obs\VisibilityConverter
     */
    private $visibilityConverter;

    /**
     * @var \League\MimeTypeDetection\MimeTypeDetector
     */
    private $mimeTypeDetector;

    /**
     * @param array<string,mixed> $options
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

    /**
     * write a file.
     */
    public function write(string $path, string $contents, Config $config): void
    {
        $this->upload($path, $contents, $config);
    }

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
            $visibility = $config->get(Config::OPTION_VISIBILITY);
            if ($visibility !== null) {
                $options['ACL'] = $options['ACL'] ?? $this->visibilityConverter->visibilityToAcl($visibility);
            }
        }

        $shouldDetermineMimetype = $contents !== '' && ! array_key_exists('ContentType', $options);

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

    /**
     * rename a file.
     */
    public function move(string $source, string $destination, Config $config): void
    {
        try {
            $this->copy($source, $destination, $config);
            $this->delete($source);
        } catch (FilesystemOperationFailed $filesystemOperationFailed) {
            throw UnableToMoveFile::fromLocationTo($source, $destination);
        }
    }

    /**
     * copy a file.
     */
    public function copy(string $source, string $destination, Config $config): void
    {
        try {
            $this->client->copyObject(array_merge($this->createOptionsFromConfig($config), [
                'Bucket' => $this->bucket,
                'Key' => $this->pathPrefixer->prefixPath($destination),
                'CopySource' => $this->bucket . '/' . $this->pathPrefixer->prefixPath($source),
                'MetadataDirective' => ObsClient::CopyMetadata,
            ]));
        } catch (ObsException $exception) {
            throw UnableToCopyFile::fromLocationTo($source, $destination, $exception);
        }
    }

    /**
     * delete a file.
     */
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

    /**
     * Delete a directory.
     */
    public function deleteDirectory(string $path): void
    {
        $files = $this->listContents($path, true);
        foreach ($files as $file) {
            $this->delete($file['path']);
        }
    }

    /**
     * create a directory.
     */
    public function createDirectory(string $path, Config $config): void
    {
        try {
            $this->write(trim($path, '/') . '/', '', $config);
        } catch (FilesystemOperationFailed $exception) {
            throw UnableToCreateDirectory::dueToFailure($path, $exception);
        }
    }

    /**
     * visibility.
     */
    public function setVisibility(string $path, string $visibility): void
    {
        try {
            $this->client->setObjectAcl([
                'Bucket' => $this->bucket,
                'Key' => $this->pathPrefixer->prefixPath($path),
                'ACL' => $this->visibilityConverter->visibilityToAcl($visibility),
            ]);
        } catch (ObsException $exception) {
            throw UnableToSetVisibility::atLocation($path, '', $exception);
        }
    }

    /**
     * Get the visibility of a file.
     */
    public function visibility(string $path): FileAttributes
    {
        try {
            $result = $this->client->getObjectAcl(
                [
                    'Bucket' => $this->bucket,
                    'Key' => $this->pathPrefixer->prefixPath($path),
                ]
            );
        } catch (Throwable $exception) {
            throw UnableToRetrieveMetadata::visibility($path, '', $exception);
        }

        $visibility = $this->visibilityConverter->aclToVisibility((array) $result->get('Grants'));

        return new FileAttributes($path, null, $visibility);
    }

    /**
     * Check whether a file exists.
     */
    public function fileExists(string $path): bool
    {
        try {
            $this->getMetadata($path, FileAttributes::ATTRIBUTE_PATH);
        } catch (Throwable $throwable) {
            return false;
        }

        return true;
    }

    /**
     * read a file.
     */
    public function read(string $path): string
    {
        return $this->getObject($path)
            ->getContents();
    }

    /**
     * read a file stream.
     *
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
     * Lists all files in the directory.
     *
     * @return \Traversable<\League\Flysystem\StorageAttributes>
     */
    public function listContents(string $path, bool $deep): iterable
    {
        $directory = substr($path, -1) === '/' ? $path : $path . '/';
        $result = $this->listDirObjects($directory, $deep);

        foreach ($result['objects'] as $files) {
            yield $this->mapObjectMetadata($files);
        }

        foreach ($result['prefix'] as $dir) {
            yield new DirectoryAttributes($dir);
        }
    }

    /**
     * get meta data.
     */
    private function getMetadata(string $path, string $type): FileAttributes
    {
        try {
            $metadata = $this->client->getObjectMetadata([
                'Bucket' => $this->bucket,
                'Key' => $this->pathPrefixer->prefixPath($path),
            ]);
        } catch (ObsException $exception) {
            throw UnableToRetrieveMetadata::create($path, $type, '', $exception);
        }

        $attributes = $this->mapObjectMetadata($metadata->toArray(), $path);

        if (! $attributes instanceof FileAttributes) {
            throw UnableToRetrieveMetadata::create($path, $type);
        }

        return $attributes;
    }

    /**
     * @param array<string,mixed> $metadata
     */
    private function mapObjectMetadata(array $metadata, ?string $path = null): StorageAttributes
    {
        if ($path === null) {
            $path = $this->pathPrefixer->stripPrefix($metadata['Key'] ?? $metadata['Prefix']);
        }

        if (substr($path, -1) === '/') {
            return new DirectoryAttributes(rtrim($path, '/'));
        }

        return new FileAttributes(
            $path,
            $metadata['ContentLength'] ?? $metadata['Size'] ?? null,
            null,
            strtotime($metadata['LastModified']),
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

    /**
     * get the size of file.
     */
    public function fileSize(string $path): FileAttributes
    {
        return $this->getMetadata($path, FileAttributes::ATTRIBUTE_FILE_SIZE);
    }

    /**
     * get mime type.
     */
    public function mimeType(string $path): FileAttributes
    {
        return $this->getMetadata($path, FileAttributes::ATTRIBUTE_MIME_TYPE);
    }

    public function lastModified(string $path): FileAttributes
    {
        return $this->getMetadata($path, FileAttributes::ATTRIBUTE_LAST_MODIFIED);
    }

    /**
     * Read an object from the ObsClient.
     */
    protected function getObject(string $path): StreamInterface
    {
        try {
            $model = $this->client->getObject([
                'Bucket' => $this->bucket,
                'Key' => $this->pathPrefixer->prefixPath($path),
            ]);

            return $model['Body'];
        } catch (Throwable $throwable) {
            throw UnableToReadFile::fromLocation($path, '', $throwable);
        }
    }

    /**
     * File list core method.
     *
     * @return array<string,mixed>
     */
    public function listDirObjects(string $dirname = '', bool $recursive = false): array
    {
        $nextMarker = '';

        $result = [];

        while (true) {
            $model = $this->client->listObjects([
                'Bucket' => $this->bucket,
                'Delimiter' => self::DELIMITER,
                'Prefix' => $dirname,
                'MaxKeys' => self::MAX_KEYS,
                'Marker' => $nextMarker,
            ]);

            $nextMarker = $model['NextMarker'];
            $objects = $model['Contents'];
            $prefixes = $model['CommonPrefixes'];
            $result = $this->processObjects($result, $objects, $dirname);
            $result = $this->processPrefixes($result, $prefixes);
            $result = $this->processRecursive($result, $recursive);

            if ($nextMarker === '') {
                break;
            }
        }

        return $result;
    }

    /**
     * @param array<string,array> $result
     *
     * @return array<string,array>
     */
    private function processRecursive(array $result, bool $recursive): array
    {
        if ($recursive) {
            foreach ($result['prefix'] as $prefix) {
                $next = $this->listDirObjects($prefix, $recursive);
                $result['objects'] = array_merge($result['objects'], $next['objects']);
            }
        }

        return $result;
    }

    /**
     * @param array<string,array> $result
     * @param array<string,mixed>|null $objects
     *
     * @return array<string,array>
     */
    private function processObjects(array $result, ?array $objects, string $dirname): array
    {
        if (! empty($objects)) {
            foreach ($objects as $object) {
                $result['objects'][] = array_merge($object, [
                    'Prefix' => $dirname,
                ]);
            }
        } else {
            $result['objects'] = [];
        }

        return $result;
    }

    /**
     * @param array<string,array> $result
     * @param array<string,mixed>|null $prefixes
     *
     * @return array<string,array>
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

        foreach (self::AVAILABLE_OPTIONS as $option) {
            $value = $config->get($option, '__NOT_SET__');

            if ($value !== '__NOT_SET__') {
                $options[$option] = $value;
            }
        }

        return $options;
    }
}
