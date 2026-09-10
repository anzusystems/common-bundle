<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Tests\Mcp;

use AnzuSystems\CommonBundle\Serializer\Handler\Handlers\RawArrayHandler;
use AnzuSystems\SerializerBundle\Handler\HandlerResolver;
use AnzuSystems\SerializerBundle\Handler\Handlers\BasicHandler;
use AnzuSystems\SerializerBundle\Handler\Handlers\ObjectHandler;
use AnzuSystems\SerializerBundle\Metadata\MetadataFactory;
use AnzuSystems\SerializerBundle\Metadata\MetadataRegistry;
use AnzuSystems\SerializerBundle\Serializer;
use AnzuSystems\SerializerBundle\Service\JsonDeserializer;
use AnzuSystems\SerializerBundle\Service\JsonSerializer;
use Psr\Log\NullLogger;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;
use Symfony\Component\DependencyInjection\ServiceLocator;

final class McpSerializerFactory
{
    public static function create(): Serializer
    {
        $metadataRegistry = new MetadataRegistry(new ArrayAdapter(), new NullLogger(), new MetadataFactory(new ParameterBag()));
        $objectHandler = null;
        $handlerLocator = new ServiceLocator([
            BasicHandler::class => static fn (): BasicHandler => new BasicHandler(),
            RawArrayHandler::class => static fn (): RawArrayHandler => new RawArrayHandler(),
            ObjectHandler::class => static function () use (&$objectHandler): ObjectHandler {
                return $objectHandler;
            },
        ]);
        $handlerResolver = new HandlerResolver($handlerLocator, [BasicHandler::class, RawArrayHandler::class, ObjectHandler::class]);
        $jsonSerializer = new JsonSerializer($handlerResolver, $metadataRegistry);
        $jsonDeserializer = new JsonDeserializer($handlerResolver, $metadataRegistry);
        $objectHandler = new ObjectHandler($jsonSerializer, $jsonDeserializer);

        return new Serializer($jsonSerializer, $jsonDeserializer);
    }
}
