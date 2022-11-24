<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Model\OpenApi\Parameter;

use AnzuSystems\Contracts\Model\Enum\EnumInterface;
use Attribute;
use Exception;
use OpenApi\Attributes\PathParameter;
use OpenApi\Attributes\Schema;

#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD | Attribute::TARGET_PROPERTY | Attribute::IS_REPEATABLE)]
final class OAEnumParameterPath extends PathParameter
{
    /**
     * @param class-string $class
     *
     * @throws Exception
     */
    public function __construct(string $name, string $class, ?string $description = null)
    {
        if (is_a($class, EnumInterface::class, true)) {
            parent::__construct(
                name: $name,
                description: $description,
                schema: new Schema(
                    type: 'string',
                    default: $class::Default,
                    enum: $class::values(),
                )
            );
        }
    }
}
