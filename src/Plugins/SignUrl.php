<?php

declare(strict_types=1);

namespace Zing\Flysystem\Obs\Plugins;

use League\Flysystem\Plugin\AbstractPlugin;

class SignUrl extends AbstractPlugin
{
    /**
     * sign url.
     *
     * @return string
     */
    public function getMethod()
    {
        return 'signUrl';
    }

    /**
     * handle.
     *
     * @param $path
     * @param \DateTimeInterface|int $expiration
     * @param array $options
     * @param mixed $method
     *
     * @return mixed
     */
    public function handle($path, $expiration, array $options = [], $method = 'GET')
    {
        return $this->filesystem->getAdapter()
            ->signUrl($path, $expiration, $options, $method);
    }
}
