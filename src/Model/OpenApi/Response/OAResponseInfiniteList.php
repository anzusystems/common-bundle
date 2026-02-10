<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Model\OpenApi\Response;

use Attribute;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes\Items;
use OpenApi\Attributes\JsonContent;
use OpenApi\Attributes\Property;
use OpenApi\Attributes\Response;
use Symfony\Component\HttpFoundation\JsonResponse;

#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
class OAResponseInfiniteList extends Response
{
    public function __construct(
        string $type,
        ?string $description = null,
        int $response = JsonResponse::HTTP_OK,
    ) {
        parent::__construct(
            response: $response,
            description: $description ?? 'List of items',
            content: new JsonContent(
                properties: [
                    new Property(
                        property: 'hasNextPage',
                        type: 'boolean',
                        default: false,
                    ),
                    new Property(
                        property: 'totalCount',
                        type: 'integer',
                        default: 0,
                        deprecated: true,
                    ),
                    new Property(
                        property: 'data',
                        type: 'array',
                        items: new Items(
                            ref: new Model(
                                type: $type
                            )
                        ),
                    ),
                ],
                type: 'object'
            )
        );
    }
}
