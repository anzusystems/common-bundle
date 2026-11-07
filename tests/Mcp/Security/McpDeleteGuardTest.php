<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Tests\Mcp\Security;

use AnzuSystems\CommonBundle\Domain\User\CurrentAnzuUserProvider;
use AnzuSystems\CommonBundle\Mcp\Exception\McpDeleteRejectedException;
use AnzuSystems\CommonBundle\Mcp\Exception\McpToolCallRejectedException;
use AnzuSystems\CommonBundle\Mcp\Security\McpDeleteGuard;
use AnzuSystems\CommonBundle\Tests\Data\Entity\User;
use AnzuSystems\CommonBundle\Tests\Data\Mcp\Model\DeletableExample;
use AnzuSystems\CommonBundle\Tests\Data\Mcp\Security\DeletableExampleDependencyProvider;
use AnzuSystems\Contracts\AnzuApp;
use DateTimeImmutable;
use DateTimeInterface;
use LogicException;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ServiceLocator;

final class McpDeleteGuardTest extends TestCase
{
    private const int CURRENT_USER_ID = 11;
    private const int OTHER_USER_ID = 12;
    private const int ENTITY_ID = 21;
    private const string RESOURCE_NAME = 'deletableExample';
    private const string FRESH_OFFSET = '-1 hour';
    private const string MAX_AGE_OFFSET = '-' . McpDeleteGuard::MAX_AGE_DAYS . ' days';
    private const string TOO_OLD_OFFSET = self::MAX_AGE_OFFSET . ' -1 second';
    private const string FIRST_DEPENDENCY = 'linked 3 times from other records';
    private const string SECOND_DEPENDENCY = 'shared with 1 group';

    /**
     * @dataProvider refusalProvider
     *
     * @param list<string> $dependencies
     * @param list<string> $expectedOtherRefusals the refusals after the one about age, which depends on the app date
     */
    public function testRefusalsNameEveryFailedCondition(
        string $createdAtOffset,
        ?int $createdById,
        array $dependencies,
        bool $expectedTooOld,
        array $expectedOtherRefusals,
    ): void {
        $createdAt = AnzuApp::date()->modify($createdAtOffset);
        $entity = $this->createEntity(createdAt: $createdAt, createdById: $createdById);

        self::assertSame(
            [...($expectedTooOld ? [$this->createTooOldRefusal($createdAt)] : []), ...$expectedOtherRefusals],
            $this->createGuard($dependencies)
                ->resolveRefusals($entity),
        );
    }

    /**
     * @return array<string, array{string, int|null, list<string>, bool, list<string>}>
     */
    public static function refusalProvider(): array
    {
        return [
            'fresh, own and unused' => [self::FRESH_OFFSET, self::CURRENT_USER_ID, [], false, []],
            'exactly the maximal age' => [self::MAX_AGE_OFFSET, self::CURRENT_USER_ID, [], false, []],
            'a second older' => [self::TOO_OLD_OFFSET, self::CURRENT_USER_ID, [], true, []],
            'another author' => [self::FRESH_OFFSET, self::OTHER_USER_ID, [], false, [McpDeleteGuard::NOT_CREATOR_MESSAGE]],
            'no author' => [self::FRESH_OFFSET, null, [], false, [McpDeleteGuard::NOT_CREATOR_MESSAGE]],
            'two dependencies' => [
                self::FRESH_OFFSET,
                self::CURRENT_USER_ID,
                [self::FIRST_DEPENDENCY, self::SECOND_DEPENDENCY],
                false,
                [sprintf(McpDeleteGuard::IN_USE_MESSAGE, self::FIRST_DEPENDENCY . '; ' . self::SECOND_DEPENDENCY)],
            ],
            'everything fails' => [
                self::TOO_OLD_OFFSET,
                self::OTHER_USER_ID,
                [self::FIRST_DEPENDENCY],
                true,
                [McpDeleteGuard::NOT_CREATOR_MESSAGE, sprintf(McpDeleteGuard::IN_USE_MESSAGE, self::FIRST_DEPENDENCY)],
            ],
        ];
    }

    public function testAssertDeletableRejectsWithEveryRefusal(): void
    {
        $createdAt = AnzuApp::date()->modify(self::TOO_OLD_OFFSET);
        $entity = $this->createEntity(createdAt: $createdAt, createdById: self::OTHER_USER_ID);

        $this->expectException(McpToolCallRejectedException::class);
        $this->expectExceptionMessage(McpDeleteRejectedException::createRefused(
            resourceName: self::RESOURCE_NAME,
            id: self::ENTITY_ID,
            refusals: [
                $this->createTooOldRefusal($createdAt),
                McpDeleteGuard::NOT_CREATOR_MESSAGE,
                sprintf(McpDeleteGuard::IN_USE_MESSAGE, self::FIRST_DEPENDENCY),
            ],
        )->getMessage());

        $this->createGuard([self::FIRST_DEPENDENCY])->assertDeletable($entity);
    }

    public function testAssertDeletablePassesADeletableEntity(): void
    {
        $entity = $this->createEntity(createdAt: AnzuApp::date()->modify(self::FRESH_OFFSET), createdById: self::CURRENT_USER_ID);

        $this->createGuard()
            ->assertDeletable($entity);

        $this->addToAssertionCount(1);
    }

    public function testEntityWithoutDependencyProviderFails(): void
    {
        $entity = $this->createEntity(createdAt: AnzuApp::date()->modify(self::FRESH_OFFSET), createdById: self::CURRENT_USER_ID);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage(sprintf(McpDeleteGuard::MISSING_DEPENDENCY_PROVIDER_MESSAGE, DeletableExample::class));

        $this->createGuard(withProvider: false)
            ->resolveRefusals($entity);
    }

    private function createTooOldRefusal(DateTimeImmutable $createdAt): string
    {
        return sprintf(
            McpDeleteGuard::TOO_OLD_MESSAGE,
            $createdAt->format(DateTimeInterface::ATOM),
            McpDeleteGuard::MAX_AGE_DAYS,
            McpDeleteGuard::MAX_AGE_DAYS,
        );
    }

    private function createEntity(DateTimeImmutable $createdAt, ?int $createdById): DeletableExample
    {
        $entity = new DeletableExample()
            ->setId(self::ENTITY_ID)
            ->setCreatedAt($createdAt);
        if (is_int($createdById)) {
            $entity->setCreatedBy(new User()->setId($createdById));
        }

        return $entity;
    }

    /**
     * @param list<string> $dependencies
     */
    private function createGuard(array $dependencies = [], bool $withProvider = true): McpDeleteGuard
    {
        $currentUserProvider = $this->createMock(CurrentAnzuUserProvider::class);
        $currentUserProvider->method('getCurrentUser')
            ->willReturn(new User()->setId(self::CURRENT_USER_ID));
        $factories = $withProvider
            ? [DeletableExample::class => static fn (): DeletableExampleDependencyProvider => new DeletableExampleDependencyProvider($dependencies)]
            : [];

        return new McpDeleteGuard(
            currentUserProvider: $currentUserProvider,
            dependencyProviders: new ServiceLocator($factories),
        );
    }
}
