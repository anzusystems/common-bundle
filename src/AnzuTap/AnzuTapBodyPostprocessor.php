<?php

namespace AnzuSystems\CommonBundle\AnzuTap;

use AnzuSystems\CommonBundle\Model\AnzuTap\Node\AnzuTapDocNode;
use AnzuSystems\CommonBundle\Model\AnzuTap\Node\AnzuTapNodeInterface;
use AnzuSystems\CommonBundle\Model\AnzuTap\Node\AnzuTapParagraphNode;
use AnzuSystems\CommonBundle\Model\AnzuTap\Node\AnzuTapTextNode;

class AnzuTapBodyPostprocessor
{
    public const array PARAGRAPH_ALLOWED_CONTENT_TYPES = [
        AnzuTapNodeInterface::TEXT,
        AnzuTapNodeInterface::HARD_BREAK,
        'embedExternalImageInline',
        'embedImageInline',
    ];

    // todo: make this configurable in new article-bundle
    private const array NODES_TO_SHAKE = [
        'button',
        'contentLock',
        'horizontalRule',
        'embedImage',
        'embedExternalImage',
        'embedVideo',
        'embedAudio',
        'embedPoll',
        'embedQuiz',
        'embedRelated',
        'embedReview',
        'embedTimeline',
        'embedWeather',
        'embedExternal',
        'embedCrossBox',
    ];

    public function postprocess(AnzuTapDocNode $body): void
    {
        $this->shakeNodes($body, self::NODES_TO_SHAKE);
        $this->fixParagraphs($body);
        $this->removeInvalidNodes($body);
    }


    protected function removeInvalidNodes(AnzuTapDocNode $body): void
    {
        $body->setContent(array_filter(
            $body->getContent(),
            static fn (AnzuTapNodeInterface $node): bool => $node->isValid()
        ));
    }

    protected function fixParagraphs(AnzuTapNodeInterface $body): void
    {
        foreach ($body->getContent() as $node) {
            if ($node->getType() === AnzuTapParagraphNode::NODE_NAME) {
                $this->fixNode($node, self::PARAGRAPH_ALLOWED_CONTENT_TYPES);
            }

            $this->fixParagraphs($node);
        }
    }

    protected function fixNode(AnzuTapNodeInterface $node, array $allowedNodes): void
    {
        $children = [];
        foreach ($node->getContent() as $child) {
            if (false === in_array($child->getType(), $allowedNodes, true)) {
                $text = $node->getNodeText();
                if (is_string($text)) {
                    $textNode = new AnzuTapTextNode($text);
                    $textNode->setParent($node);
                    $children[] = $textNode;
                }

                continue;
            }

            $children[] = $child;
        }

        $node->setContent($children);
    }

    /**
     * @param array<int, string> $nodeTypesToShake
     */
    protected function shakeNodes(AnzuTapDocNode $body, array $nodeTypesToShake): void
    {
        $topLevelNodes = [];

        foreach ($body->getContent() as $node) {
            $contentCount = count($node->getContent());
            $shakenNodes = $this->shakeAndSplitChildNodes($node, $nodeTypesToShake);

            // Check if root node was paragraph and after shaking, it lost content.
            foreach ($shakenNodes as $shakenNode) {
                if ($shakenNode === $node &&
                    $shakenNode->getType() === AnzuTapParagraphNode::NODE_NAME &&
                    0 === count($shakenNode->getContent()) &&
                    0 < $contentCount
                ) {
                    continue;
                }

                $topLevelNodes[] = $shakenNode;
                $shakenNode->setParent($body);
            }
        }

        $body->setContent($topLevelNodes);
    }

    /**
     * @param array<int, string> $nodeTypesToShake
     *
     * @return array<int, AnzuTapNodeInterface>
     */
    protected function shakeAndSplitChildNodes(AnzuTapNodeInterface $rootNode, array $nodeTypesToShake): array
    {
        $nodesToKeep = [];
        $resNodes = [];

        $moveShakingNodesBefore = true;
        foreach ($rootNode->getContent() as $node) {
            $isShakingNode = in_array($node->getType(), $nodeTypesToShake, true);

            if ($isShakingNode) {
                $resNodes[] = $node;
            }

            if (false === empty($node->getContent())) {
                $resNodes = [...$resNodes, ...$this->deepShake($node, $nodeTypesToShake)];
            }

            if (false === $isShakingNode) {
                $nodesToKeep[] = $node;
            }

            // Add origin node to right position
            if ($moveShakingNodesBefore && false === $isShakingNode) {
                $resNodes[] = $rootNode;
                $moveShakingNodesBefore = false;
            }
        }

        // origin node was not added to res nodes
        if ($moveShakingNodesBefore) {
            $resNodes[] = $rootNode;
        }

        // remove shaking nodes from content
        $rootNode->setContent($nodesToKeep);

        return $resNodes;
    }

    /**
     * @param array<int, string> $nodeTypesToShake
     *
     * @return array<int, AnzuTapNodeInterface>
     */
    protected function deepShake(AnzuTapNodeInterface $rootNode, array $nodeTypesToShake): array
    {
        $nodesToShake = [];
        $nodesToKeep = [];

        foreach ($rootNode->getContent() as $node) {
            $isShakingNode = in_array($node->getType(), $nodeTypesToShake, true);

            if ($isShakingNode) {
                $nodesToShake[] = $node;
            }

            if (false === empty($node->getContent())) {
                $nodesToShake = array_merge($nodesToShake, $this->deepShake($node, $nodeTypesToShake));
            }

            if (false === $isShakingNode) {
                $nodesToKeep[] = $node;
            }
        }
        $rootNode->setContent($nodesToKeep);

        return $nodesToShake;
    }
}
