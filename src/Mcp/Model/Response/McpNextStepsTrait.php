<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Mcp\Model\Response;

use AnzuSystems\CommonBundle\Mcp\Attribute\McpField;
use AnzuSystems\SerializerBundle\Attributes\Serialize;

trait McpNextStepsTrait
{
    /**
     * @var list<string>
     */
    #[McpField(description: 'What to do next.')]
    #[Serialize]
    public array $nextSteps {
        get => $this->getNextSteps()
            ->texts;
    }

    abstract protected function getNextSteps(): McpNotes;
}
