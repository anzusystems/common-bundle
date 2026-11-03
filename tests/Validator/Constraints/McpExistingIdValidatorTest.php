<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Tests\Validator\Constraints;

use AnzuSystems\CommonBundle\Tests\AnzuKernelTestCase;
use AnzuSystems\CommonBundle\Tests\Data\Entity\Example;
use AnzuSystems\CommonBundle\Tests\Data\Mcp\Model\Request\ExistingIdExampleRequest;
use AnzuSystems\CommonBundle\Validator\Constraints\McpExistingId;
use LogicException;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class McpExistingIdValidatorTest extends AnzuKernelTestCase
{
    private const int UNKNOWN_ID = 424_242;
    private const int OTHER_UNKNOWN_ID = 434_343;

    /**
     * @dataProvider validRequestProvider
     *
     * @param list<int> $exampleIds
     * @param int|list<int>|null $compositeId
     */
    public function testMissingOrExistingIdsPass(?int $exampleId, array $exampleIds, int|array|null $compositeId): void
    {
        $violations = $this->getValidator()
            ->validate($this->createRequest(
                exampleId: $exampleId,
                exampleIds: $exampleIds,
                compositeId: $compositeId,
            ));

        self::assertCount(0, $violations);
    }

    /**
     * @return array<string, array{?int, list<int>, int|list<int>|null}>
     */
    public static function validRequestProvider(): array
    {
        return [
            'nothing set' => [null, [], null],
            'existing id' => [Example::EXAMPLE_INSTANCE_ID, [], null],
            'existing ids with a duplicate' => [null, [Example::EXAMPLE_INSTANCE_ID, Example::EXAMPLE_INSTANCE_ID], null],
            'empty list of an entity with a composite id is not looked up' => [null, [], []],
        ];
    }

    public function testUnknownIdNamesTheResolverTool(): void
    {
        $violations = $this->getValidator()
            ->validate($this->createRequest(exampleId: self::UNKNOWN_ID));

        self::assertCount(1, $violations);
        self::assertSame('exampleId', $violations[0]->getPropertyPath());
        self::assertSame(
            strtr(McpExistingId::MESSAGE, [
                '{{ id }}' => (string) self::UNKNOWN_ID,
                '{{ resolverTool }}' => ExistingIdExampleRequest::RESOLVER_TOOL,
            ]),
            $violations[0]->getMessage(),
        );
    }

    public function testUnknownIdsOfAListAreNamedOnceInOneViolation(): void
    {
        $request = $this->createRequest(
            exampleIds: [Example::EXAMPLE_INSTANCE_ID, self::UNKNOWN_ID, self::OTHER_UNKNOWN_ID, self::UNKNOWN_ID],
        );

        $violations = $this->getValidator()
            ->validate($request);

        self::assertCount(1, $violations);
        self::assertSame('exampleIds', $violations[0]->getPropertyPath());
        self::assertSame(
            strtr(McpExistingId::LIST_MESSAGE, [
                '{{ ids }}' => sprintf('"%d", "%d"', self::UNKNOWN_ID, self::OTHER_UNKNOWN_ID),
                '{{ resolverTool }}' => ExistingIdExampleRequest::RESOLVER_TOOL,
            ]),
            $violations[0]->getMessage(),
        );
    }

    public function testEntityWithCompositeIdentifierFails(): void
    {
        $this->expectException(LogicException::class);

        $this->getValidator()
            ->validate($this->createRequest(compositeId: Example::EXAMPLE_INSTANCE_ID));
    }

    /**
     * @param list<int> $exampleIds
     * @param int|list<int>|null $compositeId
     */
    private function createRequest(?int $exampleId = null, array $exampleIds = [], int|array|null $compositeId = null): ExistingIdExampleRequest
    {
        $request = new ExistingIdExampleRequest();
        $request->exampleId = $exampleId;
        $request->exampleIds = $exampleIds;
        $request->compositeId = $compositeId;

        return $request;
    }

    private function getValidator(): ValidatorInterface
    {
        /** @var ValidatorInterface $validator */
        $validator = self::getContainer()->get(ValidatorInterface::class);

        return $validator;
    }
}
