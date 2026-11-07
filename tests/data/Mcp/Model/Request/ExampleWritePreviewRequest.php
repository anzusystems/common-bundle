<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Tests\Data\Mcp\Model\Request;

use AnzuSystems\SerializerBundle\Attributes\Serialize;
use Symfony\Component\Validator\Constraints as Assert;

class ExampleWritePreviewRequest
{
    #[Assert\NotBlank]
    #[Serialize]
    public string $title = '';
}
