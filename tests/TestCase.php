<?php

declare(strict_types=1);

namespace Zing\Flysystem\Obs\Tests;

use GuzzleHttp\Psr7\Utils;
use PHPUnit\Framework\TestCase as BaseTestCase;
use function GuzzleHttp\Psr7\stream_for;

class TestCase extends BaseTestCase
{
    /**
     * @param array<string,mixed> $options
     */
    protected function streamFor(string $content = '', array $options = []): \Psr\Http\Message\StreamInterface
    {
        if (function_exists('\GuzzleHttp\Psr7\stream_for')) {
            return stream_for($content, $options);
        }

        return Utils::streamFor($content, $options);
    }

    /**
     * @param array<string,mixed> $options
     *
     * @return resource
     */
    protected function streamForResource(string $content = '', array $options = [])
    {
        /** @var resource $resource */
        $resource = $this->streamFor($content, $options)
            ->detach();

        return $resource;
    }
}
