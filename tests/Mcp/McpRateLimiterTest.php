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
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\User\InMemoryUser;

final class McpRateLimiterTest extends TestCase
{
    private const int LIMIT = 1;
    private const int LIMIT_OVERRIDE = 2;
    private const int INTERVAL_SECONDS = 60;
    private const int USER_ID = 42;
    private const string TOKEN_KEY = 'pat_7';
    private const string FIREWALL_NAME = 'mcp';
    private const string USER_IDENTIFIER = 'mcp-user';
    private const string INTERVAL = '1 minute';

    public function testAnonymousUserIsRejected(): void
    {
        $rateLimiter = $this->createRateLimiter(AnzuApp::getUserIdAnonymous());

        $this->expectException(AccessDeniedHttpException::class);

        $rateLimiter->checkRateLimit();
    }

    public function testTokenWithoutAttributesThrowsWhenDefaultLimitExceeded(): void
    {
        $rateLimiter = $this->createRateLimiter(token: $this->createToken([]));
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

    public function testTokenAttributesOverrideLimitAndKey(): void
    {
        $storage = new InMemoryStorage();
        $rateLimiter = $this->createRateLimiter(token: $this->createToken([
            McpRateLimiter::TOKEN_ATTRIBUTE_KEY => self::TOKEN_KEY,
            McpRateLimiter::TOKEN_ATTRIBUTE_LIMIT => self::LIMIT_OVERRIDE,
        ]), storage: $storage);
        $rateLimiter->checkRateLimit();
        $rateLimiter->checkRateLimit();

        try {
            $rateLimiter->checkRateLimit();
            self::fail('Expected ' . TooManyRequestsHttpException::class);
        } catch (TooManyRequestsHttpException $exception) {
            self::assertSame((string) self::LIMIT_OVERRIDE, $exception->getHeaders()['X-RateLimit-Limit']);
        }

        $this->createRateLimiter(token: $this->createToken([]), storage: $storage)
            ->checkRateLimit();
    }

    private function createRateLimiter(
        int $userId = self::USER_ID,
        ?TokenInterface $token = null,
        ?InMemoryStorage $storage = null,
    ): McpRateLimiter {
        $user = $this->createConfiguredMock(AnzuUser::class, ['getId' => $userId]);
        $currentUserProvider = $this->createMock(CurrentAnzuUserProvider::class);
        $currentUserProvider->method('getCurrentUser')
            ->willReturn($user);
        $security = $this->createMock(Security::class);
        $security->method('getToken')
            ->willReturn($token);

        return new McpRateLimiter(
            self::LIMIT,
            self::INTERVAL,
            $storage ?? new InMemoryStorage(),
            $currentUserProvider,
            $security,
        );
    }

    /**
     * @param array<string, mixed> $attributes
     */
    private function createToken(array $attributes): TokenInterface
    {
        $token = new UsernamePasswordToken(new InMemoryUser(self::USER_IDENTIFIER, null), self::FIREWALL_NAME);
        $token->setAttributes($attributes);

        return $token;
    }
}
