<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Model\AnzuTap\Node;

class AnzuTapListItemNode extends AnzuTapNode
{
    public const string NODE_NAME = 'listItem';

    public function __construct(?array $attrs = null)
    {
        parent::__construct(
            type: self::NODE_NAME,
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
}
