<?php

declare(strict_types=1);

namespace Zing\Flysystem\Obs;

use League\Flysystem\FilesystemException;
use RuntimeException;

class UnableToGetUrl extends RuntimeException implements FilesystemException
{
    public static function missingOption(string $option, string $reason = ''): self
    {
        return new self(sprintf('Unable to get url with option %s missing.' . "\nreason: {$reason}", $option));
    }
}
