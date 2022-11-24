<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Controller;

use AnzuSystems\CommonBundle\Model\OpenApi\Response\OAResponse;
use AnzuSystems\Contracts\AnzuApp;
use AnzuSystems\Contracts\Entity\AnzuUser;
use OpenApi\Attributes as OA;
use RuntimeException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

#[OA\Tag('Debug')]
final class DebugController extends AbstractAnzuApiController
{
    /**
     * Get lead time.
     */
    #[OAResponse(description: 'Lead time')]
    public function getLeadTime(): JsonResponse
    {
        return new JsonResponse([
            'lead_time' => number_format((microtime(true) - ($_SERVER['REQUEST_TIME_FLOAT'] ?? 0.0)), 3) . 's',
        ]);
    }

    /**
     * Get opcache status.
     */
    #[OAResponse(description: 'opcache status')]
    public function opcacheStatus(): JsonResponse
    {
        $this->denyAccessUnlessGranted(AnzuUser::ROLE_ADMIN);

        return new JsonResponse([
            'opcache' => opcache_get_status(),
        ]);
    }

    /**
     * Get IP info.
     */
    #[OAResponse(description: 'IP check')]
    public function ipCheck(Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted(AnzuUser::ROLE_ADMIN);

        return new JsonResponse([
            'App' => [
                'getClientIp' => AnzuApp::getClientIp(),
            ],
            'Request' => [
                'getClientIp' => $request->getClientIp(),
                'x-forwarded-for' => $request->headers->get('x-forwarded-for'),
                'isFromTrustedProxy' => $request->isFromTrustedProxy(),
            ],
            'SERVER' => [
                'REMOTE_ADDR' => $_SERVER['REMOTE_ADDR'] ?? null,
                'HTTP_X_FORWARDED_FOR' => $_SERVER['HTTP_X_FORWARDED_FOR'] ?? null,
                'HTTP_X_FORWARDED_PORT' => $_SERVER['HTTP_X_FORWARDED_PORT'] ?? null,
                'HTTP_X_FORWARDED_PROTO' => $_SERVER['HTTP_X_FORWARDED_PROTO'] ?? null,
                'HTTP_X_REAL_IP' => $_SERVER['HTTP_X_REAL_IP'] ?? null,
            ],
        ]);
    }

    /**
     * This serves for testing purposes only.
     */
    #[OAResponse(description: 'API error test')]
    public function error(): void
    {
        $this->denyAccessUnlessGranted(AnzuUser::ROLE_ADMIN);

        throw new RuntimeException('test');
    }
}
