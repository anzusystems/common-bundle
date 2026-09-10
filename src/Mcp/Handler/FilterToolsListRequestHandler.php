<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Mcp\Handler;

use AnzuSystems\CommonBundle\Mcp\Security\McpToolAccessChecker;
use Mcp\Capability\RegistryInterface;
use Mcp\Schema\JsonRpc\Error;
use Mcp\Schema\JsonRpc\Request;
use Mcp\Schema\JsonRpc\Response;
use Mcp\Schema\Request\ListToolsRequest;
use Mcp\Schema\Result\ListToolsResult;
use Mcp\Schema\Tool;
use Mcp\Server\Handler\Request\RequestHandlerInterface;
use Mcp\Server\Session\SessionInterface;

/**
 * @implements RequestHandlerInterface<ListToolsResult>
 */
final readonly class FilterToolsListRequestHandler implements RequestHandlerInterface
{
    public function __construct(
        private RegistryInterface $registry,
        private McpToolAccessChecker $toolAccessChecker,
        private int $pageSize,
    ) {
    }

    public function supports(Request $request): bool
    {
        return $request instanceof ListToolsRequest;
    }

    public function handle(Request $request, SessionInterface $session): Response|Error
    {
        if (false === $request instanceof ListToolsRequest) {
            return Error::forInternalError('Unsupported request.', $request->getId());
        }

        $page = $this->registry->getTools($this->pageSize, $request->cursor);
        $grantedTools = [];
        foreach ($page->references as $reference) {
            if ($reference instanceof Tool && $this->toolAccessChecker->isToolGranted($reference->name)) {
                $grantedTools[] = $reference;
            }
        }

        return new Response(
            $request->getId(),
            new ListToolsResult($grantedTools, $page->nextCursor),
        );
    }
}
