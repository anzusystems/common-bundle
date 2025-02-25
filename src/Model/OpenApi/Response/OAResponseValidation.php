<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Model\OpenApi\Response;

use Attribute;
use Symfony\Component\HttpFoundation\JsonResponse;

#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
final class OAResponseValidation extends OAResponse
{
    public function __construct()
    {
        parent::__construct(
            description: 'List of validation errors.',
            response: JsonResponse::HTTP_UNPROCESSABLE_ENTITY,
        );
    }
}
