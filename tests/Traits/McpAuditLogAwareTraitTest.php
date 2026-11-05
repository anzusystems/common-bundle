<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Tests\Traits;

use AnzuSystems\CommonBundle\Log\Helper\AuditLogResourceHelper;
use AnzuSystems\CommonBundle\Tests\Data\Entity\User;
use AnzuSystems\CommonBundle\Traits\McpAuditLogAwareTrait;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

final class McpAuditLogAwareTraitTest extends TestCase
{
    private const int USER_ID = 5;
    private const string RESOURCE_NAME = 'quiz';

    public function testEntityReachesTheMainRequest(): void
    {
        $request = new Request();
        $this->createTool($request)
            ->auditEntity(new User()->setId(self::USER_ID));

        self::assertSame(User::getResourceName(), $request->attributes->get(AuditLogResourceHelper::RESOURCE_NAME_ATTR_NAME));
        self::assertSame([self::USER_ID], $request->attributes->get(AuditLogResourceHelper::RESOURCE_ID_ATTR_NAME));
    }

    public function testResourceOfADeletedEntityReachesTheMainRequest(): void
    {
        $request = new Request();
        $this->createTool($request)
            ->auditResource(self::RESOURCE_NAME, self::USER_ID);

        self::assertSame(self::RESOURCE_NAME, $request->attributes->get(AuditLogResourceHelper::RESOURCE_NAME_ATTR_NAME));
        self::assertSame([self::USER_ID], $request->attributes->get(AuditLogResourceHelper::RESOURCE_ID_ATTR_NAME));
    }

    public function testWithoutMainRequestNothingIsRecorded(): void
    {
        $tool = $this->createTool();

        $tool->auditEntity(new User()->setId(self::USER_ID));
        $tool->auditResource(self::RESOURCE_NAME, self::USER_ID);

        $this->addToAssertionCount(1);
    }

    private function createTool(?Request $request = null): object
    {
        $requestStack = new RequestStack();
        if ($request instanceof Request) {
            $requestStack->push($request);
        }
        $tool = new class() {
            use McpAuditLogAwareTrait;

            public function auditEntity(User $user): void
            {
                $this->setAuditResourceByEntity($user);
            }

            public function auditResource(string $resourceName, int $resourceId): void
            {
                $this->setAuditResource($resourceName, $resourceId);
            }
        };
        $tool->setRequestStack($requestStack);

        return $tool;
    }
}
