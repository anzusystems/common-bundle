<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Tests\Data\PHPStan\Domain\Example\Mcp;

use AnzuSystems\CommonBundle\Tests\Data\Entity\Example;
use AnzuSystems\CommonBundle\Tests\Data\Entity\User;
use AnzuSystems\CommonBundle\Tests\Data\Model\DataObject\DummyDto;
use AnzuSystems\CommonBundle\Tests\Data\Model\Enum\DummyEnum;
use AnzuSystems\SerializerBundle\Attributes\Serialize;
use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Symfony\Component\Uid\Uuid;

final class McpObjectCollections
{
    /**
     * @var Collection<int, Example>
     */
    public Collection $examples;

    /**
     * @var Collection<int, DummyDto>
     */
    #[Serialize(type: DummyDto::class)]
    public Collection $serializedDtos;

    /**
     * @param Collection<int, Collection<int, Example>> $examplesByGroupId
     */
    public function __construct(
        public Collection $examplesByGroupId,
    ) {
        $this->examples = new ArrayCollection();
        $this->serializedDtos = new ArrayCollection();
    }

    /**
     * @param Collection<string, User> $usersByEmail
     * @param list<int> $ids
     * @param list<Uuid> $docIds
     * @param list<DateTimeImmutable> $dates
     * @param list<DummyEnum> $enums
     *
     * @return Collection<int, DummyDto>
     */
    public function mapExamples(Collection $usersByEmail, array $ids, array $docIds, array $dates, array $enums): Collection
    {
        return new ArrayCollection();
    }
}
