<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Model\OpenApi\Response;

use Attribute;
use OpenApi\Attributes\JsonContent;
use OpenApi\Attributes\Property;
use OpenApi\Attributes\Response;
use OpenApi\Generator;
use Symfony\Component\Uid\Uuid;

#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
class OAResponseError extends Response
{
    public function __construct(
        int $response,
        string $description = Generator::UNDEFINED,
        string $errorExample = Generator::UNDEFINED,
        string $detailExample = Generator::UNDEFINED,
    ) {
        parent::__construct(
            response: $response,
            description: $description,
            content: new JsonContent(
                properties: [
                    new Property(property: 'error', type: 'string', example: $errorExample),
                    new Property(property: 'detail', type: 'string', example: $detailExample),
                    new Property(property: 'contextId', type: 'string', example: Uuid::v4()->toRfc4122()),
                ]
            ),
        );
    }
}
