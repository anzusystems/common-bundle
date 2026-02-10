<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Model\OpenApi\Request;

use Attribute;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes\JsonContent;
use OpenApi\Attributes\RequestBody;

#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD | Attribute::TARGET_PARAMETER)]
final class OARequest extends RequestBody
{
    public function __construct(
        string $model,
        ?string $description = null,
    ) {
        parent::__construct(
            description: $description,
            content: new JsonContent(
                ref: new Model(
                    type: $model
                )
            )
        );
    }
}
