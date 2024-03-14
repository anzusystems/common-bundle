<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Model\OpenApi\Get;

use AnzuSystems\CommonBundle\Model\OpenApi\Request\OARequest;
use AnzuSystems\CommonBundle\Model\OpenApi\Response\OAResponseInfiniteList;
use Attribute;
use OpenApi\Attributes\Get;

#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD | Attribute::TARGET_PARAMETER)]
final class OAGetSearch extends Get
{
    public function __construct(string $searchType, string $responseType)
    {
        parent::__construct(
            requestBody: new OARequest($searchType, description: 'Please use this model as a reference for what can be added into query parameters.'),
            responses: [
                new OAResponseInfiniteList(type: $responseType),
            ]
        );
    }
}
