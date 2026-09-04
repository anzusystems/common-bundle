<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Tests\Mcp;

use AnzuSystems\CommonBundle\Domain\User\CurrentAnzuUserProvider;
use AnzuSystems\CommonBundle\Mcp\McpRateLimiter;
use AnzuSystems\Contracts\AnzuApp;
use AnzuSystems\Contracts\Entity\AnzuUser;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\RateLimiter\Storage\InMemoryStorage;

final class McpRateLimiterTest extends TestCase
{
    private const int LIMIT = 1;
    private const int BATCH_SIZE = 100;
    private const int ELEVATED_LIMIT = 2;
    private const int INTERVAL_SECONDS = 60;
    private const int USER_ID = 42;
    private const string ELEVATED_ROLE = 'ROLE_SYS_MCP';
    private const string INTERVAL = '1 minute';

    public function testAnonymousUserIsRejected(): void
    {
        $rateLimiter = $this->createRateLimiter(AnzuApp::getUserIdAnonymous());

        $this->expectException(AccessDeniedHttpException::class);

        $rateLimiter->checkRateLimit();
    }

    public function testDefaultLimitExceededThrowsTooManyRequests(): void
    {
        $rateLimiter = $this->createRateLimiter();
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

    public function testElevatedRoleUsesElevatedLimit(): void
    {
        $rateLimiter = $this->createRateLimiter(elevated: true);
        $rateLimiter->checkRateLimit();
        $rateLimiter->checkRateLimit();

        try {
            $rateLimiter->checkRateLimit();
            self::fail('Expected ' . TooManyRequestsHttpException::class);
        } catch (TooManyRequestsHttpException $exception) {
            self::assertSame((string) self::ELEVATED_LIMIT, $exception->getHeaders()['X-RateLimit-Limit']);
        }
    }

    public function testBatchConsumesOneTokenPerMessage(): void
    {
        $rateLimiter = $this->createRateLimiter(elevated: true);

        $rateLimiter->checkRateLimit(self::ELEVATED_LIMIT);

        $this->expectException(TooManyRequestsHttpException::class);

        $rateLimiter->checkRateLimit();
    }

    public function testBatchLargerThanLimitIsClampedInsteadOfFailing(): void
    {
        $rateLimiter = $this->createRateLimiter();

        $rateLimiter->checkRateLimit(self::BATCH_SIZE);

        $this->expectException(TooManyRequestsHttpException::class);

        $rateLimiter->checkRateLimit();
    }

    private function createRateLimiter(int $userId = self::USER_ID, bool $elevated = false): McpRateLimiter
    {
        $user = $this->createConfiguredMock(AnzuUser::class, ['getId' => $userId]);
        $currentUserProvider = $this->createMock(CurrentAnzuUserProvider::class);
        $currentUserProvider->method('getCurrentUser')
            ->willReturn($user);
        $security = $this->createMock(Security::class);
        $security->method('isGranted')
            ->with(self::ELEVATED_ROLE)
            ->willReturn($elevated);

        return new McpRateLimiter(
            self::LIMIT,
            self::INTERVAL,
            new InMemoryStorage(),
            $currentUserProvider,
            $security,
            self::ELEVATED_ROLE,
            self::ELEVATED_LIMIT,
        );
    }
}
