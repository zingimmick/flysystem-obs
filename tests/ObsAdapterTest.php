<?php

declare(strict_types=1);

namespace Zing\Flysystem\Obs\Tests;

use League\Flysystem\AdapterInterface;
use League\Flysystem\Config;
use League\Flysystem\Filesystem;
use Obs\ObsClient;
use Zing\Flysystem\Obs\ObsAdapter;
use Zing\Flysystem\Obs\Plugins\FileUrl;
use Zing\Flysystem\Obs\Plugins\Kernel;
use Zing\Flysystem\Obs\Plugins\SetBucket;
use Zing\Flysystem\Obs\Plugins\SignatureConfig;
use Zing\Flysystem\Obs\Plugins\SignUrl;
use Zing\Flysystem\Obs\Plugins\TemporaryUrl;

class ObsAdapterTest extends TestCase
{
    public function testInvalid(): void
    {
        $config = [
            'key' => 'invalid key',
            'secret' => 'invalid secret',
            'bucket' => 'test',
            'endpoint' => 'obs.cn-east-3.myhuaweicloud.com',
            'path_style' => '',
            'region' => '',
        ];
        $obsClient = new ObsClient($config);
        $obsAdapter = new ObsAdapter($obsClient, $config['endpoint'], $config['bucket']);
        $config = new Config();
        self::assertFalse($obsAdapter->write('11', 'test', $config));
        self::assertFalse($obsAdapter->read('11'));
        self::assertFalse($obsAdapter->readStream('11'));
        self::assertFalse($obsAdapter->has('11'));
        self::assertFalse($obsAdapter->setVisibility('11', AdapterInterface::VISIBILITY_PUBLIC));
        self::assertFalse($obsAdapter->getVisibility('11'));
        self::assertFalse($obsAdapter->getSize('11'));
        self::assertFalse($obsAdapter->signUrl('11', 10, [], null));
        self::assertFalse($obsAdapter->getMimetype('11'));
        self::assertFalse($obsAdapter->getTimestamp('11'));
        self::assertFalse($obsAdapter->delete('11'));
        self::assertFalse($obsAdapter->rename('11', '22'));
        self::assertFalse($obsAdapter->copy('11', '22'));
        self::assertFalse($obsAdapter->update('11', 'test', $config));
        $filesystem = new Filesystem($obsAdapter);
        $filesystem->addPlugin(new FileUrl());
        $filesystem->addPlugin(new Kernel());
        $filesystem->addPlugin(new SetBucket());
        $filesystem->addPlugin(new SignatureConfig());
        $filesystem->addPlugin(new SignUrl());
        $filesystem->addPlugin(new TemporaryUrl());
        self::assertInstanceOf(ObsClient::class, $filesystem->kernel());
    }
}
