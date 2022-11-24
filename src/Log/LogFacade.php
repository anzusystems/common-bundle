<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Log;

use AnzuSystems\CommonBundle\Document\Log;
use AnzuSystems\CommonBundle\Log\Factory\LogContextFactory;
use AnzuSystems\CommonBundle\Log\Factory\LogFactory;
use AnzuSystems\CommonBundle\Log\Model\LogDto;
use AnzuSystems\SerializerBundle\Exception\SerializerException;
use AnzuSystems\SerializerBundle\Serializer;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;

final class LogFacade
{
    public function __construct(
        private readonly LoggerInterface $appLogger,
        private readonly LogContextFactory $logContextFactory,
        private readonly Serializer $serializer,
    ) {
    }

    /**
     * @throws SerializerException
     */
    public function create(Request $request, LogDto $logDto): Log
    {
        $context = $this->logContextFactory->buildCustomFromRequest($request, $logDto);
        $this->appLogger->{$logDto->getLevel()->logMethodName()}(
            $logDto->getMessage(),
            $this->serializer->toArray($context)
        );

        return LogFactory::buildCustomLog($logDto, $context);
    }
}
