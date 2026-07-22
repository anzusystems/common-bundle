<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Mcp\Log;

use AnzuSystems\CommonBundle\Log\Repository\AuditLogRepository;
use AnzuSystems\CommonBundle\Log\Repository\JournalLogRepository;
use AnzuSystems\CommonBundle\Mcp\Model\McpAuditLogFilter;
use AnzuSystems\CommonBundle\Mcp\Resolver\McpDateWindowResolver;
use AnzuSystems\Contracts\AnzuApp;
use DateTimeImmutable;
use DateTimeInterface;
use MongoDB\BSON\UTCDateTime;

final readonly class McpLogFinder
{
    public const int LIMIT_DEFAULT = 20;
    public const int LIMIT_MAX = 50;
    public const int FIELD_TRUNCATE_LENGTH = 2_000;
    public const string TRUNCATED_SUFFIX = '…(truncated)';

    private const int LIMIT_MIN = 1;
    private const int BY_CONTEXT_SCAN_DAYS = McpDateWindowResolver::LOG_WINDOW_MAX_DAYS;
    private const string EMPTY_STRING = '';

    public function __construct(
        private AuditLogRepository $auditLogRepository,
        private JournalLogRepository $journalLogRepository,
        private McpLogRepository $mcpLogRepository,
        private McpDateWindowResolver $dateWindowResolver,
    ) {
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function findAuditLogs(McpAuditLogFilter $filter): array
    {
        $window = $this->dateWindowResolver->resolveLogWindow($filter->from, $filter->until);
        $documents = $this->auditLogRepository->findLatest(
            from: $window->from,
            until: $window->until,
            userId: $filter->userId,
            pathContains: $filter->pathContains,
            resourceName: $filter->resourceName,
            contextId: $filter->contextId,
            onlyErrors: $filter->onlyErrors,
            limit: $this->clampLimit($filter->limit),
        );

        return array_map($this->mapAuditLog(...), $documents);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function findAppLogs(
        ?string $level,
        ?string $messageContains,
        ?string $contextId,
        ?string $from,
        ?string $until,
        int $limit,
    ): array {
        $window = $this->dateWindowResolver->resolveLogWindow($from, $until);
        $documents = $this->journalLogRepository->findLatest(
            from: $window->from,
            until: $window->until,
            levelName: null === $level ? null : strtoupper($level),
            messageContains: $messageContains,
            contextId: $contextId,
            limit: $this->clampLimit($limit),
        );

        return array_map($this->mapAppLog(...), $documents);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function findAuditLogsByContextId(string $contextId): array
    {
        $documents = $this->auditLogRepository->findLatestByContextId($contextId, $this->createByContextFrom(), self::LIMIT_MAX);

        return array_map($this->mapAuditLog(...), $documents);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function findAppLogsByContextId(string $contextId): array
    {
        $documents = $this->journalLogRepository->findLatestByContextId($contextId, $this->createByContextFrom(), self::LIMIT_MAX);

        return array_map($this->mapAppLog(...), $documents);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function findMcpLogsByContextId(string $contextId): array
    {
        $documents = $this->mcpLogRepository->findLatestByContextId($contextId, $this->createByContextFrom(), self::LIMIT_MAX);

        return array_map($this->mapMcpLog(...), $documents);
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
