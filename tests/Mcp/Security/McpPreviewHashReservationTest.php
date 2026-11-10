<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Tests\Mcp\Security;

use AnzuSystems\CommonBundle\Domain\User\CurrentAnzuUserProvider;
use AnzuSystems\CommonBundle\Mcp\Exception\McpWriteRejectedException;
use AnzuSystems\CommonBundle\Mcp\Security\McpPreviewHashReservation;
use AnzuSystems\CommonBundle\Tests\Data\Mcp\Model\Request\ExampleWriteRequest;
use AnzuSystems\Contracts\Entity\AnzuUser;
use PHPUnit\Framework\TestCase;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Clock\MockClock;

final class McpPreviewHashReservationTest extends TestCase
{
    private const string TOOL = 'example_write';
    private const int USER_ID = 42;
    private const int OTHER_USER_ID = 43;
    private const string PREVIEW_HASH = 'd04da74f4926e319f2beddde58330604183699c996a026576e10c10cc8a1e375';
    private const string OTHER_PREVIEW_HASH = 'bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb';

    public function testSecondReservationOfAHashIsRefused(): void
    {
        $reservation = $this->createReservation(new ArrayAdapter());
        $reservation->reserve(self::TOOL, $this->createInput(self::PREVIEW_HASH));

        $this->expectExceptionObject(McpWriteRejectedException::createPreviewHashUsed(self::TOOL, McpPreviewHashReservation::LIFETIME_SECONDS));

        $reservation->reserve(self::TOOL, $this->createInput(self::PREVIEW_HASH));
    }

    public function testOtherHashAndOtherUserAreNotRefused(): void
    {
        $pool = new ArrayAdapter();
        $this->createReservation($pool)
            ->reserve(self::TOOL, $this->createInput(self::PREVIEW_HASH));

        $this->createReservation($pool)
            ->reserve(self::TOOL, $this->createInput(self::OTHER_PREVIEW_HASH));
        $this->createReservation(pool: $pool, userId: self::OTHER_USER_ID)->reserve(self::TOOL, $this->createInput(self::PREVIEW_HASH));

        self::assertCount(3, $pool->getValues());
    }

    public function testReleasedHashCanBeReservedAgain(): void
    {
        $pool = new ArrayAdapter();
        $reservation = $this->createReservation($pool);
        $reservation->reserve(self::TOOL, $this->createInput(self::PREVIEW_HASH));

        $reservation->release($this->createInput(self::PREVIEW_HASH));
        $reservation->reserve(self::TOOL, $this->createInput(self::PREVIEW_HASH));

        self::assertCount(1, $pool->getValues());
    }

    public function testReservationExpiresAfterItsLifetime(): void
    {
        $clock = new MockClock();
        $pool = new ArrayAdapter(clock: $clock);
        $reservation = $this->createReservation($pool);
        $reservation->reserve(self::TOOL, $this->createInput(self::PREVIEW_HASH));

        $clock->sleep(McpPreviewHashReservation::LIFETIME_SECONDS + 1);
        $reservation->reserve(self::TOOL, $this->createInput(self::PREVIEW_HASH));

        self::assertCount(1, $pool->getValues());
    }

    private function createInput(string $previewHash): ExampleWriteRequest
    {
        $input = new ExampleWriteRequest();
        $input->previewHash = $previewHash;

        return $input;
    }

    private function createReservation(CacheItemPoolInterface $pool, int $userId = self::USER_ID): McpPreviewHashReservation
    {
        $currentUserProvider = $this->createStub(CurrentAnzuUserProvider::class);
        $currentUserProvider->method('getCurrentUser')
            ->willReturn($this->createConfiguredMock(AnzuUser::class, ['getId' => $userId]));

        return new McpPreviewHashReservation($pool, $currentUserProvider);
    }
}
