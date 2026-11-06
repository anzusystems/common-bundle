<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Mcp\Exception;

use AnzuSystems\CommonBundle\Mcp\Handler\McpToolReferenceHandler;
use AnzuSystems\CommonBundle\Mcp\Model\McpPreviewHashAwareInterface;
use AnzuSystems\CommonBundle\Mcp\Model\McpWritePreviewInterface;
use LogicException;

/**
 * An MCP server or tool that a compiler pass refuses, so the mistake fails the container compilation.
 */
final class McpConfigurationException extends LogicException
{
    private const string TOOL_NAME_SEPARATOR = ', ';

    public static function createInvalidServerName(string $parameter): self
    {
        return new self(sprintf('The "%s" parameter must be the mcp server name.', $parameter));
    }

    public static function createServerNotConfigured(string $serverName, string $parameter): self
    {
        return new self(sprintf('The mcp server "%s" set in "%s" is not configured in mcp.servers.', $serverName, $parameter));
    }

    public static function createForeignReferenceHandler(string $serverName): self
    {
        return new self(sprintf('The mcp server "%s" already has a reference handler, so %s cannot wrap the SDK one.', $serverName, McpToolReferenceHandler::class));
    }

    public static function createAppTemplateOnAsMcpTool(string $class, string $toolKey): self
    {
        return new self(sprintf(
            'The MCP tool "%s" declares #[AsMcpTool] and the MCP App template of "%s": %s returns a CallToolResult, but the template needs an array, so drop one of them.',
            $class,
            $toolKey,
            McpToolReferenceHandler::class,
        ));
    }

    /**
     * @param list<string> $toolMethods
     */
    public static function createDuplicateToolName(string $toolName, array $toolMethods): self
    {
        return new self(sprintf('The MCP tool name "%s" is registered by more than one tool (%s), every tool name must be unique.', $toolName, implode(self::TOOL_NAME_SEPARATOR, $toolMethods)));
    }

    /**
     * @param list<string> $toolMethods
     */
    public static function createInvalidToolName(string $toolName, array $toolMethods): self
    {
        return new self(sprintf('The MCP tool name "%s" of %s is invalid: use 1 to 64 letters, digits, dots, underscores, hyphens or slashes.', $toolName, implode(self::TOOL_NAME_SEPARATOR, $toolMethods)));
    }

    public static function createInvalidCategory(string $category, string $toolName, string $pattern): self
    {
        return new self(sprintf('The category "%s" of the MCP tool "%s" must be camelCase (%s), so a client can build a translation key from it.', $category, $toolName, $pattern));
    }

    public static function createPreviewOfUnknownTool(string $previewToolName, string $writeToolName): self
    {
        return new self(sprintf('The MCP tool "%s" is a preview of the unknown MCP tool "%s".', $previewToolName, $writeToolName));
    }

    public static function createPreviewOfReadTool(string $previewToolName, string $toolName): self
    {
        return new self(sprintf('The MCP tool "%s" can only preview a write tool declared by #[AsMcpTool], "%s" is not one.', $previewToolName, $toolName));
    }

    public static function createWritablePreview(string $previewToolName, string $writeToolName): self
    {
        return new self(sprintf('The MCP tool "%s" previews "%s", so it must be readOnly.', $previewToolName, $writeToolName));
    }

    /**
     * @param list<string> $previewToolNames
     */
    public static function createSecondPreview(string $writeToolName, array $previewToolNames): self
    {
        return new self(sprintf('The MCP tool "%s" is previewed by more than one tool (%s), a write tool has one preview at most.', $writeToolName, implode(self::TOOL_NAME_SEPARATOR, $previewToolNames)));
    }

    public static function createPreviewWithPermission(string $previewToolName, string $writeToolName): self
    {
        return new self(sprintf('The MCP tool "%s" takes the permission of "%s", remove its own permission.', $previewToolName, $writeToolName));
    }

    public static function createPreviewInOtherCategory(string $previewToolName, string $writeToolName, string $category): self
    {
        return new self(sprintf('The MCP tool "%1$s" previews "%2$s", so it must be in the category "%3$s" of "%2$s".', $previewToolName, $writeToolName, $category));
    }

    public static function createUnknownRateLimiter(string $toolName, string $rateLimiter): self
    {
        return new self(sprintf('The MCP tool "%s" names the unknown rate limiter "%s": declare it in framework.rate_limiter.', $toolName, $rateLimiter));
    }

    public static function createReadToolRateLimiter(string $toolName): self
    {
        return new self(sprintf('The MCP tool "%s" is a read tool, so it cannot name a rate limiter.', $toolName));
    }

    public static function createToolWithoutPermission(string $toolName): self
    {
        return new self(sprintf('The MCP tool "%s" has no permission: declare it with #[AsMcpTool(permission: …)].', $toolName));
    }

    public static function createWriteToolWithoutPreview(string $toolName): self
    {
        return new self(sprintf('The MCP write tool "%1$s" has no preview: declare a read tool with #[AsMcpTool(previewOf: "%1$s")].', $toolName));
    }

    public static function createPreviewResponseWithoutConfirmation(string $previewToolName, string $writeToolName): self
    {
        return new self(sprintf('The MCP tool "%s" previews "%s", so its response class must implement %s.', $previewToolName, $writeToolName, McpWritePreviewInterface::class));
    }

    public static function createWriteInputNotPreviewInput(string $toolName, string $previewToolName): self
    {
        return new self(sprintf('The input class of the MCP write tool "%s" must implement %s and extend the input class of its preview "%s".', $toolName, McpPreviewHashAwareInterface::class, $previewToolName));
    }

    public static function createToolWithoutInvoke(string $class, string $invokeMethod): self
    {
        return new self(sprintf('The MCP tool "%s" declares #[AsMcpTool], so it declares %s().', $class, $invokeMethod));
    }

    public static function createInvalidToolReturn(string $class, string $method, string $resultClass, string $type): self
    {
        return new self(sprintf('The MCP tool "%s::%s()" returns a response class or %s, not "%s".', $class, $method, $resultClass, $type));
    }

    public static function createInvalidToolParameter(string $class, string $method, string $contextClass, string $parameter): self
    {
        return new self(sprintf(
            'The MCP tool "%s::%s()" takes its input class and then the optional %s, so "$%s" does not fit: move it into the input class.',
            $class,
            $method,
            $contextClass,
            $parameter,
        ));
    }

    public static function createNotAsMcpTool(string $toolMethod): self
    {
        return new self(sprintf('The MCP tool "%s" is not declared by #[AsMcpTool].', $toolMethod));
    }
}
