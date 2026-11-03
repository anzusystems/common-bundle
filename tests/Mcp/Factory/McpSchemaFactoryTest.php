<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Tests\Mcp\Factory;

use AnzuSystems\CommonBundle\Mcp\Factory\McpSchemaFactory;
use AnzuSystems\CommonBundle\Mcp\Model\Response\McpLogsByContextResponse;
use AnzuSystems\CommonBundle\Tests\Data\Mcp\Model\Request\HookedExampleRequest;
use AnzuSystems\CommonBundle\Tests\Data\Mcp\Model\Request\UuidExampleRequest;
use PHPUnit\Framework\TestCase;

final class McpSchemaFactoryTest extends TestCase
{
    private const array UUID_SCHEMA = [
        'type' => 'string',
        'format' => 'uuid',
    ];
    private const string REQUIRED = 'required';
    private const array LOGS_BY_CONTEXT_FIELDS = ['auditLogs', 'appLogs', 'mcpToolCalls', 'hint'];

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
