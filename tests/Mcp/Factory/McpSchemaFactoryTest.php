<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Tests\Mcp\Factory;

use AnzuSystems\CommonBundle\Mcp\Factory\McpSchemaFactory;
use AnzuSystems\CommonBundle\Mcp\Model\Decorator\McpToolCallLogDecorator;
use AnzuSystems\CommonBundle\Mcp\Model\McpPageWindow;
use AnzuSystems\CommonBundle\Mcp\Model\Response\McpLogsByContextResponse;
use AnzuSystems\CommonBundle\Serializer\Handler\Handlers\McpDateTimeHandler;
use AnzuSystems\CommonBundle\Tests\Data\Mcp\Model\Request\ArrayExampleRequest;
use AnzuSystems\CommonBundle\Tests\Data\Mcp\Model\Request\BareArrayExampleRequest;
use AnzuSystems\CommonBundle\Tests\Data\Mcp\Model\Request\ChildExampleRequest;
use AnzuSystems\CommonBundle\Tests\Data\Mcp\Model\Request\CollectionExampleRequest;
use AnzuSystems\CommonBundle\Tests\Data\Mcp\Model\Request\CompoundExampleRequest;
use AnzuSystems\CommonBundle\Tests\Data\Mcp\Model\Request\ConstraintExampleRequest;
use AnzuSystems\CommonBundle\Tests\Data\Mcp\Model\Request\CursorExampleRequest;
use AnzuSystems\CommonBundle\Tests\Data\Mcp\Model\Request\DateExampleRequest;
use AnzuSystems\CommonBundle\Tests\Data\Mcp\Model\Request\DescribedEnumExampleRequest;
use AnzuSystems\CommonBundle\Tests\Data\Mcp\Model\Request\EnumExampleRequest;
use AnzuSystems\CommonBundle\Tests\Data\Mcp\Model\Request\HookedExampleRequest;
use AnzuSystems\CommonBundle\Tests\Data\Mcp\Model\Request\McpDateExampleRequest;
use AnzuSystems\CommonBundle\Tests\Data\Mcp\Model\Request\NullableBareArrayExampleRequest;
use AnzuSystems\CommonBundle\Tests\Data\Mcp\Model\Request\PageExampleRequest;
use AnzuSystems\CommonBundle\Tests\Data\Mcp\Model\Request\PreviewHashExampleRequest;
use AnzuSystems\CommonBundle\Tests\Data\Mcp\Model\Request\PromotedExampleRequest;
use AnzuSystems\CommonBundle\Tests\Data\Mcp\Model\Request\TreeExampleRequest;
use AnzuSystems\CommonBundle\Tests\Data\Mcp\Model\Request\UnionTypeExampleRequest;
use AnzuSystems\CommonBundle\Tests\Data\Mcp\Model\Request\UntypedCollectionExampleRequest;
use AnzuSystems\CommonBundle\Tests\Data\Mcp\Model\Request\UuidExampleRequest;
use AnzuSystems\CommonBundle\Tests\Data\Mcp\Model\Response\BareArrayExampleResponse;
use AnzuSystems\CommonBundle\Tests\Data\Mcp\Model\Response\CursorExampleResponse;
use AnzuSystems\CommonBundle\Tests\Data\Mcp\Model\Response\SerializedNameChildExampleResponse;
use AnzuSystems\CommonBundle\Tests\Data\Mcp\Model\Response\UntypedCollectionExampleResponse;
use AnzuSystems\CommonBundle\Tests\Data\Mcp\Validator\Constraints\ExampleIdFilter;
use AnzuSystems\CommonBundle\Tests\Data\Model\Enum\DummyEnum;
use AnzuSystems\CommonBundle\Tests\Mcp\McpSerializerFactory;
use DateTimeImmutable;
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
    private const array LOGS_BY_CONTEXT_FIELDS = ['auditLogs', 'appLogs', 'mcpToolCalls'];

    public function testPreviewHashIsLeftToTheValidator(): void
    {
        $schema = new McpSchemaFactory()
            ->createForClass(PreviewHashExampleRequest::class);

        self::assertArrayHasKey('previewHash', $schema[McpSchemaFactory::PROPERTIES]);
        self::assertArrayNotHasKey(self::REQUIRED, $schema);
    }

    public function testCompoundConstraintDescribesThePropertyLikeItsConstraints(): void
    {
        $schema = new McpSchemaFactory()
            ->createForClass(CompoundExampleRequest::class);

        self::assertSame(ExampleIdFilter::MAX, $schema[McpSchemaFactory::PROPERTIES]['ids']['maxItems']);
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

    /**
     * @dataProvider arrayPropertyProvider
     *
     * @param array<string, mixed> $expectedSchema
     */
    public function testArrayIsAnObjectOnlyWithAStringKey(string $property, array $expectedSchema): void
    {
        $schema = new McpSchemaFactory()
            ->createForClass(ArrayExampleRequest::class);

        self::assertSame($expectedSchema, $schema[McpSchemaFactory::PROPERTIES][$property]);
    }

    /**
     * @return array<string, array{string, array<string, mixed>}>
     */
    public static function arrayPropertyProvider(): array
    {
        $stringList = ['type' => 'array', 'items' => ['type' => 'string'], 'default' => []];

        return [
            'array<X>' => ['arrayOfStrings', $stringList],
            'list<X>' => ['listOfStrings', $stringList],
            'array<int, X>' => ['stringsByInt', $stringList],
            'array<array-key, X>' => ['stringsByArrayKey', $stringList],
            'array<mixed> has no item schema' => ['arrayOfMixed', ['type' => 'array', 'default' => []]],
            'array<string, mixed>' => ['mixedByString', ['type' => 'object', 'additionalProperties' => true, 'default' => []]],
            'array<string, X>' => ['stringsByString', ['type' => 'object', 'additionalProperties' => ['type' => 'string'], 'default' => []]],
            'array<int, X> kept by RawArrayHandler' => ['rawStringsByInt', ['type' => 'object', 'additionalProperties' => ['type' => 'string'], 'default' => []]],
            'nullable array<int, X> kept by RawArrayHandler' => [
                'nullableRawStringsByInt',
                ['type' => ['null', 'object'], 'additionalProperties' => ['type' => 'string'], 'default' => null],
            ],
            'list<X> kept by RawArrayHandler' => ['rawListOfStrings', $stringList],
        ];
    }

    public function testParamsOfAToolCallLogStayAnObject(): void
    {
        $schema = new McpSchemaFactory()
            ->createForClass(McpToolCallLogDecorator::class);

        self::assertSame(
            ['type' => 'object', 'additionalProperties' => true],
            $schema[McpSchemaFactory::PROPERTIES]['params'],
        );
    }

    /**
     * @dataProvider untypedArrayProvider
     *
     * @param class-string $class
     */
    public function testUntypedArrayFailsWithItsMemberName(string $class, string $member): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage(sprintf(McpSchemaFactory::UNTYPED_ARRAY_MESSAGE, $class, $member));

        new McpSchemaFactory()
            ->createForClass($class);
    }

    /**
     * @return array<string, array{class-string, string}>
     */
    public static function untypedArrayProvider(): array
    {
        return [
            'property' => [BareArrayExampleRequest::class, 'items'],
            'nullable property' => [NullableBareArrayExampleRequest::class, 'items'],
            'getter' => [BareArrayExampleResponse::class, 'getItems'],
        ];
    }

    public function testTypeTheSchemaCannotDescribeFailsWithTheType(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage(sprintf(McpSchemaFactory::UNDESCRIBABLE_TYPE_MESSAGE, 'int|string'));

        new McpSchemaFactory()
            ->createForClass(UnionTypeExampleRequest::class);
    }

    /**
     * @dataProvider mcpDatePropertyProvider
     *
     * @param array<string, mixed> $expectedSchema
     */
    public function testMcpDatePropertyIsAStringDescribingItsFormat(string $property, array $expectedSchema): void
    {
        $factory = new McpSchemaFactory();
        $schema = $factory->createForClass(McpDateExampleRequest::class);

        self::assertSame($expectedSchema, $schema[McpSchemaFactory::PROPERTIES][$property]);
        self::assertSame(['at'], $schema[self::REQUIRED]);
        self::assertContains(McpDateTimeHandler::class, $factory->getDescribedClasses());
    }

    /**
     * @return array<string, array{string, array<string, mixed>}>
     */
    public static function mcpDatePropertyProvider(): array
    {
        return [
            '?DateTimeImmutable' => ['from', [
                'type' => ['null', 'string'],
                'description' => McpDateExampleRequest::FROM_DESCRIPTION . ' ' . McpDateTimeHandler::FORMAT_DESCRIPTION,
                'default' => null,
            ]],
            'end of a period' => ['until', [
                'type' => ['null', 'string'],
                'description' => McpDateExampleRequest::UNTIL_DESCRIPTION . ' ' . McpDateTimeHandler::FORMAT_DESCRIPTION . ' ' . McpDateTimeHandler::END_OF_DAY_DESCRIPTION,
                'default' => null,
            ]],
            'DateTimeImmutable' => ['at', [
                'type' => 'string',
                'description' => McpDateTimeHandler::FORMAT_DESCRIPTION,
            ]],
        ];
    }

    public function testClassWithoutSerializeMembersFailsWithItsName(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage(sprintf(McpSchemaFactory::UNDESCRIBED_CLASS_MESSAGE, DateTimeImmutable::class));

        new McpSchemaFactory()
            ->createForClass(DateExampleRequest::class);
    }

    public function testClassContainingItselfFailsWithItsName(): void
    {
        $this->expectExceptionObject(new LogicException(sprintf(McpSchemaFactory::SELF_CONTAINING_CLASS_MESSAGE, TreeExampleRequest::class)));

        new McpSchemaFactory()
            ->createForClass(TreeExampleRequest::class);
    }

    public function testPrivatePropertiesOfAParentAreDescribedAndTheChildWins(): void
    {
        $schema = new McpSchemaFactory()
            ->createForClass(ChildExampleRequest::class);

        self::assertSame(['own', 'shared', 'parentOnly'], array_keys($schema[McpSchemaFactory::PROPERTIES]));
        self::assertSame('child', $schema[McpSchemaFactory::PROPERTIES]['shared']['default']);
        self::assertSame('parent', $schema[McpSchemaFactory::PROPERTIES]['parentOnly']['default']);
    }

    public function testSchemaHasTheKeysTheSerializerWrites(): void
    {
        $schema = new McpSchemaFactory()
            ->createForClass(SerializedNameChildExampleResponse::class);
        $serialized = McpSerializerFactory::create()
            ->toArray(new SerializedNameChildExampleResponse());

        self::assertSame(array_keys($serialized), array_keys($schema[McpSchemaFactory::PROPERTIES]));
        self::assertSame(
            ['title', 'renamed', 'inherited', 'active', 'label', 'issue', 'getÉtat'],
            array_keys($schema[McpSchemaFactory::PROPERTIES]),
        );
        self::assertSame('string', $schema[McpSchemaFactory::PROPERTIES]['title'][McpSchemaFactory::TYPE]);
        self::assertSame('child', $serialized['title']);
    }

    /**
     * @dataProvider describedEnumProvider
     */
    public function testDescribedEnumValuesFollowTheFieldDescriptionAndMarkTheDefault(string $property, string $expectedDescription): void
    {
        $schema = new McpSchemaFactory()
            ->createForClass(DescribedEnumExampleRequest::class);

        self::assertSame($expectedDescription, $schema[McpSchemaFactory::PROPERTIES][$property][McpSchemaFactory::DESCRIPTION]);
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function describedEnumProvider(): array
    {
        return [
            'required with its own description' => [
                'status',
                DescribedEnumExampleRequest::STATUS_DESCRIPTION . ' "unchanged" = kept as it is, "changed" = kept with another text.',
            ],
            'default' => [
                'defaultStatus',
                '"unchanged" (default) = kept as it is, "changed" = kept with another text.',
            ],
            'nullable with a default' => [
                'nullableStatus',
                '"unchanged" = kept as it is, "changed" (default) = kept with another text.',
            ],
        ];
    }

    public function testDescribedClassesListNestedClassesEnumsAndCompoundConstraints(): void
    {
        $factory = new McpSchemaFactory();

        $factory->createForClass(CollectionExampleRequest::class);
        $factory->createForClass(CompoundExampleRequest::class);
        $factory->createForClass(EnumExampleRequest::class);

        self::assertContains(CollectionExampleRequest::class, $factory->getDescribedClasses());
        self::assertContains(HookedExampleRequest::class, $factory->getDescribedClasses());
        self::assertContains(ExampleIdFilter::class, $factory->getDescribedClasses());
        self::assertContains(DummyEnum::class, $factory->getDescribedClasses());
    }

    /**
     * @dataProvider constraintProvider
     *
     * @param array<string, mixed> $expectedSchema
     */
    public function testConstraintBecomesItsSchemaKeyword(string $property, array $expectedSchema): void
    {
        $schema = new McpSchemaFactory()
            ->createForClass(ConstraintExampleRequest::class);

        self::assertSame($expectedSchema, $schema[McpSchemaFactory::PROPERTIES][$property]);
    }

    /**
     * @return array<string, array{string, array<string, mixed>}>
     */
    public static function constraintProvider(): array
    {
        return [
            'All' => ['all', [
                'type' => 'array',
                'items' => ['type' => 'string', 'maxLength' => 5, 'minLength' => 1],
                'default' => [],
            ]],
            'Range' => ['range', ['type' => 'integer', 'minimum' => 1, 'maximum' => 5, 'default' => 1]],
            'GreaterThan on an integer is the next inclusive bound' => ['greaterThanInteger', ['type' => 'integer', 'minimum' => 1, 'default' => 1]],
            'GreaterThan on a number stays exclusive' => ['greaterThanNumber', ['type' => 'number', 'exclusiveMinimum' => 0, 'default' => 1.0]],
            'GreaterThan with a fraction' => ['greaterThanFraction', ['type' => 'number', 'exclusiveMinimum' => 0.5, 'default' => 1.0]],
            'Positive' => ['positive', ['type' => 'integer', 'minimum' => 1, 'default' => 1]],
            'GreaterThanOrEqual' => ['greaterThanOrEqual', ['type' => 'integer', 'minimum' => 1, 'default' => 1]],
            'LessThan on an integer is the previous inclusive bound' => ['lessThanInteger', ['type' => 'integer', 'maximum' => 9, 'default' => 1]],
            'LessThan on a number stays exclusive' => ['lessThanNumber', ['type' => 'number', 'exclusiveMaximum' => 10, 'default' => 1.0]],
            'LessThanOrEqual' => ['lessThanOrEqual', ['type' => 'integer', 'maximum' => 10, 'default' => 1]],
            'Choice' => ['choice', ['type' => 'string', 'enum' => ['first', 'second'], 'default' => 'first']],
            'Choice on a nullable property keeps null' => ['nullableChoice', ['type' => ['null', 'string'], 'enum' => ['first', 'second', null], 'default' => null]],
            'Choice with multiple limits each item' => ['multipleChoice', ['type' => 'array', 'items' => ['type' => 'string', 'enum' => ['first', 'second']], 'default' => []]],
            'Choice with match false is left to the validator' => ['notMatchingChoice', ['type' => 'string', 'default' => 'third']],
        ];
    }

    /**
     * @dataProvider requiredConstraintProvider
     */
    public function testConstraintMakesAPropertyWithADefaultRequired(string $property, bool $expectedRequired): void
    {
        $schema = new McpSchemaFactory()
            ->createForClass(ConstraintExampleRequest::class);

        self::assertSame($expectedRequired, in_array(needle: $property, haystack: $schema[self::REQUIRED] ?? [], strict: true));
    }

    /**
     * @return array<string, array{string, bool}>
     */
    public static function requiredConstraintProvider(): array
    {
        return [
            'NotBlank' => ['notBlank', true],
            'NotBlank allowing null' => ['notBlankAllowingNull', false],
            'NotNull' => ['notNull', true],
            'Count with a minimum' => ['countMin', true],
            'no constraint that requires a value' => ['range', false],
        ];
    }

    public function testPageOfAPageRequestIsLimitedToThePageWindow(): void
    {
        $schema = new McpSchemaFactory()
            ->createForClass(PageExampleRequest::class);

        self::assertSame(
            [
                'type' => 'integer',
                'minimum' => McpPageWindow::PAGE_MIN,
                'maximum' => McpPageWindow::PAGE_MAX,
                'description' => PageExampleRequest::PAGE_DESCRIPTION,
                'default' => McpPageWindow::PAGE_MIN,
            ],
            $schema[McpSchemaFactory::PROPERTIES]['page'],
        );
        self::assertArrayNotHasKey(self::REQUIRED, $schema);
    }

    public function testCursorOfACursorRequestIsAnOptionalString(): void
    {
        $schema = new McpSchemaFactory()
            ->createForClass(CursorExampleRequest::class);

        self::assertSame(
            [
                'type' => ['null', 'string'],
                'description' => CursorExampleRequest::CURSOR_DESCRIPTION,
                'default' => null,
            ],
            $schema[McpSchemaFactory::PROPERTIES]['cursor'],
        );
        self::assertArrayNotHasKey(self::REQUIRED, $schema);
    }

    public function testNextCursorOfACursorResponseIsARequiredNullableString(): void
    {
        $schema = new McpSchemaFactory()
            ->createForClass(CursorExampleResponse::class);

        self::assertSame(
            [
                'type' => ['null', 'string'],
                'description' => CursorExampleResponse::NEXT_CURSOR_DESCRIPTION,
            ],
            $schema[McpSchemaFactory::PROPERTIES]['nextCursor'],
        );
        self::assertSame(['items', 'nextCursor'], $schema[self::REQUIRED]);
    }

    public function testPromotedPropertyTakesTheDefaultOfItsConstructorParameter(): void
    {
        $schema = new McpSchemaFactory()
            ->createForClass(PromotedExampleRequest::class);

        self::assertSame(['type' => 'integer', 'default' => PromotedExampleRequest::LIMIT_DEFAULT], $schema[McpSchemaFactory::PROPERTIES]['limit']);
        self::assertSame(['query'], $schema[self::REQUIRED]);
    }

    public function testPromotedPropertyWithAnObjectDefaultHasNoDefaultInTheSchema(): void
    {
        $schema = new McpSchemaFactory()
            ->createForClass(PromotedExampleRequest::class);

        self::assertSame(['type' => 'array', 'items' => ['type' => 'integer']], $schema[McpSchemaFactory::PROPERTIES]['ids']);
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
    }
}
