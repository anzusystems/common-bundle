<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Validator\Constraints;

use Attribute;
use Symfony\Component\Validator\Constraint;

#[Attribute(Attribute::TARGET_PROPERTY)]
final class McpExistingId extends Constraint
{
    public const string RESOLVER_TOOL_PLACEHOLDER = '{{ resolverTool }}';
    public const string MESSAGE = 'Unknown id "{{ id }}" — no such record exists. Resolve it via ' . self::RESOLVER_TOOL_PLACEHOLDER . ' instead of guessing the id.';
    public const string LIST_MESSAGE = 'Unknown ids {{ ids }} — no such records exist. Resolve them via ' . self::RESOLVER_TOOL_PLACEHOLDER . ' instead of guessing the ids.';

    public string $message = self::MESSAGE;
    public string $listMessage = self::LIST_MESSAGE;

    /**
     * @param class-string $entity
     */
    public function __construct(
        public string $entity,
        public string $resolverTool,
        ?array $groups = null,
        mixed $payload = null,
    ) {
        parent::__construct(groups: $groups, payload: $payload);
    }
}
