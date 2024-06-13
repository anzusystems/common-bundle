<?php

namespace AnzuSystems\CommonBundle\DependencyInjection\CompilerPass;

use AnzuSystems\CommonBundle\AnzuSystemsCommonBundle;
use AnzuSystems\CommonBundle\AnzuTap\Transformer\Mark\AnzuMarkTransformerInterface;
use AnzuSystems\CommonBundle\AnzuTap\Transformer\Node\AnzuNodeTransformerInterface;
use AnzuSystems\CommonBundle\AnzuTap\Transformer\Node\XRemoveTransformer;
use AnzuSystems\CommonBundle\AnzuTap\Transformer\Node\XSkipTransformer;
use AnzuSystems\CommonBundle\AnzuTap\TransformerProvider\NodeTransformerProviderInterface;
use AnzuSystems\CommonBundle\DependencyInjection\AnzuSystemsCommonExtension;
use AnzuSystems\CommonBundle\DependencyInjection\Configuration;
use AnzuSystems\CommonBundle\Event\Listener\ExceptionListener;
use AnzuSystems\CommonBundle\Model\AnzuTap\AnzuTapEditor;
use AnzuSystems\CommonBundle\Model\User\UserDto;
use Symfony\Component\DependencyInjection\Argument\IteratorArgument;
use Symfony\Component\DependencyInjection\Argument\ServiceLocatorArgument;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

final class AnzuTapEditorCompilerPass implements CompilerPassInterface
{
    public function __construct(
        private AnzuSystemsCommonExtension $extension
    ) {
    }

    public function process(ContainerBuilder $container): void
    {
        $editors = $this->extension->getProcessedConfig()['editors'] ?? [];

        dump('Processing compiler pass');

        foreach ($editors as $editorName => $editorConfig) {
            $definitionName = sprintf('%s $%sEditor', AnzuTapEditor::class, $editorName);

            if (false === $container->hasDefinition($definitionName)) {
                continue;
            }

            $allowedNodeTransformers = [];
            /** @var class-string<AnzuNodeTransformerInterface> $serviceName */
            foreach ($editorConfig[Configuration::EDITOR_ALLOWED_NODE_TRANSFORMERS] ?? [] as $serviceName) {
                foreach ($serviceName::getSupportedNodeNames() as $supportedNodeName) {
                    $allowedNodeTransformers[$supportedNodeName] = new Reference($serviceName);
                }
            }

            foreach ($editorConfig[Configuration::EDITOR_REMOVE_NODES] ?? [] as $nodeName) {
                $allowedNodeTransformers[$nodeName] = new Reference(XRemoveTransformer::class);
            }

            foreach ($editorConfig[Configuration::EDITOR_SKIP_NODES] ?? [] as $nodeName) {
                $allowedNodeTransformers[$nodeName] = new Reference(XSkipTransformer::class);
            }

            $allowedMarkTransformers = [];
            /** @var class-string<AnzuMarkTransformerInterface> $serviceName */
            foreach ($editorConfig[Configuration::EDITOR_ALLOWED_MARK_TRANSFORMERS] ?? [] as $serviceName) {
                foreach ($serviceName::getSupportedNodeNames() as $supportedNodeName) {
                    $allowedMarkTransformers[$supportedNodeName] = new Reference($serviceName);
                }
            }

            $container
                ->getDefinition($definitionName)
                ->setArgument('$resolvedNodeTransformers', new ServiceLocatorArgument($allowedNodeTransformers))
                ->setArgument('$resolvedMarkTransformers', new ServiceLocatorArgument($allowedMarkTransformers))
            ;
        }
    }
}
