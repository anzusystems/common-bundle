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
                [$this->createMessage('Property ' . McpObjectArrays::class . '::$examples', 'list<' . Example::class . '>', Example::class), 17],
                [$this->createMessage('Property ' . McpObjectArrays::class . '::$dtos', 'array<' . DummyDto::class . '>|null', DummyDto::class), 22],
                [$this->createMessage('Property ' . McpObjectArrays::class . '::$serializedDtos', 'list<' . DummyDto::class . '>', DummyDto::class), 27],
                [$this->createMessage('Property ' . McpObjectArrays::class . '::$examplesByGroupId', 'array<int, list<' . Example::class . '>>', Example::class), 35],
                [$this->createMessage('Property ' . McpObjectArrays::class . '::$serializedExamples', 'list<' . DummyDto::class . '>', DummyDto::class), 36],
                [$this->createMessage('Parameter $usersByEmail of method ' . McpObjectArrays::class . '::mapExamples()', 'array<string, ' . User::class . '>', User::class), 46],
                [$this->createMessage('Return type of method ' . McpObjectArrays::class . '::mapExamples()', 'list<' . DummyDto::class . '>', DummyDto::class), 46],
                [$this->createMessage('Return type of method ' . McpSharedObjectArrays::class . '::findExamples()', 'list<' . Example::class . '>', Example::class), 14],
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
