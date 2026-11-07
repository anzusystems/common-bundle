<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Mcp\Model\Response;

use AnzuSystems\SerializerBundle\Attributes\Serialize;

trait McpPageResponseTrait
{
    #[Serialize]
    public int $page {
        get => $this->pageWindow->page;
    }

    #[Serialize]
    public int $limit {
        get => $this->pageWindow->limit;
    }
}
