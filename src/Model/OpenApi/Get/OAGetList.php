<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Model\OpenApi\Get;

use AnzuSystems\CommonBundle\ApiFilter\ApiParams;
use AnzuSystems\CommonBundle\Model\OpenApi\Response\OAResponseList;
use Attribute;
use OpenApi\Attributes\Get;
use OpenApi\Attributes\QueryParameter;

#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
final class OAGetList extends Get
{
    /**
     * @param QueryParameter[] $customFilters
     */
    public function __construct(string $type, array $customFilters = [])
    {
        parent::__construct(
            parameters: iterator_to_array(ApiParams::generateAllAvailableOAQueryParams()),
            responses: [new OAResponseList($type)]
        );
    }
}
