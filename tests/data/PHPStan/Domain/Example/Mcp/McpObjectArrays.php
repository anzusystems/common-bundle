<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Tests\Data\PHPStan\Domain\Example\Mcp;

use AnzuSystems\CommonBundle\Tests\Data\Entity\Example;
use AnzuSystems\CommonBundle\Tests\Data\Entity\User;
use AnzuSystems\CommonBundle\Tests\Data\Model\DataObject\DummyDto;
use AnzuSystems\SerializerBundle\Attributes\Serialize;

final class McpObjectArrays
{
    /**
     * @var list<Example>
     */
    public array $examples = [];

    /**
     * @var DummyDto[]|null
     */
    public ?array $dtos = null;

    /**
     * @var list<DummyDto>
     */
    #[Serialize(type: DummyDto::class)]
    public array $serializedDtos = [];

    /**
     * @param array<int, list<Example>> $examplesByGroupId
     * @param list<DummyDto> $serializedExamples
     */
    public function __construct(
        public array $examplesByGroupId,
        #[Serialize(type: DummyDto::class)]
        public array $serializedExamples,
    ) {
    }

    /**
     * @param array<string, User> $usersByEmail
     *
     * @return list<DummyDto>
     */
    public function mapExamples(array $usersByEmail): array
    {
        return [];
    }
}
