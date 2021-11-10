<?php

declare(strict_types=1);

namespace Zing\Flysystem\Obs;

interface VisibilityConverter
{
    public function visibilityToAcl(string $visibility): string;

    /**
     * @param array<array{Grantee?: array<string,mixed>, Permission?: string}> $grants
     */
    public function aclToVisibility(array $grants): string;

    public function defaultForDirectories(): string;
}
