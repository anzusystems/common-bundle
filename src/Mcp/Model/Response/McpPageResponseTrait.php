<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Mcp\Model\Response;

use AnzuSystems\CommonBundle\Mcp\Resolver\McpPageWindowResolver;
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

    /**
     * @var list<string>
     */
    #[Serialize]
    public array $warnings {
        get {
            if (false === $this->pageWindow->clamped) {
                return [];
            }

            return [new McpPageWindowResolver()->getClampWarning($this->pageWindow)];
        }
    }
}
