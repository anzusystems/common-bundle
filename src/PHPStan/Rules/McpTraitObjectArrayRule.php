<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\PHPStan\Rules;

use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\Node\InTraitNode;
use PHPStan\Rules\Rule;

/**
 * InClassNode carries only the class's own statements, so the members of a used trait reach a rule through InTraitNode.
 *
 * @implements Rule<InTraitNode>
 */
final readonly class McpTraitObjectArrayRule implements Rule
{
    public function __construct(
        private McpObjectArrayRule $objectArrayRule,
    ) {
    }

    public function getNodeType(): string
    {
        return InTraitNode::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        return $this->objectArrayRule->findErrors(classLike: $node->getOriginalNode(), classReflection: $node->getTraitReflection());
    }
}
