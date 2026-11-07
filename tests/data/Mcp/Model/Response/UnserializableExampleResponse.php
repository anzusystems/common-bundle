<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Tests\Data\Mcp\Model\Response;

use AnzuSystems\SerializerBundle\Attributes\Serialize;
use RuntimeException;

final class UnserializableExampleResponse
{
    public const string MESSAGE = 'The response cannot be serialized.';

    #[Serialize]
    public string $title {
        get => throw new RuntimeException(self::MESSAGE);
    }
}
