# Flysystem OBS

<p align="center">
<a href="https://github.com/zingimmick/flysystem-obs/actions"><img src="https://github.com/zingimmick/flysystem-obs/workflows/tests/badge.svg" alt="Build Status"></a>
<a href="https://codecov.io/gh/zingimmick/flysystem-obs"><img src="https://codecov.io/gh/zingimmick/flysystem-obs/branch/master/graph/badge.svg" alt="Code Coverage" /></a>
<a href="https://packagist.org/packages/zing/flysystem-obs"><img src="https://poser.pugx.org/zing/flysystem-obs/v/stable.svg" alt="Latest Stable Version"></a>
<a href="https://packagist.org/packages/zing/flysystem-obs"><img src="https://poser.pugx.org/zing/flysystem-obs/downloads" alt="Total Downloads"></a>
<a href="https://packagist.org/packages/zing/flysystem-obs"><img src="https://poser.pugx.org/zing/flysystem-obs/v/unstable.svg" alt="Latest Unstable Version"></a>
<a href="https://packagist.org/packages/zing/flysystem-obs"><img src="https://poser.pugx.org/zing/flysystem-obs/license" alt="License"></a>
</p>

> **Requires**
> - **[PHP 7.2.0+](https://php.net/releases/)**
> - **[Flysystem 2.0+](https://github.com/thephpleague/flysystem/releases)**

## Version Information

| Version | Flysystem | PHP Version | Status                  |
|:--------|:----------|:------------|:------------------------|
| 2.x     | 2.x - 3.x | >= 7.2      | Active support :rocket: |
| 1.x     | 1.x       | >= 7.2      | Active support          |

Require Flysystem OBS using [Composer](https://getcomposer.org):

```bash
composer require zing/flysystem-obs
```

## Usage

```php

use League\Flysystem\Filesystem;
use Obs\ObsClient;
use Zing\Flysystem\Obs\ObsAdapter;

$prefix = '';
$config = [
    'key' => 'aW52YWxpZC1rZXk=',
    'secret' => 'aW52YWxpZC1zZWNyZXQ=',
    'bucket' => 'test',
    'endpoint' => 'obs.cn-east-3.myhuaweicloud.com',
];

$config['options'] = [
    'url' => '',
    'endpoint' => $config['endpoint'], 
    'bucket_endpoint' => '',
    'temporary_url' => '',
];

$client = new ObsClient($config);
$adapter = new ObsAdapter($client, $config['bucket'], $prefix, null, null, $config['options']);
$flysystem = new Filesystem($adapter);
```

## Integration

- Laravel: [zing/laravel-flysystem-obs](https://github.com/zingimmick/laravel-flysystem-obs)

## Reference

[league/flysystem-aws-s3-v3](https://github.com/thephpleague/flysystem-aws-s3-v3)

## License

Flysystem OBS is an open-sourced software licensed under the [MIT license](LICENSE).
