<?php

declare(strict_types=1);

namespace Zing\Flysystem\Obs;

use League\Flysystem\Visibility;
use Obs\ObsClient;

class PortableVisibilityConverter implements VisibilityConverter
{
    /**
     * @var string
     */
    private const PUBLIC_GRANTEE_URI = 'http://acs.amazonaws.com/groups/global/AllUsers';

    /**
     * @var string
     */
    private const PUBLIC_GRANTS_PERMISSION = 'READ';

    /**
     * @var string
     */
    private const PUBLIC_ACL = ObsClient::AclPublicRead;

    /**
     * @var string
     */
    private const PRIVATE_ACL = ObsClient::AclPrivate;

    /**
     * @var string
     */
    private $defaultForDirectories;

    public function __construct(string $defaultForDirectories = Visibility::PUBLIC)
    {
        $this->defaultForDirectories = $defaultForDirectories;
    }

    public function visibilityToAcl(string $visibility): string
    {
        if ($visibility === Visibility::PUBLIC) {
            return self::PUBLIC_ACL;
        }

        return self::PRIVATE_ACL;
    }

    public function aclToVisibility(array $grants): string
    {
        foreach ($grants as $grant) {
            $granteeUri = $grant['Grantee']['URI'] ?? null;
            $permission = $grant['Permission'] ?? null;
            if (! in_array($granteeUri, [self::PUBLIC_GRANTEE_URI, ObsClient::AllUsers], true)) {
                continue;
            }

            if ($permission !== self::PUBLIC_GRANTS_PERMISSION) {
                continue;
            }

            return Visibility::PUBLIC;
        }

        return Visibility::PRIVATE;
    }

    public function defaultForDirectories(): string
    {
        return $this->defaultForDirectories;
    }
}
