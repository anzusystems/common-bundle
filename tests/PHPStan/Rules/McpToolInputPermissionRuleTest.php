<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Tests\PHPStan\Rules;

use AnzuSystems\CommonBundle\PHPStan\Rules\McpToolInputPermissionRule;
use AnzuSystems\CommonBundle\Tests\Data\PHPStan\Mcp\InputPermission\CheckInBranchTool;
use AnzuSystems\CommonBundle\Tests\Data\PHPStan\Mcp\InputPermission\ExamplePermission;
use AnzuSystems\CommonBundle\Tests\Data\PHPStan\Mcp\InputPermission\MissingCheckTool;
use AnzuSystems\CommonBundle\Tests\Data\PHPStan\Mcp\InputPermission\ScopedExampleInterface;
use AnzuSystems\CommonBundle\Tests\Data\PHPStan\Mcp\InputPermission\WrongPermissionTool;
use AnzuSystems\CommonBundle\Tests\Data\PHPStan\Mcp\InputPermission\WrongSubjectTool;
use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;

/**
 * @extends RuleTestCase<McpToolInputPermissionRule>
 */
final class McpToolInputPermissionRuleTest extends RuleTestCase
{
    private const string PARAMETER = 'request';

    public function testToolOfAScopedRequestWithoutTheCheckIsReported(): void
    {
        $this->analyse(
            [__DIR__ . '/../../data/PHPStan/Mcp/InputPermission/tools.php'],
            [
                [$this->createMessage(MissingCheckTool::class), 25],
                [$this->createMessage(WrongPermissionTool::class), 33],
                [$this->createMessage(WrongSubjectTool::class), 46],
                [$this->createMessage(CheckInBranchTool::class), 59],
            ],
        );
    }

    protected function getRule(): Rule
    {
        return new McpToolInputPermissionRule(
            reflectionProvider: self::createReflectionProvider(),
            inputPermissions: [ScopedExampleInterface::class => ExamplePermission::SCOPED],
        );
    }

    private function createMessage(string $toolClass): string
    {
        return sprintf(McpToolInputPermissionRule::MESSAGE, $toolClass, ScopedExampleInterface::class, ExamplePermission::SCOPED, self::PARAMETER);
    }
}
