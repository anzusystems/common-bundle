<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Model\OpenApi\Response;

use Attribute;
use Nelmio\ApiDocBundle\Annotation\Model;
use OpenApi\Attributes\Items;
use OpenApi\Attributes\JsonContent;
use OpenApi\Attributes\Response;
use Symfony\Component\HttpFoundation\JsonResponse;

#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
class OAResponse extends Response
{
    public function __construct(
        null|string|array $model = null,
        ?string $description = null,
        int $response = JsonResponse::HTTP_OK,
    ) {
        if (null === $model) {
            parent::__construct(
                response: $response,
                description: $description,
            );
        }
        if (is_string($model)) {
            parent::__construct(
                response: $response,
                description: $description ?? 'Item',
                content: new JsonContent(
                    ref: new Model(
                        type: $model
                    )
                ),
            );
        }
        if (is_iterable($model)) {
            parent::__construct(
                response: $response,
                description: $description ?? 'List of items',
                content: new JsonContent(
                    type: 'array',
                    items: new Items(
                        ref: new Model(
                            type: $model[(int) array_key_first($model)]
                        )
                    )
                ),
            );
        }
    }
}
