<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Tests\Data\Mcp\Model\Request;

use AnzuSystems\CommonBundle\Serializer\Handler\Handlers\UuidArrayHandler;
use AnzuSystems\SerializerBundle\Attributes\Serialize;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;

final class UuidExampleRequest
{
    public const int DOC_IDS_MAX = 10;

    #[Serialize]
    public Uuid $docId;

    #[Serialize]
    public ?Uuid $parentDocId = null;

    /**
     * @var list<Uuid>
     */
    #[Assert\Count(max: self::DOC_IDS_MAX)]
    #[Serialize(handler: UuidArrayHandler::class)]
    public array $docIds = [];
}
