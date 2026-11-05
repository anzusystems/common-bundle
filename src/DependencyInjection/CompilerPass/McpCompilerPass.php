<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\DependencyInjection\CompilerPass;

use AnzuSystems\CommonBundle\Helper\StringHelper;
use AnzuSystems\CommonBundle\Mcp\Attribute\AsMcpTool;
use AnzuSystems\CommonBundle\Mcp\Controller\McpController;
use AnzuSystems\CommonBundle\Mcp\Model\McpPreviewHashAwareInterface;
use AnzuSystems\CommonBundle\Mcp\Model\McpToolSignature;
use AnzuSystems\CommonBundle\Mcp\Model\McpWritePreviewInterface;
use AnzuSystems\CommonBundle\Mcp\Resolver\McpToolSignatureResolver;
use AnzuSystems\CommonBundle\Mcp\Security\McpToolAccessChecker;
use LogicException;
use Mcp\Capability\Attribute\McpTool;
use Mcp\Capability\Discovery\ElementMetadataResolver;
use Mcp\Capability\Tool\NameValidator;
use Mcp\Schema\ServerCapabilities;
use ReflectionAttribute;
use ReflectionMethod;
use Symfony\Component\Config\Resource\FileResource;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\Filesystem\Filesystem;

final class McpCompilerPass implements CompilerPassInterface
{
    public const string SERVER_NAME_PARAM = 'anzu_systems_common.mcp.server_name';
    public const string INSTRUCTIONS_FILE_PARAM = 'anzu_systems_common.mcp.instructions_file';
    public const string BUILDER_ID_FORMAT = 'mcp.server.%s.builder';
    public const string SESSION_CACHE_ID = 'anzu_systems_common.mcp.session_cache';
    public const string TOOL_TAG = 'mcp.tool';
    public const string INVALID_CATEGORY_MESSAGE = 'The category "%s" of the MCP tool "%s" must be camelCase (' . self::CATEGORY_PATTERN . '), so a client can build a translation key from it.';
    public const string PREVIEW_OF_UNKNOWN_TOOL_MESSAGE = 'The MCP tool "%s" is a preview of the unknown MCP tool "%s".';
    public const string PREVIEW_OF_READ_TOOL_MESSAGE = 'The MCP tool "%s" can only preview a write tool declared by #[AsMcpTool], "%s" is not one.';
    public const string WRITABLE_PREVIEW_MESSAGE = 'The MCP tool "%s" previews "%s", so it must be readOnly.';
    public const string SECOND_PREVIEW_MESSAGE = 'The MCP tool "%s" is previewed by more than one tool (%s), a write tool has one preview at most.';
    public const string MAPPED_PREVIEW_MESSAGE = 'The MCP tool "%s" takes the permission of "%s", remove its own permission.';
    public const string TOOL_WITHOUT_PERMISSION_MESSAGE = 'The MCP tool "%s" has no permission: declare it with #[AsMcpTool(permission: …)].';
    public const string PREVIEW_IN_OTHER_CATEGORY_MESSAGE = 'The MCP tool "%1$s" previews "%2$s", so it must be in the category "%3$s" of "%2$s".';
    public const string WRITE_TOOL_WITHOUT_PREVIEW_MESSAGE = 'The MCP write tool "%1$s" has no preview: declare a read tool with #[AsMcpTool(previewOf: "%1$s")].';
    public const string PREVIEW_RESPONSE_WITHOUT_CONFIRMATION_MESSAGE = 'The MCP tool "%s" previews "%s", so its response class must implement %s.';
    public const string WRITE_INPUT_NOT_PREVIEW_INPUT_MESSAGE = 'The input class of the MCP write tool "%s" must implement %s and extend the input class of its preview "%s".';

    // 0 means forever in the SDK; a client that opens a listen stream anyway gets its ack and complete within one 250 ms tick.
    public const float SUBSCRIPTION_LIFETIME_SECONDS = 0.001;

