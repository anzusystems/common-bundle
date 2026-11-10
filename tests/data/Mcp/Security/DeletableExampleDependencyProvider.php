<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Tests\Data\Mcp\Security;

use AnzuSystems\CommonBundle\Mcp\Security\McpDeleteDependencyProviderInterface;
use AnzuSystems\CommonBundle\Tests\Data\Mcp\Model\DeletableExample;
use Symfony\Component\DependencyInjection\Attribute\AsTaggedItem;

#[AsTaggedItem(index: DeletableExample::class)]
final readonly class DeletableExampleDependencyProvider implements McpDeleteDependencyProviderInterface
{
    /**
     * @param list<string> $dependencies
     */
    public function __construct(
        private array $dependencies,
    ) {
    }

    public function provideDependencies(object $entity): array
    {
        return $this->dependencies;
    }
}
