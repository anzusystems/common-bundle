<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Tests\Data\Mcp\Model\Request;

use AnzuSystems\CommonBundle\Tests\Data\Mcp\Validator\Constraints\ExampleIdFilter;
use AnzuSystems\SerializerBundle\Attributes\Serialize;

final class CompoundExampleRequest
{
    /**
     * @var list<int>
     */
    #[ExampleIdFilter]
    #[Serialize]
    public array $ids = [];
}
