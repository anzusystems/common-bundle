<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Mcp\Attribute;

use Attribute;
use LogicException;

/**
 * Describes a field of an MCP tool input or output, or a value of a backed enum such a field takes. The values replace
 * the {{ name }} placeholders of the description, like the parameters of a validation message.
 */
#[Attribute(Attribute::TARGET_PROPERTY | Attribute::TARGET_METHOD | Attribute::TARGET_CLASS_CONSTANT)]
final readonly class McpField
{
    private const string PLACEHOLDER_FORMAT = '{{ %s }}';
    private const string PLACEHOLDER_PATTERN = '/{{ (\w+) }}/';

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
     *
     * @throws LogicException
     */
    public static function fillPlaceholders(string $description, array $values): string
    {
        preg_match_all(self::PLACEHOLDER_PATTERN, $description, $matches);
        $usedNames = array_unique($matches[1]);
        $missingNames = array_diff($usedNames, array_keys($values));
        if ([] !== $missingNames) {
            throw new LogicException(sprintf(
                'Placeholders "%s" have no value in the MCP description "%s".',
                implode('", "', $missingNames),
                $description,
            ));
        }
        $unusedNames = array_diff(array_keys($values), $usedNames);
        if ([] !== $unusedNames) {
            throw new LogicException(sprintf(
                'Values "%s" have no placeholder in the MCP description "%s".',
                implode('", "', $unusedNames),
                $description,
            ));
        }

        $placeholders = [];
        foreach ($values as $name => $value) {
            $placeholders[sprintf(self::PLACEHOLDER_FORMAT, $name)] = (string) $value;
        }

        return strtr($description, $placeholders);
    }
}
