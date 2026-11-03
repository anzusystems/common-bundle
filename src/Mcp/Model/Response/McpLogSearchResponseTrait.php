<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Mcp\Model\Response;

use AnzuSystems\CommonBundle\Mcp\Attribute\McpField;
use AnzuSystems\CommonBundle\Mcp\Model\McpLogSearchResult;
use AnzuSystems\CommonBundle\Mcp\Resolver\McpDateWindowResolver;
use AnzuSystems\SerializerBundle\Attributes\Serialize;
use DateTimeInterface;

trait McpLogSearchResponseTrait
{
    private const string WINDOW_TRUNCATED_WARNING = 'The requested window was longer than the %d-day cap and was '
        . 'shortened: only records between from and until were searched, the rest of the requested range was not. '
        . 'Repeat the search on the remaining days.';
    private const string MORE_RECORDS_WARNING = 'Only the first %d matching records were returned and more exist. '
        . 'Narrow the filters or the window instead of raising the limit.';

    #[McpField(description: 'Start of the time window actually searched.')]
    #[Serialize]
    public string $from {
        get => $this->result->window->from->format(DateTimeInterface::ATOM);
    }

    #[McpField(description: 'End of the time window actually searched; a longer requested window is shortened and reported in warnings, so repeat the search on the remaining days.')]
    #[Serialize]
    public string $until {
        get => $this->result->window->until->format(DateTimeInterface::ATOM);
    }

    #[Serialize]
    public int $limit {
        get => $this->result->limit;
    }

    #[Serialize]
    public string $hint {
        get => self::HINT_CONTEXT_ID;
    }

    /**
     * @var list<string>
     */
    #[Serialize]
    public array $warnings {
        get {
            $warnings = [];
            if ($this->result->window->truncated) {
                $warnings[] = sprintf(self::WINDOW_TRUNCATED_WARNING, McpDateWindowResolver::LOG_WINDOW_MAX_DAYS);
            }
            if ($this->result->hasMore) {
                $warnings[] = sprintf(self::MORE_RECORDS_WARNING, $this->result->limit);
            }

            return $warnings;
        }
    }

    public function __construct(
        private readonly McpLogSearchResult $result,
    ) {
    }
}
