<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Mcp\Security\Voter;

use AnzuSystems\CommonBundle\Mcp\Security\McpToolPermission;
use AnzuSystems\CommonBundle\Security\Voter\AbstractVoter;

/**
 * @template-extends AbstractVoter<string, null>
 */
final class McpToolPermissionVoter extends AbstractVoter
{
    protected function supports(string $attribute, mixed $subject): bool
    {
        return McpToolPermission::isToolPermission($attribute);
    }

    protected function getSupportedPermissions(): array
    {
        return [];
    }
}
