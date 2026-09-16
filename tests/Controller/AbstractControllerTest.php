<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Tests\Controller;

use AnzuSystems\CommonBundle\ApiFilter\ApiResponseList;
use AnzuSystems\CommonBundle\Tests\AnzuWebTestCase;
use AnzuSystems\CommonBundle\Tests\Data\Entity\User;
use AnzuSystems\CommonBundle\Tests\Data\Repository\UserRepository;
use AnzuSystems\CommonBundle\Tests\Data\Security\TestHeaderAuthenticator;
use AnzuSystems\Contracts\AnzuApp;
use AnzuSystems\SerializerBundle\Serializer;
use JsonException;
use LogicException;
use Symfony\Component\HttpFoundation\Request;

abstract class AbstractControllerTest extends AnzuWebTestCase
{
    protected const int NON_EXISTENT_USER_ID = 999;

    protected User $user;
    private Serializer $serializer;

    /**
     * Log in anonymous user.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->loginUser();

        $this->serializer = self::getContainer()->get(Serializer::class);
    }

    protected function tearDown(): void
    {
        self::getContainer()->get('doctrine.orm.entity_manager')->detach($this->user);
        parent::tearDown();
    }

    protected function loginUser(?int $userId = null): void
    {
        $userId ??= AnzuApp::getUserIdAnonymous();
        $user = self::getContainer()->get(UserRepository::class)->find($userId);
        if (false === $user instanceof User) {
            throw new LogicException(sprintf('User "%d" to log in does not exist, add it to the fixtures.', $userId));
        }

        $this->user = $user;
        $this->sendUserIdHeader($userId);
    }

    /**
     * Authenticate as a user that is not in the database.
     */
    protected function loginNonExistentUser(int $userId = self::NON_EXISTENT_USER_ID): void
    {
        if (self::getContainer()->get(UserRepository::class)->find($userId) instanceof User) {
            throw new LogicException(sprintf('User "%d" exists, pick an id that no fixture creates.', $userId));
        }

        $this->sendUserIdHeader($userId);
    }

    /**
     * @template T of object
     *
     * @param class-string<T> $deserializationClass
     *
     * @return ApiResponseList<T>
     */
    protected function getList(string $uri, string $deserializationClass, array $params = []): ApiResponseList
    {
        self::$client->request(method: Request::METHOD_GET, uri: $uri, parameters: $params);

        /** @var ApiResponseList $apiResponseList */
        $apiResponseList = $this->serializer->deserialize(
            self::$client->getResponse()->getContent(),
            ApiResponseList::class
        );

        return $apiResponseList->setData(
            $this->serializer->fromArray($apiResponseList->getData(), $deserializationClass, [])
        );
    }

    /**
     * @template T of object
     *
     * @param class-string<T>|null $deserializationClass
     *
     * @return T|array
     *
     * @throws JsonException
     */
    protected function get(string $uri, ?string $deserializationClass = null, array $params = []): object|array
    {
        self::$client->request(method: Request::METHOD_GET, uri: $uri, parameters: $params);

        return $this->deserializeResponse($deserializationClass);
    }

    /**
     * @template T of object
     *
     * @param class-string<T>|null $deserializationClass
     *
     * @return T|array
     *
     * @throws JsonException
     */
    protected function post(string $uri, ?object $content = null, ?string $deserializationClass = null, array $params = []): object|array
    {
        self::$client->request(
            method: Request::METHOD_POST,
            uri: $uri,
            parameters: $params,
            content: $content ? $this->serializer->serialize($content) : null,
        );

        return $this->deserializeResponse($deserializationClass);
    }

    /**
     * @template T of object
     *
     * @param class-string<T>|null $deserializationClass
     *
     * @return T|array
     *
     * @throws JsonException
     */
    protected function deserializeResponse(?string $deserializationClass = null): object|array
    {
        if (null === $deserializationClass) {
            return json_decode(
                json: self::$client->getResponse()->getContent(),
                associative: true,
                depth: 255,
                flags: JSON_THROW_ON_ERROR
            );
        }

        return $this->serializer->deserialize(
            self::$client->getResponse()->getContent(),
            $deserializationClass
        );
    }

    private function sendUserIdHeader(int $userId): void
    {
        self::$client->setServerParameter(
            'HTTP_' . str_replace('-', '_', strtoupper(TestHeaderAuthenticator::USER_ID_HEADER)),
            (string) $userId
        );
    }
}
