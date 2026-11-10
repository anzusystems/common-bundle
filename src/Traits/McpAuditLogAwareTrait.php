<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Traits;

use AnzuSystems\CommonBundle\Log\Helper\AuditLogResourceHelper;
use AnzuSystems\Contracts\Entity\Interfaces\BaseIdentifiableInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\Service\Attribute\Required;

trait McpAuditLogAwareTrait
{
    private readonly RequestStack $requestStack;

    #[Required]
    public function setRequestStack(
        RequestStack $requestStack
    ): void {
        $this->requestStack = $requestStack;
    }

    private function setAuditResourceByEntity(BaseIdentifiableInterface $entity): void
    {
        $request = $this->requestStack->getMainRequest();
        if ($request instanceof Request) {
            AuditLogResourceHelper::setResourceByEntity(request: $request, entity: $entity);
        }
    }

    private function setAuditResource(string $resourceName, int|string $resourceId): void
    {
        $request = $this->requestStack->getMainRequest();
        if ($request instanceof Request) {
            AuditLogResourceHelper::setResource(request: $request, resourceName: $resourceName, resourceId: $resourceId);
        }
    }
}
