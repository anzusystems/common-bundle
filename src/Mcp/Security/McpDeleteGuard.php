<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Mcp\Security;

use AnzuSystems\CommonBundle\Domain\User\CurrentAnzuUserProvider;
use AnzuSystems\CommonBundle\Mcp\Exception\McpToolCallRejectedException;
use AnzuSystems\Contracts\AnzuApp;
use AnzuSystems\Contracts\Entity\Interfaces\BaseIdentifiableInterface;
use AnzuSystems\Contracts\Entity\Interfaces\TimeTrackingInterface;
use AnzuSystems\Contracts\Entity\Interfaces\UserTrackingInterface;
use DateTimeInterface;
use LogicException;
use Symfony\Contracts\Service\ServiceProviderInterface;

/**
 * Does not check the delete permission: the host tool does, on the stored entity.
 */
final readonly class McpDeleteGuard
{
    public const int MAX_AGE_DAYS = 7;
    public const string REFUSED_MESSAGE = 'The %s "%s" cannot be deleted through MCP: %s Tell the user and do not retry.';
    public const string TOO_OLD_MESSAGE = 'It was created on %s, more than %d days ago; MCP deletes only what was created in the last %d days.';
    public const string NOT_CREATOR_MESSAGE = 'It was created by another user; MCP deletes only what the current user created.';
    public const string IN_USE_MESSAGE = 'It is in use: %s.';
    public const string MISSING_DEPENDENCY_PROVIDER_MESSAGE = 'No MCP delete dependency provider for "%s".';

    private const string REFUSAL_SEPARATOR = ' ';
    private const string DEPENDENCY_SEPARATOR = '; ';

    /**
     * @param ServiceProviderInterface<McpDeleteDependencyProviderInterface> $dependencyProviders keyed by the entity class
     */
    public function __construct(
        private CurrentAnzuUserProvider $currentUserProvider,
        private ServiceProviderInterface $dependencyProviders,
    ) {
    }

    /**
     * @return list<string>
     */
    public function resolveRefusals(BaseIdentifiableInterface&TimeTrackingInterface&UserTrackingInterface $entity): array
    {
        $dependencyProvider = $this->resolveDependencyProvider($entity);
        $refusals = [];
        $createdAt = $entity->getCreatedAt();
        if ($createdAt < AnzuApp::date()->modify(sprintf('-%d days', self::MAX_AGE_DAYS))) {
            $refusals[] = sprintf(self::TOO_OLD_MESSAGE, $createdAt->format(DateTimeInterface::ATOM), self::MAX_AGE_DAYS, self::MAX_AGE_DAYS);
        }
        if ($entity->getCreatedBy()->isNot($this->currentUserProvider->getCurrentUser())) {
            $refusals[] = self::NOT_CREATOR_MESSAGE;
        }
        $dependencies = $dependencyProvider->provideDependencies($entity);
        if (false === empty($dependencies)) {
            $refusals[] = sprintf(self::IN_USE_MESSAGE, implode(self::DEPENDENCY_SEPARATOR, $dependencies));
        }

        return $refusals;
    }

    public function assertDeletable(BaseIdentifiableInterface&TimeTrackingInterface&UserTrackingInterface $entity): void
    {
        $refusals = $this->resolveRefusals($entity);
        if (empty($refusals)) {
            return;
        }

        throw new McpToolCallRejectedException(sprintf(
            self::REFUSED_MESSAGE,
            $entity::getResourceName(),
            $entity->getId(),
            implode(self::REFUSAL_SEPARATOR, $refusals),
        ));
    }

    private function resolveDependencyProvider(object $entity): McpDeleteDependencyProviderInterface
    {
        if (false === $this->dependencyProviders->has($entity::class)) {
            throw new LogicException(sprintf(self::MISSING_DEPENDENCY_PROVIDER_MESSAGE, $entity::class));
        }

        return $this->dependencyProviders->get($entity::class);
    }
}
