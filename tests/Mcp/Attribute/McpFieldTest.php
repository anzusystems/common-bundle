<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Tests\Mcp\Attribute;

use AnzuSystems\CommonBundle\Mcp\Attribute\McpField;
use LogicException;
use PHPUnit\Framework\TestCase;

final class McpFieldTest extends TestCase
{
    public function testFillsEveryPlaceholder(): void
    {
        $field = new McpField('Call {{ tool }} with at most {{ max }} ids, then {{ tool }} again.', [
            'tool' => 'get_articles',
            'max' => 10,
        ]);

        self::assertSame('Call get_articles with at most 10 ids, then get_articles again.', $field->description);
    }

    public function testKeepsADescriptionWithoutPlaceholders(): void
    {
        self::assertSame('Plain text.', new McpField('Plain text.')->description);
    }

    /**
     * @dataProvider provideMismatchedValues
     *
     * @param array<string, int|float|string> $values
     */
    public function testRejectsMismatchedValues(string $description, array $values, string $expectedMessage): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage($expectedMessage);

        McpField::fillPlaceholders($description, $values);
    }

    /**
     * @return iterable<string, array{string, array<string, int|float|string>, string}>
     */
    public static function provideMismatchedValues(): iterable
    {
        yield 'placeholder without value' => ['Call {{ tool }}.', [], 'Placeholders "tool" have no value'];
        yield 'misspelled placeholder' => ['Call {{ toll }}.', ['tool' => 'x'], 'Placeholders "toll" have no value'];
        yield 'value without placeholder' => ['Call it.', ['tool' => 'x'], 'Values "tool" have no placeholder'];
    }
}
