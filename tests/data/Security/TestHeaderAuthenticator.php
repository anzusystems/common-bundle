<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Tests\Data\Security;

use AnzuSystems\CommonBundle\Tests\Data\Entity\User;
use AnzuSystems\CommonBundle\Tests\Data\Repository\UserRepository;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;

/**
 * Authenticates every request by its own header, the way host applications authenticate by a token cookie or header:
 * the user and its roles are loaded from the database, and an unknown user is rejected with a 401.
 */
final class TestHeaderAuthenticator extends AbstractAuthenticator
{
    public const string USER_ID_HEADER = 'X-Test-User-Id';

    public function __construct(
        private readonly UserRepository $userRepository,
    ) {
    }

    public function supports(Request $request): ?bool
    {
        return '' !== (string) $request->headers->get(self::USER_ID_HEADER);
    }

    public function authenticate(Request $request): Passport
    {
        $userId = (int) $request->headers->get(self::USER_ID_HEADER);

        return new SelfValidatingPassport(
            new UserBadge(
                (string) $userId,
                fn (): ?User => $this->userRepository->find($userId),
            )
        );
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        return null;
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        return new JsonResponse(['error' => 'unauthenticated'], Response::HTTP_UNAUTHORIZED);
    }
}
