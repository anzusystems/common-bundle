<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Log\Helper;

use AnzuSystems\Contracts\Entity\Interfaces\BaseIdentifiableInterface;
use Symfony\Component\HttpFoundation\Request;

final class AuditLogResourceHelper
{
    public const string RESOURCE_NAME_ATTR_NAME = '_audit_log_resource_name';
    public const string RESOURCE_ID_ATTR_NAME = '_audit_log_resource_id';
    public const string RESOURCE_EXCLUDE_ATTR_NAME = '_audit_log_resource_exclude';

    public static function setResource(
        Request $request,
        string $resourceName,
        string|int|array $resourceId,
    ): void {
        $request->attributes->set(self::RESOURCE_NAME_ATTR_NAME, $resourceName);
        $request->attributes->set(self::RESOURCE_ID_ATTR_NAME, (array) $resourceId);
    }

    public static function setResourceByEntity(
        Request $request,
        BaseIdentifiableInterface $entity,
    ): void {
        $request->attributes->set(self::RESOURCE_NAME_ATTR_NAME, $entity::getResourceName());
        $request->attributes->set(self::RESOURCE_ID_ATTR_NAME, (array) $entity->getId());
    }

    public static function excludeFromAuditLogs(
        Request $request
    ): void {
        $request->attributes->set(self::RESOURCE_EXCLUDE_ATTR_NAME, true);
    }
}
