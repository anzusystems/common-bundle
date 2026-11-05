<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\DependencyInjection\CompilerPass;

use AnzuSystems\CommonBundle\Mcp\Controller\McpController;
use AnzuSystems\CommonBundle\Mcp\Exception\McpConfigurationException;
use Mcp\Schema\ServerCapabilities;
use Symfony\Component\Config\Resource\FileResource;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\Filesystem\Filesystem;

final class McpCompilerPass implements CompilerPassInterface
{
    use McpServerNameTrait;

    public const string SERVER_NAME_PARAM = 'anzu_systems_common.mcp.server_name';
    public const string INSTRUCTIONS_FILE_PARAM = 'anzu_systems_common.mcp.instructions_file';
    public const string BUILDER_ID_FORMAT = 'mcp.server.%s.builder';
    public const string SESSION_CACHE_ID = 'anzu_systems_common.mcp.session_cache';

    // 0 means forever in the SDK; a client that opens a listen stream anyway gets its ack and complete within one 250 ms tick.
    public const float SUBSCRIPTION_LIFETIME_SECONDS = 0.001;

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

        $serverName = $this->resolveServerName($container);
        $container
            ->setAlias(sprintf('mcp.server.%s.controller', $serverName), McpController::class)
            ->setPublic(true);
        $this->setInstructionsFromFile($container, $serverName);
        $this->configureServerBuilder($container, $serverName);
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
        $builderId = $this->resolveBuilderId($serverName);
        if (false === $container->hasDefinition($builderId)) {
            throw McpConfigurationException::createServerNotConfigured($serverName, self::SERVER_NAME_PARAM);
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
        $builderId = $this->resolveBuilderId($serverName);
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
}
