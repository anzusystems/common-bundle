<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Controller;

use AnzuSystems\CommonBundle\Traits\ResourceLockerAwareTrait;
use AnzuSystems\CommonBundle\Traits\SerializerAwareTrait;
use AnzuSystems\Contracts\Entity\AnzuUser;
use AnzuSystems\Contracts\Response\Cache\CacheSettingsInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;

/**
 * @method AnzuUser getUser()
 */
#[AsController]
abstract class AbstractAnzuApiController extends AbstractController
{
    use SerializerAwareTrait;
    use ResourceLockerAwareTrait;

    public function isJsonRequest(Request $request): bool
    {
        return str_contains((string) $request->headers->get('Content-Type', ''), 'application/json');
    }

    /**
     * Get one item (existing or updated) or list of items.
     */
    protected function okResponse(
        array|object $data,
        ?CacheSettingsInterface $cacheSettings = null
    ): JsonResponse {
        $response = $this->getResponse($data);
        if ($cacheSettings instanceof CacheSettingsInterface && $this->getParameter('app_cache_proxy_enabled')) {
            $cacheSettings->setCache($response);
        }

        return $response;
    }

    /**
     * Get newly created entity.
     */
    protected function createdResponse(array | object $data): JsonResponse
    {
        return $this->getResponse($data, JsonResponse::HTTP_CREATED);
    }

    /**
     * Get no content after deletion.
     */
    protected function noContentResponse(): JsonResponse
    {
        return new JsonResponse(null, JsonResponse::HTTP_NO_CONTENT);
    }

    protected function getResponse(
        array | object $data,
        int $statusCode = JsonResponse::HTTP_OK,
    ): JsonResponse {
        return new JsonResponse(
            $this->serializer->serialize($data),
            $statusCode,
            [],
            true
        );
    }

    protected function lockApi(bool $blocking = false): void
    {
        $this->resourceLocker->lock(
            (string) $this->container->get('request_stack')->getCurrentRequest()?->get('_route'),
            $blocking
        );
    }
}
