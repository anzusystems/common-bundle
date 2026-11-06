<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Mcp\Security;

/**
 * Narrows the input of every call before its tool runs, e.g. an empty site filter to the sites the user may read. Without
 * this service an empty filter stays empty and a repository reads every site.
 */
interface McpInputEnricherInterface
{
    public function enrich(object $input): void;
}
