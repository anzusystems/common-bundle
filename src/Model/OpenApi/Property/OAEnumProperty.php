<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Model\OpenApi\Property;

use AnzuSystems\Contracts\Model\Enum\EnumInterface;
use Attribute;
use Exception;
use OpenApi\Attributes\Property;

#[Attribute(Attribute::TARGET_PROPERTY | Attribute::IS_REPEATABLE)]
final class OAEnumProperty extends Property
{
    /**
     * @param class-string $class
     *
     * @throws Exception
     */
    public function __construct(string $class, ?string $description = null)
    {
        if (is_a($class, EnumInterface::class, true)) {
            parent::__construct(
                description: $description,
                type: 'string',
                default: $class::Default,
                enum: $class::values(),
            );
        }
    }
}
