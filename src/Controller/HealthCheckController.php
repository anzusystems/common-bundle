<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Controller;

use AnzuSystems\CommonBundle\HealthCheck\HealthChecker;
use AnzuSystems\CommonBundle\Model\OpenApi\Response\OAResponse;
use AnzuSystems\SerializerBundle\Exception\SerializerException;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;

#[OA\Tag('HealthCheck')]
final class HealthCheckController extends AbstractAnzuApiController
{
    public function __construct(
        private readonly HealthChecker $healthChecker,
    ) {
    }

    /**
     * Health check.
     *
     * @throws SerializerException
     */
    #[OAResponse(description: 'Health check')]
    public function healthCheck(): JsonResponse
    {
        return $this->okResponse(
            $this->healthChecker->check()
        );
    }
}
