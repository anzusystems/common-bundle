<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Mcp\Handler;

use AnzuSystems\CommonBundle\Mcp\Security\McpPromptAccessChecker;
use Mcp\Capability\RegistryInterface;
use Mcp\Schema\JsonRpc\Error;
use Mcp\Schema\JsonRpc\Request;
use Mcp\Schema\JsonRpc\Response;
use Mcp\Schema\Prompt;
use Mcp\Schema\Request\ListPromptsRequest;
use Mcp\Schema\Result\ListPromptsResult;
use Mcp\Server\Handler\Request\RequestHandlerInterface;
use Mcp\Server\Session\SessionInterface;

/**
 * @implements RequestHandlerInterface<ListPromptsResult>
 */
final readonly class FilterPromptsListRequestHandler implements RequestHandlerInterface
{
    public function __construct(
        private RegistryInterface $registry,
        private McpPromptAccessChecker $promptAccessChecker,
        private int $pageSize,
    ) {
    }

    public function supports(Request $request): bool
    {
        return $request instanceof ListPromptsRequest;
    }

    public function handle(Request $request, SessionInterface $session): Response|Error
    {
        if (false === $request instanceof ListPromptsRequest) {
            return Error::forInternalError('Unsupported request.', $request->getId());
        }

        $page = $this->registry->getPrompts($this->pageSize, $request->cursor);
        $grantedPrompts = [];
        foreach ($page->references as $reference) {
            if ($reference instanceof Prompt && $this->promptAccessChecker->isPromptGranted($reference->name)) {
                $grantedPrompts[] = $reference;
            }
        }

        return new Response(
            $request->getId(),
            new ListPromptsResult($grantedPrompts, $page->nextCursor),
        );
    }
}
