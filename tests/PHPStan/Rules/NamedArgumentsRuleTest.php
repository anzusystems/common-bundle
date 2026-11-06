<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Tests\PHPStan\Rules;

use AnzuSystems\CommonBundle\PHPStan\Rules\NamedArgumentsRule;
use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;

/**
 * @extends RuleTestCase<NamedArgumentsRule>
 */
final class NamedArgumentsRuleTest extends RuleTestCase
{
    private const string DATA_DIR = __DIR__ . '/../../data/PHPStan';

    public function testOnlyUnnamedCallsInCheckedClassesAreReported(): void
    {
        $this->analyse(
            [
                self::DATA_DIR . '/Mcp/McpNamedArguments.php',
                self::DATA_DIR . '/Domain/Example/NamedArgumentsOutsideMcp.php',
            ],
            [
                [$this->createMessage('takeThree', 'first, second, third'), 25],
                [$this->createMessage('takeThree', 'first'), 26],
                [$this->createMessage('createFromThree', 'first, second, third'), 27],
                [$this->createMessage('__construct', 'first, second, third'), 28],
                [$this->createMessage('str_replace', 'search, replace, subject'), 29],
            ],
        );
    }

    protected function getRule(): Rule
    {
        return new NamedArgumentsRule(
            reflectionProvider: self::createReflectionProvider(),
            classNamespaces: ['AnzuSystems\CommonBundle\Tests\Data\PHPStan\Mcp'],
        );
    }

    private function createMessage(string $callee, string $parameterNames): string
    {
        return sprintf(NamedArgumentsRule::MESSAGE, $callee, 3, $parameterNames);
    }
}
