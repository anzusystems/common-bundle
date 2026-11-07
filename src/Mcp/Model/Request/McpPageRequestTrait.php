<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Mcp\Model\Request;

use AnzuSystems\CommonBundle\Mcp\Attribute\McpField;
use AnzuSystems\CommonBundle\Mcp\Model\McpPageWindow;
use AnzuSystems\SerializerBundle\Attributes\Serialize;
use Symfony\Component\Validator\Constraints as Assert;

trait McpPageRequestTrait
{
    #[Assert\Range(min: McpPageWindow::PAGE_MIN, max: McpPageWindow::PAGE_MAX)]
    #[McpField(description: 'Page number, starting at ' . McpPageWindow::PAGE_MIN . '.')]
    #[Serialize]
    public int $page = McpPageWindow::PAGE_MIN;
}
