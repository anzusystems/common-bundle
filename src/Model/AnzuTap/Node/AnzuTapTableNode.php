<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Model\AnzuTap\Node;

final class AnzuTapTableNode extends AnzuTapNode
{
    public const string CAPTION_ATTR = 'caption';
    public function __construct(?array $attrs = null)
    {
        parent::__construct(
            type: self::TABLE,
            attrs: $attrs
        );
    }

    public function addContent(AnzuTapNodeInterface $node): AnzuTapNodeInterface
    {
        if ($node instanceof AnzuTapParagraphNode) {
            if (isset($this->attrs[self::CAPTION_ATTR]) && false === empty($this->attrs[self::CAPTION_ATTR])) {
                return $this;
            }

            $this->attrs[self::CAPTION_ATTR] = (string) $node->getNodeText();

            return $this;
        }
        // only table row is supported
        if (false === ($node instanceof AnzuTapTableRowNode)) {
            return $this;
        }

        return parent::addContent($node);
    }

    protected function getMarksAllowList(): array
    {
        return [];
    }
}
