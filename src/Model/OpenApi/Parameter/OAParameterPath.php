<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Model\OpenApi\Parameter;

use Attribute;
use OpenApi\Attributes\Parameter;

#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD | Attribute::TARGET_PROPERTY | Attribute::IS_REPEATABLE)]
final class OAParameterPath extends Parameter
{
    public function __construct(
        ?string $name = null,
        ?string $description = null,
    ) {
        parent::__construct(
            name: $name,
            description: $description ?? 'ID of item',
            in: 'path'
        );
    }
}
