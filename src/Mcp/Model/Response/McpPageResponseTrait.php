<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Mcp\Model\Response;

use AnzuSystems\SerializerBundle\Attributes\Serialize;

/**
 * The using class holds its page as the promoted McpPageWindow $pageWindow.
 */
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
