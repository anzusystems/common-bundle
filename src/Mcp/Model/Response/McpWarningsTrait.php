<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Mcp\Model\Response;

use AnzuSystems\CommonBundle\Mcp\Attribute\McpField;
use AnzuSystems\SerializerBundle\Attributes\Serialize;

trait McpWarningsTrait
{
    /**
     * @var list<string>
     */
    #[McpField(description: 'How the result is incomplete or adjusted.')]
    #[Serialize]
    public array $warnings {
        get => $this->getWarnings()
            ->texts;
    }

    abstract protected function getWarnings(): McpNotes;
}
