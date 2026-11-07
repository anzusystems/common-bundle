<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Tests\Data\Mcp\Model\Request;

use AnzuSystems\CommonBundle\Mcp\Attribute\McpField;
use AnzuSystems\CommonBundle\Tests\Data\Mcp\Model\Enum\ExampleItemStatus;
use AnzuSystems\SerializerBundle\Attributes\Serialize;

final class DescribedEnumExampleRequest
{
    public const string STATUS_DESCRIPTION = 'State of the item.';

    #[McpField(description: self::STATUS_DESCRIPTION)]
    #[Serialize]
    public ExampleItemStatus $status;

    #[Serialize]
    public ExampleItemStatus $defaultStatus = ExampleItemStatus::Default;

    #[Serialize]
    public ?ExampleItemStatus $nullableStatus = ExampleItemStatus::Changed;
}
