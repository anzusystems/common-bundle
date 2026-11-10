<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Tests\Serializer\Handler;

use AnzuSystems\CommonBundle\Tests\Data\Mcp\Model\Request\UuidExampleRequest;
use AnzuSystems\CommonBundle\Tests\Mcp\McpSerializerFactory;
use AnzuSystems\SerializerBundle\Exception\SerializerException;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\MaxUuid;
use Symfony\Component\Uid\NilUuid;
use Symfony\Component\Uid\Uuid;

final class UuidArrayHandlerTest extends TestCase
{
    private const string DOC_ID = '3cc622b2-1619-4ed3-8692-c7d7dd54485e';
    private const string OTHER_DOC_ID = '0b6f0f4e-6d1a-4a8e-9f7a-2a3c5e6f7a8b';
    private const string NIL_UUID = '00000000-0000-0000-0000-000000000000';
    private const string MAX_UUID_UPPER_CASE = 'FFFFFFFF-FFFF-FFFF-FFFF-FFFFFFFFFFFF';

    public function testDeserializesAListOfUuidsAndSerializesItBack(): void
    {
        $serializer = McpSerializerFactory::create();

        $request = $serializer->fromArray(
            [
                'docId' => self::DOC_ID,
                'docIds' => [self::DOC_ID, strtoupper(self::OTHER_DOC_ID)],
            ],
            UuidExampleRequest::class,
        );

        self::assertInstanceOf(UuidExampleRequest::class, $request);
        self::assertEquals([Uuid::fromString(self::DOC_ID), Uuid::fromString(self::OTHER_DOC_ID)], $request->docIds);
        self::assertSame([self::DOC_ID, self::OTHER_DOC_ID], $serializer->toArray($request)['docIds']);
    }

    /**
     * @dataProvider itemProvider
     */
    public function testItemIsReadLikeTheSingleUuidField(string $item, Uuid $expectedUuid): void
    {
        $request = McpSerializerFactory::create()->fromArray(
            ['docId' => $item, 'docIds' => [$item]],
            UuidExampleRequest::class,
        );

        self::assertInstanceOf(UuidExampleRequest::class, $request);
        self::assertEquals($expectedUuid, $request->docId);
        self::assertEquals([$expectedUuid], $request->docIds);
    }

    public static function itemProvider(): array
    {
        return [
            'empty text' => ['', new NilUuid()],
            'nil' => [self::NIL_UUID, new NilUuid()],
            'max in upper case' => [self::MAX_UUID_UPPER_CASE, new MaxUuid()],
            'uuid' => [self::DOC_ID, Uuid::fromString(self::DOC_ID)],
            'base58' => [Uuid::fromString(self::DOC_ID)->toBase58(), Uuid::fromString(self::DOC_ID)],
        ];
    }

    /**
     * @dataProvider invalidItemProvider
     */
    public function testInvalidItemFailsTheDeserialization(mixed $item): void
    {
        $this->expectException(SerializerException::class);

        McpSerializerFactory::create()->fromArray(
            ['docId' => self::DOC_ID, 'docIds' => [self::DOC_ID, $item]],
            UuidExampleRequest::class,
        );
    }

    public static function invalidItemProvider(): array
    {
        return [
            'text' => ['not-a-uuid'],
            'number' => [5],
            'null' => [null],
        ];
    }
}
