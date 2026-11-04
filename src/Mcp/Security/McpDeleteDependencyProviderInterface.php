<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Mcp\Security;

use AnzuSystems\CommonBundle\AnzuSystemsCommonBundle;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag(name: AnzuSystemsCommonBundle::TAG_MCP_DELETE_DEPENDENCY_PROVIDER)]
interface McpDeleteDependencyProviderInterface
{
    /**
     * @return class-string
     */
    public static function getSupportedEntity(): string;

    /**
     * @return list<string> short English descriptions of what uses the entity, e.g. "embedded 3 times in articles";
     *                      empty when nothing does
     */
    public function provideDependencies(object $entity): array;
}
