<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Tests\Mcp\Model\Response;

use AnzuSystems\CommonBundle\Mcp\McpToolExecutor;
use AnzuSystems\CommonBundle\Mcp\Model\McpDateWindow;
use AnzuSystems\CommonBundle\Mcp\Model\McpLogSearchResult;
use AnzuSystems\CommonBundle\Mcp\Model\Response\McpAppLogSearchResponse;
use AnzuSystems\CommonBundle\Mcp\Model\Response\McpAuditLogSearchResponse;
use AnzuSystems\CommonBundle\Tests\Mcp\McpSerializerFactory;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class McpLogSearchResponseTest extends TestCase
{
    private const array LOGS = [['message' => 'first', 'resourceIds' => []]];

    public function testCompleteAppLogResultCarriesEmptyWarnings(): void
    {
        $response = McpSerializerFactory::create()
            ->toArray(new McpAppLogSearchResponse($this->createSearchResult(truncated: false, limit: 20, hasMore: false)));

        self::assertSame(['appLogs', 'from', 'until', 'limit', 'hint', McpToolExecutor::WARNINGS_KEY], array_keys($response));
        self::assertSame(self::LOGS, $response['appLogs']);
        self::assertSame('2026-07-19T00:00:00+00:00', $response['from']);
        self::assertSame('2026-07-20T00:00:00+00:00', $response['until']);
        self::assertSame(20, $response['limit']);
        self::assertStringContainsString('get_logs_by_context', $response['hint']);
        self::assertSame([], $response[McpToolExecutor::WARNINGS_KEY]);
    }

    public function testTruncatedWindowAndTruncatedResultsAreBothReportedOnAuditLogResult(): void
    {
        $response = McpSerializerFactory::create()
            ->toArray(new McpAuditLogSearchResponse($this->createSearchResult(truncated: true, limit: 50, hasMore: true)));

        self::assertSame(self::LOGS, $response['auditLogs']);
        self::assertCount(2, $response[McpToolExecutor::WARNINGS_KEY]);
        self::assertStringContainsString('31-day cap', $response[McpToolExecutor::WARNINGS_KEY][0]);
        self::assertStringContainsString('first 50', $response[McpToolExecutor::WARNINGS_KEY][1]);
    }

    private function createSearchResult(bool $truncated, int $limit, bool $hasMore): McpLogSearchResult
    {
        return new McpLogSearchResult(
            self::LOGS,
            new McpDateWindow(
                new DateTimeImmutable('2026-07-19T00:00:00+00:00'),
                new DateTimeImmutable('2026-07-20T00:00:00+00:00'),
                $truncated,
            ),
            $limit,
            $hasMore,
        );
    }
}
