<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Tests\Mcp;

use AnzuSystems\CommonBundle\Helper\MongoHelper;
use AnzuSystems\CommonBundle\Mcp\Log\McpLogRepository;
use DateTimeImmutable;
use MongoDB\BSON\UTCDateTime;
use MongoDB\Collection;
use MongoDB\Driver\CursorInterface;
use PHPUnit\Framework\TestCase;

final class McpLogRepositoryTest extends TestCase
{
    private const string CONTEXT_ID = 'ctx-1';
    private const int LIMIT = 5;

    public function testFindLatestByContextIdBoundsByIdAndReturnsNewestFirst(): void
    {
        $from = new DateTimeImmutable('2026-08-14 20:26:40+00:00');
        $older = [
            'datetime' => new UTCDateTime(1_000),
            'id' => 'older',
        ];
        $newer = [
            'datetime' => new UTCDateTime(2_000),
            'id' => 'newer',
        ];

        $cursor = $this->createMock(CursorInterface::class);
        $cursor->method('toArray')
            ->willReturn([$older, $newer]);

        $collection = $this->createMock(Collection::class);
        $collection
            ->expects(self::once())
            ->method('find')
            ->with(
                self::callback(static fn (array $filter): bool => self::CONTEXT_ID === $filter['contextId']
                    && (string) new UTCDateTime($from) === (string) $filter['datetime']['$gte']
                    && (string) MongoHelper::minObjectIdFor($from->modify('-1 minute')) === (string) $filter[MongoHelper::FIELD_ID]['$gte']),
                self::callback(static fn (array $options): bool => [MongoHelper::FIELD_ID => MongoHelper::SORT_DESC] === $options['sort']
                    && self::LIMIT === $options['limit']),
            )
            ->willReturn($cursor);

        $documents = (new McpLogRepository($collection))->findLatestByContextId(self::CONTEXT_ID, $from, self::LIMIT);

        self::assertSame(['newer', 'older'], array_column($documents, 'id'));
    }
}
