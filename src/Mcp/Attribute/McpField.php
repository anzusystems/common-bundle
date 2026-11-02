<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Mcp\Attribute;

use Attribute;

/**
 * Describes a field of an MCP tool input or output, or a value of a backed enum such a field takes. The values replace
 * the {{ name }} placeholders of the description, like the parameters of a validation message.
 */
#[Attribute(Attribute::TARGET_PROPERTY | Attribute::TARGET_METHOD | Attribute::TARGET_CLASS_CONSTANT)]
final readonly class McpField
{
    private const string PLACEHOLDER_FORMAT = '{{ %s }}';

    public string $description;

    /**
     * @param array<string, int|float|string> $values
     */
    public function __construct(string $description, array $values = [])
    {
        $this->description = self::fillPlaceholders($description, $values);
    }

    /**
     * @param array<string, int|float|string> $values
     */
    public static function fillPlaceholders(string $description, array $values): string
    {
        $placeholders = [];
        foreach ($values as $name => $value) {
            $placeholders[sprintf(self::PLACEHOLDER_FORMAT, $name)] = (string) $value;
        }

        return strtr($description, $placeholders);
    }
}
