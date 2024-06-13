<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Model\AnzuTap\Node;

final class AnzuTapTableNode extends AnzuTapNode
{
    public const string NODE_NAME = 'table';

    public function __construct(?array $attrs = null)
    {
        parent::__construct(
            type: self::NODE_NAME,
            attrs: $attrs
        );
    }

    public function addContent(AnzuTapNodeInterface $node): AnzuTapNodeInterface
    {
        if ($node instanceof AnzuTapParagraphNode) {
            if (isset($this->attrs['caption']) && false === empty($this->attrs['caption'])) {
                return $this;
            }

            $this->attrs['caption'] = (string) $node->getNodeText();

            return $this;
        }
        // only table row is supported
        if (false === ($node instanceof AnzuTapTableRowNode)) {
            return $this;
        }

        return parent::addContent($node);
    }
}
