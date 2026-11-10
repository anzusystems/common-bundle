<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Mcp\Model\Response;

use AnzuSystems\CommonBundle\Mcp\Model\McpPageWindow;
use AnzuSystems\SerializerBundle\Attributes\Serialize;

trait McpPageResponseTrait
{
    #[Serialize]
    public int $page {
        get => $this->getPageWindow()
            ->page;
    }

    #[Serialize]
    public int $limit {
        get => $this->getPageWindow()
            ->limit;
    }

    abstract protected function getPageWindow(): McpPageWindow;
}