    private const string TOOL_TAG_METHOD = 'method';
    private const string INVOKE_METHOD = '__invoke';
    private const string TOOL_METHOD_FORMAT = '%s::%s';
    private const string TOOL_METHOD_SEPARATOR = '::';
    private const string TOOL_PERMISSIONS_ARGUMENT = '$toolPermissions';
    private const string READ_ONLY_TOOL_NAMES_ARGUMENT = '$readOnlyToolNames';
    private const string TOOL_CATEGORIES_ARGUMENT = '$toolCategories';
    private const string WRITE_TOOL_NAMES_BY_PREVIEW_ARGUMENT = '$writeToolNamesByPreview';
    private const string CATEGORY_PATTERN = '/^[a-z][a-zA-Z0-9]*$/';
    private const string SET_INSTRUCTIONS_METHOD = 'setInstructions';
    private const string SET_CAPABILITIES_METHOD = 'setCapabilities';
    private const string SET_SUBSCRIPTION_LIFETIME_METHOD = 'setSubscriptionLifetime';
    private const string WITHOUT_INPUT_REQUIRED_SHIM_METHOD = 'withoutInputRequiredShim';
    private const string PROMPT_TAG = 'mcp.prompt';
    private const string RESOURCE_TAG = 'mcp.resource';
    private const string RESOURCE_TEMPLATE_TAG = 'mcp.resource_template';

    public function process(ContainerBuilder $container): void
    {
        if (false === $container->hasDefinition(McpController::class)) {
            return;
        }

        $serverName = $container->getParameter(self::SERVER_NAME_PARAM);
        if (false === is_string($serverName)) {
            throw new LogicException(sprintf('The "%s" parameter must be the mcp server name.', self::SERVER_NAME_PARAM));
        }

        $container
            ->setAlias(sprintf('mcp.server.%s.controller', $serverName), McpController::class)
            ->setPublic(true);
        $this->setInstructionsFromFile($container, $serverName);
        $this->configureServerBuilder($container, $serverName);

        $toolsByName = $this->resolveToolsByName($container);
        $this->assertToolNamesAreUnique($toolsByName);
        $this->assertToolNamesAreValid($toolsByName);
        $accessCheckerDefinition = $container->getDefinition(McpToolAccessChecker::class);
        $toolPermissions = $this->resolveToolPermissions($container, $toolsByName);
        $toolCategories = $this->resolveToolCategories($toolsByName);
        $readOnlyToolNames = $this->resolveReadOnlyToolNames($toolsByName);
        $writeToolNamesByPreview = $this->resolveWriteToolNamesByPreview(
            toolsByName: $toolsByName,
            toolPermissions: $toolPermissions,
            toolCategories: $toolCategories,
            readOnlyToolNames: $readOnlyToolNames,
        );
        $accessCheckerDefinition
            ->replaceArgument(self::TOOL_PERMISSIONS_ARGUMENT, $toolPermissions)
            ->replaceArgument(self::READ_ONLY_TOOL_NAMES_ARGUMENT, $readOnlyToolNames)
            ->replaceArgument(self::TOOL_CATEGORIES_ARGUMENT, $toolCategories)
            ->replaceArgument(self::WRITE_TOOL_NAMES_BY_PREVIEW_ARGUMENT, $writeToolNamesByPreview);
        $this->assertWriteTools(
            toolsByName: $toolsByName,
            readOnlyToolNames: $readOnlyToolNames,
            writeToolNamesByPreview: $writeToolNamesByPreview,
        );
        $this->assertToolsHavePermission($toolsByName, $toolPermissions);
    }

    private function setInstructionsFromFile(ContainerBuilder $container, string $serverName): void
    {
        if (false === $container->hasParameter(self::INSTRUCTIONS_FILE_PARAM)) {
            return;
        }
        $path = $container->getParameterBag()
            ->resolveValue($container->getParameter(self::INSTRUCTIONS_FILE_PARAM));
        if (false === is_string($path)) {
            return;
        }
        $builderId = sprintf(self::BUILDER_ID_FORMAT, $serverName);
        if (false === $container->hasDefinition($builderId)) {
            throw new LogicException(sprintf('The mcp server "%s" set in "%s" is not configured in mcp.servers.', $serverName, self::SERVER_NAME_PARAM));
        }
        $instructions = new Filesystem()
            ->readFile($path);
        $container->addResource(new FileResource($path));
        $container
            ->getDefinition($builderId)
            ->removeMethodCall(self::SET_INSTRUCTIONS_METHOD)
            ->addMethodCall(self::SET_INSTRUCTIONS_METHOD, [$instructions]);
    }

