<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Mcp\Factory;

/**
 * The fields of an MCP tool result as a list appended to the tool description: clients hand the model the description
 * and the input schema, but not necessarily the output schema.
 */
final readonly class McpOutputDescriptionFactory
{
    private const string HEADING = 'Returns:';
    private const string INDENT = '  ';
    private const string LINE_FORMAT = '%s- %s';
    private const string DESCRIBED_LINE_FORMAT = '%s- %s: %s';
    private const string LIST_SUFFIX = '[]';
    private const string MAP_SUFFIX = '{}';

    public function __construct(
        private McpSchemaFactory $schemaFactory = new McpSchemaFactory(),
    ) {
    }

    /**
     * @param class-string $class
     */
    public function createForClass(string $class): string
    {
        return implode(PHP_EOL, [self::HEADING, ...$this->createLines($this->schemaFactory->createForClass($class), '')]);
    }

    /**
     * @param array<array-key, mixed> $schema
     *
     * @return list<string>
     */
    private function createLines(array $schema, string $indent): array
    {
        $lines = [];
        foreach ((array) ($schema[McpSchemaFactory::PROPERTIES] ?? []) as $name => $field) {
            $field = (array) $field;
            $nested = $field;
            if (McpSchemaFactory::isOfType($field, McpSchemaFactory::TYPE_ARRAY)) {
                $name .= self::LIST_SUFFIX;
                $nested = (array) ($field[McpSchemaFactory::ITEMS] ?? []);
            }
            if (is_array($field[McpSchemaFactory::ADDITIONAL_PROPERTIES] ?? null)) {
                $name .= self::MAP_SUFFIX;
                $nested = $field[McpSchemaFactory::ADDITIONAL_PROPERTIES];
            }
            $description = $field[McpSchemaFactory::DESCRIPTION] ?? null;
            $lines[] = is_string($description)
                ? sprintf(self::DESCRIBED_LINE_FORMAT, $indent, $name, $description)
                : sprintf(self::LINE_FORMAT, $indent, $name);
            $lines = [...$lines, ...$this->createLines($nested, $indent . self::INDENT)];
        }

        return $lines;
    }
}
