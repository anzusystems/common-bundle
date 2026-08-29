<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Tests\Mcp\Model;

use AnzuSystems\CommonBundle\Mcp\McpToolExecutor;
use AnzuSystems\CommonBundle\Mcp\Model\McpDateWindow;
use AnzuSystems\CommonBundle\Mcp\Model\McpLogSearchResult;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class McpLogSearchResultTest extends TestCase
{
    private const string HINT = 'test hint';
    private const string LOGS_KEY = 'appLogs';

    public function testCompleteResultCarriesNoWarnings(): void
    {
        $response = $this->createSearchResult(truncated: false, limit: 20, requestedLimit: 20)
            ->toToolResponse(logsKey: self::LOGS_KEY, hint: self::HINT);

        self::assertSame([['message' => 'first']], $response[self::LOGS_KEY]);
        self::assertSame('2026-07-19T00:00:00+00:00', $response['from']);
        self::assertSame('2026-07-20T00:00:00+00:00', $response['until']);
        self::assertSame(20, $response['limit']);
        self::assertSame(self::HINT, $response['hint']);
        self::assertArrayNotHasKey(McpToolExecutor::WARNINGS_KEY, $response);
    }

    public function testTruncatedWindowAndClampedLimitAreBothReported(): void
    {
        $response = $this->createSearchResult(truncated: true, limit: 50, requestedLimit: 500)
            ->toToolResponse(logsKey: self::LOGS_KEY, hint: self::HINT);

        self::assertCount(2, $response[McpToolExecutor::WARNINGS_KEY]);
        self::assertStringContainsString('31-day cap', $response[McpToolExecutor::WARNINGS_KEY][0]);
        self::assertStringContainsString('500', $response[McpToolExecutor::WARNINGS_KEY][1]);
    }

    private function createSearchResult(bool $truncated, int $limit, int $requestedLimit): McpLogSearchResult
    {
        return new McpLogSearchResult(
            [['message' => 'first']],
            new McpDateWindow(
                new DateTimeImmutable('2026-07-19T00:00:00+00:00'),
                new DateTimeImmutable('2026-07-20T00:00:00+00:00'),
                $truncated,
            ),
            $limit,
            $requestedLimit,
        );
    }
}