    private function configureServerBuilder(ContainerBuilder $container, string $serverName): void
    {
        $builderId = sprintf(self::BUILDER_ID_FORMAT, $serverName);
        if (false === $container->hasDefinition($builderId)) {
            return;
        }
        $container
            ->getDefinition($builderId)
            ->addMethodCall(self::SET_CAPABILITIES_METHOD, [new Definition(ServerCapabilities::class, [
                '$tools' => true,
                '$toolsListChanged' => false,
                '$resources' => $this->hasTaggedService($container, self::RESOURCE_TAG, self::RESOURCE_TEMPLATE_TAG),
                '$resourcesSubscribe' => false,
                '$resourcesListChanged' => false,
                '$prompts' => $this->hasTaggedService($container, self::PROMPT_TAG),
                '$promptsListChanged' => false,
                '$logging' => true,
                '$completions' => true,
            ])])
            ->addMethodCall(self::SET_SUBSCRIPTION_LIFETIME_METHOD, [self::SUBSCRIPTION_LIFETIME_SECONDS])
            // With the shim a returned InputRequiredResult would hold the worker on a handshake revision.
            ->addMethodCall(self::WITHOUT_INPUT_REQUIRED_SHIM_METHOD);
    }

    private function hasTaggedService(ContainerBuilder $container, string ...$tags): bool
    {
        return array_any(
            $tags,
            static fn (string $tag): bool => false === empty($container->findTaggedServiceIds($tag)),
        );
    }

    /**
     * @return array<string, array<string, McpTool>>
     */
    private function resolveToolsByName(ContainerBuilder $container): array
    {
        $toolsByName = [];
        foreach ($this->resolveToolMethods($container) as $toolMethod => $reflection) {
            $tool = $this->resolveToolAttribute($reflection);
            if (null === $tool) {
                continue;
            }
            $toolsByName[ElementMetadataResolver::resolveName($reflection, $tool->name)][$toolMethod] = $tool;
        }

        return $toolsByName;
    }

    /**
     * @return array<string, ReflectionMethod>
     */
    private function resolveToolMethods(ContainerBuilder $container): array
    {
        $toolMethods = [];
        foreach ($container->findTaggedServiceIds(self::TOOL_TAG) as $serviceId => $tags) {
            $class = $this->resolveToolClass($container, $serviceId);
            if (null === $class) {
                continue;
            }
            foreach ($tags as $tagAttributes) {
                $method = $tagAttributes[self::TOOL_TAG_METHOD] ?? self::INVOKE_METHOD;
                if (method_exists($class, $method)) {
                    $toolMethods[sprintf(self::TOOL_METHOD_FORMAT, $class, $method)] = new ReflectionMethod($class, $method);
                }
            }
        }

        return $toolMethods;
    }

    /**
     * @return class-string|null
     */
    private function resolveToolClass(ContainerBuilder $container, string $serviceId): ?string
    {
        $definition = $container->getDefinition($serviceId);
        if ($definition->isAbstract()) {
            return null;
        }
        $class = $container->getParameterBag()
            ->resolveValue($definition->getClass() ?? $serviceId);
        if (is_string($class) && class_exists($class)) {
            return $class;
        }

        return null;
    }

    private function resolveToolAttribute(ReflectionMethod $reflection): ?McpTool
    {
        $attributes = $reflection->getAttributes(McpTool::class, ReflectionAttribute::IS_INSTANCEOF);
        if ([] === $attributes && self::INVOKE_METHOD === $reflection->getName()) {
            $attributes = $reflection->getDeclaringClass()
                ->getAttributes(McpTool::class, ReflectionAttribute::IS_INSTANCEOF);
        }
        if ([] === $attributes) {
            return null;
        }

        return $attributes[0]->newInstance();
    }

    /**
     * @param array<string, array<string, McpTool>> $toolsByName
     */
    private function assertToolNamesAreUnique(array $toolsByName): void
    {
        foreach ($toolsByName as $toolName => $tools) {
            if (count($tools) > 1) {
                throw new LogicException(sprintf(
                    'The MCP tool name "%s" is registered by more than one tool (%s), every tool name must be unique.',
                    $toolName,
                    implode(', ', array_keys($tools)),
                ));
            }
        }
    }

