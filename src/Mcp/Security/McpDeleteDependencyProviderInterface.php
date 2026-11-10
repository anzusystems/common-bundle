<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Mcp\Security;

use AnzuSystems\CommonBundle\AnzuSystemsCommonBundle;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * An implementation names its entity class with #[AsTaggedItem(index: Note::class)], the key McpDeleteGuard finds it by.
 */
#[AutoconfigureTag(name: AnzuSystemsCommonBundle::TAG_MCP_DELETE_DEPENDENCY_PROVIDER)]
interface McpDeleteDependencyProviderInterface
{
    /**
     * @return list<string> short English descriptions of what uses the entity, e.g. "linked 3 times from other records";
     *                      empty when nothing does
     */
    public function provideDependencies(object $entity): array;
}
