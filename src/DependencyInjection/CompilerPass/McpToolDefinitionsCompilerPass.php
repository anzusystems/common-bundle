<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\DependencyInjection\CompilerPass;

use AnzuSystems\CommonBundle\Helper\StringHelper;
use AnzuSystems\CommonBundle\Mcp\Attribute\AsMcpTool;
use AnzuSystems\CommonBundle\Mcp\Controller\McpController;
use AnzuSystems\CommonBundle\Mcp\Exception\McpConfigurationException;
use AnzuSystems\CommonBundle\Mcp\McpToolDefinitions;
use AnzuSystems\CommonBundle\Mcp\McpWriteRateLimiter;
use AnzuSystems\CommonBundle\Mcp\Model\McpPreviewHashAwareInterface;
use AnzuSystems\CommonBundle\Mcp\Model\McpToolDefinition;
use AnzuSystems\CommonBundle\Mcp\Model\McpToolSignature;
use AnzuSystems\CommonBundle\Mcp\Model\McpWritePreviewInterface;
use AnzuSystems\CommonBundle\Mcp\Resolver\McpToolSignatureResolver;
use Mcp\Capability\Attribute\McpTool;
use Mcp\Capability\Discovery\ElementMetadataResolver;
use Mcp\Capability\Tool\NameValidator;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\Compiler\ServiceLocatorTagPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

final class McpToolDefinitionsCompilerPass implements CompilerPassInterface
{
    use McpTaggedElementTrait;

    public const string TOOL_TAG = 'mcp.tool';
    public const string CATEGORY_PATTERN = '/^[a-z][a-zA-Z0-9]*$/';
    public const string DEFINITIONS_ARGUMENT = '$definitions';

    private const string RATE_LIMITER_ID_FORMAT = 'limiter.%s';

    public function process(ContainerBuilder $container): void
    {
        if (false === $container->hasDefinition(McpController::class)) {
            return;
        }

        $toolsByName = $this->resolveToolsByName($container);
        $this->assertToolNamesAreUnique($toolsByName);
        $this->assertToolNamesAreValid($toolsByName);
        $toolPermissions = $this->resolveToolPermissions($container, $toolsByName);
        $toolCategories = $this->resolveToolCategories($toolsByName);
        $readOnlyToolNames = $this->resolveReadOnlyToolNames($toolsByName);
        $writeToolNamesByPreview = $this->resolveWriteToolNamesByPreview(
            toolsByName: $toolsByName,
            toolPermissions: $toolPermissions,
            toolCategories: $toolCategories,
            readOnlyToolNames: $readOnlyToolNames,
        );
        $this->assertWriteTools(
            toolsByName: $toolsByName,
            readOnlyToolNames: $readOnlyToolNames,
            writeToolNamesByPreview: $writeToolNamesByPreview,
        );
        $this->assertToolsHavePermission($toolsByName, $toolPermissions);
        $toolRateLimiters = $this->configureWriteRateLimiter(container: $container, toolsByName: $toolsByName, readOnlyToolNames: $readOnlyToolNames);
        $container
            ->getDefinition(McpToolDefinitions::class)
            ->replaceArgument(self::DEFINITIONS_ARGUMENT, $this->createToolDefinitions(
                toolsByName: $toolsByName,
                toolPermissions: $toolPermissions,
                toolCategories: $toolCategories,
                readOnlyToolNames: $readOnlyToolNames,
                writeToolNamesByPreview: $writeToolNamesByPreview,
                toolRateLimiters: $toolRateLimiters,
            ));
    }

    /**
     * @param array<string, array<string, McpTool>> $toolsByName
     * @param array<string, string> $toolPermissions
     * @param array<string, string> $toolCategories
     * @param list<string> $readOnlyToolNames
     * @param array<string, string> $writeToolNamesByPreview
     * @param array<string, string> $toolRateLimiters
     *
     * @return array<string, Definition>
     */
    private function createToolDefinitions(
        array $toolsByName,
        array $toolPermissions,
        array $toolCategories,
        array $readOnlyToolNames,
        array $writeToolNamesByPreview,
        array $toolRateLimiters,
    ): array {
        $previewToolNames = array_flip($writeToolNamesByPreview);
        $definitions = [];
        foreach ($toolsByName as $toolName => $tools) {
            $tool = array_first($tools);
            $definitions[$toolName] = new Definition(McpToolDefinition::class, [
                '$permission' => $toolPermissions[$toolName] ?? null,
                '$readOnly' => in_array(needle: $toolName, haystack: $readOnlyToolNames, strict: true),
                '$category' => $toolCategories[$toolName] ?? null,
                '$previewOf' => $writeToolNamesByPreview[$toolName] ?? null,
                '$previewTool' => $previewToolNames[$toolName] ?? null,
                '$rateLimiter' => $toolRateLimiters[$toolName] ?? null,
                '$signature' => $tool instanceof AsMcpTool ? $this->createSignatureDefinition($tools) : null,
                '$explicitGrant' => $tool instanceof AsMcpTool && $tool->explicitGrant,
            ]);
        }

        return $definitions;
    }

    /**
     * @param array<string, McpTool> $tools
     */
    private function createSignatureDefinition(array $tools): Definition
    {
        $signature = $this->resolveToolSignature($tools);

        return new Definition(McpToolSignature::class, [
            '$inputParameterName' => $signature->inputParameterName,
            '$inputClass' => $signature->inputClass,
            '$outputClass' => $signature->outputClass,
        ]);
    }

