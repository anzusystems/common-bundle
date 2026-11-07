<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Tests\Data\Mcp\Model\Request;

use AnzuSystems\SerializerBundle\Attributes\Serialize;

final class TreeExampleRequest
{
    #[Serialize]
    public string $name = '';

    /**
     * @var list<self>
     */
    #[Serialize(type: self::class)]
    public array $children = [];
}
