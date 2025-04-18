<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\AnzuTap;

use AnzuSystems\CommonBundle\AnzuTap\Transformer\Mark\AnzuMarkTransformerInterface;
use AnzuSystems\CommonBundle\AnzuTap\Transformer\Node\AnzuNodeTransformerInterface;
use AnzuSystems\CommonBundle\AnzuTap\TransformerProvider\MarkTransformerProviderInterface;
use AnzuSystems\CommonBundle\AnzuTap\TransformerProvider\NodeTransformerProviderInterface;
use AnzuSystems\CommonBundle\Entity\Interfaces\EmbedKindInterface;
use AnzuSystems\CommonBundle\Model\AnzuTap\AnzuTapBody;
use AnzuSystems\CommonBundle\Model\AnzuTap\EmbedContainer;
use AnzuSystems\CommonBundle\Model\AnzuTap\Node\AnzuTapDocNode;
use AnzuSystems\CommonBundle\Model\AnzuTap\Node\AnzuTapEmbedNodeNode;
use AnzuSystems\CommonBundle\Model\AnzuTap\Node\AnzuTapNode;
use AnzuSystems\CommonBundle\Model\AnzuTap\Node\AnzuTapNodeInterface;
use DOMDocument;
use DOMElement;
use DOMNode;
use DOMText;
use Symfony\Component\DependencyInjection\ServiceLocator;

final class AnzuTapEditor
{
    private array $storedMarks = [];
    private EmbedContainer $embedContainer;

    public function __construct(
        private readonly NodeTransformerProviderInterface $transformerProvider,
        private readonly MarkTransformerProviderInterface $markTransformerProvider,
        private readonly ServiceLocator $resolvedMarkTransformers,
        private readonly ServiceLocator $resolvedNodeTransformers,
        private readonly AnzuNodeTransformerInterface $defaultTransformer,
        private readonly AnzuTapBodyPreprocessor $preprocessor,
        private readonly AnzuTapBodyPostprocessor $postprocessor,
    ) {
    }

    public function transformNode(DOMNode $node): AnzuTapDocNode
    {
        $this->clear();

        $body = new AnzuTapDocNode();
        $this->processChildren($node, $body, $body);
        $this->postprocessor->postprocess($body);

        return $body;
    }

    public function transform(string $data): AnzuTapBody
    {
        $data = $this->preprocessor->prepareBody($data);
        libxml_use_internal_errors(true);
        libxml_clear_errors();
        $this->clear();

        $document = new DOMDocument();
        $document->loadHTML($data);
        $bodyNode = $document->getElementsByTagName('body')->item(0);

        $body = new AnzuTapDocNode();
        if (false === (null === $bodyNode)) {
            $this->processChildren($bodyNode, $body, $body);
            $this->postprocessor->postprocess($body);
        }

        return new AnzuTapBody(
            $this->embedContainer,
            $body,
        );
    }

    public function getMarkTransformer(DOMElement | DOMText $element): ?AnzuMarkTransformerInterface
    {
        $key = $this->markTransformerProvider->getMarkTransformerKey($element);
        if ($this->resolvedMarkTransformers->has($key)) {
            return $this->resolvedMarkTransformers->get($key);
        }

        return null;
    }

    public function getNodeTransformer(DOMElement | DOMText $element): AnzuNodeTransformerInterface
    {
        $key = $this->transformerProvider->getNodeTransformerKey($element);

        if ($this->resolvedNodeTransformers->has($key)) {
            return $this->resolvedNodeTransformers->get($key);
        }

        return $this->defaultTransformer;
    }

    private function clear(): void
    {
        $this->storedMarks = [];
        $this->embedContainer = new EmbedContainer();
    }

    private function processChildren(DOMNode $node, AnzuTapNodeInterface $anzuTapParentNode, AnzuTapDocNode $root): array
    {
        $nodes = [];

        foreach ($node->childNodes as $childNode) {
            if (false === ($childNode instanceof DOMText || $childNode instanceof DOMElement)) {
                continue;
            }

            if ($childNode instanceof DOMElement) {
                $markTransformer = $this->getMarkTransformer($childNode);
                if ($markTransformer && $markTransformer->supports($childNode)) {
                    $mark = $markTransformer->transform($childNode);

                    if (null === $mark) {
                        continue;
                    }

                    $this->storedMarks[] = $mark;

                    if ($childNode->hasChildNodes()) {
                        $nodes = array_merge($nodes, $this->processChildren($childNode, $anzuTapParentNode, $root));
                    }

                    if ($mark) {
                        array_pop($this->storedMarks);
                    }

                    continue;
                }
            }

            $nodeTransformer = $this->getNodeTransformer($childNode);
            $anzuTapNode = $this->processNode($childNode, $nodeTransformer, $anzuTapParentNode);

            if (null === $anzuTapNode) {
                if ($childNode->hasChildNodes() && false === $nodeTransformer->skipChildren()) {
                    $nodes = array_merge($nodes, $this->processChildren($childNode, $anzuTapParentNode, $root));
                }

                continue;
            }

            if (false === empty($this->storedMarks)) {
                $anzuTapNode->setMarks($this->getUniqueMarks());
            }

            if ($childNode->hasChildNodes() && false === $nodeTransformer->skipChildren()) {
                $this->processChildren($childNode, $anzuTapNode, $root);
            }

            if (0 === count($anzuTapNode->getContent())) {
                if ($nodeTransformer->removeWhenEmpty()) {
                    continue;
                }

                $nodeTransformer->fixEmpty($anzuTapNode);
            }

            $anzuTapParentNode->addContent($anzuTapNode);
        }

        return $nodes;
    }

    private function getUniqueMarks(): array
    {
        $marks = [];
        foreach ($this->storedMarks as $mark) {
            $marks[$mark['type']] = $mark;
        }

        return array_values($marks);
    }

    private function processNode(
        DOMElement | DOMText $node,
        AnzuNodeTransformerInterface $nodeTransformer,
        AnzuTapNodeInterface $anzuTapParentNode,
    ): ?AnzuTapNodeInterface {
        /** @psalm-suppress PossiblyInvalidArgument */
        $transformedNode = $nodeTransformer->transform($node, $this->embedContainer, $anzuTapParentNode);

        if (null === $transformedNode) {
            return null;
        }

        if ($transformedNode instanceof EmbedKindInterface) {
            $this->embedContainer->addEmbed($transformedNode);

            return new AnzuTapEmbedNodeNode(
                type: $transformedNode->getNodeType(),
                id: $transformedNode->getId()
            );
        }

        return $transformedNode;
    }
}
