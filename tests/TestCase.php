<?php

declare(strict_types=1);

namespace Zing\Flysystem\Obs\Tests;

use GuzzleHttp\Psr7\Utils;
use PHPUnit\Framework\TestCase as BaseTestCase;
use function GuzzleHttp\Psr7\stream_for;

class TestCase extends BaseTestCase
{
    protected function streamFor($resource = '', array $options = [])
    {
        if (function_exists('\GuzzleHttp\Psr7\stream_for')) {
            return stream_for($resource, $options);
        }

        return Utils::streamFor($resource, $options);
    }
}
