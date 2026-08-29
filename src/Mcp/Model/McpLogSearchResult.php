<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Mcp\Model;

use AnzuSystems\CommonBundle\Mcp\McpToolExecutor;
use AnzuSystems\CommonBundle\Mcp\Resolver\McpDateWindowResolver;
use DateTimeInterface;

final readonly class McpLogSearchResult
{
    private const string WINDOW_TRUNCATED_WARNING = 'The requested window was longer than the %d-day cap and was '
        . 'shortened: only records between from and until were searched, the rest of the requested range was not. '
        . 'Repeat the search on the remaining days.';
    private const string LIMIT_CLAMPED_WARNING = 'The requested limit %d is above the maximum and was reduced to %d, '
        . 'so more matching records exist than were returned. Narrow the filters or the window instead of raising the limit.';

    /**
     * @param list<array<string, mixed>> $logs
     */
    public function __construct(
        public array $logs,
        public McpDateWindow $window,
        public int $limit,
        public int $requestedLimit,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toToolResponse(string $logsKey, string $hint): array
    {
        $response = [
            $logsKey => $this->logs,
            'from' => $this->window->from->format(DateTimeInterface::ATOM),
            'until' => $this->window->until->format(DateTimeInterface::ATOM),
            'limit' => $this->limit,
            'hint' => $hint,
        ];
        $warnings = $this->createWarnings();
        if (false === empty($warnings)) {
            $response[McpToolExecutor::WARNINGS_KEY] = $warnings;
        }

        return $response;
    }

    /**
     * @return list<string>
     */
    private function createWarnings(): array
    {
        $warnings = [];
        if ($this->window->truncated) {
            $warnings[] = sprintf(self::WINDOW_TRUNCATED_WARNING, McpDateWindowResolver::LOG_WINDOW_MAX_DAYS);
        }
        if ($this->limit < $this->requestedLimit) {
            $warnings[] = sprintf(self::LIMIT_CLAMPED_WARNING, $this->requestedLimit, $this->limit);
        }

        return $warnings;
    }
}
