<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Mcp\Attribute;

use Attribute;
use LogicException;

/**
 * Describes a field of an MCP tool input or output, or a value of a backed enum such a field takes. The description values replace
 * the {{ name }} placeholders of the description, like the parameters of a validation message.
 */
#[Attribute(Attribute::TARGET_PROPERTY | Attribute::TARGET_METHOD | Attribute::TARGET_CLASS_CONSTANT)]
final readonly class McpField
{
    private const string PLACEHOLDER_FORMAT = '{{ %s }}';
    private const string PLACEHOLDER_PATTERN = '/{{ (\w+) }}/';
    private const string NAME_SEPARATOR = '", "';

    public string $description;

    /**
     * @param array<string, int|float|string> $descriptionValues
     */
    public function __construct(string $description, array $descriptionValues = [])
    {
        $this->description = self::fillPlaceholders($description, $descriptionValues);
    }

    /**
     * @param array<string, int|float|string> $values
     *
     * @throws LogicException
     */
    public static function fillPlaceholders(string $description, array $values): string
    {
        preg_match_all(pattern: self::PLACEHOLDER_PATTERN, subject: $description, matches: $matches);
        $usedNames = array_unique($matches[1]);
        $missingNames = array_diff($usedNames, array_keys($values));
        if (false === empty($missingNames)) {
            throw new LogicException(sprintf(
                'Placeholders "%s" have no value in the MCP description "%s".',
                implode(self::NAME_SEPARATOR, $missingNames),
                $description,
            ));
        }
        $unusedNames = array_diff(array_keys($values), $usedNames);
        if (false === empty($unusedNames)) {
            throw new LogicException(sprintf(
                'Values "%s" have no placeholder in the MCP description "%s".',
                implode(self::NAME_SEPARATOR, $unusedNames),
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
