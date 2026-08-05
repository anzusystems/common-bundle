<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Mcp\Handler;

use AnzuSystems\CommonBundle\Mcp\McpToolExecutor;
use Mcp\Capability\Formatter\ToolResultFormatter;
use Mcp\Capability\Registry\ToolReference;
use Mcp\Capability\RegistryInterface;
use Mcp\Exception\ToolNotFoundException;
use Mcp\Schema\JsonRpc\Error;
use Mcp\Schema\JsonRpc\Request;
use Mcp\Schema\JsonRpc\Response;
use Mcp\Schema\Request\CallToolRequest;
use Mcp\Schema\Result\CallToolResult;
use Mcp\Server\Handler\Request\RequestHandlerInterface;
use Mcp\Server\Session\SessionInterface;
use Psr\Log\LoggerInterface;

/**
 * @implements RequestHandlerInterface<CallToolResult>
 */
final readonly class StrictToolArgumentsRequestHandler implements RequestHandlerInterface
{
    public function __construct(
        private RegistryInterface $registry,
        private LoggerInterface $logger,
    ) {
    }

    public function supports(Request $request): bool
    {
        return $request instanceof CallToolRequest
            && false === empty($this->findUnknownArguments($request));
    }

    public function handle(Request $request, SessionInterface $session): Response|Error
    {
        if (false === $request instanceof CallToolRequest) {
            return Error::forInternalError('Unsupported request.', $request->getId());
        }

        $unknownArguments = $this->findUnknownArguments($request);
        $message = sprintf(
            'Unknown argument%s "%s". Allowed arguments: %s.',
            1 === count($unknownArguments) ? '' : 's',
            implode('", "', $unknownArguments),
            implode(', ', $this->getAllowedArguments($this->registry->getTool($request->name))),
        );
        $this->logger->info('Mcp tool call rejected.', [
            'tool' => $request->name,
            'unknownArguments' => $unknownArguments,
        ]);
        $payload = [McpToolExecutor::ERROR_KEY => $message];

        return new Response(
            $request->getId(),
            new CallToolResult(new ToolResultFormatter()->format($payload), structuredContent: $payload),
        );
    }

    /**
     * @return list<string>
     */
    private function findUnknownArguments(CallToolRequest $request): array
    {
        try {
            $reference = $this->registry->getTool($request->name);
        } catch (ToolNotFoundException) {
            return [];
        }

        return array_values(array_diff(
            array_map(strval(...), array_keys($request->arguments)),
            $this->getAllowedArguments($reference),
        ));
    }

    /**
     * @return list<string>
     */
    private function getAllowedArguments(ToolReference $reference): array
    {
        return array_map(strval(...), array_keys((array) ($reference->tool->inputSchema['properties'] ?? [])));
    }
}
