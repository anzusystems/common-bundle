<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Tests\Data\Controller;

use AnzuSystems\CommonBundle\ApiFilter\ApiParams;
use AnzuSystems\CommonBundle\Controller\AbstractAnzuApiController;
use AnzuSystems\CommonBundle\Request\ParamConverter\ApiFilterParamConverter;
use AnzuSystems\CommonBundle\Request\ParamConverter\EnumParamConverter;
use AnzuSystems\CommonBundle\Request\ParamConverter\ValueObjectParamConverter;
use AnzuSystems\CommonBundle\Tests\Data\Model\DataObject\DummyDto;
use AnzuSystems\CommonBundle\Tests\Data\Model\Enum\DummyEnum;
use AnzuSystems\CommonBundle\Tests\Data\Model\ValueObject\DummyValueObject;
use AnzuSystems\CommonBundle\Tests\Data\Response\Cache\UserCacheSettings;
use AnzuSystems\SerializerBundle\Request\ParamConverter\SerializerParamConverter;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\ParamConverter;
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

    #[Route('/param-converter/value-object/{dummy}', methods: [Request::METHOD_GET])]
    #[ParamConverter('dummy', converter: ValueObjectParamConverter::class)]
    public function valueObjectConverterTest(DummyValueObject $dummy): Response
    {
        return new Response($dummy->toString());
    }

    #[Route('/param-converter/enum/{dummy}', methods: [Request::METHOD_GET])]
    #[ParamConverter('dummy', converter: EnumParamConverter::class)]
    public function enumConverterTest(DummyEnum $dummy): Response
    {
        return new Response($dummy->toString());
    }

    #[Route('/param-converter/serializer', methods: [Request::METHOD_POST])]
    #[ParamConverter('dummy', converter: SerializerParamConverter::class)]
    public function serializerConverterTest(DummyDto $dummy): JsonResponse
    {
        return $this->okResponse($dummy);
    }

    #[Route('/param-converter/api-filter', methods: [Request::METHOD_GET])]
    #[ParamConverter('dummy', converter: ApiFilterParamConverter::class)]
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
