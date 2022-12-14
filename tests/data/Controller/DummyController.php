<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Tests\Data\Controller;

use AnzuSystems\CommonBundle\ApiFilter\ApiParams;
use AnzuSystems\CommonBundle\Controller\AbstractAnzuApiController;
use AnzuSystems\CommonBundle\Tests\Data\Model\DataObject\DummyDto;
use AnzuSystems\CommonBundle\Tests\Data\Model\ValueObject\DummyValueObject;
use AnzuSystems\CommonBundle\Tests\Data\Response\Cache\UserCacheSettings;
use AnzuSystems\SerializerBundle\Attributes\SerializeParam;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

final class DummyController extends AbstractAnzuApiController
{
    #[Route('/audit', methods: [Request::METHOD_POST])]
    public function auditLogTest(): JsonResponse
    {
        return $this->okResponse([]);
    }

    #[Route('/value-resolver/value-object/{dummy}', methods: [Request::METHOD_GET])]
    public function valueObjectConverterTest(DummyValueObject $dummy): Response
    {
        return new Response($dummy->toString());
    }

    #[Route('/value-resolver/serializer', methods: [Request::METHOD_POST])]
    public function serializerConverterTest(#[SerializeParam] DummyDto $dummy): JsonResponse
    {
        return $this->okResponse($dummy);
    }

    #[Route('/value-resolver/api-filter', methods: [Request::METHOD_GET])]
    public function apiFilterConverterTest(ApiParams $dummy): JsonResponse
    {
        return $this->okResponse($dummy);
    }

    #[Route('/cache-test', methods: [Request::METHOD_GET])]
    public function cacheTest(): JsonResponse
    {
        return $this->okResponse($this->getUser(), new UserCacheSettings($this->getUser()));
    }
}
