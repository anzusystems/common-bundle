<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Tests\Data\Mcp\Model\Request;

use AnzuSystems\CommonBundle\Serializer\Handler\Handlers\RawArrayHandler;
use AnzuSystems\SerializerBundle\Attributes\Serialize;

final class ArrayExampleRequest
{
    /**
     * @var array<string>
     */
    #[Serialize]
    public array $arrayOfStrings = [];

    /**
     * @var list<string>
     */
    #[Serialize]
    public array $listOfStrings = [];

    /**
     * @var array<int, string>
     */
    #[Serialize]
    public array $stringsByInt = [];

    /**
     * @var array<array-key, string>
     */
    #[Serialize]
    public array $stringsByArrayKey = [];

    /**
     * @var array<mixed>
     */
    #[Serialize]
    public array $arrayOfMixed = [];

    /**
     * @var array<string, mixed>
     */
    #[Serialize]
    public array $mixedByString = [];

    /**
     * @var array<string, string>
     */
    #[Serialize]
    public array $stringsByString = [];

    /**
     * @var array<int, string>
     */
    #[Serialize(handler: RawArrayHandler::class)]
    public array $rawStringsByInt = [];

    /**
     * @var array<int, string>|null
     */
    #[Serialize(handler: RawArrayHandler::class)]
    public ?array $nullableRawStringsByInt = null;

    /**
     * @var list<string>
     */
    #[Serialize(handler: RawArrayHandler::class)]
    public array $rawListOfStrings = [];
}
