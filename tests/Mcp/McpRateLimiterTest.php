<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Tests\Mcp;

use AnzuSystems\CommonBundle\Domain\User\CurrentAnzuUserProvider;
use AnzuSystems\CommonBundle\Mcp\McpRateLimiter;
use AnzuSystems\Contracts\AnzuApp;
use AnzuSystems\Contracts\Entity\AnzuUser;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\RateLimiter\Storage\InMemoryStorage;

final class McpRateLimiterTest extends TestCase
{
    private const int LIMIT = 1;
    private const int INTERVAL_SECONDS = 60;
    private const int USER_ID = 42;

    public function testAnonymousUserIsRejected(): void
    {
        $rateLimiter = new McpRateLimiter(
            $this->createLimiterFactory(),
            $this->createCurrentUserProvider(AnzuApp::getUserIdAnonymous()),
        );

        $this->expectException(AccessDeniedHttpException::class);

        $rateLimiter->checkRateLimit();
    }

    public function testThrowsWhenLimitExceeded(): void
    {
        $rateLimiter = new McpRateLimiter($this->createLimiterFactory(), $this->createCurrentUserProvider());
        $rateLimiter->checkRateLimit();

        try {
            $rateLimiter->checkRateLimit();
            self::fail('Expected ' . TooManyRequestsHttpException::class);
        } catch (TooManyRequestsHttpException $exception) {
            self::assertSame(Response::HTTP_TOO_MANY_REQUESTS, $exception->getStatusCode());
            $headers = $exception->getHeaders();
            self::assertSame((string) self::LIMIT, $headers['X-RateLimit-Limit']);
            self::assertSame('0', $headers['X-RateLimit-Remaining']);
            self::assertArrayHasKey('X-RateLimit-Reset', $headers);
            self::assertArrayHasKey('Retry-After', $headers);
            self::assertGreaterThanOrEqual(0, (int) $headers['Retry-After']);
            self::assertLessThanOrEqual(self::INTERVAL_SECONDS, (int) $headers['Retry-After']);
        }
    }

    private function createLimiterFactory(): RateLimiterFactory
    {
        return new RateLimiterFactory(
            [
                'id' => 'mcp_test',
                'policy' => 'sliding_window',
                'limit' => self::LIMIT,
                'interval' => '1 minute',
            ],
            new InMemoryStorage(),
        );
    }

    private function createCurrentUserProvider(int $userId = self::USER_ID): CurrentAnzuUserProvider
    {
        $user = $this->createConfiguredMock(AnzuUser::class, ['getId' => $userId]);
        $currentUserProvider = $this->createMock(CurrentAnzuUserProvider::class);
        $currentUserProvider->method('getCurrentUser')
            ->willReturn($user);

        return $currentUserProvider;
    }
}
