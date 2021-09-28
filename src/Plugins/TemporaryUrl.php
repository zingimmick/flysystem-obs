<?php

declare(strict_types=1);

namespace Zing\Flysystem\Obs\Plugins;

use League\Flysystem\Plugin\AbstractPlugin;

class TemporaryUrl extends AbstractPlugin
{
    /**
     * getTemporaryUrl.
     *
     * @return string
     */
    public function getMethod()
    {
        return 'getTemporaryUrl';
    }

    /**
     * handle.
     *
     * @param string $path
     * @param \DateTimeInterface|int $expiration
     * @param mixed $method
     *
     * @return mixed
     */
    public function handle($path, $expiration, array $options = [], $method = 'GET')
    {
        return $this->filesystem->getAdapter()
            ->getTemporaryUrl($path, $expiration, $options, $method);
    }
}
