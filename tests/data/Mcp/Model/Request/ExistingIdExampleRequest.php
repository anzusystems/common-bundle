<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Tests\Data\Mcp\Model\Request;

use AnzuSystems\CommonBundle\Tests\Data\Entity\Example;
use AnzuSystems\CommonBundle\Tests\Data\Entity\ExampleCompositeIdEntity;
use AnzuSystems\CommonBundle\Validator\Constraints\McpExistingId;

final class ExistingIdExampleRequest
{
    public const string RESOLVER_TOOL = 'list_examples';

    #[McpExistingId(entity: Example::class, resolverTool: self::RESOLVER_TOOL)]
    public ?int $exampleId = null;

    /**
     * @var list<int>
     */
    #[McpExistingId(entity: Example::class, resolverTool: self::RESOLVER_TOOL)]
    public array $exampleIds = [];

    /**
     * @var int|list<int>|null
     */
    #[McpExistingId(entity: ExampleCompositeIdEntity::class, resolverTool: self::RESOLVER_TOOL)]
    public int|array|null $compositeId = null;
}
