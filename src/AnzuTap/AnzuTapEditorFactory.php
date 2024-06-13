<?php

namespace AnzuSystems\CommonBundle\AnzuTap;


use AnzuSystems\CommonBundle\AnzuTap\Transformer\Mark\AnzuMarkTransformerInterface;
use AnzuSystems\CommonBundle\AnzuTap\Transformer\Node\AnzuNodeTransformerInterface;
use AnzuSystems\CommonBundle\AnzuTap\Transformer\Node\XRemoveTransformer;
use AnzuSystems\CommonBundle\AnzuTap\Transformer\Node\XSkipTransformer;
use AnzuSystems\CommonBundle\AnzuTap\TransformerProvider\AnzuTapMarkNodeTransformerProvider;
use AnzuSystems\CommonBundle\AnzuTap\TransformerProvider\AnzuTapNodeTransformerProvider;
use AnzuSystems\CommonBundle\Model\AnzuTap\AnzuTapEditor;
use AnzuSystems\CommonBundle\Model\AnzuTap\Configuration\AnzuTapConfigurationInterface;
use Symfony\Component\DependencyInjection\Attribute\TaggedIterator;
use Symfony\Component\DependencyInjection\Attribute\TaggedLocator;
use Symfony\Component\DependencyInjection\ServiceLocator;

final class AnzuTapEditorFactory
{
    /**
     * @var iterable<AnzuNodeTransformerInterface>
     */
    private iterable $nodeTransformers;

    /**
     * @var iterable<AnzuMarkTransformerInterface>
     */
    private iterable $markTransformers;

    public function __construct(
        #[TaggedIterator(tag: AnzuNodeTransformerInterface::class)]
        iterable $nodeTransformers,
        #[TaggedIterator(tag: AnzuMarkTransformerInterface::class)]
        iterable $markTransformers,
//        private readonly AnzuTapBodyShaker $shaker,
        private readonly XRemoveTransformer $removeTransformer,
        private readonly XSkipTransformer $skipTransformer,
    ) {
        $this->nodeTransformers = $nodeTransformers;
        $this->markTransformers = $markTransformers;
    }

    public function createEditor(AnzuTapConfigurationInterface $configuration): AnzuTapEditor
    {
        return new AnzuTapEditor(
            transformerProvider: new AnzuTapNodeTransformerProvider(
                $this->getDefaultTransformer($configuration),
                $this->resolveNodeTransformer($configuration),
            ),
//            shaker: $this->shaker,
            markTransformerProvider: new AnzuTapMarkNodeTransformerProvider(
                $this->resolveMarkTransformer($configuration)
            ),
        );
    }

//    // Todo get transformer from tagged locator
//    private function getDefaultTransformer(AnzuTapConfigurationInterface $configuration): AnzuNodeTransformerInterface
//    {
//        if ($this->skipTransformer::class === $configuration->getDefaultTransformer()) {
//            return $this->skipTransformer;
//        }
//        if ($this->removeTransformer::class === $configuration->getDefaultTransformer()) {
//            return $this->removeTransformer;
//        }
//
//        return $this->unknownTransformer;
//    }

    private function resolveNodeTransformer(AnzuTapConfigurationInterface $configuration): array
    {
        $resolvedNodeTransformers = [];

        /** @var AnzuNodeTransformerInterface $nodeTransformer */
        foreach ($this->nodeTransformers as $nodeTransformer) {
            if (false === in_array($nodeTransformer::class, $configuration->getAllowedNodeTransformers(), true)) {
                continue;
            }
            foreach ($nodeTransformer::getSupportedNodeNames() as $nodeName) {
                if (array_key_exists($nodeName, $resolvedNodeTransformers)) {
                    continue;
                }

                $resolvedNodeTransformers[$nodeName] = $nodeTransformer;
            }
        }

        foreach ($configuration->getRemove() as $removeName) {
            $resolvedNodeTransformers[$removeName] = $this->removeTransformer;
        }

        foreach ($configuration->getSkip() as $skipName) {
            $resolvedNodeTransformers[$skipName] = $this->skipTransformer;
        }

        return $resolvedNodeTransformers;
    }

    private function resolveMarkTransformer(AnzuTapConfigurationInterface $configuration): array
    {
        $resolvedMarkTransformers = [];

        /** @var AnzuMarkTransformerInterface $nodeTransformer */
        foreach ($this->markTransformers as $nodeTransformer) {
            if (false === in_array($nodeTransformer::class, $configuration->getAllowedMarkTransformers(), true)) {
                continue;
            }
            foreach ($nodeTransformer::getSupportedNodeNames() as $nodeName) {
                if (array_key_exists($nodeName, $resolvedMarkTransformers)) {
                    continue;
                }

                $resolvedMarkTransformers[$nodeName] = $nodeTransformer;
            }
        }

        return $resolvedMarkTransformers;
    }
}
