<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Tests\Mcp\Factory;

use AnzuSystems\CommonBundle\Mcp\Factory\McpOutputDescriptionFactory;
use AnzuSystems\CommonBundle\Tests\Data\Mcp\Model\Request\ArrayExampleRequest;
use AnzuSystems\CommonBundle\Tests\Data\Mcp\Model\Request\CollectionExampleRequest;
use AnzuSystems\CommonBundle\Tests\Data\Mcp\Model\Response\DescribedExampleResponse;
use PHPUnit\Framework\TestCase;

final class McpOutputDescriptionFactoryTest extends TestCase
{
    public function testKeyedCollectionListsTheFieldsOfItsItems(): void
    {
        self::assertSame(
            implode(PHP_EOL, [
                'Returns:',
                '- items[] (query)',
                '- itemList[] (query)',
                '- optionalItems[] (query)',
                '- itemsByKey{} (query)',
            ]),
            new McpOutputDescriptionFactory()
                ->createForClass(CollectionExampleRequest::class),
        );
    }

    public function testPlainFieldsShareTheFirstLineAndADescribedObjectListsItsFieldsInline(): void
    {
        self::assertSame(
            implode(PHP_EOL, [
                'Returns:',
                '- id, name',
                '- count: Described plain field.',
                '- item (query): Described object of plain fields.',
            ]),
            new McpOutputDescriptionFactory()
                ->createForClass(DescribedExampleResponse::class),
        );
    }

    public function testMapOfUnknownValuesHasNoSuffix(): void
    {
        $description = new McpOutputDescriptionFactory()
            ->createForClass(ArrayExampleRequest::class);

        self::assertStringContainsString('mixedByString, stringsByString{}', $description);
    }
}
