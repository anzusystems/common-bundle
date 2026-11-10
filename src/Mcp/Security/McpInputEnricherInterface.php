<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Mcp\Security;

/**
 * Without this service an empty filter stays empty and a repository reads every record, not only the user's.
 */
interface McpInputEnricherInterface
{
    public function enrich(object $input): void;
}
