<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Tests\Data\PHPStan\Mcp\InputPermission;

use AnzuSystems\CommonBundle\Mcp\Attribute\AsMcpTool;

#[AsMcpTool(name: 'checked', description: '', category: 'examples', readOnly: true)]
final readonly class CheckedTool
{
    public function __invoke(ScopedExampleRequest $request): void
    {
        $this->denyAccessUnlessGranted(ExamplePermission::SCOPED, $request);
    }

    private function denyAccessUnlessGranted(string $attributes, object $subject): void
    {
    }
}

#[AsMcpTool(name: 'missing', description: '', category: 'examples', readOnly: true)]
final readonly class MissingCheckTool
{
    public function __invoke(ScopedExampleRequest $request): void
    {
    }
}

#[AsMcpTool(name: 'wrong_permission', description: '', category: 'examples', readOnly: true)]
final readonly class WrongPermissionTool
{
    public function __invoke(ScopedExampleRequest $request): void
    {
        $this->denyAccessUnlessGranted(ExamplePermission::OTHER, $request);
    }

    private function denyAccessUnlessGranted(string $attributes, object $subject): void
    {
    }
}

#[AsMcpTool(name: 'wrong_subject', description: '', category: 'examples', readOnly: true)]
final readonly class WrongSubjectTool
{
    public function __invoke(ScopedExampleRequest $request, object $other): void
    {
        $this->denyAccessUnlessGranted(ExamplePermission::SCOPED, $other);
    }

    private function denyAccessUnlessGranted(string $attributes, object $subject): void
    {
    }
}

#[AsMcpTool(name: 'branch', description: '', category: 'examples', readOnly: true)]
final readonly class CheckInBranchTool
{
    public function __invoke(ScopedExampleRequest $request, bool $enabled): void
    {
        if ($enabled) {
            $this->denyAccessUnlessGranted(ExamplePermission::SCOPED, $request);
        }
    }

    private function denyAccessUnlessGranted(string $attributes, object $subject): void
    {
    }
}

#[AsMcpTool(name: 'early_return', description: '', category: 'examples', readOnly: true)]
final readonly class CheckAfterEarlyReturnTool
{
    public function __invoke(ScopedExampleRequest $request, bool $enabled): void
    {
        if ($enabled) {
            return;
        }
        $this->denyAccessUnlessGranted(ExamplePermission::SCOPED, $request);
    }

    private function denyAccessUnlessGranted(string $attributes, object $subject): void
    {
    }
}

#[AsMcpTool(name: 'second_check', description: '', category: 'examples', readOnly: true)]
final readonly class CheckedAfterOtherCheckTool
{
    public function __invoke(ScopedExampleRequest $request): void
    {
        $this->denyAccessUnlessGranted(ExamplePermission::OTHER, $request);
        $this->denyAccessUnlessGranted(ExamplePermission::SCOPED, $request);
    }

    private function denyAccessUnlessGranted(string $attributes, object $subject): void
    {
    }
}

#[AsMcpTool(name: 'unscoped', description: '', category: 'examples', readOnly: true)]
final readonly class UnscopedRequestTool
{
    public function __invoke(UnscopedExampleRequest $request): void
    {
    }
}

final readonly class NotATool
{
    public function __invoke(ScopedExampleRequest $request): void
    {
    }
}
