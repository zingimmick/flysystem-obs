<?php


namespace Zing\Flysystem\Obs;

use League\Flysystem\Adapter\AbstractAdapter;
use League\Flysystem\AdapterInterface;
use League\Flysystem\Config;
use Obs\ObsClient;
use Obs\ObsException;

class ObsAdapter extends AbstractAdapter
{
    /**
     * @var
     */
    protected $endpoint;

    /**
     * @var
     */
    protected $bucket;

    /**
     * @var
     */
    protected $isCName;
    /**
     * @var \Obs\ObsClient
     */
    protected $client;


    /**
     * @var bool
     */
    protected $useSSL = false;

    /**
     * @var string|null
     */
    protected $cdnUrl;

    /**
     *
     * @param \Obs\ObsClient $client
     * @param string $endpoint
     * @param string $bucket
     * @param bool $isCName
     * @param string $prefix
     */
    public function __construct(ObsClient $client, string $endpoint, string $bucket, $isCName = false, $prefix = '')
    {
        $this->client = $client;
        $this->endpoint = $endpoint;
        $this->bucket = $bucket;
        $this->isCName = $isCName;
        $this->setPathPrefix($prefix);
        $this->checkEndpoint();
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
     * @return void
     */
    public function setBucket($bucket)
    {
        $this->bucket = $bucket;
    }

    /**
     * Get the S3Client instance.
     *
     * @return ObsClient
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

        $options = [];

        if ($config->has('options')) {
            $options = $config->get('options');
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
     * @throws \Obs\ObsException
     * @return bool
     *
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
        $path = $this->applyPathPrefix($path);
        $newpath = $this->applyPathPrefix($newpath);

        try {
            $this->client->copyObject([
                'Bucket' => $this->bucket,
                'Key' => $newpath,
                'CopySource' => $this->bucket . '/' . $path,
                'MetadataDirective' => ObsClient::CopyMetadata,
            ]);
        } catch (ObsException $exception) {
            return false;
        }

        return true;
    }

    /**
     * delete a file.
     *
     * @param string $path
     *
     * @throws \Obs\ObsException
     * @return bool
     *
     */
    public function delete($path)
    {
        $path = $this->applyPathPrefix($path);

        try {
            $this->client->deleteObject([
                'Bucket' => $this->bucket, 'Key' => $path,
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
     * @throws \Obs\ObsException
     * @return bool
     *
     */
    public function deleteDir($dirname)
    {
        $files = $this->listContents($dirname, true);
        foreach ($files as $file) {
            $this->delete($file['path']);
        }

        return ! $this->has($dirname);
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

        return $this->write($defaultFile, null, $config);
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
        $acl = $visibility ===AdapterInterface::VISIBILITY_PUBLIC  ? ObsClient::AclPublicRead : ObsClient::AclPrivate;

        try {
            $this->client->setObjectAcl([
                'Bucket' => $this->bucket,
                'Key' => $this->applyPathPrefix($path),
                'ACL' => $acl,
            ]);
        } catch (ObsException $exception) {
            return false;
        }

        return compact('visibility', 'path');
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
        return ['visibility' => $visibility];
    }

    public const PUBLIC_GRANT_URI = 'http://acs.amazonaws.com/groups/global/AllUsers';

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

        $visibility = AdapterInterface::VISIBILITY_PRIVATE;

        foreach ($model['Grants'] as $grant) {
            if (
                isset($grant['Grantee']['URI'])
                && $grant['Grantee']['URI'] === self::PUBLIC_GRANT_URI
                && $grant['Permission'] === 'READ'
            ) {
                $visibility = AdapterInterface::VISIBILITY_PUBLIC;
                break;
            }
        }

        return $visibility;
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
            $contents = $this->getObject($path);
        } catch (ObsException $exception) {
            return false;
        }

        return compact('contents', 'path');
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
            $stream = fopen('php://temp', 'w+b');
            fwrite($stream, $this->getObject($path));
            rewind($stream);
        } catch (ObsException $exception) {
            return false;
        }

        return compact('stream', 'path');
    }

    /**
     * Lists all files in the directory.
     *
     * @param string $directory
     * @param bool $recursive
     *
     * @throws \Obs\ObsException
     * @return array
     *
     */
    public function listContents($directory = '', $recursive = false)
    {
        $list = [];
        $directory = '/' === substr($directory, -1) ? $directory : $directory . '/';
        $result = $this->listDirObjects($directory, $recursive);

        foreach ($result['objects'] as $files) {
            if (! $fileInfo = $this->getMetadata($files['Key'])) {
                continue;
            }
            $list[] = $fileInfo;
        }

        foreach ($result['prefix'] as $dir) {
            $list[] = [
                'type' => 'dir',
                'path' => $dir,
            ];
        }

        return $list;
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
                'Bucket' => $this->bucket, 'Key' => $path,
            ]);
        } catch (ObsException $exception) {
            return false;
        }
        if ($this->isOnlyDir($this->removePathPrefix($path))) {
            return [
                'type' => 'dir',
                'path' => rtrim($this->removePathPrefix($path), '/'),
            ];
        }
        return [
            'type' => 'file',
            'mimetype' => $metadata['ContentType'],
            'path' => $this->removePathPrefix($path),
            'timestamp' => strtotime($metadata['LastModified']),
            'size' => $metadata['ContentLength'],
        ];
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
     * Check if the path contains only directories
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

