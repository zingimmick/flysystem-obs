<?php

declare(strict_types=1);

namespace Zing\Flysystem\Obs\Tests\Plugins;

use League\Flysystem\Filesystem;
use Mockery;
use Obs\ObsClient;
use Zing\Flysystem\Obs\ObsAdapter;
use Zing\Flysystem\Obs\Plugins\Kernel;
use Zing\Flysystem\Obs\Tests\TestCase;

class KernelTest extends TestCase
{
    public function testKernel(): void
    {
        $adapter = Mockery::mock(ObsAdapter::class);
        $adapter->shouldReceive('getClient')
            ->withNoArgs()
            ->once()
            ->andReturn(Mockery::mock(ObsClient::class));
        $filesystem = new Filesystem($adapter);
        $filesystem->addPlugin(new Kernel());
        self::assertInstanceOf(ObsClient::class, $filesystem->kernel());
    }
}
