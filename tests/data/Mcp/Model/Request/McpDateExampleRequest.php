<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Tests\Data\Mcp\Model\Request;

use AnzuSystems\CommonBundle\Mcp\Attribute\McpField;
use AnzuSystems\CommonBundle\Serializer\Handler\Handlers\McpDateTimeHandler;
use AnzuSystems\SerializerBundle\Attributes\Serialize;
use DateTimeImmutable;

final class McpDateExampleRequest
{
    public const string FROM_DESCRIPTION = 'Only records at or after this date-time.';
    public const string UNTIL_DESCRIPTION = 'Only records at or before this date-time.';

    #[McpField(description: self::FROM_DESCRIPTION)]
    #[Serialize(handler: McpDateTimeHandler::class)]
    public ?DateTimeImmutable $from = null;

    #[McpField(description: self::UNTIL_DESCRIPTION)]
    #[Serialize(handler: McpDateTimeHandler::class, type: McpDateTimeHandler::END_OF_DAY)]
    public ?DateTimeImmutable $until = null;

    #[Serialize(handler: McpDateTimeHandler::class)]
    public DateTimeImmutable $at;
}
