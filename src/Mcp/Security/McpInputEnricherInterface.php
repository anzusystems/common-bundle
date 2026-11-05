<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Mcp\Security;

/**
 * Narrows an input that passed its inputPermission, e.g. an empty site filter to the sites the user may read. A voter
 * accepts such a filter, so a tool with an inputPermission does not compile without this service.
 */
interface McpInputEnricherInterface
{
    public function enrich(object $input): void;
}
