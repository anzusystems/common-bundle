<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Tests\PHPStan\Rules;

use AnzuSystems\CommonBundle\PHPStan\Rules\McpObjectArrayRule;
use AnzuSystems\CommonBundle\Tests\Data\Entity\Example;
use AnzuSystems\CommonBundle\Tests\Data\Entity\User;
use AnzuSystems\CommonBundle\Tests\Data\Model\DataObject\DummyDto;
use AnzuSystems\CommonBundle\Tests\Data\PHPStan\Domain\Example\Mcp\McpObjectArrays;
use AnzuSystems\CommonBundle\Tests\Data\PHPStan\Mcp\McpSharedObjectArrays;
use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;

/**
 * @extends RuleTestCase<McpObjectArrayRule>
 */
final class McpObjectArrayRuleTest extends RuleTestCase
{
    private const string DATA_DIR = __DIR__ . '/../../data/PHPStan';
    private const string DATA_NAMESPACE = 'AnzuSystems\CommonBundle\Tests\Data\PHPStan';

    public function testObjectArraysInMcpClassesAreReported(): void
    {
        $this->analyse(
            [
                self::DATA_DIR . '/Domain/Example/Mcp/McpObjectArrays.php',
                self::DATA_DIR . '/Domain/Example/Mcp/McpObjectCollections.php',
                self::DATA_DIR . '/Mcp/McpSharedObjectArrays.php',
                self::DATA_DIR . '/Domain/Example/ObjectArraysOutsideMcp.php',
            ],
            [
                [$this->createMessage(subject: 'Property ' . McpObjectArrays::class . '::$examples', type: 'list<' . Example::class . '>', objectClassName: Example::class), 17],
                [$this->createMessage(subject: 'Property ' . McpObjectArrays::class . '::$dtos', type: 'array<' . DummyDto::class . '>|null', objectClassName: DummyDto::class), 22],
                [$this->createMessage(subject: 'Property ' . McpObjectArrays::class . '::$serializedDtos', type: 'list<' . DummyDto::class . '>', objectClassName: DummyDto::class), 27],
                [$this->createMessage(subject: 'Property ' . McpObjectArrays::class . '::$examplesByGroupId', type: 'array<int, list<' . Example::class . '>>', objectClassName: Example::class), 35],
                [$this->createMessage(subject: 'Property ' . McpObjectArrays::class . '::$serializedExamples', type: 'list<' . DummyDto::class . '>', objectClassName: DummyDto::class), 36],
                [$this->createMessage(subject: 'Parameter $usersByEmail of method ' . McpObjectArrays::class . '::mapExamples()', type: 'array<string, ' . User::class . '>', objectClassName: User::class), 46],
                [$this->createMessage(subject: 'Return type of method ' . McpObjectArrays::class . '::mapExamples()', type: 'list<' . DummyDto::class . '>', objectClassName: DummyDto::class), 46],
                [$this->createMessage(subject: 'Return type of method ' . McpSharedObjectArrays::class . '::findExamples()', type: 'list<' . Example::class . '>', objectClassName: Example::class), 14],
            ],
        );
    }

    protected function getRule(): Rule
    {
        return new McpObjectArrayRule(
            reflectionProvider: self::createReflectionProvider(),
            classNamespaces: [self::DATA_NAMESPACE . '\Domain\*\Mcp', self::DATA_NAMESPACE . '\Mcp'],
            objectNamespace: 'AnzuSystems\CommonBundle\Tests\Data',
        );
    }

    private function createMessage(string $subject, string $type, string $objectClassName): string
    {
        return sprintf(McpObjectArrayRule::MESSAGE, $subject, $type, $objectClassName);
    }
}
