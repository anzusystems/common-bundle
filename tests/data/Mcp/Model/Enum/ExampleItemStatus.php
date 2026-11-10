<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Tests\Data\Mcp\Model\Enum;

use AnzuSystems\CommonBundle\Mcp\Attribute\McpField;
use AnzuSystems\Contracts\Model\Enum\BaseEnumTrait;
use AnzuSystems\Contracts\Model\Enum\EnumInterface;

enum ExampleItemStatus: string implements EnumInterface
{
    use BaseEnumTrait;

    #[McpField(description: 'kept as it is')]
    case Unchanged = 'unchanged';

    #[McpField(description: 'kept with another text')]
    case Changed = 'changed';

    case Removed = 'removed';

    public const self Default = self::Unchanged;
}
