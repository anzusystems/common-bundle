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
    use McpWarningsTrait;

    #[Serialize]
    public int $page {
        get => $this->pageWindow->page;
    }

    #[Serialize]
    public int $limit {
        get => $this->pageWindow->limit;
    }

    protected function getWarnings(): McpNotes
    {
        return new McpNotes()
            ->addIf($this->pageWindow->clamped, new McpPageWindowResolver()->getClampWarning($this->pageWindow));
    }
}
