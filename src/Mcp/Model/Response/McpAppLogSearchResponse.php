<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Mcp\Model\Response;

use AnzuSystems\CommonBundle\Mcp\Attribute\McpField;
use AnzuSystems\CommonBundle\Mcp\Model\Decorator\McpAppLogDecorator;
use AnzuSystems\SerializerBundle\Attributes\Serialize;
use Doctrine\Common\Collections\Collection;

final class McpAppLogSearchResponse
{
    /**
     * @use McpLogSearchResponseTrait<McpAppLogDecorator>
     */
    use McpLogSearchResponseTrait;

    /**
     * @var Collection<int, McpAppLogDecorator>
     */
    #[McpField(description: 'Newest first: records with a level, a message and the contextId of the request that produced them.')]
    #[Serialize(type: McpAppLogDecorator::class)]
    public Collection $appLogs {
        get => $this->result->logs;
    }
}
