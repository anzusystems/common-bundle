<?php
declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Model\OpenApi\Get;

use AnzuSystems\CommonBundle\ApiFilter\ApiParams;
use AnzuSystems\CommonBundle\Model\OpenApi\Response\OAResponseInfiniteList;
use Attribute;
use OpenApi\Attributes\Get;

#[Attribute(\Attribute::TARGET_CLASS | Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
final class OAGetInfiniteList extends Get
{
    public function __construct(string $type) {
        parent::__construct(
            parameters: iterator_to_array(ApiParams::generateAllAvailableOAQueryParams()),
            responses: [
                new OAResponseInfiniteList(type: $type)
            ]
        );
    }
}
