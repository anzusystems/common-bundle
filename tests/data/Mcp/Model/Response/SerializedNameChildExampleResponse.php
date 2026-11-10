<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Tests\Data\Mcp\Model\Response;

use AnzuSystems\SerializerBundle\Attributes\Serialize;

final class SerializedNameChildExampleResponse extends SerializedNameParentExampleResponse
{
    #[Serialize]
    public string $title = 'child';

    #[Serialize(serializedName: 'renamed')]
    public string $original = 'original';

    #[Serialize]
    public function isActive(): bool
    {
        return true;
    }

    #[Serialize]
    public function getLabel(): string
    {
        return 'label';
    }

    #[Serialize]
    public function issue(): string
    {
        return 'issue';
    }

    #[Serialize]
    public function getÉtat(): string
    {
        return 'état';
    }
}
