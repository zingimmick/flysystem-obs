<?php

declare(strict_types=1);

namespace Zing\Flysystem\Obs\Plugins;

use League\Flysystem\Plugin\AbstractPlugin;

class Kernel extends AbstractPlugin
{
    /**
     * @return string
     */
    public function getMethod()
    {
        return 'kernel';
    }

    /**
     * @return mixed
     */
    public function handle()
    {
        return $this->filesystem->getAdapter()
            ->getClient();
    }
}
