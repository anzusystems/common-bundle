<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Mcp\Handler;

use AnzuSystems\CommonBundle\Mcp\Security\McpToolPermission;
use Mcp\Capability\RegistryInterface;
use Mcp\Schema\JsonRpc\Error;
use Mcp\Schema\JsonRpc\Request;
use Mcp\Schema\JsonRpc\Response;
use Mcp\Schema\Request\ListToolsRequest;
use Mcp\Schema\Result\ListToolsResult;
use Mcp\Schema\Tool;
use Mcp\Server\Handler\Request\RequestHandlerInterface;
use Mcp\Server\Session\SessionInterface;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * @implements RequestHandlerInterface<ListToolsResult>
 */
final readonly class FilterToolsListRequestHandler implements RequestHandlerInterface
{
    public function __construct(
        private RegistryInterface $registry,
        private Security $security,
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
        $grantedTools = array_values(array_filter(
            $page->references,
            fn (Tool $tool): bool => $this->security->isGranted(McpToolPermission::forTool($tool->name)),
        ));

        return new Response(
            $request->getId(),
            new ListToolsResult($grantedTools, $page->nextCursor),
        );
    }
}
