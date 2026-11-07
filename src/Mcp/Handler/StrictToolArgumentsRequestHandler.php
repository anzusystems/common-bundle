<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Mcp\Handler;

use AnzuSystems\CommonBundle\Mcp\Exception\McpToolArgumentsInputException;
use AnzuSystems\CommonBundle\Mcp\Log\McpToolCallLogger;
use AnzuSystems\CommonBundle\Mcp\Security\McpToolAccessChecker;
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

/**
 * Runs before the SDK validates the arguments against the schema of the tool, which would answer a JSON-RPC error without
 * the allowed arguments and without a call log.
 *
 * @implements RequestHandlerInterface<CallToolResult>
 */
final readonly class StrictToolArgumentsRequestHandler implements RequestHandlerInterface
{
    private const int NO_DURATION_MS = 0;

    public function __construct(
        private RegistryInterface $registry,
        private McpToolAccessChecker $toolAccessChecker,
        private McpToolCallLogger $toolCallLogger,
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

        $allowedArguments = $this->getAllowedArguments($this->registry->getTool($request->name));
        $message = McpToolArgumentsInputException::createUnknownArguments(
            $this->findUnknownArguments($request),
            $allowedArguments,
        )->getMessage();
        // A value under an unknown name can be anything, so only its name goes to the log, in the error.
        $this->toolCallLogger->logCall(
            toolName: $request->name,
            params: array_intersect_key($request->arguments, array_flip($allowedArguments)),
            error: $message,
            durationMs: self::NO_DURATION_MS,
            session: $session,
        );

        return new Response($request->getId(), McpToolReferenceHandler::createErrorResult($message));
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
        if (false === $this->toolAccessChecker->isToolGranted($request->name)) {
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
