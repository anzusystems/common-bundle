<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Tests\Mcp\Factory;

use AnzuSystems\CommonBundle\Mcp\Factory\McpSchemaFactory;
use AnzuSystems\CommonBundle\Mcp\Model\Response\McpLogsByContextResponse;
use AnzuSystems\CommonBundle\Tests\Data\Mcp\Model\Request\CollectionExampleRequest;
use AnzuSystems\CommonBundle\Tests\Data\Mcp\Model\Request\CompoundExampleRequest;
use AnzuSystems\CommonBundle\Tests\Data\Mcp\Model\Request\HookedExampleRequest;
use AnzuSystems\CommonBundle\Tests\Data\Mcp\Model\Request\UntypedCollectionExampleRequest;
use AnzuSystems\CommonBundle\Tests\Data\Mcp\Model\Request\UuidExampleRequest;
use AnzuSystems\CommonBundle\Tests\Data\Mcp\Model\Response\UntypedCollectionExampleResponse;
use AnzuSystems\CommonBundle\Tests\Data\Mcp\Validator\Constraints\ExampleIdFilter;
use LogicException;
use PHPUnit\Framework\TestCase;

final class McpSchemaFactoryTest extends TestCase
{
    private const array UUID_SCHEMA = [
        'type' => 'string',
        'format' => 'uuid',
    ];
    private const array HOOKED_SCHEMA = [
        'type' => 'object',
        'properties' => [
            'query' => [
                'type' => 'string',
                'maxLength' => HookedExampleRequest::QUERY_MAX_LENGTH,
                'default' => '',
            ],
        ],
        'additionalProperties' => false,
    ];
    private const string REQUIRED = 'required';
    private const array LOGS_BY_CONTEXT_FIELDS = ['auditLogs', 'appLogs', 'mcpToolCalls', 'hint'];

    public function testCompoundConstraintDescribesThePropertyLikeItsConstraints(): void
    {
        $schema = new McpSchemaFactory()
            ->createForClass(CompoundExampleRequest::class);

        self::assertSame(ExampleIdFilter::MAX, $schema['properties']['ids']['maxItems']);
    }

    /**
     * @dataProvider uuidPropertyProvider
     *
     * @param array<string, mixed> $expectedSchema
     */
    public function testUuidPropertyIsAStringOfUuidFormat(string $property, array $expectedSchema): void
    {
        $schema = new McpSchemaFactory()
            ->createForClass(UuidExampleRequest::class);

        self::assertSame($expectedSchema, $schema[McpSchemaFactory::PROPERTIES][$property]);
    }

    /**
     * @return array<string, array{string, array<string, mixed>}>
     */
    public static function uuidPropertyProvider(): array
    {
        return [
            'Uuid' => ['docId', self::UUID_SCHEMA],
            '?Uuid' => ['parentDocId', ['type' => ['null', 'string'], 'format' => 'uuid', 'default' => null]],
            'list<Uuid>' => ['docIds', [
                'type' => 'array',
                'items' => self::UUID_SCHEMA,
                'maxItems' => UuidExampleRequest::DOC_IDS_MAX,
                'default' => [],
            ]],
        ];
    }

    /**
     * @dataProvider collectionPropertyProvider
     *
     * @param array<string, mixed> $expectedSchema
     */
    public function testCollectionPropertyIsDescribedLikeAList(string $property, array $expectedSchema): void
    {
        $schema = new McpSchemaFactory()
            ->createForClass(CollectionExampleRequest::class);

        self::assertSame($expectedSchema, $schema[McpSchemaFactory::PROPERTIES][$property]);
    }

    /**
     * @return array<string, array{string, array<string, mixed>}>
     */
    public static function collectionPropertyProvider(): array
    {
        return [
            'Collection<int, X>' => ['items', [
                'type' => 'array',
                'items' => self::HOOKED_SCHEMA,
                'minItems' => 1,
                'maxItems' => CollectionExampleRequest::ITEMS_MAX,
            ]],
            '?Collection<int, X>' => ['optionalItems', [
                'type' => ['null', 'array'],
                'items' => self::HOOKED_SCHEMA,
                'default' => null,
            ]],
            'Collection<string, X>' => ['itemsByKey', [
                'type' => 'object',
                'additionalProperties' => self::HOOKED_SCHEMA,
            ]],
        ];
    }

    public function testCollectionHasTheSchemaOfTheListItReplaces(): void
    {
        $schema = new McpSchemaFactory()
            ->createForClass(CollectionExampleRequest::class);

        self::assertSame($schema[McpSchemaFactory::PROPERTIES]['itemList'], $schema[McpSchemaFactory::PROPERTIES]['items']);
        self::assertContains('items', $schema[self::REQUIRED]);
        self::assertContains('itemList', $schema[self::REQUIRED]);
    }

    /**
     * @dataProvider untypedCollectionProvider
     *
     * @param class-string $class
     */
    public function testUntypedCollectionFailsWithItsMemberName(string $class, string $member): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage(sprintf(McpSchemaFactory::UNTYPED_COLLECTION_MESSAGE, $class, $member));

        new McpSchemaFactory()
            ->createForClass($class);
    }

    /**
     * @return array<string, array{class-string, string}>
     */
    public static function untypedCollectionProvider(): array
    {
        return [
            'property without a docblock' => [UntypedCollectionExampleRequest::class, 'items'],
            'getter with a bare @return' => [UntypedCollectionExampleResponse::class, 'getItems'],
        ];
    }

    public function testHookedRequestPropertyKeepsItsDefaultAndConstraints(): void
    {
        $schema = new McpSchemaFactory()
            ->createForClass(HookedExampleRequest::class);

        self::assertSame(
            ['type' => 'string', 'maxLength' => HookedExampleRequest::QUERY_MAX_LENGTH, 'default' => ''],
            $schema[McpSchemaFactory::PROPERTIES]['query'],
        );
        self::assertArrayNotHasKey(self::REQUIRED, $schema);
    }

    public function testVirtualOutputPropertiesAreRequiredInDeclarationOrder(): void
    {
        $schema = new McpSchemaFactory()
            ->createForClass(McpLogsByContextResponse::class);

        self::assertSame(self::LOGS_BY_CONTEXT_FIELDS, $schema[self::REQUIRED]);
        self::assertSame(self::LOGS_BY_CONTEXT_FIELDS, array_keys($schema[McpSchemaFactory::PROPERTIES]));
        self::assertSame(['type' => 'string'], $schema[McpSchemaFactory::PROPERTIES]['hint']);
    }
}
