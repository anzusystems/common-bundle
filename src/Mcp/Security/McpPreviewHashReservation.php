<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Mcp\Security;

use AnzuSystems\CommonBundle\Domain\User\CurrentAnzuUserProvider;
use AnzuSystems\CommonBundle\Mcp\Exception\McpWriteRejectedException;
use AnzuSystems\CommonBundle\Mcp\Model\McpPreviewHashAwareInterface;
use Psr\Cache\CacheItemPoolInterface;

/**
 * The hash of a preview without a current state covers only the tool and the arguments, so no write invalidates it: a
 * client that retries after a timeout would create the record twice. Get and save are not atomic, two calls at the same
 * moment can both pass.
 */
final readonly class McpPreviewHashReservation
{
    public const int LIFETIME_SECONDS = 300;

    private const string KEY_FORMAT = 'mcp_preview_hash_%d_%s';

    public function __construct(
        private CacheItemPoolInterface $cachePool,
        private CurrentAnzuUserProvider $currentUserProvider,
    ) {
    }

    public function reserve(string $toolName, McpPreviewHashAwareInterface $input): void
    {
        $item = $this->cachePool->getItem($this->createKey($input));
        if ($item->isHit()) {
            throw McpWriteRejectedException::createPreviewHashUsed($toolName, self::LIFETIME_SECONDS);
        }
        $item->set(true);
        $item->expiresAfter(self::LIFETIME_SECONDS);
        $this->cachePool->save($item);
    }

    public function release(McpPreviewHashAwareInterface $input): void
    {
        $this->cachePool->deleteItem($this->createKey($input));
    }

    private function createKey(McpPreviewHashAwareInterface $input): string
    {
        return sprintf(self::KEY_FORMAT, $this->currentUserProvider->getCurrentUser()->getId(), $input->previewHash);
    }
}
