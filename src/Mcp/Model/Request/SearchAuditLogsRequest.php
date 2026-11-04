<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Mcp\Model\Request;

use AnzuSystems\CommonBundle\Mcp\Attribute\McpField;
use AnzuSystems\CommonBundle\Mcp\Log\McpLogFinder;
use AnzuSystems\CommonBundle\Mcp\Resolver\McpDateWindowResolver;
use AnzuSystems\SerializerBundle\Attributes\Serialize;
use Symfony\Component\Validator\Constraints as Assert;

final class SearchAuditLogsRequest
{
    #[McpField(description: 'Filter by the id of the user who made the request. Omit to search requests of all users.')]
    #[Serialize]
    public ?int $userId = null;

    #[McpField(description: 'When true (default), only failed requests are returned: http status 400 or higher, or a non-empty error or exception. Set it to false to include successful requests.')]
    #[Serialize]
    public bool $onlyErrors = true;

    #[McpField(description: 'Case-insensitive substring match on the request path, e.g. "article".')]
    #[Serialize]
    public ?string $pathContains = null;

    #[McpField(description: 'Exact resource name of the affected entity as stored in the audit record, e.g. "articleKindStandard".')]
    #[Serialize]
    public ?string $resourceName = null;

    #[McpField(description: 'Exact contextId (UUID) of one request, as found in other log records.')]
    #[Serialize]
    public ?string $contextId = null;

    #[McpField(description: 'Only records at or after this ISO 8601 date-time, e.g. "2026-07-20T06:00:00+02:00". Defaults to 1 day before until. ' . McpDateWindowResolver::DATE_TIME_OFFSET_DESCRIPTION)]
    #[Serialize]
    public ?string $from = null;

    #[McpField(
        description: <<<'TXT'
            Only records at or before this ISO 8601 date-time.
            Defaults to now; the from..until window is capped at {{ logWindowMaxDays }} days.
            {{ dateTimeOffset }}
            TXT
        ,
        values: [
            'dateTimeOffset' => McpDateWindowResolver::DATE_TIME_OFFSET_DESCRIPTION,
            'logWindowMaxDays' => McpDateWindowResolver::LOG_WINDOW_MAX_DAYS,
        ],
    )]
    #[Serialize]
    public ?string $until = null;

    #[Assert\GreaterThanOrEqual(value: McpLogFinder::LIMIT_MIN)]
    #[McpField(
        description: 'Maximum number of records, capped at {{ limitMax }}.',
        values: ['limitMax' => McpLogFinder::LIMIT_MAX],
    )]
    #[Serialize]
    public int $limit = McpLogFinder::LIMIT_DEFAULT;
}
