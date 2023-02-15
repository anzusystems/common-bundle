<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Controller;

use AnzuSystems\CommonBundle\ApiFilter\ApiParams;
use AnzuSystems\CommonBundle\Domain\Job\JobFacade;
use AnzuSystems\CommonBundle\Entity\Job;
use AnzuSystems\CommonBundle\Entity\JobUserDataDelete;
use AnzuSystems\CommonBundle\Exception\ValidationException;
use AnzuSystems\CommonBundle\Model\OpenApi\Parameter\OAParameterPath;
use AnzuSystems\CommonBundle\Model\OpenApi\Request\OARequest;
use AnzuSystems\CommonBundle\Model\OpenApi\Response\OAResponse;
use AnzuSystems\CommonBundle\Model\OpenApi\Response\OAResponseCreated;
use AnzuSystems\CommonBundle\Model\OpenApi\Response\OAResponseDeleted;
use AnzuSystems\CommonBundle\Model\OpenApi\Response\OAResponseInfiniteList;
use AnzuSystems\CommonBundle\Model\OpenApi\Response\OAResponseValidation;
use AnzuSystems\CommonBundle\Repository\JobRepository;
use AnzuSystems\Contracts\AnzuApp;
use AnzuSystems\Contracts\Exception\AppReadOnlyModeException;
use AnzuSystems\SerializerBundle\Attributes\SerializeParam;
use Doctrine\ORM\Exception\ORMException;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

#[OA\Tag('Job')]
abstract class AbstractJobController extends AbstractAnzuApiController
{
    public function __construct(
        protected readonly JobFacade $jobFacade,
        protected readonly JobRepository $jobRepo,
    ) {
    }

    /**
     * Get one item.
     */
    #[Route('/{job}', 'get_one', ['job' => '\d+'], methods: [Request::METHOD_GET])]
    #[OAParameterPath('job'), OAResponse(Job::class)]
    public function getOne(Job $job): JsonResponse
    {
        if ($this->getViewAcl()) {
            $this->denyAccessUnlessGranted($this->getViewAcl());
        }

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
        if ($this->getViewAcl()) {
            $this->denyAccessUnlessGranted($this->getViewAcl());
        }

        return $this->okResponse(
            $this->jobRepo->findByApiParamsWithInfiniteListing($apiParams),
        );
    }

    /**
     * Create JobUserDataDelete item.
     *
     * @throws ValidationException
     * @throws AppReadOnlyModeException
     */
    #[Route('/user-data-delete', 'create_job_user_data_delete', methods: [Request::METHOD_POST])]
    #[OARequest(JobUserDataDelete::class), OAResponseCreated(JobUserDataDelete::class), OAResponseValidation]
    public function createJobUserDataDelete(#[SerializeParam] JobUserDataDelete $job): JsonResponse
    {
        AnzuApp::throwOnReadOnlyMode();
        if ($this->getCreateAcl()) {
            $this->denyAccessUnlessGranted($this->getCreateAcl());
        }

        return $this->createdResponse(
            $this->jobFacade->create($job)
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
        if ($this->getDeleteAcl()) {
            $this->denyAccessUnlessGranted($this->getDeleteAcl());
        }

        $this->jobFacade->delete($job);

        return $this->noContentResponse();
    }

    abstract protected function getViewAcl(): string;

    abstract protected function getCreateAcl(): string;

    abstract protected function getDeleteAcl(): string;
}
