<?php

declare(strict_types=1);

namespace Zing\Flysystem\Obs;

interface VisibilityConverter
{
    public function visibilityToAcl(string $visibility): string;

    /**
     * @param array<array<string,mixed>> $grants
     */
    public function aclToVisibility(array $grants): string;

    public function defaultForDirectories(): string;
}
