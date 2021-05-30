<?php

declare(strict_types=1);

namespace Zing\Flysystem\Obs\Tests\Plugins;

use League\Flysystem\Filesystem;
use Mockery;
use Zing\Flysystem\Obs\ObsAdapter;
use Zing\Flysystem\Obs\Plugins\TemporaryUrl;
use Zing\Flysystem\Obs\Tests\TestCase;

class TemporaryUrlTest extends TestCase
{
    public function testGetTemporaryUrl(): void
    {
        $adapter = Mockery::mock(ObsAdapter::class);
        $adapter->shouldReceive('getTemporaryUrl')
            ->withArgs(['test', 10, [], 'GET'])->once()->andReturn('test-url');
        $filesystem = new Filesystem($adapter);
        $filesystem->addPlugin(new TemporaryUrl());
        self::assertSame('test-url', $filesystem->getTemporaryUrl('test', 10));
    }
}
