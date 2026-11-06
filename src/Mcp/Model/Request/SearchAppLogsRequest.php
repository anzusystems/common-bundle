<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Mcp\Model\Request;

use AnzuSystems\CommonBundle\Mcp\Attribute\McpField;
use AnzuSystems\SerializerBundle\Attributes\Serialize;
use Symfony\Component\Validator\Constraints as Assert;

final class SearchAppLogsRequest
{
    use McpLogSearchRequestTrait;

    private const array LEVELS = ['DEBUG', 'INFO', 'NOTICE', 'WARNING', 'ERROR', 'CRITICAL', 'ALERT', 'EMERGENCY'];

    #[Assert\Choice(choices: self::LEVELS)]
    #[McpField(description: 'Omit for all levels.')]
    #[Serialize]
    public ?string $level = null;

    #[McpField(description: 'Case-insensitive substring match on the log message.')]
    #[Serialize]
    public ?string $messageContains = null;
}