    /**
     * @return array<string, array<string, McpTool>>
     */
    private function resolveToolsByName(ContainerBuilder $container): array
    {
        $toolsByName = [];
        foreach ($this->resolveTaggedMethods($container, self::TOOL_TAG) as $toolMethod => $reflection) {
            $tool = $this->resolveElementAttribute($reflection, McpTool::class);
            if (null === $tool) {
                continue;
            }
            $toolsByName[ElementMetadataResolver::resolveName($reflection, $tool->name)][$toolMethod] = $tool;
        }

        return $toolsByName;
    }

    /**
     * @param array<string, array<string, McpTool>> $toolsByName
     */
    private function assertToolNamesAreUnique(array $toolsByName): void
    {
        foreach ($toolsByName as $toolName => $tools) {
            if (count($tools) > 1) {
                throw McpConfigurationException::createDuplicateToolName((string) $toolName, array_keys($tools));
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
                throw McpConfigurationException::createInvalidToolName((string) $toolName, array_keys($tools));
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
            if ($tool instanceof AsMcpTool && is_string($tool->permission)) {
                $toolPermissions[$toolName] = (string) $container->getParameterBag()->resolveValue($tool->permission);
            }
        }

        return $toolPermissions;
    }

    /**
     * @param array<string, array<string, McpTool>> $toolsByName
     * @param list<string> $readOnlyToolNames
     *
     * @return array<string, string> tool name => rate limiter name
     */
    private function configureWriteRateLimiter(ContainerBuilder $container, array $toolsByName, array $readOnlyToolNames): array
    {
        $toolRateLimiters = [];
        $rateLimiters = [];
        foreach ($toolsByName as $toolName => $tools) {
            $tool = array_first($tools);
            if (false === $tool instanceof AsMcpTool || null === $tool->rateLimiter) {
                continue;
            }
            if (in_array(needle: $toolName, haystack: $readOnlyToolNames, strict: true)) {
                throw McpConfigurationException::createReadToolRateLimiter($toolName);
            }
            $rateLimiterId = sprintf(self::RATE_LIMITER_ID_FORMAT, $tool->rateLimiter);
            if (false === $container->has($rateLimiterId)) {
                throw McpConfigurationException::createUnknownRateLimiter($toolName, $tool->rateLimiter);
            }
            $toolRateLimiters[$toolName] = $tool->rateLimiter;
            $rateLimiters[$tool->rateLimiter] = new Reference($rateLimiterId);
        }
        $container
            ->getDefinition(McpWriteRateLimiter::class)
            ->replaceArgument('$rateLimiters', ServiceLocatorTagPass::register($container, $rateLimiters));

        return $toolRateLimiters;
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
                throw McpConfigurationException::createToolWithoutPermission($toolName);
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
        return array_filter(
            $toolsByName,
            static fn (array $tools): bool => array_any(
                $tools,
                static fn (McpTool $tool): bool => true === $tool->annotations?->readOnlyHint,
            ),
        ) |> array_keys(...);
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
                throw McpConfigurationException::createInvalidCategory(category: $tool->category, toolName: $toolName, pattern: self::CATEGORY_PATTERN);
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
                throw McpConfigurationException::createSecondPreview($tool->previewOf, [$otherPreviewName, (string) $toolName]);
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
            throw McpConfigurationException::createPreviewOfUnknownTool($previewName, $writeToolName);
        }
        if (
            in_array(needle: $writeToolName, haystack: $readOnlyToolNames, strict: true)
            || false === array_key_exists($writeToolName, $toolCategories)
        ) {
            throw McpConfigurationException::createPreviewOfReadTool($previewName, $writeToolName);
        }
        if (false === in_array(needle: $previewName, haystack: $readOnlyToolNames, strict: true)) {
            throw McpConfigurationException::createWritablePreview($previewName, $writeToolName);
        }
        if (array_key_exists($previewName, $toolPermissions)) {
            throw McpConfigurationException::createPreviewWithPermission($previewName, $writeToolName);
        }
        if (StringHelper::isNotSame($preview->category, $toolCategories[$writeToolName])) {
            throw McpConfigurationException::createPreviewInOtherCategory(previewToolName: $previewName, writeToolName: $writeToolName, category: $toolCategories[$writeToolName]);
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
                throw McpConfigurationException::createWriteToolWithoutPreview($toolName);
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
        if (false === is_string($previewOutputClass) || false === is_a(object_or_class: $previewOutputClass, class: McpWritePreviewInterface::class, allow_string: true)) {
            throw McpConfigurationException::createPreviewResponseWithoutConfirmation($previewName, $toolName);
        }
        $inputClass = $signature->inputClass;
        $previewInputClass = $previewSignature->inputClass;
        if (
            false === is_string($inputClass)
            || false === is_string($previewInputClass)
            || false === is_a(object_or_class: $inputClass, class: McpPreviewHashAwareInterface::class, allow_string: true)
            || false === is_a(object_or_class: $inputClass, class: $previewInputClass, allow_string: true)
        ) {
            throw McpConfigurationException::createWriteInputNotPreviewInput($toolName, $previewName);
        }
    }

    /**
     * @param array<string, McpTool> $tools
     */
    private function resolveToolSignature(array $tools): McpToolSignature
    {
        $toolMethod = (string) array_key_first($tools);
        $class = explode(self::ELEMENT_METHOD_SEPARATOR, $toolMethod)[0];

        return new McpToolSignatureResolver()
            ->resolve($class)
            ?? throw McpConfigurationException::createNotAsMcpTool($toolMethod);
    }
}
