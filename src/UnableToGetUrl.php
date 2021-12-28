<?php

namespace Zing\Flysystem\Obs;

use League\Flysystem\FilesystemException;
use RuntimeException;

class UnableToGetUrl extends RuntimeException implements FilesystemException
{
    public static function missingOption(string $option): UnableToGetUrl
    {
        return new self("Unable to get url with option $option missing.");
    }
}
