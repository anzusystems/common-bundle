<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Model\OpenApi\Response;

use Attribute;
use OpenApi\Attributes\Response;
use Symfony\Component\HttpFoundation\JsonResponse;

#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
final class OAResponseUnauthorized extends Response
{
    public function __construct()
    {
        parent::__construct(
            response: JsonResponse::HTTP_UNAUTHORIZED,
            description: 'Unauthorized.',
        );
    }
}
