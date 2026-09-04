<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Mcp\Log;

use AnzuSystems\CommonBundle\Log\Repository\AuditLogRepository;
use AnzuSystems\CommonBundle\Log\Repository\JournalLogRepository;
use AnzuSystems\CommonBundle\Mcp\Model\McpLogsByContextResult;
use AnzuSystems\CommonBundle\Mcp\Model\McpLogSearchResult;
use AnzuSystems\CommonBundle\Mcp\Model\Request\GetLogsByContextRequest;
use AnzuSystems\CommonBundle\Mcp\Model\Request\SearchAppLogsRequest;
use AnzuSystems\CommonBundle\Mcp\Model\Request\SearchAuditLogsRequest;
use AnzuSystems\CommonBundle\Mcp\Resolver\McpContextIdResolver;
use AnzuSystems\CommonBundle\Mcp\Resolver\McpDateWindowResolver;
use AnzuSystems\Contracts\AnzuApp;
use DateTimeImmutable;
use DateTimeInterface;
use MongoDB\BSON\UTCDateTime;

final readonly class McpLogFinder
{
    public const int LIMIT_DEFAULT = 20;
    public const int LIMIT_MAX = 50;
    public const int LIMIT_MIN = 1;
    public const string LIMIT_MIN_MESSAGE = 'limit must be at least {{ compared_value }}.';
    public const int FIELD_TRUNCATE_LENGTH = 2_000;
    public const string TRUNCATED_SUFFIX = '…(truncated)';

    private const int BY_CONTEXT_SCAN_DAYS = McpDateWindowResolver::LOG_WINDOW_MAX_DAYS;
    private const int LOOKAHEAD = 1;
    private const string EMPTY_STRING = '';

    public function __construct(
        private AuditLogRepository $auditLogRepository,
        private JournalLogRepository $journalLogRepository,
        private McpLogRepository $mcpLogRepository,
        private McpDateWindowResolver $dateWindowResolver,
        private McpContextIdResolver $contextIdResolver,
    ) {
    }

    public function findAuditLogs(SearchAuditLogsRequest $request): McpLogSearchResult
    {
        $window = $this->dateWindowResolver->resolveLogWindow($request->from, $request->until);
        $limit = $this->clampLimit($request->limit);
        $documents = $this->auditLogRepository->findLatest(
            from: $window->from,
            until: $window->until,
            userId: $request->userId,
            pathContains: $request->pathContains,
            resourceName: $request->resourceName,
            contextId: $this->contextIdResolver->resolveOptional($request->contextId),
            onlyErrors: $request->onlyErrors,
            limit: $limit + self::LOOKAHEAD,
        );

        return new McpLogSearchResult(
            array_map($this->mapAuditLog(...), array_slice($documents, 0, $limit)),
            $window,
            $limit,
            count($documents) > $limit,
        );
    }

    public function findAppLogs(SearchAppLogsRequest $request): McpLogSearchResult
    {
        $window = $this->dateWindowResolver->resolveLogWindow($request->from, $request->until);
        $limit = $this->clampLimit($request->limit);
        $documents = $this->journalLogRepository->findLatest(
            from: $window->from,
            until: $window->until,
            levelName: null === $request->level ? null : strtoupper($request->level),
            messageContains: $request->messageContains,
            contextId: $this->contextIdResolver->resolveOptional($request->contextId),
            limit: $limit + self::LOOKAHEAD,
        );

        return new McpLogSearchResult(
            array_map($this->mapAppLog(...), array_slice($documents, 0, $limit)),
            $window,
            $limit,
            count($documents) > $limit,
        );
    }

    public function findLogsByContext(GetLogsByContextRequest $request): McpLogsByContextResult
    {
        $contextId = $this->contextIdResolver->resolve($request->contextId);
        $from = $this->createByContextFrom();

        return new McpLogsByContextResult(
            array_map($this->mapAuditLog(...), $this->auditLogRepository->findLatestByContextId($contextId, $from, self::LIMIT_MAX)),
            array_map($this->mapAppLog(...), $this->journalLogRepository->findLatestByContextId($contextId, $from, self::LIMIT_MAX)),
            array_map($this->mapMcpLog(...), $this->mcpLogRepository->findLatestByContextId($contextId, $from, self::LIMIT_MAX)),
        );
    }

    private function createByContextFrom(): DateTimeImmutable
    {
        return AnzuApp::date()->modify(sprintf('-%d days', self::BY_CONTEXT_SCAN_DAYS));
    }

    private function clampLimit(int $limit): int
    {
        return min(max($limit, self::LIMIT_MIN), self::LIMIT_MAX);
    }

    /**
     * @param array<string, mixed> $document
     *
     * @return array<string, mixed>
     */
    private function mapAuditLog(array $document): array
    {
        $context = $this->toArrayValue($document['context'] ?? []);

        return [
            'datetime' => $this->formatDateTime($document['datetime'] ?? null),
            'method' => $this->toStringValue($context['method'] ?? null),
            'path' => $this->toStringValue($context['path'] ?? null),
            'resourceName' => $this->toStringValue($context['resourceName'] ?? null),
            'resourceIds' => array_values($this->toArrayValue($context['resourceIds'] ?? [])),
            'httpStatus' => $this->toIntValue($context['httpStatus'] ?? null),
            'error' => $this->truncate($this->toStringValue($context['error'] ?? null)),
            'exception' => $this->truncate($this->toStringValue($context['exception'] ?? null)),
            'contextId' => $this->toStringValue($context['contextId'] ?? null),
            'userId' => $this->toIntValue($context['userId'] ?? null),
            'content' => $this->truncate($this->toStringValue($context['content'] ?? null)),
            'response' => $this->truncate($this->toStringValue($context['response'] ?? null)),
        ];
    }

    /**
     * @param array<string, mixed> $document
     *
     * @return array<string, mixed>
     */
    private function mapAppLog(array $document): array
    {
        $context = $this->toArrayValue($document['context'] ?? []);

        return [
            'datetime' => $this->formatDateTime($document['datetime'] ?? null),
            'levelName' => $this->toStringValue($document[JournalLogRepository::FIELD_LEVEL_NAME] ?? null),
            'message' => $this->truncate($this->toStringValue($document['message'] ?? null)),
            'contextId' => $this->toStringValue($context['contextId'] ?? null),
            'userId' => $this->toIntValue($context['userId'] ?? null),
            'path' => $this->toStringValue($context['path'] ?? null),
        ];
    }

    /**
     * @param array<string, mixed> $document
     *
     * @return array<string, mixed>
     */
    private function mapMcpLog(array $document): array
    {
        return [
            'datetime' => $this->formatDateTime($document['datetime'] ?? null),
            'levelName' => $this->toStringValue($document['levelName'] ?? null),
            'tool' => $this->toStringValue($document['tool'] ?? null),
            'params' => $this->toArrayValue($document['params'] ?? []),
            'userId' => $this->toIntValue($document['userId'] ?? null),
            'contextId' => $this->toStringValue($document['contextId'] ?? null),
            'durationMs' => $this->toIntValue($document['durationMs'] ?? null),
            'error' => $this->truncate($this->toStringValue($document['error'] ?? null)),
        ];
    }

    private function truncate(string $value): string
    {
        if (mb_strlen($value) <= self::FIELD_TRUNCATE_LENGTH) {
            return $value;
        }

        return mb_substr($value, 0, self::FIELD_TRUNCATE_LENGTH) . self::TRUNCATED_SUFFIX;
    }

    private function formatDateTime(mixed $datetime): string
    {
        if ($datetime instanceof UTCDateTime) {
            return $datetime->toDateTime()
                ->format(DateTimeInterface::ATOM);
        }

        return self::EMPTY_STRING;
    }

    private function toStringValue(mixed $value): string
    {
        if (is_string($value)) {
            return $value;
        }

        return self::EMPTY_STRING;
    }

    private function toIntValue(mixed $value): int
    {
        if (is_int($value)) {
            return $value;
        }

        return 0;
    }

    /**
     * @return array<array-key, mixed>
     */
    private function toArrayValue(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }

        return [];
    }
}
