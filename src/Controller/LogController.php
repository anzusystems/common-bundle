<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Controller;

use AnzuSystems\CommonBundle\ApiFilter\ApiParams;
use AnzuSystems\CommonBundle\Document\Log;
use AnzuSystems\CommonBundle\Log\LogFacade;
use AnzuSystems\CommonBundle\Log\Model\LogDto;
use AnzuSystems\CommonBundle\Log\Repository\AppLogRepository;
use AnzuSystems\CommonBundle\Log\Repository\AuditLogRepository;
use AnzuSystems\CommonBundle\Model\OpenApi\Parameter\OAParameterPath;
use AnzuSystems\CommonBundle\Model\OpenApi\Request\OARequest;
use AnzuSystems\CommonBundle\Model\OpenApi\Response\OAResponse;
use AnzuSystems\CommonBundle\Model\OpenApi\Response\OAResponseCreated;
use AnzuSystems\CommonBundle\Model\OpenApi\Response\OAResponseList;
use AnzuSystems\SerializerBundle\Attributes\SerializeParam;
use AnzuSystems\SerializerBundle\Exception\SerializerException;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

#[OA\Tag('Log')]
final class LogController extends AbstractAnzuApiController
{
    public function __construct(
        private readonly ?AuditLogRepository $auditLogRepo,
        private readonly ?AppLogRepository $appLogRepo,
        private readonly LogFacade $logFacade,
    ) {
    }

    /**
     * Get list of audit logs.
     *
     * @throws SerializerException
     */
    #[OAResponseList(Log::class)]
    public function getAuditLogs(ApiParams $apiParams): JsonResponse
    {
        if (null === $this->auditLogRepo) {
            throw new NotFoundHttpException('Not configured to serve audit logs.');
        }

        return $this->okResponse(
            $this->auditLogRepo->findByApiParams($apiParams)
        );
    }

    /**
     * Get list of app logs.
     *
     * @throws SerializerException
     */
    #[OAResponseList(Log::class)]
    public function getAppLogs(ApiParams $apiParams): JsonResponse
    {
        if (null === $this->appLogRepo) {
            throw new NotFoundHttpException('Not configured to serve app logs.');
        }

        return $this->okResponse(
            $this->appLogRepo->findByApiParams($apiParams)
        );
    }

    /**
     * Get one app log.
     */
    #[OAParameterPath('id'), OAResponse(Log::class)]
    public function getOneAppLog(string $id): JsonResponse
    {
        if (null === $this->appLogRepo) {
            throw new NotFoundHttpException('Not configured to serve app logs.');
        }

        $log = $this->appLogRepo->find($id);
        if ($log instanceof Log) {
            return $this->okResponse($log);
        }

        throw new NotFoundHttpException(sprintf('App log with id "%s" was not found.', $id));
    }

    /**
     * Get one audit log.
     *
     * @throws SerializerException
     */
    #[OAParameterPath('id'), OAResponse(Log::class)]
    public function getOneAuditLog(string $id): JsonResponse
    {
        if (null === $this->auditLogRepo) {
            throw new NotFoundHttpException('Not configured to serve audit logs.');
        }

        $log = $this->auditLogRepo->find($id);
        if ($log instanceof Log) {
            return $this->okResponse($log);
        }

        throw new NotFoundHttpException(sprintf('Audit log with id "%s" was not found.', $id));
    }

    /**
     * Create custom app log (i.e. Admin FE error log).
     *
     * @throws SerializerException
     */
    #[OARequest(LogDto::class), OAResponseCreated(Log::class)]
    public function create(Request $request, #[SerializeParam] LogDto $logDto): JsonResponse
    {
        return $this->createdResponse(
            $this->logFacade->create($request, $logDto)
        );
    }
}
