<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Tests\Data\Controller;

use AnzuSystems\CommonBundle\ApiFilter\ApiParams;
use AnzuSystems\CommonBundle\Controller\AbstractAnzuApiController;
use AnzuSystems\CommonBundle\Log\Helper\AuditLogResourceHelper;
use AnzuSystems\CommonBundle\Model\Attributes\ArrayStringParam;
use AnzuSystems\CommonBundle\Tests\Data\Entity\Example;
use AnzuSystems\CommonBundle\Tests\Data\Model\DataObject\DummyDto;
use AnzuSystems\CommonBundle\Tests\Data\Model\DataObject\SerializerTestDto;
use AnzuSystems\CommonBundle\Tests\Data\Model\ValueObject\DummyValueObject;
use AnzuSystems\CommonBundle\Tests\Data\Response\Cache\UserCacheSettings;
use AnzuSystems\SerializerBundle\Attributes\SerializeParam;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/dummy')]
final class DummyController extends AbstractAnzuApiController
{
    #[Route('/audit', methods: [Request::METHOD_POST])]
    public function auditLogTest(Request $request): JsonResponse
    {
        AuditLogResourceHelper::setResource(request: $request, resourceName: 'test', resourceId: 123);

        return $this->okResponse([]);
    }

    #[Route('/value-resolver/value-object/{dummy}', methods: [Request::METHOD_GET])]
    public function valueObjectValueResolverTest(DummyValueObject $dummy): Response
    {
        return new Response($dummy->toString());
    }

    #[Route('/value-resolver/serializer', methods: [Request::METHOD_POST])]
    public function serializerValueResolverTest(#[SerializeParam] DummyDto $dummy): JsonResponse
    {
        return $this->okResponse($dummy);
    }

    #[Route('/serializer/test', methods: [Request::METHOD_POST])]
    public function serializerTest(#[SerializeParam] SerializerTestDto $test): JsonResponse
    {
        return $this->okResponse($test);
    }

    #[Route('/value-resolver/api-filter', methods: [Request::METHOD_GET])]
    public function apiFilterValueResolverTest(ApiParams $dummy): JsonResponse
    {
        return $this->okResponse($dummy);
    }

    #[Route('/value-resolver/array-string/{dummy}', methods: [Request::METHOD_GET])]
    public function apiFilterConverterTest(
        #[ArrayStringParam(itemsLimit: 3, itemNormalizer: 'intval', separator: ',')] array $dummy,
    ): JsonResponse {
        return $this->okResponse($dummy);
    }

    #[Route('/cache-test', methods: [Request::METHOD_GET])]
    public function cacheTest(): JsonResponse
    {
        return $this->okResponse($this->getUser(), new UserCacheSettings($this->getUser()));
    }

    #[Route('/doctrine/type/enum/{example}', methods: [Request::METHOD_GET])]
    public function doctrineTypeEnum(Example $example): Response
    {
        return $this->okResponse($example);
    }
}
