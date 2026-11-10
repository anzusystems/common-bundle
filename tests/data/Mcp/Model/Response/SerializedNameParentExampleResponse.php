<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Tests\Data\Mcp\Model\Response;

use AnzuSystems\SerializerBundle\Attributes\Serialize;

abstract class SerializedNameParentExampleResponse
{
    #[Serialize]
    protected string $inherited = 'inherited';

    #[Serialize(serializedName: 'title')]
    private int $parentTitle = 1;
}
