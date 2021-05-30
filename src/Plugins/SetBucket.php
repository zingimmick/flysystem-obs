<?php

declare(strict_types=1);

namespace Zing\Flysystem\Obs\Plugins;

use League\Flysystem\Plugin\AbstractPlugin;

class SetBucket extends AbstractPlugin
{
    /**
     * sign url.
     *
     * @return string
     */
    public function getMethod()
    {
        return 'bucket';
    }

    /**
     * handle.
     *
     * @param $bucket
     *
     * @return mixed
     */
    public function handle($bucket)
    {
        return $this->filesystem->getAdapter()
            ->setBucket($bucket);
    }
}
