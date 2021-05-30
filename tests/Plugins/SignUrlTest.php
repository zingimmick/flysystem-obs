<?php

declare(strict_types=1);

namespace Zing\Flysystem\Obs\Tests\Plugins;

use League\Flysystem\Filesystem;
use Mockery;
use Zing\Flysystem\Obs\ObsAdapter;
use Zing\Flysystem\Obs\Plugins\SignUrl;
use Zing\Flysystem\Obs\Tests\TestCase;

class SignUrlTest extends TestCase
{
    public function testSignUrl(): void
    {
        $adapter = Mockery::mock(ObsAdapter::class);
        $adapter->shouldReceive('signUrl')
            ->withArgs(['test', 10, [], 'GET'])->once()->andReturn('test-url');
        $filesystem = new Filesystem($adapter);
        $filesystem->addPlugin(new SignUrl());
        self::assertSame('test-url', $filesystem->signUrl('test', 10));
    }
}
