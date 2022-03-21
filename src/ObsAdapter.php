<?php

declare(strict_types=1);

namespace Zing\Flysystem\Obs;

use GuzzleHttp\Psr7\Uri;
use League\Flysystem\Adapter\AbstractAdapter;
use League\Flysystem\AdapterInterface;
use League\Flysystem\Config;
use League\Flysystem\Util;
use Obs\ObsClient;
use Obs\ObsException;

class ObsAdapter extends AbstractAdapter
{
    /**
     * @var string
     */
    public const PUBLIC_GRANT_URI = 'http://acs.amazonaws.com/groups/global/AllUsers';

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
    protected static $metaOptions = [
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
    protected $endpoint;

    /**
     * @var string
     */
    protected $bucket;

    /**
     * @var mixed[]
     */
    protected $options = [];

    /**
     * @var \Obs\ObsClient
     */
    protected $client;

    /**
     * @param string $prefix
     * @param mixed[] $options
     */
    public function __construct(ObsClient $client, string $endpoint, string $bucket, $prefix = '', array $options = [])
    {
        $this->client = $client;
        $this->endpoint = $endpoint;
        $this->bucket = $bucket;
        $this->options = $options;
        $this->setPathPrefix($prefix);
    }

    /**
     * Get the S3Client bucket.
     *
     * @return string
     */
    public function getBucket()
    {
        return $this->bucket;
    }

    /**
     * Set the S3Client bucket.
     *
     * @param mixed $bucket
     */
    public function setBucket($bucket): void
    {
        $this->bucket = $bucket;
    }

    /**
     * Get the S3Client instance.
     *
     * @return \Obs\ObsClient
     */
    public function getClient()
    {
        return $this->client;
    }

    /**
     * write a file.
     *
     * @param string $path
     * @param string $contents
     *
     * @return array|false
     */
    public function write($path, $contents, Config $config)
    {
        $path = $this->applyPathPrefix($path);

        $options = $this->getOptionsFromConfig($config);
        if (! isset($options['ACL'])) {
            /** @var string|null $visibility */
            $visibility = $config->get('visibility');
            if ($visibility !== null) {
                $options['ACL'] = $options['ACL'] ?? ($visibility === self::VISIBILITY_PUBLIC ? ObsClient::AclPublicRead : ObsClient::AclPrivate);
            }
        }

        $shouldDetermineMimetype = $contents !== '' && ! \array_key_exists('ContentType', $options);

        if ($shouldDetermineMimetype) {
            $mimeType = Util::guessMimeType($path, $contents);
            if ($mimeType) {
                $options['ContentType'] = $mimeType;
            }
        }

        try {
            $this->client->putObject(array_merge($options, [
                'Bucket' => $this->bucket,
                'Key' => $path,
                'Body' => $contents,
            ]));
        } catch (ObsException $obsException) {
            return false;
        }

        return true;
    }

    /**
     * Write a new file using a stream.
     *
     * @param string $path
     * @param resource $resource
     *
     * @return array|false
     */
    public function writeStream($path, $resource, Config $config)
    {
        $contents = stream_get_contents($resource);

        return $this->write($path, $contents, $config);
    }

    /**
     * Update a file.
     *
     * @param string $path
     * @param string $contents
     *
     * @return array|false
     */
    public function update($path, $contents, Config $config)
    {
        return $this->write($path, $contents, $config);
    }

    /**
     * Update a file using a stream.
     *
     * @param string $path
     * @param resource $resource
     *
     * @return array|false
     */
    public function updateStream($path, $resource, Config $config)
    {
        return $this->writeStream($path, $resource, $config);
    }

    /**
     * rename a file.
     *
     * @param string $path
     * @param string $newpath
     *
     * @return bool
     */
    public function rename($path, $newpath)
    {
        if (! $this->copy($path, $newpath)) {
            return false;
        }

        return $this->delete($path);
    }

    /**
     * copy a file.
     *
     * @param string $path
     * @param string $newpath
     *
     * @return bool
     */
    public function copy($path, $newpath)
    {
        $newpath = $this->applyPathPrefix($newpath);

        try {
            $this->client->copyObject([
                'Bucket' => $this->bucket,
                'Key' => $newpath,
                'CopySource' => $this->bucket . '/' . $this->applyPathPrefix($path),
                'MetadataDirective' => ObsClient::CopyMetadata,
                'ACL' => $this->getRawVisibility($path),
            ]);
        } catch (ObsException $obsException) {
            return false;
        }

        return true;
    }

    /**
     * delete a file.
     *
     * @param string $path
     *
     * @return bool
     */
    public function delete($path)
    {
        $path = $this->applyPathPrefix($path);

        try {
            $this->client->deleteObject([
                'Bucket' => $this->bucket,
                'Key' => $path,
            ]);
        } catch (ObsException $obsException) {
            return false;
        }

        return ! $this->has($path);
    }

    /**
     * Delete a directory.
     *
     * @param string $dirname
     *
     * @return bool
     */
    public function deleteDir($dirname)
    {
        $result = $this->listDirObjects($dirname, true);
        $keys = array_column($result['objects'], 'Key');
        if ($keys === []) {
            return true;
        }

        try {
            foreach (array_chunk($keys, 1000) as $items) {
                $this->client->deleteObjects([
                    'Bucket' => $this->bucket,
                    'Objects' => array_map(function ($key): array {
                        return [
                            'Key' => $key,
                        ];
                    }, $items),
                ]);
            }
        } catch (ObsException $obsException) {
            return false;
        }

        return true;
    }

    /**
     * create a directory.
     *
     * @param string $dirname
     *
     * @return bool
     */
    public function createDir($dirname, Config $config)
    {
        $defaultFile = trim($dirname, '/') . '/';

        return $this->write($defaultFile, '', $config);
    }

    /**
     * visibility.
     *
     * @param string $path
     * @param string $visibility
     *
     * @return array|false
     */
    public function setVisibility($path, $visibility)
    {
        $acl = $visibility === AdapterInterface::VISIBILITY_PUBLIC ? ObsClient::AclPublicRead : ObsClient::AclPrivate;

        try {
            $this->client->setObjectAcl([
                'Bucket' => $this->bucket,
                'Key' => $this->applyPathPrefix($path),
                'ACL' => $acl,
            ]);
        } catch (ObsException $obsException) {
            return false;
        }

        return [
            'visibility' => $visibility,
            'path' => $path,
        ];
    }

    /**
     * Get the visibility of a file.
     *
     * @param string $path
     *
     * @return array|false
     */
    public function getVisibility($path)
    {
        try {
            $visibility = $this->getRawVisibility($path);
        } catch (ObsException $obsException) {
            return false;
        }

        return [
            'visibility' => $visibility,
        ];
    }

    /**
     * Get the object acl presented as a visibility.
     *
     * @param string $path
     *
     * @return string
     */
    protected function getRawVisibility($path)
    {
        $model = $this->client->getObjectAcl(
            [
                'Bucket' => $this->bucket,
                'Key' => $this->applyPathPrefix($path),
            ]
        );

        foreach ($model['Grants'] as $grant) {
            if (! isset($grant['Grantee']['URI'])) {
                continue;
            }

            if (! \in_array($grant['Grantee']['URI'], [self::PUBLIC_GRANT_URI, ObsClient::AllUsers], true)) {
                continue;
            }

            if ($grant['Permission'] !== 'READ') {
                continue;
            }

            return AdapterInterface::VISIBILITY_PUBLIC;
        }

        return AdapterInterface::VISIBILITY_PRIVATE;
    }

    /**
     * Check whether a file exists.
     *
     * @param string $path
     *
     * @return array|bool|null
     */
    public function has($path)
    {
        return $this->getMetadata($path);
    }

    /**
     * read a file.
     *
     * @param string $path
     *
     * @return array|false
     */
    public function read($path)
    {
        try {
            $contents = $this->getObject($path)
                ->getContents();
        } catch (ObsException $obsException) {
            return false;
        }

        return [
            'contents' => $contents,
            'path' => $path,
        ];
    }

    /**
     * read a file stream.
     *
     * @param string $path
     *
     * @return array|false
     */
    public function readStream($path)
    {
        try {
            $stream = $this->getObject($path)
                ->detach();
        } catch (ObsException $obsException) {
            return false;
        }

        return [
            'stream' => $stream,
            'path' => $path,
        ];
    }

    /**
     * Lists all files in the directory.
     *
     * @param string $directory
     * @param bool $recursive
     *
     * @return array
     */
    public function listContents($directory = '', $recursive = false)
    {
        $list = [];
        $directory = rtrim($directory, '/');
        $result = $this->listDirObjects($directory, $recursive);

        foreach ($result['objects'] as $files) {
            $path = $this->removePathPrefix(rtrim((string) ($files['Key'] ?? $files['Prefix']), '/'));
            if ($path === $directory) {
                continue;
            }

            $list[] = $this->mapObjectMetadata($files);
        }

        foreach ($result['prefix'] as $dir) {
            $list[] = [
                'type' => 'dir',
                'path' => rtrim($this->removePathPrefix($dir), '/'),
            ];
        }

        return $list;
    }

    /**
     * @param mixed $metadata
     * @param mixed|null $path
     *
     * @return array<string, mixed>|array<string, string>
     */
    private function mapObjectMetadata($metadata, $path = null)
    {
        if ($path === null) {
            $path = $metadata['Key'] ?? $metadata['Prefix'];
        }

        if ($this->isOnlyDir($this->removePathPrefix($path))) {
            return [
                'type' => 'dir',
                'path' => rtrim($this->removePathPrefix($path), '/'),
            ];
        }

        return [
            'type' => 'file',
            'mimetype' => $metadata['ContentType'] ?? null,
            'path' => $this->removePathPrefix($path),
            'timestamp' => strtotime($metadata['LastModified']),
            'size' => $metadata['ContentLength'] ?? $metadata['Size'] ?? null,
        ];
    }

    /**
     * get meta data.
     *
     * @param string $path
     *
     * @return array|false
     */
    public function getMetadata($path)
    {
        $path = $this->applyPathPrefix($path);

        try {
            $metadata = $this->client->getObjectMetadata([
                'Bucket' => $this->bucket,
                'Key' => $path,
            ]);
        } catch (ObsException $obsException) {
            return false;
        }

        return $this->mapObjectMetadata($metadata, $path);
    }

    /**
     * get the size of file.
     *
     * @param string $path
     *
     * @return array|false
     */
    public function getSize($path)
    {
        return $this->getMetadata($path);
    }

    /**
     * get mime type.
     *
     * @param string $path
     *
     * @return array|false
     */
    public function getMimetype($path)
    {
        return $this->getMetadata($path);
    }

    /**
     * get timestamp.
     *
     * @param string $path
     *
     * @return array|false
     */
    public function getTimestamp($path)
    {
        return $this->getMetadata($path);
    }

    /**
     * Check if the path contains only directories.
     *
     * @param string $path
     *
     * @return bool
     */
    private function isOnlyDir($path)
    {
        return substr($path, -1) === '/';
    }

    /**
     * Get resource url.
     *
     * @param string $path
     *
     * @return string
     */
    public function getUrl($path)
    {
        $path = $this->applyPathPrefix($path);

        if (isset($this->options['url'])) {
            return $this->concatPathToUrl($this->options['url'], $path);
        }

        return $this->normalizeHost() . ltrim($path, '/');
    }

    protected function concatPathToUrl($url, $path)
    {
        return rtrim($url, '/') . '/' . ltrim($path, '/');
    }

    protected function replaceBaseUrl($uri, $url)
    {
        $parsed = parse_url($url);

        return $uri
            ->withScheme($parsed['scheme'])
            ->withHost($parsed['host'])
            ->withPort($parsed['port'] ?? null);
    }

    /**
     * normalize Host.
     *
     * @return string
     */
    protected function normalizeHost()
    {
        $endpoint = $this->endpoint;
        if (strpos($endpoint, 'http') !== 0) {
            $endpoint = 'https://' . $endpoint;
        }

        $url = parse_url($endpoint);
        $domain = $url['host'];
        if (! ($this->options['bucket_endpoint'] ?? false)) {
            $domain = $this->bucket . '.' . $domain;
        }

        $domain = sprintf('%s://%s', $url['scheme'], $domain);

        return rtrim($domain, '/') . '/';
    }

    /**
     * Read an object from the ObsClient.
     *
     * @param $path
     *
     * @return \Obs\Internal\Common\CheckoutStream
     */
    protected function getObject($path)
    {
        $path = $this->applyPathPrefix($path);

        $model = $this->client->getObject([
            'Bucket' => $this->bucket,
            'Key' => $path,
        ]);

        return $model['Body'];
    }

    /**
     * File list core method.
     *
     * @param string $dirname
     * @param bool $recursive
     *
     * @return array
     */
    public function listDirObjects($dirname = '', $recursive = false)
    {
        $prefix = trim($this->applyPathPrefix($dirname), '/');
        $prefix = empty($prefix) ? '' : $prefix . '/';

        $nextMarker = '';

        $result = [];

        while (true) {
            $model = $this->client->listObjects([
                'Bucket' => $this->bucket,
                'Prefix' => $prefix,
                'MaxKeys' => self::MAX_KEYS,
                'Marker' => $nextMarker,
            ]);
            if (! $recursive) {
                $model['Delimiter'] = self::DELIMITER;
            }

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
     * @param mixed $objects
     * @param mixed $dirname
     *
     * @return mixed[]
     */
    private function processObjects(array $result, $objects, $dirname): array
    {
        $result['objects'] = [];
        if (! empty($objects)) {
            foreach ($objects as $object) {
                $result['objects'][] = array_merge($object, [
                    'Prefix' => $dirname,
                ]);
            }
        }

        return $result;
    }

    /**
     * @param mixed $prefixes
     *
     * @return mixed[]
     */
    private function processPrefixes(array $result, $prefixes): array
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
     * sign url.
     *
     * @param $path
     * @param \DateTimeInterface|int $expiration
     * @param mixed $method
     *
     * @return bool|string
     */
    public function signUrl($path, $expiration, array $options = [], $method = 'GET')
    {
        $expires = $expiration instanceof \DateTimeInterface ? $expiration->getTimestamp() - time() : $expiration;
        $path = $this->applyPathPrefix($path);

        try {
            $model = $this->client->createSignedUrl([
                'Method' => $method,
                'Bucket' => $this->bucket,
                'Key' => $path,
                'Expires' => $expires,
                'QueryParams' => $options,
            ]);

            return $model['SignedUrl'];
        } catch (ObsException $obsException) {
            return false;
        }
    }

    /**
     * temporary file url.
     *
     * @param string $path
     * @param \DateTimeInterface|int $expiration
     * @param mixed $method
     *
     * @return bool|string
     */
    public function getTemporaryUrl($path, $expiration, array $options = [], $method = 'GET')
    {
        $url = $this->signUrl($path, $expiration, $options, $method);
        if ($url === false) {
            return false;
        }

        $uri = new Uri($url);
        $url = $this->options['temporary_url'] ?? null;
        if ($url !== null) {
            $uri = $this->replaceBaseUrl($uri, $url);
        }

        return (string) $uri;
    }

    /**
     * Get options from the config.
     *
     * @return array
     */
    protected function getOptionsFromConfig(Config $config)
    {
        $options = $this->options;
        $visibility = $config->get('visibility');
        if ($visibility) {
            // For local reference
            $options['visibility'] = $visibility;
            // For external reference
            $options['ACL'] = $visibility === AdapterInterface::VISIBILITY_PUBLIC ? ObsClient::AclPublicRead : ObsClient::AclPrivate;
        }

        $mimetype = $config->get('mimetype');
        if ($mimetype) {
            // For local reference
            $options['mimetype'] = $mimetype;
            // For external reference
            $options['ContentType'] = $mimetype;
        }

        foreach (static::$metaOptions as $option) {
            if (! $config->has($option)) {
                continue;
            }

            $options[$option] = $config->get($option);
        }

        return $options;
    }
}
