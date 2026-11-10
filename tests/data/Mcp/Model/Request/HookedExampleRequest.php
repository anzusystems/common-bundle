<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Tests\Data\Mcp\Model\Request;

use AnzuSystems\SerializerBundle\Attributes\Serialize;
use Symfony\Component\Validator\Constraints as Assert;

final class HookedExampleRequest
{
    public const int QUERY_MAX_LENGTH = 50;

    #[Assert\Length(max: self::QUERY_MAX_LENGTH)]
    #[Serialize]
    public string $query = '' {
        set => trim($value);
    }
}
