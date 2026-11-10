<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Tests\Data\Mcp\Model\Request;

use AnzuSystems\SerializerBundle\Attributes\Serialize;

abstract class ParentExampleRequest
{
    #[Serialize]
    private string $parentOnly = 'parent';

    #[Serialize]
    private string $shared = 'parent';
}
