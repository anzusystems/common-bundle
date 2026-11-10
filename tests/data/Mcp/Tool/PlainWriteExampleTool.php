<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Tests\Data\Mcp\Tool;

use Mcp\Capability\Attribute\McpTool;

final readonly class PlainWriteExampleTool
{
    public const string NAME = 'plain_write_example';

    /**
     * @return array<string, string>
     */
    #[McpTool(name: self::NAME)]
    public function __invoke(string $query): array
    {
        return ['query' => $query];
    }
}
