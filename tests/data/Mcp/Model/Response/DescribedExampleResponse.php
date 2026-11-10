<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Tests\Data\Mcp\Model\Response;

use AnzuSystems\CommonBundle\Mcp\Attribute\McpField;
use AnzuSystems\CommonBundle\Tests\Data\Mcp\Model\Request\HookedExampleRequest;
use AnzuSystems\SerializerBundle\Attributes\Serialize;

final class DescribedExampleResponse
{
    #[McpField(
        description: <<<'TXT'
            Described plain field.
            Its second sentence.
            TXT
        ,
    )]
    #[Serialize]
    public int $count = 0;

    #[McpField(description: 'Described object of plain fields.')]
    #[Serialize]
    public ?HookedExampleRequest $item = null;

    #[Serialize]
    public int $id = 0;

    #[Serialize]
    public string $name = '';
}
