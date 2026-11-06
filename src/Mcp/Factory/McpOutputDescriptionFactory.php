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
    private const string DESCRIPTION_FORMAT = '%s: %s';
    private const string FIELDS_FORMAT = '%s (%s)';
    private const string FIELD_SEPARATOR = ', ';
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
     * Fields without a description and without fields of their own share the first line, and so do the fields of an
     * object that has only such fields: a line per field costs the model tokens without telling it anything.
     *
     * @param array<array-key, mixed> $schema
     *
     * @return list<string>
     */
    private function createLines(array $schema, string $indent): array
    {
        $plainNames = [];
        $lines = [];
        foreach ($this->getFields($schema) as $name => $field) {
            [$name, $nested] = $this->resolveNested(name: (string) $name, field: $field);
            $description = $field[McpSchemaFactory::DESCRIPTION] ?? null;
            if (null === $description && empty($this->getFields($nested))) {
                $plainNames[] = $name;

                continue;
            }
            $lines = [...$lines, ...$this->createFieldLines(name: $name, description: $description, nested: $nested, indent: $indent)];
        }
        if (empty($plainNames)) {
            return $lines;
        }

        return [sprintf(self::LINE_FORMAT, $indent, implode(self::FIELD_SEPARATOR, $plainNames)), ...$lines];
    }

    /**
     * @param array<array-key, mixed> $nested
     *
     * @return list<string>
     */
    private function createFieldLines(string $name, mixed $description, array $nested, string $indent): array
    {
        $nestedLines = $this->createLines($nested, $indent . self::INDENT);
        $inlineFields = $this->getInlineFields($nested);
        if (is_array($inlineFields)) {
            $name = sprintf(self::FIELDS_FORMAT, $name, implode(self::FIELD_SEPARATOR, $inlineFields));
            $nestedLines = [];
        }
        $line = is_string($description) ? sprintf(self::DESCRIPTION_FORMAT, $name, $this->joinLines($description)) : $name;

        return [sprintf(self::LINE_FORMAT, $indent, $line), ...$nestedLines];
    }

    /**
     * @param array<array-key, mixed> $field
     *
     * @return array{string, array<array-key, mixed>}
     */
    private function resolveNested(string $name, array $field): array
    {
        $nested = $field;
        if (McpSchemaFactory::isOfType($field, McpSchemaFactory::TYPE_ARRAY)) {
            $name .= self::LIST_SUFFIX;
            $nested = (array) ($field[McpSchemaFactory::ITEMS] ?? []);
        }
        if (is_array($field[McpSchemaFactory::ADDITIONAL_PROPERTIES] ?? null)) {
            $name .= self::MAP_SUFFIX;
            $nested = $field[McpSchemaFactory::ADDITIONAL_PROPERTIES];
        }

        return [$name, $nested];
    }

    /**
     * @param array<array-key, mixed> $schema
     *
     * @return list<string>|null the names of the fields when none of them has a description or fields of its own
     */
    private function getInlineFields(array $schema): ?array
    {
        $names = [];
        foreach ($this->getFields($schema) as $name => $field) {
            [$name, $nested] = $this->resolveNested(name: (string) $name, field: $field);
            if (isset($field[McpSchemaFactory::DESCRIPTION]) || false === empty($this->getFields($nested))) {
                return null;
            }
            $names[] = $name;
        }

        return empty($names) ? null : $names;
    }

    private function joinLines(string $description): string
    {
        return (string) preg_replace(pattern: '/\s*\R\s*/u', replacement: ' ', subject: $description);
    }

    /**
     * @param array<array-key, mixed> $schema
     *
     * @return array<array-key, array<array-key, mixed>>
     */
    private function getFields(array $schema): array
    {
        return array_map(
            static fn (mixed $field): array => (array) $field,
            (array) ($schema[McpSchemaFactory::PROPERTIES] ?? []),
        );
    }
}
