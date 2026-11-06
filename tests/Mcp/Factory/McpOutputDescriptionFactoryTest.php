<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Tests\Mcp\Factory;

use AnzuSystems\CommonBundle\Mcp\Factory\McpOutputDescriptionFactory;
use AnzuSystems\CommonBundle\Tests\Data\Mcp\Model\Request\ArrayExampleRequest;
use AnzuSystems\CommonBundle\Tests\Data\Mcp\Model\Request\CollectionExampleRequest;
use PHPUnit\Framework\TestCase;

final class McpOutputDescriptionFactoryTest extends TestCase
{
    public function testKeyedCollectionListsTheFieldsOfItsItems(): void
    {
        self::assertSame(
            implode(PHP_EOL, [
                'Returns:',
                '- items[]',
                '  - query',
                '- itemList[]',
                '  - query',
                '- optionalItems[]',
                '  - query',
                '- itemsByKey{}',
                '  - query',
            ]),
            new McpOutputDescriptionFactory()
                ->createForClass(CollectionExampleRequest::class),
        );
    }

    public function testMapOfUnknownValuesHasNoSuffix(): void
    {
        $description = new McpOutputDescriptionFactory()
            ->createForClass(ArrayExampleRequest::class);

        self::assertStringContainsString('- mixedByString' . PHP_EOL, $description);
        self::assertStringContainsString('- stringsByString{}', $description);
    }
}
