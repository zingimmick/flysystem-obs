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
     * @param int $timeout
     * @param array $options
     * @param mixed $method
     * @return mixed
     */
    public function handle($path, $timeout, array $options = [], $method = 'GET')
    {
        return $this->filesystem->getAdapter()
            ->getTemporaryUrl($path, $timeout, $options, $method);
    }
}
