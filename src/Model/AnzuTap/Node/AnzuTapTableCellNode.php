<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Model\AnzuTap\Node;

class AnzuTapTableCellNode extends AnzuTapNode
{
    public const string NODE_NAME = 'tableCell';

    public function __construct(?array $attrs = null)
    {
        parent::__construct(
            type: $this->getNodeName(),
            attrs: $attrs
        );
    }

    public function addContent(AnzuTapNodeInterface $node): AnzuTapNodeInterface
    {
        if (false === (AnzuTapParagraphNode::NODE_NAME === $node->getType())) {
            $paragraph = $this->upsertFirstContentParagraph();

            return $paragraph->addContent($node);
        }

        return parent::addContent($node);
    }

    protected function getNodeName(): string
    {
        return self::NODE_NAME;
    }
}
