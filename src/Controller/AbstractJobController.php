<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Controller;

use AnzuSystems\CommonBundle\ApiFilter\ApiParams;
use AnzuSystems\CommonBundle\Domain\Job\JobFacade;
use AnzuSystems\CommonBundle\Entity\Job;
use AnzuSystems\CommonBundle\Model\OpenApi\Parameter\OAParameterPath;
use AnzuSystems\CommonBundle\Model\OpenApi\Response\OAResponse;
use AnzuSystems\CommonBundle\Model\OpenApi\Response\OAResponseDeleted;
use AnzuSystems\CommonBundle\Model\OpenApi\Response\OAResponseInfiniteList;
use AnzuSystems\CommonBundle\Repository\JobRepository;
use AnzuSystems\Contracts\AnzuApp;
use AnzuSystems\Contracts\Exception\AppReadOnlyModeException;
use Doctrine\ORM\Exception\ORMException;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

#[OA\Tag('Job')]
abstract class AbstractJobController extends AbstractAnzuApiController
{
    public function __construct(
        private readonly JobFacade $jobFacade,
        private readonly JobRepository $jobRepo,
    ) {
    }

    /**
     * Get one item.
     */
    #[Route('/{job}', 'get_one', ['job' => '\d+'], methods: [Request::METHOD_GET])]
    #[OAParameterPath('job'), OAResponse(Job::class)]
    public function getOne(Job $job): JsonResponse
    {
        return $this->okResponse($job);
    }

    /**
     * Get list of items.
     *
     * @throws ORMException
     */
    #[Route('', 'get_list', methods: [Request::METHOD_GET])]
    #[OAResponseInfiniteList(Job::class)]
    public function getList(ApiParams $apiParams): JsonResponse
    {
        return $this->okResponse(
            $this->jobRepo->findByApiParamsWithInfiniteListing($apiParams),
        );
    }

    /**
     * Delete item.
     *
     * @throws AppReadOnlyModeException
     */
    #[Route('/{job}', 'delete', requirements: ['job' => '\d+'], methods: [Request::METHOD_DELETE])]
    #[OAParameterPath('job'), OAResponseDeleted]
    public function delete(Job $job): JsonResponse
    {
        AnzuApp::throwOnReadOnlyMode();
        $this->denyAccessUnlessGranted($this->getDeleteAcl());

        $this->jobFacade->delete($job);

        return $this->noContentResponse();
    }

    abstract protected function getDeleteAcl(): string;
}
