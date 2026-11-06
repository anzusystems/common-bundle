<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Tests\Mcp\Handler;

use AnzuSystems\CommonBundle\Mcp\Log\McpLogger;
use AnzuSystems\CommonBundle\Mcp\Model\Response\McpLogsByContextResponse;
use AnzuSystems\CommonBundle\Mcp\Resolver\McpToolErrorResolver;
use AnzuSystems\CommonBundle\Mcp\Security\McpInputEnricherInterface;
use AnzuSystems\CommonBundle\Tests\Data\Mcp\Tool\ExampleContextTool;
use AnzuSystems\CommonBundle\Tests\Data\Mcp\Tool\ExampleSearchTool;
use AnzuSystems\CommonBundle\Tests\Data\Mcp\Tool\ExampleWithoutInputTool;
use Mcp\Capability\Registry\ToolReference;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

final class McpToolReferenceHandlerCallTest extends AbstractMcpToolReferenceHandlerTestCase
{
    public function testSuccessSerializesObjectResultAndLogsRequestParams(): void
    {
        $handler = $this->createHandler(
            tool: new ExampleSearchTool(static fn (): McpLogsByContextResponse => self::createResponse()),
        );

        $result = $this->callTool(handler: $handler, toolClass: ExampleSearchTool::class, toolName: ExampleSearchTool::NAME, limit: self::LIMIT);

        self::assertFalse($result->isError);
        self::assertSame(['auditLogs', 'appLogs', 'mcpToolCalls', 'hint'], array_keys($result->structuredContent));
        self::assertSame([], $result->structuredContent['auditLogs']);
        self::assertCount(1, $this->insertedDocuments);
        $document = $this->insertedDocuments[0];
        self::assertSame(McpLogger::LEVEL_NAME_INFO, $document['levelName']);
        self::assertSame(ExampleSearchTool::NAME, $document['tool']);
        self::assertSame(
            [
                'level' => self::LEVEL,
                'messageContains' => null,
                'contextId' => null,
                'from' => null,
                'until' => null,
                'limit' => self::LIMIT,
            ],
            $document['params'],
        );
        self::assertSame(self::USER_ID, $document['userId']);
        self::assertNull($document['error']);
    }

    public function testRequestContextIsInjectedAndCallToolResultIsPassedThroughAndLogged(): void
    {
        $handler = $this->createHandler(tool: new ExampleContextTool());

        $result = $this->callTool(handler: $handler, toolClass: ExampleContextTool::class, toolName: ExampleContextTool::NAME, limit: self::LIMIT);

        self::assertFalse($result->isError);
        self::assertSame(['tool' => ExampleContextTool::NAME, 'level' => self::LEVEL], $result->structuredContent);
        self::assertCount(1, $this->insertedDocuments);
        self::assertSame(ExampleContextTool::NAME, $this->insertedDocuments[0]['tool']);
        self::assertSame(self::LIMIT, $this->insertedDocuments[0]['params']['limit']);
        self::assertNull($this->insertedDocuments[0]['error']);
    }

    public function testRefusedEnrichmentStopsTheCallBeforeTheToolRuns(): void
    {
        $runs = 0;
        $enricher = $this->createStub(McpInputEnricherInterface::class);
        $enricher->method('enrich')
            ->willThrowException(new AccessDeniedException());
        $handler = $this->createHandler(
            tool: new ExampleSearchTool(static function () use (&$runs): McpLogsByContextResponse {
                ++$runs;

                return self::createResponse();
            }),
            inputEnricher: $enricher,
        );

        $result = $handler->handle(
            new ToolReference($this->createSdkTool(ExampleSearchTool::NAME), [ExampleSearchTool::class, self::INVOKE_METHOD]),
            $this->createModernArguments(toolName: ExampleSearchTool::NAME, toolArguments: []),
        );

        $this->assertToolError(McpToolErrorResolver::ACCESS_DENIED_MESSAGE, $result);
        self::assertSame(0, $runs);
    }

    public function testInputIsEnrichedBeforeTheToolRuns(): void
    {
        $enrichedInput = null;
        $toolInput = null;
        $enricher = $this->createMock(McpInputEnricherInterface::class);
        $enricher->expects(self::once())
            ->method('enrich')
            ->willReturnCallback(static function (object $input) use (&$enrichedInput): void {
                $enrichedInput = $input;
            });
        $handler = $this->createHandler(
            tool: new ExampleSearchTool(static function (object $input) use (&$toolInput): McpLogsByContextResponse {
                $toolInput = $input;

                return self::createResponse();
            }),
            inputEnricher: $enricher,
        );

        $handler->handle(
            new ToolReference($this->createSdkTool(ExampleSearchTool::NAME), [ExampleSearchTool::class, self::INVOKE_METHOD]),
            $this->createModernArguments(toolName: ExampleSearchTool::NAME, toolArguments: []),
        );

        self::assertNotNull($toolInput);
        self::assertSame($toolInput, $enrichedInput);
    }

    public function testToolWithoutInputIsNotEnriched(): void
    {
        $enricher = $this->createMock(McpInputEnricherInterface::class);
        $enricher->expects(self::never())->method('enrich');
        $handler = $this->createHandler(
            tool: new ExampleWithoutInputTool(static fn (): McpLogsByContextResponse => self::createResponse()),
            inputEnricher: $enricher,
        );

        $handler->handle(
            new ToolReference($this->createSdkTool(ExampleWithoutInputTool::NAME), [ExampleWithoutInputTool::class, self::INVOKE_METHOD]),
            $this->createModernArguments(toolName: ExampleWithoutInputTool::NAME, toolArguments: []),
        );
    }
}
