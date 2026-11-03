<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Tests\Mcp\Factory;

use AnzuSystems\CommonBundle\Mcp\Factory\McpSchemaFactory;
use AnzuSystems\CommonBundle\Tests\Data\Mcp\Model\Request\UuidExampleRequest;
use PHPUnit\Framework\TestCase;

final class McpSchemaFactoryTest extends TestCase
{
    private const array UUID_SCHEMA = [
        'type' => 'string',
        'format' => 'uuid',
    ];

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
}
