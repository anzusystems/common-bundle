<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Tests\Controller;

use AnzuSystems\CommonBundle\ApiFilter\ApiResponseList;
use AnzuSystems\CommonBundle\Document\Log;
use AnzuSystems\CommonBundle\Log\Factory\LogContextFactory;
use AnzuSystems\CommonBundle\Log\Model\LogDto;
use AnzuSystems\Contracts\Model\Enum\LogLevel;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class LogControllerTest extends AbstractControllerTest
{
    public function testJournalLogs(): void
    {
        // create journal log record
        $logContextFactory = self::getContainer()->get(LogContextFactory::class);
        self::getContainer()->get('monolog.logger.journal')->error('Foo bar baz', $logContextFactory->buildFromRequestToArray(new Request()));

        /** @var ApiResponseList<Log> $response */
        $response = $this->getList(uri: '/log/journal', deserializationClass: Log::class);
        self::assertResponseIsSuccessful();
        self::assertNotEmpty($response->getData());
        self::assertContainsOnlyInstancesOf(Log::class, $response->getData());

        $log = $response->getData()[0];
        $response = $this->get(uri: '/log/journal/' . $log->getId(), deserializationClass: Log::class);
        self::assertResponseIsSuccessful();
        self::assertSame($response->getId(), $log->getId());
    }

    public function testAuditLogs(): void
    {
        // create audit log by making post request
        $this->post(uri: '/dummy/audit');

        /** @var ApiResponseList<Log> $response */
        $response = $this->getList(uri: '/log/audit?order[id]=desc', deserializationClass: Log::class);
        self::assertResponseIsSuccessful();
        self::assertNotEmpty($response->getData());
        self::assertContainsOnlyInstancesOf(Log::class, $response->getData());

        $log = $response->getData()[0];
        $response = $this->get(uri: '/log/audit/' . $log->getId(), deserializationClass: Log::class);
        self::assertResponseIsSuccessful();
        self::assertSame($response->getId(), $log->getId());
        self::assertSame('test', $log->getContext()->getResourceName());
        self::assertSame(['123'], $log->getContext()->getResourceIds());
    }

    public function testCustomLog(): void
    {
        $logDto = (new LogDto())
            ->setMessage('Custom error message.')
            ->setAppSystem('admin')
            ->setLevel(LogLevel::Critical)
            ->setContent('Frontend error description.')
            ->setPath('/some/fe/page#showList')
            ->setContextId(uuid_create())
        ;
        $log = $this->post('/log', $logDto, Log::class);
        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
        self::assertEquals($logDto->getMessage(), $log->getMessage());
        self::assertEquals($logDto->getAppSystem(), $log->getContext()->getAppSystem());
        self::assertEquals($logDto->getLevel()->toString(), $log->getLevelName());
        self::assertEquals($logDto->getContent(), $log->getContext()->getContent());
        self::assertEquals($logDto->getPath(), $log->getContext()->getPath());
        self::assertEquals($logDto->getContextId(), $log->getContext()->getContextId());

        $filterLog = [
            'filter_eq' => ['context.contextId' => $log->getContext()->getContextId()],
        ];
        $logs = $this->getList('/log/journal', Log::class, $filterLog);
        /** @var Log $foundLog */
        $foundLog = $logs->getData()[0];
        self::assertEquals($logDto->getMessage(), $foundLog->getMessage());
        self::assertEquals($logDto->getAppSystem(), $foundLog->getContext()->getAppSystem());
        self::assertEquals($logDto->getLevel()->toString(), $foundLog->getLevelName());
        self::assertEquals($logDto->getContent(), $foundLog->getContext()->getContent());
        self::assertEquals($logDto->getPath(), $foundLog->getContext()->getPath());
        self::assertEquals($logDto->getContextId(), $foundLog->getContext()->getContextId());
    }
}
