<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Mcp\Log;

use AnzuSystems\CommonBundle\Log\Repository\AuditLogRepository;
use AnzuSystems\CommonBundle\Log\Repository\JournalLogRepository;
use AnzuSystems\CommonBundle\Mcp\Model\Decorator\AbstractMcpLogDecorator;
use AnzuSystems\CommonBundle\Mcp\Model\Decorator\McpAppLogDecorator;
use AnzuSystems\CommonBundle\Mcp\Model\Decorator\McpAuditLogDecorator;
use AnzuSystems\CommonBundle\Mcp\Model\Decorator\McpToolCallLogDecorator;
use AnzuSystems\CommonBundle\Mcp\Model\McpLogsByContextResult;
use AnzuSystems\CommonBundle\Mcp\Model\McpLogSearchResult;
use AnzuSystems\CommonBundle\Mcp\Model\Request\GetLogsByContextRequest;
use AnzuSystems\CommonBundle\Mcp\Model\Request\SearchAppLogsRequest;
use AnzuSystems\CommonBundle\Mcp\Model\Request\SearchAuditLogsRequest;
use AnzuSystems\CommonBundle\Mcp\Resolver\McpContextIdResolver;
use AnzuSystems\CommonBundle\Mcp\Resolver\McpDateWindowResolver;
use AnzuSystems\Contracts\AnzuApp;
use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

final readonly class McpLogFinder
{
    public const int LIMIT_DEFAULT = 20;
    public const int LIMIT_MAX = 50;
    public const int LIMIT_MIN = 1;

    private const int BY_CONTEXT_SCAN_DAYS = McpDateWindowResolver::LOG_WINDOW_MAX_DAYS;
    private const int LOOKAHEAD = 1;

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
            $this->decorate(array_slice($documents, 0, $limit), McpAuditLogDecorator::class),
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
            $this->decorate(array_slice($documents, 0, $limit), McpAppLogDecorator::class),
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
            $this->decorate($this->auditLogRepository->findLatestByContextId($contextId, $from, self::LIMIT_MAX), McpAuditLogDecorator::class),
            $this->decorate($this->journalLogRepository->findLatestByContextId($contextId, $from, self::LIMIT_MAX), McpAppLogDecorator::class),
            $this->decorate($this->mcpLogRepository->findLatestByContextId($contextId, $from, self::LIMIT_MAX), McpToolCallLogDecorator::class),
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
     * @template T of AbstractMcpLogDecorator
     *
     * @param list<array<string, mixed>> $documents
     * @param class-string<T> $decoratorClass
     *
     * @return Collection<int, T>
     */
    private function decorate(array $documents, string $decoratorClass): Collection
    {
        return new ArrayCollection(array_map(
            static fn (array $document): AbstractMcpLogDecorator => new $decoratorClass($document),
            $documents,
        ));
    }
}
