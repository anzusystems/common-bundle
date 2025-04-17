<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Model\AnzuTap\Node;

class AnzuTapTableCellNode extends AnzuTapNode
{
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

    protected function getMarksAllowList(): array
    {
        return [];
    }

    protected function getNodeName(): string
    {
        return self::TABLE_CELL;
    }
}