    /**
     * The SDK registers a tool with an invalid name and only logs a warning when the server starts.
     *
     * @param array<string, array<string, McpTool>> $toolsByName
     */
    private function assertToolNamesAreValid(array $toolsByName): void
    {
        $nameValidator = new NameValidator();
        foreach ($toolsByName as $toolName => $tools) {
            if (false === $nameValidator->isValid((string) $toolName)) {
                throw new LogicException(sprintf(
                    'The MCP tool name "%s" of %s is invalid: use 1 to 64 letters, digits, dots, underscores, hyphens or slashes.',
                    $toolName,
                    implode(', ', array_keys($tools)),
                ));
            }
        }
    }

    /**
     * @param array<string, array<string, McpTool>> $toolsByName
     *
     * @return array<string, string>
     */
    private function resolveToolPermissions(ContainerBuilder $container, array $toolsByName): array
    {
        $toolPermissions = [];
        foreach ($toolsByName as $toolName => $tools) {
            $tool = array_first($tools);
            if ($tool instanceof AsMcpTool && null !== $tool->permission) {
                $toolPermissions[$toolName] = (string) $container->getParameterBag()->resolveValue($tool->permission);
            }
        }

        return $toolPermissions;
    }

    /**
     * @param array<string, array<string, McpTool>> $toolsByName
     * @param array<string, string> $toolPermissions
     */
    private function assertToolsHavePermission(array $toolsByName, array $toolPermissions): void
    {
        foreach ($toolsByName as $toolName => $tools) {
            $tool = array_first($tools);
            if (
                $tool instanceof AsMcpTool
                && null === $tool->previewOf
                && false === array_key_exists($toolName, $toolPermissions)
            ) {
                throw new LogicException(sprintf(self::TOOL_WITHOUT_PERMISSION_MESSAGE, $toolName));
            }
        }
    }

    /**
     * @param array<string, array<string, McpTool>> $toolsByName
     *
     * @return list<string>
     */
    private function resolveReadOnlyToolNames(array $toolsByName): array
    {
        return array_keys(array_filter(
            $toolsByName,
            static fn (array $tools): bool => array_any(
                $tools,
                static fn (McpTool $tool): bool => true === $tool->annotations?->readOnlyHint,
            ),
        ));
    }

    /**
     * @param array<string, array<string, McpTool>> $toolsByName
     *
     * @return array<string, string>
     */
    private function resolveToolCategories(array $toolsByName): array
    {
        $toolCategories = [];
        foreach ($toolsByName as $toolName => $tools) {
            $tool = array_first($tools);
            if (false === $tool instanceof AsMcpTool) {
                continue;
            }
            if (1 !== preg_match(self::CATEGORY_PATTERN, $tool->category)) {
                throw new LogicException(sprintf(self::INVALID_CATEGORY_MESSAGE, $tool->category, $toolName));
            }
            $toolCategories[$toolName] = $tool->category;
        }

        return $toolCategories;
    }

    /**
     * @param array<string, array<string, McpTool>> $toolsByName
     * @param array<string, string> $toolPermissions
     * @param array<string, string> $toolCategories
     * @param list<string> $readOnlyToolNames
     *
     * @return array<string, string>
     */
    private function resolveWriteToolNamesByPreview(
        array $toolsByName,
        array $toolPermissions,
        array $toolCategories,
        array $readOnlyToolNames,
    ): array {
        $writeToolNamesByPreview = [];
        foreach ($toolsByName as $toolName => $tools) {
            $tool = array_first($tools);
            if (false === $tool instanceof AsMcpTool || false === is_string($tool->previewOf)) {
                continue;
            }
            $this->assertPreview(
                previewName: $toolName,
                preview: $tool,
                writeToolName: $tool->previewOf,
                toolsByName: $toolsByName,
                toolPermissions: $toolPermissions,
                toolCategories: $toolCategories,
                readOnlyToolNames: $readOnlyToolNames,
            );
            $otherPreviewName = array_search(needle: $tool->previewOf, haystack: $writeToolNamesByPreview, strict: true);
            if (is_string($otherPreviewName)) {
                throw new LogicException(sprintf(
                    self::SECOND_PREVIEW_MESSAGE,
                    $tool->previewOf,
                    implode(', ', [$otherPreviewName, $toolName]),
                ));
            }
            $writeToolNamesByPreview[$toolName] = $tool->previewOf;
        }

        return $writeToolNamesByPreview;
    }

