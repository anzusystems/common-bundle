<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Tests\PHPStan\Rules;

use AnzuSystems\CommonBundle\PHPStan\Rules\McpObjectArrayRule;
use AnzuSystems\CommonBundle\PHPStan\Rules\McpTraitObjectArrayRule;
use AnzuSystems\CommonBundle\Tests\Data\Entity\Example;
use AnzuSystems\CommonBundle\Tests\Data\PHPStan\Mcp\McpObjectArraysTrait;
use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;

/**
 * @extends RuleTestCase<McpTraitObjectArrayRule>
 */
final class McpTraitObjectArrayRuleTest extends RuleTestCase
{
    private const string DATA_DIR = __DIR__ . '/../../data/PHPStan/Mcp';

    public function testObjectArrayInMcpTraitIsReported(): void
    {
        $this->analyse(
            [self::DATA_DIR . '/McpObjectArraysTrait.php', self::DATA_DIR . '/McpObjectArraysTraitUser.php'],
            [
                [sprintf(McpObjectArrayRule::MESSAGE, 'Property ' . McpObjectArraysTrait::class . '::$examples', 'list<' . Example::class . '>', Example::class), 14],
            ],
        );
    }

    protected function getRule(): Rule
    {
        return new McpTraitObjectArrayRule(
            new McpObjectArrayRule(
                reflectionProvider: self::createReflectionProvider(),
                classNamespaces: ['AnzuSystems\CommonBundle\Tests\Data\PHPStan\Mcp'],
                objectNamespace: 'AnzuSystems\CommonBundle\Tests\Data',
            ),
        );
    }
}
