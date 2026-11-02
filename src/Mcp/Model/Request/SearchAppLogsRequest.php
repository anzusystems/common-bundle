<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Mcp\Model\Request;

use AnzuSystems\CommonBundle\Mcp\Attribute\McpField;
use AnzuSystems\CommonBundle\Mcp\Log\McpLogFinder;
use AnzuSystems\CommonBundle\Mcp\Resolver\McpDateWindowResolver;
use AnzuSystems\SerializerBundle\Attributes\Serialize;
use Symfony\Component\Validator\Constraints as Assert;

final class SearchAppLogsRequest
{
    #[McpField(description: 'Log level name, e.g. "ERROR", "WARNING", "INFO". Case-insensitive, exact match. Omit for all levels.')]
    #[Serialize]
    public ?string $level = null;

    #[McpField(description: 'Case-insensitive substring match on the log message.')]
    #[Serialize]
    public ?string $messageContains = null;

    #[McpField(description: 'Exact contextId (UUID) of one request, as found in other log records.')]
    #[Serialize]
    public ?string $contextId = null;

    #[McpField(description: 'Only records at or after this ISO 8601 date-time, e.g. "2026-07-20T06:00:00+02:00". Defaults to 1 day before until.')]
    #[Serialize]
    public ?string $from = null;

    #[McpField(
        description: <<<'TXT'
            Only records at or before this ISO 8601 date-time.
            Defaults to now; the from..until window is capped at {{ logWindowMaxDays }} days.
            TXT
        ,
        values: ['logWindowMaxDays' => McpDateWindowResolver::LOG_WINDOW_MAX_DAYS],
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