    /**
     * @param array<string, array<string, McpTool>> $toolsByName
     * @param array<string, string> $toolPermissions
     * @param array<string, string> $toolCategories
     * @param list<string> $readOnlyToolNames
     */
    private function assertPreview(
        string $previewName,
        AsMcpTool $preview,
        string $writeToolName,
        array $toolsByName,
        array $toolPermissions,
        array $toolCategories,
        array $readOnlyToolNames,
    ): void {
        if (false === array_key_exists($writeToolName, $toolsByName)) {
            throw new LogicException(sprintf(self::PREVIEW_OF_UNKNOWN_TOOL_MESSAGE, $previewName, $writeToolName));
        }
        if (
            in_array(needle: $writeToolName, haystack: $readOnlyToolNames, strict: true)
            || false === array_key_exists($writeToolName, $toolCategories)
        ) {
            throw new LogicException(sprintf(self::PREVIEW_OF_READ_TOOL_MESSAGE, $previewName, $writeToolName));
        }
        if (false === in_array(needle: $previewName, haystack: $readOnlyToolNames, strict: true)) {
            throw new LogicException(sprintf(self::WRITABLE_PREVIEW_MESSAGE, $previewName, $writeToolName));
        }
        if (array_key_exists($previewName, $toolPermissions)) {
            throw new LogicException(sprintf(self::MAPPED_PREVIEW_MESSAGE, $previewName, $writeToolName));
        }
        if (StringHelper::isNotSame($preview->category, $toolCategories[$writeToolName])) {
            throw new LogicException(sprintf(self::PREVIEW_IN_OTHER_CATEGORY_MESSAGE, $previewName, $writeToolName, $toolCategories[$writeToolName]));
        }
    }

    /**
     * @param array<string, array<string, McpTool>> $toolsByName
     * @param list<string> $readOnlyToolNames
     * @param array<string, string> $writeToolNamesByPreview
     */
    private function assertWriteTools(array $toolsByName, array $readOnlyToolNames, array $writeToolNamesByPreview): void
    {
        foreach ($toolsByName as $toolName => $tools) {
            if (false === array_first($tools) instanceof AsMcpTool || in_array(needle: $toolName, haystack: $readOnlyToolNames, strict: true)) {
                continue;
            }
            $previewName = array_search(needle: $toolName, haystack: $writeToolNamesByPreview, strict: true);
            if (false === is_string($previewName)) {
                throw new LogicException(sprintf(self::WRITE_TOOL_WITHOUT_PREVIEW_MESSAGE, $toolName));
            }
            $this->assertWriteToolFitsPreview(
                toolName: $toolName,
                previewName: $previewName,
                signature: $this->resolveToolSignature($tools),
                previewSignature: $this->resolveToolSignature($toolsByName[$previewName]),
            );
        }
    }

    private function assertWriteToolFitsPreview(
        string $toolName,
        string $previewName,
        McpToolSignature $signature,
        McpToolSignature $previewSignature,
    ): void {
        $previewOutputClass = $previewSignature->outputClass;
        if (false === is_string($previewOutputClass) || false === is_a($previewOutputClass, McpWritePreviewInterface::class, true)) {
            throw new LogicException(sprintf(self::PREVIEW_RESPONSE_WITHOUT_CONFIRMATION_MESSAGE, $previewName, $toolName, McpWritePreviewInterface::class));
        }
        $inputClass = $signature->inputClass;
        $previewInputClass = $previewSignature->inputClass;
        if (
            false === is_string($inputClass)
            || false === is_string($previewInputClass)
            || false === is_a($inputClass, McpPreviewHashAwareInterface::class, true)
            || false === is_a($inputClass, $previewInputClass, true)
        ) {
            throw new LogicException(sprintf(self::WRITE_INPUT_NOT_PREVIEW_INPUT_MESSAGE, $toolName, McpPreviewHashAwareInterface::class, $previewName));
        }
    }

    /**
     * @param array<string, McpTool> $tools
     */
    private function resolveToolSignature(array $tools): McpToolSignature
    {
        $toolMethod = (string) array_key_first($tools);
        $class = explode(self::TOOL_METHOD_SEPARATOR, $toolMethod)[0];

        return new McpToolSignatureResolver()
            ->resolve($class)
            ?? throw new LogicException(sprintf('The MCP tool "%s" is not declared by #[AsMcpTool].', $toolMethod));
    }
}
