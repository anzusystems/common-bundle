<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Model\AnzuTap\Node;

final class AnzuTapDocNode extends AnzuTapNode
{
    public function __construct(
    ) {
        parent::__construct(self::DOC);
    }

    public function addContent(AnzuTapNodeInterface $node): AnzuTapNodeInterface
    {
        if (self::HARD_BREAK === $node->getType()) {
            return $this;
        }

        if (self::TEXT === $node->getType()) {
            $paragraph = $this->upsertFirstContentParagraph();

            return $paragraph->addContent($node);
        }

        return parent::addContent($node);
    }
}
