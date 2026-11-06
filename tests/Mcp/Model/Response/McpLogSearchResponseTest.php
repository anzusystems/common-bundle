<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Tests\Mcp\Model\Response;

use AnzuSystems\CommonBundle\Mcp\Model\Decorator\AbstractMcpLogDecorator;
use AnzuSystems\CommonBundle\Mcp\Model\Decorator\McpAppLogDecorator;
use AnzuSystems\CommonBundle\Mcp\Model\Decorator\McpAuditLogDecorator;
use AnzuSystems\CommonBundle\Mcp\Model\Decorator\McpToolCallLogDecorator;
use AnzuSystems\CommonBundle\Mcp\Model\McpDateWindow;
use AnzuSystems\CommonBundle\Mcp\Model\McpLogSearchResult;
use AnzuSystems\CommonBundle\Mcp\Model\Response\McpAppLogSearchResponse;
use AnzuSystems\CommonBundle\Mcp\Model\Response\McpAuditLogSearchResponse;
use AnzuSystems\CommonBundle\Tests\Mcp\McpSerializerFactory;
use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use PHPUnit\Framework\TestCase;

final class McpLogSearchResponseTest extends TestCase
{
    private const string MESSAGE = 'first';

    public function testCompleteAppLogResultCarriesEmptyWarnings(): void
    {
        $response = McpSerializerFactory::create()
            ->toArray(new McpAppLogSearchResponse($this->createSearchResult(truncated: false, limit: 20, hasMore: false)));

        self::assertSame(['appLogs', 'from', 'until', 'limit', 'warnings'], array_keys($response));
        self::assertSame(self::MESSAGE, $response['appLogs'][0]['message']);
        self::assertNull($response['appLogs'][0]['userId']);
        self::assertSame('2026-07-19T00:00:00+00:00', $response['from']);
        self::assertSame('2026-07-20T00:00:00+00:00', $response['until']);
        self::assertSame(20, $response['limit']);
        self::assertSame([], $response['warnings']);
    }

    public function testTruncatedWindowAndTruncatedResultsAreBothReportedOnAuditLogResult(): void
    {
        $response = McpSerializerFactory::create()
            ->toArray(new McpAuditLogSearchResponse($this->createSearchResult(truncated: true, limit: 50, hasMore: true, log: new McpAuditLogDecorator([]))));

        self::assertSame([], $response['auditLogs'][0]['resourceIds']);
        self::assertNull($response['auditLogs'][0]['httpStatus']);
        self::assertCount(2, $response['warnings']);
        self::assertStringContainsString('31-day cap', $response['warnings'][0]);
        self::assertStringContainsString('first 50', $response['warnings'][1]);
    }

    public function testToolCallKeepsItsClient(): void
    {
        $response = McpSerializerFactory::create()
            ->toArray(new McpToolCallLogDecorator(['client' => 'claude-code', 'params' => ['a' => 1]]));

        self::assertSame('claude-code', $response['client']);
        self::assertSame(['a' => 1], $response['params']);
    }

    public function testLongValueIsTruncated(): void
    {
        $response = McpSerializerFactory::create()
            ->toArray(new McpAppLogDecorator(['message' => str_repeat('m', AbstractMcpLogDecorator::FIELD_TRUNCATE_LENGTH + 1)]));

        self::assertStringEndsWith(AbstractMcpLogDecorator::TRUNCATED_SUFFIX, $response['message']);
    }

    private function createSearchResult(bool $truncated, int $limit, bool $hasMore, ?AbstractMcpLogDecorator $log = null): McpLogSearchResult
    {
        return new McpLogSearchResult(
            logs: new ArrayCollection([$log ?? new McpAppLogDecorator(['message' => self::MESSAGE])]),
            window: new McpDateWindow(
                from: new DateTimeImmutable('2026-07-19T00:00:00+00:00'),
                until: new DateTimeImmutable('2026-07-20T00:00:00+00:00'),
                truncated: $truncated,
            ),
            limit: $limit,
            hasMore: $hasMore,
        );
    }
}
