<?php


namespace Zing\Flysystem\Obs\Tests\Plugins;



use League\Flysystem\Filesystem;
use Mockery;
use Zing\Flysystem\Obs\ObsAdapter;
use Zing\Flysystem\Obs\Plugins\SignatureConfig;
use Zing\Flysystem\Obs\Tests\TestCase;

class SignatureConfigTest extends TestCase
{

    public function testSignatureConfig()
    {
        $adapter = Mockery::mock(ObsAdapter::class);
        $adapter->shouldReceive('signatureConfig')->withArgs(['test', 10, [], 30, 1048576000, []])->once()->andReturn('test-url');
        $filesystem = new Filesystem($adapter);
        $filesystem->addPlugin(new SignatureConfig());
        self::assertEquals('test-url', $filesystem->signatureConfig('test', 10));
    }
}
