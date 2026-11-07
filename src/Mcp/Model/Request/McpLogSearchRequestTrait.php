<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Mcp\Model\Request;

use AnzuSystems\CommonBundle\Mcp\Attribute\McpField;
use AnzuSystems\CommonBundle\Mcp\Log\McpLogFinder;
use AnzuSystems\CommonBundle\Mcp\Resolver\McpDateWindowResolver;
use AnzuSystems\CommonBundle\Serializer\Handler\Handlers\McpDateTimeHandler;
use AnzuSystems\SerializerBundle\Attributes\Serialize;
use DateTimeImmutable;
use Symfony\Component\Validator\Constraints as Assert;

trait McpLogSearchRequestTrait
{
    #[McpField(description: 'Exact contextId (UUID) of one request, as found in other log records.')]
    #[Serialize]
    public ?string $contextId = null;

    #[McpField(description: 'Only records at or after this date-time. Defaults to 1 day before until.')]
    #[Serialize(handler: McpDateTimeHandler::class)]
    public ?DateTimeImmutable $from = null;

    #[Assert\GreaterThanOrEqual(propertyPath: 'from', message: McpDateWindowResolver::NOT_BEFORE_FROM_MESSAGE)]
    #[McpField(
        description: <<<'TXT'
            Only records at or before this date-time.
            Defaults to now; the from..until window is capped at {{ logWindowMaxDays }} days.
            TXT
        ,
        descriptionValues: ['logWindowMaxDays' => McpDateWindowResolver::LOG_WINDOW_MAX_DAYS],
    )]
    #[Serialize(handler: McpDateTimeHandler::class)]
    public ?DateTimeImmutable $until = null;

    #[Assert\Range(min: McpLogFinder::LIMIT_MIN, max: McpLogFinder::LIMIT_MAX)]
    #[McpField(description: 'Maximum number of records.')]
    #[Serialize]
    public int $limit = McpLogFinder::LIMIT_DEFAULT;
}
