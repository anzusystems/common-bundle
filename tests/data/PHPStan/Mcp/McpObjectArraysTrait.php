<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Tests\Data\PHPStan\Mcp;

use AnzuSystems\CommonBundle\Tests\Data\Entity\Example;

trait McpObjectArraysTrait
{
    /**
     * @var list<Example>
     */
    public array $examples = [];
}
