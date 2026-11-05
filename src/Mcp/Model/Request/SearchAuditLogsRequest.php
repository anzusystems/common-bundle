<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Mcp\Model\Request;

use AnzuSystems\CommonBundle\Mcp\Attribute\McpField;
use AnzuSystems\SerializerBundle\Attributes\Serialize;

final class SearchAuditLogsRequest
{
    use McpLogSearchRequestTrait;

    #[McpField(description: 'Filter by the id of the user who made the request. Omit to search requests of all users.')]
    #[Serialize]
    public ?int $userId = null;

    #[McpField(description: 'When true (default), only failed requests are returned: http status 400 or higher, or a non-empty error or exception. Set it to false to include successful requests.')]
    #[Serialize]
    public bool $onlyErrors = true;

    #[McpField(description: 'Case-insensitive substring match on the request path, e.g. "article".')]
    #[Serialize]
    public ?string $pathContains = null;

    #[McpField(description: 'Exact resource name of the affected entity as stored in the audit record, e.g. "articleKindStandard".')]
    #[Serialize]
    public ?string $resourceName = null;
}
