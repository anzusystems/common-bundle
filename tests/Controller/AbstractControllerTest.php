<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Tests\Controller;

use AnzuSystems\CommonBundle\ApiFilter\ApiResponseList;
use AnzuSystems\CommonBundle\Tests\AnzuWebTestCase;
use AnzuSystems\CommonBundle\Tests\Data\Entity\User;
use AnzuSystems\SerializerBundle\Serializer;
use AnzuSystems\Contracts\AnzuApp;
use Doctrine\ORM\EntityManagerInterface;
use JsonException;
use Symfony\Component\HttpFoundation\Request;

abstract class AbstractControllerTest extends AnzuWebTestCase
{
    protected User $user;
    private Serializer $serializer;

    /**
     * Log in anonymous user.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->loginUser();

        // CurrentUserProvider requires the user entity to be managed.
        self::getContainer()->get(EntityManagerInterface::class)->persist($this->user);

        $this->serializer = self::getContainer()->get(Serializer::class);
    }

    protected function loginUser(array $roles = []): void
    {
        $this->user = (new User())->setId(AnzuApp::getUserIdAnonymous())->setRoles($roles);
        self::$client->loginUser($this->user);
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
    protected function get(string $uri, string $deserializationClass = null, array $params = []): object | array
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
    protected function post(string $uri, object $content = null, string $deserializationClass = null, array $params = []): object | array
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
    protected function deserializeResponse(string $deserializationClass = null): object | array
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

    protected function tearDown(): void
    {
        self::getContainer()->get('doctrine.orm.entity_manager')->detach($this->user);
        parent::tearDown();
    }
}
