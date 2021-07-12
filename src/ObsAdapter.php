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
    private const EXTRA_METADATA_FIELDS = ['Metadata', 'StorageClass', 'ETag', 'VersionId'];

    /**
     * @var array
     */
    protected static $metaOptions = ['ACL', 'Expires', 'StorageClass'];

    /**
     * @var string
     */
    protected $bucket;

    /**
     * @var array
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
     * @param \Obs\ObsClient $client
     * @param string $bucket
     * @param string $prefix
     * @param \Zing\Flysystem\Obs\VisibilityConverter|null $visibility
     * @param \League\MimeTypeDetection\MimeTypeDetector|null $mimeTypeDetector
     * @param array $options
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
     *
     * @param string $path
     * @param string $contents
     * @param \League\Flysystem\Config $config
     *
     * @return array|false
     */
    public function write(string $path, string $contents, Config $config): void
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
     * @param resource $contents
     *
     * @throws \League\Flysystem\UnableToWriteFile
     * @throws \League\Flysystem\FilesystemException
     */
    public function writeStream(string $path, $contents, Config $config): void
    {
        $this->write($path, stream_get_contents($contents), $config);
    }

    /**
     * rename a file.
     *
     * @param string $source
     * @param string $destination
     * @param \League\Flysystem\Config $config
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
     *
     * @param string $source
     * @param string $destination
     * @param \League\Flysystem\Config $config
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
     *
     * @param string $path
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
     *
     * @param string $path
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
     *
     * @param string $path
     * @param \League\Flysystem\Config $config
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
     *
     * @param string $path
     * @param string $visibility
     *
     * @return array|false
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
     *
     * @param string $path
     *
     * @return array|false
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
     *
     * @param string $path
     *
     * @return array|bool|null
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
     *
     * @param string $path
     *
     * @return array|false
     */
    public function read(string $path): string
    {
        return $this->getObject($path)
            ->getContents();
    }

    /**
     * read a file stream.
     *
     * @param string $path
     *
     * @return resource
     */
    public function readStream(string $path)
    {
        return $this->getObject($path)
            ->detach();
    }

    /**
     * Lists all files in the directory.
     *
     * @param string $path
     * @param bool $deep
     *
     * @return \Traversable
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
     *
     * @param string $path
     * @param string $type
     *
     * @return \League\Flysystem\FileAttributes
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
     *
     * @param string $path
     *
     * @return array|false
     */
    public function fileSize(string $path): FileAttributes
    {
        return $this->getMetadata($path, FileAttributes::ATTRIBUTE_FILE_SIZE);
    }

    /**
     * get mime type.
     *
     * @param string $path
     *
     * @return array|false
     */
    public function mimeType(string $path): FileAttributes
    {
        return $this->getMetadata($path, FileAttributes::ATTRIBUTE_MIME_TYPE);
    }

    /**
     * get timestamp.
     *
     * @param string $path
     *
     * @return array|false
     */
    public function lastModified(string $path): FileAttributes
    {
        return $this->getMetadata($path, FileAttributes::ATTRIBUTE_LAST_MODIFIED);
    }

    /**
     * Read an object from the ObsClient.
     *
     * @param $path
     *
     * @return \Psr\Http\Message\StreamInterface
     */
    protected function getObject($path): StreamInterface
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
     * @param string $dirname
     * @param bool $recursive
     *
     * @throws \Obs\ObsException
     *
     * @return array
     */
    public function listDirObjects(string $dirname = '', bool $recursive = false): array
    {
        $delimiter = '/';
        $nextMarker = '';
        $maxKeys = 1000;

        $result = [];

        while (true) {
            $options = [
                'Bucket' => $this->bucket,
                'Delimiter' => $delimiter,
                'Prefix' => $dirname,
                'MaxKeys' => $maxKeys,
                'Marker' => $nextMarker,
            ];

            $model = $this->client->listObjects($options);

            $nextMarker = $model['NextMarker'];
            $objects = $model['Contents'];
            $prefixes = $model['CommonPrefixes'];
            if (! empty($objects)) {
                foreach ($objects as $object) {
                    $result['objects'][] = array_merge($object, [
                        'Prefix' => $dirname,
                    ]);
                }
            } else {
                $result['objects'] = [];
            }

            if (! empty($prefixes)) {
                foreach ($prefixes as $prefix) {
                    $result['prefix'][] = $prefix['Prefix'];
                }
            } else {
                $result['prefix'] = [];
            }

            // Recursive directory
            if ($recursive) {
                foreach ($result['prefix'] as $prefix) {
                    $next = $this->listDirObjects($prefix, $recursive);
                    $result['objects'] = array_merge($result['objects'], $next['objects']);
                }
            }

            if ($nextMarker === '') {
                break;
            }
        }//end while

        return $result;
    }

    /**
     * Get options from the config.
     *
     * @param \League\Flysystem\Config $config
     *
     * @return array
     */
    protected function createOptionsFromConfig(Config $config): array
    {
        $options = $this->options;

        foreach (static::$metaOptions as $option) {
            $value = $config->get($option, '__NOT_SET__');

            if ($value !== '__NOT_SET__') {
                $options[$option] = $value;
            }
        }

        return $options;
    }
}
