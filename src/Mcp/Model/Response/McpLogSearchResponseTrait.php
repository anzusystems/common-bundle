<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Mcp\Model\Response;

use AnzuSystems\CommonBundle\Mcp\Attribute\McpField;
use AnzuSystems\CommonBundle\Mcp\Model\Decorator\AbstractMcpLogDecorator;
use AnzuSystems\CommonBundle\Mcp\Model\McpLogSearchResult;
use AnzuSystems\CommonBundle\Mcp\Resolver\McpDateWindowResolver;
use AnzuSystems\SerializerBundle\Attributes\Serialize;
use DateTimeInterface;

/**
 * @template T of AbstractMcpLogDecorator
 */
trait McpLogSearchResponseTrait
{
    use McpWarningsTrait;

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

    /**
     * @param McpLogSearchResult<T> $result
     */
    public function __construct(
        private readonly McpLogSearchResult $result,
    ) {
    }

    private static function windowTruncatedWarning(): string
    {
        return sprintf(
            'The requested window was longer than the %d-day cap and was shortened: only records between from and until were searched, the rest of the requested range was not. Repeat the search on the remaining days.',
            McpDateWindowResolver::LOG_WINDOW_MAX_DAYS,
        );
    }

    private static function moreRecordsWarning(int $limit): string
    {
        return sprintf('Only the first %d matching records were returned and more exist. Narrow the filters or the window instead of raising the limit.', $limit);
    }

    protected function getWarnings(): McpNotes
    {
        return new McpNotes()
            ->addIf($this->result->window->truncated, self::windowTruncatedWarning())
            ->addIf($this->result->hasMore, self::moreRecordsWarning($this->result->limit));
    }
}
