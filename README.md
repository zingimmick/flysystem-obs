# Flysystem OBS
<p align="center">
<a href="https://github.com/zingimmick/flysystem-obs/actions"><img src="https://github.com/zingimmick/flysystem-obs/workflows/tests/badge.svg" alt="Build Status"></a>
<a href="https://codecov.io/gh/zingimmick/flysystem-obs"><img src="https://codecov.io/gh/zingimmick/flysystem-obs/branch/master/graph/badge.svg" alt="Code Coverage" /></a>
<a href="https://packagist.org/packages/zing/flysystem-obs"><img src="https://poser.pugx.org/zing/flysystem-obs/v/stable.svg" alt="Latest Stable Version"></a>
<a href="https://packagist.org/packages/zing/flysystem-obs"><img src="https://poser.pugx.org/zing/flysystem-obs/downloads" alt="Total Downloads"></a>
<a href="https://packagist.org/packages/zing/flysystem-obs"><img src="https://poser.pugx.org/zing/flysystem-obs/v/unstable.svg" alt="Latest Unstable Version"></a>
<a href="https://packagist.org/packages/zing/flysystem-obs"><img src="https://poser.pugx.org/zing/flysystem-obs/license" alt="License"></a>
</p>

> **Requires [PHP 7.2.0+](https://php.net/releases/)**

Require Flysystem OBS using [Composer](https://getcomposer.org):

```bash
composer require zing/flysystem-obs
```

## Usage

```php
use League\Flysystem\Filesystem;
use Zing\Flysystem\Obs\ObsAdapter;
use Obs\ObsClient;

$prefix = '';
$config = [
   'key' => 'aW52YWxpZC1rZXk=',
   'secret' => 'aW52YWxpZC1zZWNyZXQ=',
   'bucket' => 'test',
   'endpoint' => 'obs.cn-east-3.myhuaweicloud.com',
   'is_cname' => true
];

$options = [
    'url' => 'https://oss.cdn.com',
    'bucket_endpoint' => $config['is_cname'] ?? false
];

$client = new ObsClient($config);
$adapter = new ObsAdapter($client, $config['endpoint'], $config['bucket'], $prefix, $options);
$flysystem = new Filesystem($adapter);
```

## Plugins

```php
use Zing\Flysystem\Obs\Plugins\FileUrl;
use Zing\Flysystem\Obs\Plugins\SignUrl;
use Zing\Flysystem\Obs\Plugins\TemporaryUrl;
use Zing\Flysystem\Obs\Plugins\SetBucket;

/** @var \League\Flysystem\Filesystem $filesystem */

// get file url
$filesystem->addPlugin(new FileUrl());
$filesystem->getUrl('file.txt');

// get temporary url
$filesystem->addPlugin(new SignUrl());
$timeout = 30;
// GET
$filesystem->signUrl('file.txt', $timeout, ['x-image-process' => 'image/crop,x_100,y_50']);
// PUT
$filesystem->signUrl('file.txt', $timeout, ['x-image-process' => 'image/crop,x_100,y_50'], 'PUT');
// alias for signUrl()
$filesystem->addPlugin(new TemporaryUrl());
$filesystem->getTemporaryUrl('file.txt', $timeout);

// change bucket
$filesystem->addPlugin(new SetBucket());
$filesystem->bucket('test')->has('file.txt');
```

## Reference

[league/flysystem-aws-s3-v3](https://github.com/thephpleague/flysystem-aws-s3-v3)

[iidestiny/flysystem-oss](https://github.com/iiDestiny/flysystem-oss)

## License

Flysystem OBS is an open-sourced software licensed under the [MIT license](LICENSE).
