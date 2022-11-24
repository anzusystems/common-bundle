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
use AnzuSystems\CommonBundle\Request\ParamConverter\ApiFilterParamConverter;
use AnzuSystems\SerializerBundle\Exception\SerializerException;
use AnzuSystems\SerializerBundle\Request\ParamConverter\SerializerParamConverter;
use OpenApi\Attributes as OA;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\ParamConverter;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

#[OA\Tag('Log')]
final class LogController extends AbstractAnzuApiController
{
    public function __construct(
        private readonly AuditLogRepository $auditLogRepo,
        private readonly AppLogRepository $appLogRepo,
        private readonly LogFacade $logFacade,
    ) {
    }

    /**
     * Get list of audit logs.
     */
    #[ParamConverter('apiParams', converter: ApiFilterParamConverter::class)]
    #[OAResponse([Log::class])]
    public function getAuditLogs(ApiParams $apiParams): JsonResponse
    {
        return $this->okResponse(
            $this->auditLogRepo->findByApiParams($apiParams)
        );
    }

    /**
     * Get list of app logs.
     */
    #[ParamConverter('apiParams', converter: ApiFilterParamConverter::class)]
    #[OAResponse([Log::class])]
    public function getAppLogs(ApiParams $apiParams): JsonResponse
    {
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
        $log = $this->appLogRepo->find($id);
        if ($log instanceof Log) {
            return $this->okResponse($log);
        }

        throw new NotFoundHttpException(sprintf('App log with id "%s" was not found.', $id));
    }

    /**
     * Get one audit log.
     */
    #[OAParameterPath('id'), OAResponse(Log::class)]
    public function getOneAuditLog(string $id): JsonResponse
    {
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
    #[ParamConverter('logDto', converter: SerializerParamConverter::class)]
    #[OARequest(LogDto::class), OAResponseCreated(Log::class)]
    public function create(Request $request, LogDto $logDto): JsonResponse
    {
        return $this->createdResponse(
            $this->logFacade->create($request, $logDto)
        );
    }
}
