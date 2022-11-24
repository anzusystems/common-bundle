<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Model\OpenApi\Response;

use Attribute;
use Symfony\Component\HttpFoundation\JsonResponse;

#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
final class OAResponseCreated extends OAResponse
{
    public function __construct(?string $model = null, ?string $description = null)
    {
        parent::__construct(
            model: $model,
            description: $description ?? 'Created item',
            response: JsonResponse::HTTP_CREATED
        );
    }
}
