<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Mcp\Model\Response;

final class McpNotes
{
    /**
     * @param list<string> $texts
     */
    public function __construct(
        public private(set) array $texts = [],
    ) {
    }

    public function addIf(bool $condition, string $text): self
    {
        if ($condition) {
            $this->texts[] = $text;
        }

        return $this;
    }
}
