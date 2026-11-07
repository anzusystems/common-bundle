<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Tests\Serializer\Handler;

use AnzuSystems\CommonBundle\Mcp\Exception\McpToolArgumentsInputException;
use AnzuSystems\CommonBundle\Serializer\Handler\Handlers\McpDateTimeHandler;
use AnzuSystems\CommonBundle\Tests\Data\Mcp\Model\Request\McpDateExampleRequest;
use AnzuSystems\CommonBundle\Tests\Mcp\McpSerializerFactory;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class McpDateTimeHandlerTest extends TestCase
{
    private const string NON_UTC_TIMEZONE = 'Europe/Bratislava';
    private const string AT = '2026-10-07T00:00:00+00:00';
    private const string INVALID_DATE = 'not-a-date';

    private string $defaultTimezone;

    protected function setUp(): void
    {
        $this->defaultTimezone = date_default_timezone_get();
        date_default_timezone_set(self::NON_UTC_TIMEZONE);
    }

    protected function tearDown(): void
    {
        date_default_timezone_set($this->defaultTimezone);
    }

    /**
     * @dataProvider dateProvider
     */
    public function testDateIsReadInUtcWhateverTheDefaultTimezone(string $value, string $expectedDate): void
    {
        $request = $this->deserialize([
            'from' => $value,
            'at' => self::AT,
        ]);

        self::assertInstanceOf(DateTimeImmutable::class, $request->from);
        self::assertSame($expectedDate, $request->from->format(DATE_ATOM));
        self::assertSame('UTC', $request->from->getTimezone()->getName());
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function dateProvider(): array
    {
        return [
            'with an offset' => ['2026-10-07T00:00:00+02:00', '2026-10-06T22:00:00+00:00'],
            'with Z' => ['2026-10-07T00:00:00Z', '2026-10-07T00:00:00+00:00'],
            'without an offset' => ['2026-10-07T00:00:00', '2026-10-07T00:00:00+00:00'],
            'a date only' => ['2026-10-01', '2026-10-01T00:00:00+00:00'],
            'with a fraction' => ['2026-10-07T00:00:00.500000Z', '2026-10-07T00:00:00+00:00'],
        ];
    }

    public function testNullStaysNullAndTheDateIsSerializedInTheDefaultFormat(): void
    {
        $serializer = McpSerializerFactory::create();
        $request = $this->deserialize(['from' => null, 'at' => self::AT]);

        self::assertNull($request->from);
        self::assertSame(['from' => null, 'at' => '2026-10-07T00:00:00.000000Z'], $serializer->toArray($request));
    }

    /**
     * @dataProvider invalidDateProvider
     */
    public function testValueOutsideIso8601IsAnInvalidArgumentOfItsField(string $value): void
    {
        $this->expectException(McpToolArgumentsInputException::class);
        $this->expectExceptionMessage(
            McpToolArgumentsInputException::createInvalidArgument(propertyPath: 'from', message: sprintf(McpDateTimeHandler::INVALID_VALUE_MESSAGE, $value))->getMessage(),
        );

        $this->deserialize(['from' => $value, 'at' => self::AT]);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function invalidDateProvider(): array
    {
        return [
            'unreadable text' => [self::INVALID_DATE],
            'empty text' => [''],
            'relative word' => ['yesterday'],
            'relative interval' => ['+3 days'],
            'impossible day' => ['2026-02-31'],
            'impossible time' => ['2026-10-07T25:00:00Z'],
            'space instead of T' => ['2026-10-07 06:00:00'],
        ];
    }

    /**
     * @param array<string, mixed> $data
     */
    private function deserialize(array $data): McpDateExampleRequest
    {
        $request = McpSerializerFactory::create()->fromArray($data, McpDateExampleRequest::class);
        self::assertInstanceOf(McpDateExampleRequest::class, $request);

        return $request;
    }
}
