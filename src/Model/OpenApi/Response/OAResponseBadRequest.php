<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Model\OpenApi\Response;

use Attribute;
use Symfony\Component\HttpFoundation\JsonResponse;

#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
final class OAResponseBadRequest extends OAResponseError
{
    public function __construct()
    {
        parent::__construct(
            response: JsonResponse::HTTP_BAD_REQUEST,
            description: 'Bad request.',
            errorExample: 'bad_request',
        );
    }
}
