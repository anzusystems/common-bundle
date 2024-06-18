<?php

namespace AnzuSystems\CommonBundle\AnzuTap;

use AnzuSystems\CommonBundle\Model\AnzuTap\Node\AnzuTapDocNode;
use AnzuSystems\CommonBundle\Model\AnzuTap\Node\AnzuTapNodeInterface;
use AnzuSystems\CommonBundle\Model\AnzuTap\Node\AnzuTapParagraphNode;

class AnzuTapBodyPostprocessor
{
    // todo refactor configuration
    private const array NODES_TO_SHAKE = ['button'];

    public function shakeNodes(AnzuTapDocNode $body): void
    {
        $topLevelNodes = [];

        foreach ($body->getContent() as $node) {
            $nodesToShake = $this->getNodesToShake($node);

            // Check if root node was paragraph and after shaking, it lost content.
            if (false === (
                0 < count($nodesToShake) &&
                0 === count($node->getContent()) &&
                $node->getType() === AnzuTapParagraphNode::NODE_NAME
            )
            ) {
                $topLevelNodes[] = $node;
            }

            foreach ($nodesToShake as $nodeToShake) {
                $topLevelNodes[] = $nodeToShake;
                $nodeToShake->setParent($body);
            }
        }

        $body->setContent($topLevelNodes);
    }

    /**
     * @return array<int, AnzuTapNodeInterface>
     */
    private function getNodesToShake(AnzuTapNodeInterface $rootNode): array
    {
        $nodesToShake = [];
        $nodesToKeep = [];

        foreach ($rootNode->getContent() as $node) {
            $isShakingNode = in_array($node->getType(), self::NODES_TO_SHAKE, true);

            if ($isShakingNode) {
                $nodesToShake[] = $node;
            }

            if (false === empty($node->getContent())) {
                $nodesToShake = array_merge($nodesToShake, $this->getNodesToShake($node));
            }

            if (false === $isShakingNode) {
                $nodesToKeep[] = $node;
            }
        }
        $rootNode->setContent($nodesToKeep);

        return $nodesToShake;
    }
}
