<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Controller;

use AnzuSystems\CommonBundle\Model\OpenApi\Response\OAResponse;
use AnzuSystems\CommonBundle\Security\PermissionConfig;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;

#[OA\Tag('Permission')]
final class PermissionController extends AbstractAnzuApiController
{
    public function __construct(
        private readonly PermissionConfig $permissionConfig,
    ) {
    }

    #[OAResponse(description: 'Permission configuration.')]
    public function getConfig(): JsonResponse
    {
        return $this->okResponse(
            $this->permissionConfig
        );
    }
}
