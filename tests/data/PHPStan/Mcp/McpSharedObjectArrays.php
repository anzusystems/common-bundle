<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Tests\Data\PHPStan\Mcp;

use AnzuSystems\CommonBundle\Tests\Data\Entity\Example;

final class McpSharedObjectArrays
{
    /**
     * @return list<Example>
     */
    public function findExamples(): array
    {
        return [];
    }
}
