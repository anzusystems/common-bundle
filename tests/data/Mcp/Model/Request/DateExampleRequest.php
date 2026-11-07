<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Tests\Data\Mcp\Model\Request;

use AnzuSystems\SerializerBundle\Attributes\Serialize;
use DateTimeImmutable;

final class DateExampleRequest
{
    #[Serialize]
    public ?DateTimeImmutable $from = null;
}
