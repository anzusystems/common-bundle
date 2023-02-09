<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Tests\Controller;

use AnzuSystems\CommonBundle\ApiFilter\ApiInfiniteResponseList;
use AnzuSystems\CommonBundle\Entity\JobUserDataDelete;
use AnzuSystems\CommonBundle\Model\Enum\JobStatus;
use AnzuSystems\CommonBundle\Validator\Constraints\EntityExists;
use AnzuSystems\Contracts\AnzuApp;
use JsonException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class JobControllerTest extends AbstractControllerTest
{
    /**
     * @throws JsonException
     */
    public function testCrud(): void
    {
        // 1.a Creation with validation error
        $job = (new JobUserDataDelete())
            ->setTargetUserId(123)
            ->setAnonymizeUser(true)
        ;
        $response = $this->post('/job/user-data-delete', $job);
        $this->assertValidationErrors($response, ['targetUserId' => [EntityExists::MESSAGE]]);

        // 1.b Creation successfully
        $job = (new JobUserDataDelete())
            ->setTargetUserId(AnzuApp::getUserIdAdmin())
            ->setAnonymizeUser(true)
        ;
        $responseCreate = $this->post('/job/user-data-delete', $job, JobUserDataDelete::class);
        $this->assertSame($job->getTargetUserId(), $responseCreate->getTargetUserId());
        $this->assertSame($job->isAnonymizeUser(), $responseCreate->isAnonymizeUser());
        $this->assertSame(JobStatus::Default, $responseCreate->getStatus());

        // 2. Get one
        $responseGetOne = $this->get(sprintf('/job/%d', $responseCreate->getId()), JobUserDataDelete::class);
        $this->assertSame($responseCreate->getId(), $responseGetOne->getId());

        // 3. Get list
        $responseList = $this->get('/job', ApiInfiniteResponseList::class);
        $this->assertFalse($responseList->isHasNextPage());
        $this->assertCount(1, $responseList->getData());
        $this->assertSame($responseGetOne->getId(), $responseList->getData()[0]['id']);

        // 4. Delete
        $response = $this::$client->request(Request::METHOD_DELETE, sprintf('/job/%d', $responseCreate->getId()));
        $this->assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);
    }
}