        if (! is_null($this->cdnUrl)) {
            return rtrim($this->cdnUrl, '/') . '/' . ltrim($path, '/');
        }

        return $this->normalizeHost() . ltrim($path, '/');
    }
    /**
     * normalize Host.
     *
     * @return string
     */
    protected function normalizeHost()
    {
        if ($this->isCName) {
            $domain = $this->endpoint;
        } else {
            $domain = $this->bucket . '.' . $this->endpoint;
        }

        if ($this->useSSL) {
            $domain = "https://{$domain}";
        } else {
            $domain = "http://{$domain}";
        }

        return rtrim($domain, '/') . '/';
    }

    /**
     * Check the endpoint to see if SSL can be used.
     */
    protected function checkEndpoint()
    {
        if (0 === strpos($this->endpoint, 'http://')) {
            $this->endpoint = substr($this->endpoint, strlen('http://'));
            $this->useSSL = false;
        } elseif (0 === strpos($this->endpoint, 'https://')) {
            $this->endpoint = substr($this->endpoint, strlen('https://'));
            $this->useSSL = true;
        }
    }

    /**
     * Read an object from the ObsClient.
     *
     * @param $path
     *
     * @return string
     */
    protected function getObject($path)
    {
        $path = $this->applyPathPrefix($path);

        $model= $this->client->getObject([
            'Bucket' => $this->bucket, 'Key' => $path,
        ]);

        return $model['Body'];
    }

    /**
     * File list core method.
     *
     * @param string $dirname
     * @param bool $recursive
     *
     * @throws \Obs\ObsException
     * @return array
     *
     */
    public function listDirObjects($dirname = '', $recursive = false)
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

            try {
                $model = $this->client->listObjects($options);
            } catch (ObsException $exception) {
                throw $exception;
            }

            $nextMarker = $model['NextMarker'];
            $objects = $model['Contents'];
            $prefixes = $model['CommonPrefixes'];
            if (! empty($objects)) {
                foreach ($objects as $object) {
                    $result['objects'][] = array_merge($object,['Prefix'=>$dirname]);
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

            if ('' === $nextMarker) {
                break;
            }
        }

        return $result;
    }

    /**
     * 设置cdn的url.
     *
     * @param string|null $url
     */
    public function setCdnUrl($url)
    {
        $this->cdnUrl = $url;
    }

    /**
     * 获取直传配置.
     *
     * @param string $prefix
     * @param null $callBackUrl
     * @param array $customData
     * @param int $expire
     * @param int $contentLengthRangeValue
     * @param array $systemData
     *
     * @throws \Exception
     * @return false|array
     *
     */
    public function signatureConfig($prefix = '', $callBackUrl = null, $customData = [], $expire = 30, $contentLengthRangeValue = 1048576000, $systemData = [])
    {
        if (! empty($prefix)) {
            $prefix = ltrim($prefix, '/');
        }
        return $this->client->createPostSignature([
            'Bucket' => $this->bucket,
            'Key' => $prefix,
            'Expires' => $expire,
        ])->toArray();
    }

    /**
     * sign url.
     *
     * @param $path
     * @param $timeout
     *
     * @return bool|string
     */
    public function signUrl($path, $timeout, array $options = [], $method = 'GET')
    {
        $path = $this->applyPathPrefix($path);

        try {
            $model = $this->client->createSignedUrl(array_merge([
                'Method' => $method,
                'Bucket' => $this->bucket,
                'Key' => $path,
                'Expires' => $timeout,
            ],$options));
        } catch (ObsException $exception) {
            return false;
        }

        return $model['SignedUrl'];
    }

    /**
     * temporary file url.
     *
     * @param $path
     * @param $expiration
     *
     * @return bool|string
     */
    public function getTemporaryUrl($path, $expiration, array $options = [], $method = 'GET')
    {
        return $this->signUrl($path, Carbon::now()->diffInSeconds($expiration), $options, $method);
    }
}
