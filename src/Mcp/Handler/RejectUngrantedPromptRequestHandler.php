<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Mcp\Handler;

use AnzuSystems\CommonBundle\Mcp\Exception\McpToolAccessRejectedException;
use AnzuSystems\CommonBundle\Mcp\Security\McpPromptAccessChecker;
use Mcp\Schema\JsonRpc\Error;
use Mcp\Schema\JsonRpc\Request;
use Mcp\Schema\PromptReference;
use Mcp\Schema\Request\CompletionCompleteRequest;
use Mcp\Schema\Request\GetPromptRequest;
use Mcp\Schema\Result\CompletionCompleteResult;
use Mcp\Schema\Result\GetPromptResult;
use Mcp\Server\Handler\Request\RequestHandlerInterface;
use Mcp\Server\Session\SessionInterface;

/**
 * @implements RequestHandlerInterface<GetPromptResult|CompletionCompleteResult>
 */
final readonly class RejectUngrantedPromptRequestHandler implements RequestHandlerInterface
{
    public function __construct(
        private McpPromptAccessChecker $promptAccessChecker,
    ) {
    }

    public function supports(Request $request): bool
    {
        $promptName = $this->resolvePromptName($request);

        return is_string($promptName) && false === $this->promptAccessChecker->isPromptGranted($promptName);
    }

    public function handle(Request $request, SessionInterface $session): Error
    {
        $promptName = $this->resolvePromptName($request);
        if (null === $promptName) {
            return Error::forInternalError('Unsupported request.', $request->getId());
        }

        return Error::forInvalidParams(
            McpToolAccessRejectedException::createPromptNotGranted($promptName)->getMessage(),
            $request->getId(),
        );
    }

    private function resolvePromptName(Request $request): ?string
    {
        return match (true) {
            $request instanceof GetPromptRequest => $request->name,
            $request instanceof CompletionCompleteRequest && $request->ref instanceof PromptReference => $request->ref->name,
            default => null,
        };
    }
}
