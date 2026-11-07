<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Tests\Mcp\Tool;

use AnzuSystems\CommonBundle\Log\Repository\AuditLogRepository;
use AnzuSystems\CommonBundle\Log\Repository\JournalLogRepository;
use AnzuSystems\CommonBundle\Mcp\Log\McpLogFinder;
use AnzuSystems\CommonBundle\Mcp\Log\McpLogRepository;
use AnzuSystems\CommonBundle\Mcp\Model\Decorator\McpAppLogDecorator;
use AnzuSystems\CommonBundle\Mcp\Model\Request\SearchAppLogsRequest;
use AnzuSystems\CommonBundle\Mcp\Resolver\McpContextIdResolver;
use AnzuSystems\CommonBundle\Mcp\Resolver\McpDateWindowResolver;
use AnzuSystems\CommonBundle\Mcp\Tool\SearchAppLogsTool;
use AnzuSystems\CommonBundle\Tests\AnzuKernelTestCase;
use AnzuSystems\SerializerBundle\Serializer;
use DateTimeImmutable;
use MongoDB\BSON\ObjectId;
use MongoDB\BSON\UTCDateTime;
use MongoDB\Collection;

final class SearchAppLogsToolTest extends AnzuKernelTestCase
{
    private const string LAST_DAY = '2026-10-31';
    private const string LATE_IN_LAST_DAY = '2026-10-31T22:30:00Z';
    private const string JOURNAL_LOG_COLLECTION = 'anzu_mongo_journal_log_collection';

    public function testUntilAsADateIncludesTheRecordsOfThatDay(): void
    {
        $message = sprintf('until-date-%s', bin2hex(random_bytes(8)));
        $collection = $this->getJournalLogCollection();
        $id = $this->insertJournalLog(collection: $collection, datetime: new DateTimeImmutable(self::LATE_IN_LAST_DAY), message: $message);

        try {
            $request = self::getContainer()->get(Serializer::class)
                ->fromArray([
                    'until' => self::LAST_DAY,
                    'messageContains' => $message,
                ], SearchAppLogsRequest::class);
            self::assertInstanceOf(SearchAppLogsRequest::class, $request);

            $logs = $this->createTool($collection)($request)
                ->appLogs;
        } finally {
            $collection->deleteOne(['_id' => $id]);
        }

        self::assertSame([$message], $logs->map(static fn (McpAppLogDecorator $log): ?string => $log->message)->getValues());
    }

    private function insertJournalLog(Collection $collection, DateTimeImmutable $datetime, string $message): ObjectId
    {
        $id = new ObjectId(sprintf('%08x%s', $datetime->getTimestamp(), bin2hex(random_bytes(8))));
        $collection->insertOne([
            '_id' => $id,
            'datetime' => new UTCDateTime($datetime),
            'level_name' => 'info',
            'message' => $message,
            'context' => [],
        ]);

        return $id;
    }

    private function createTool(Collection $journalLogCollection): SearchAppLogsTool
    {
        $container = self::getContainer();

        return new SearchAppLogsTool(new McpLogFinder(
            auditLogRepository: $container->get(AuditLogRepository::class),
            journalLogRepository: $container->get(JournalLogRepository::class),
            mcpLogRepository: new McpLogRepository($journalLogCollection),
            dateWindowResolver: new McpDateWindowResolver(),
            contextIdResolver: new McpContextIdResolver(),
        ));
    }

    private function getJournalLogCollection(): Collection
    {
        $collection = self::getContainer()->get(self::JOURNAL_LOG_COLLECTION);
        self::assertInstanceOf(Collection::class, $collection);

        return $collection;
    }
}
