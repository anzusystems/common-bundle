<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\AnzuTap\Transformer\Node;

use AnzuSystems\CommonBundle\AnzuTap\Transformer\Traits\AttributesTrait;
use AnzuSystems\CommonBundle\Model\AnzuTap\EmbedContainer;
use AnzuSystems\CommonBundle\Model\AnzuTap\Node\AnzuTapNodeInterface;
use AnzuSystems\CommonBundle\Model\AnzuTap\Node\AnzuTapParagraphNode;
use AnzuSystems\CommonBundle\Model\AnzuTap\Node\AnzuTapTableCellNode;
use AnzuSystems\CommonBundle\Model\AnzuTap\Node\AnzuTapTableHeaderNode;
use DOMElement;

final class TableCellTransformer extends AbstractNodeTransformer
{
    use AttributesTrait;

    private const string NODE_NAME_TH = 'th';
    private const string NODE_NAME_TD = 'td';

    public static function getSupportedNodeNames(): array
    {
        return [
            self::NODE_NAME_TD,
            self::NODE_NAME_TH,
        ];
    }

    public function fixEmpty(AnzuTapNodeInterface $node): void
    {
        $node->addContent(new AnzuTapParagraphNode());
    }

    public function transform(DOMElement $element, EmbedContainer $embedContainer, AnzuTapNodeInterface $parent): AnzuTapNodeInterface
    {
        $attrs = $this->getAttrs(['colspan', 'rowspan'], $element);
        $attrs = empty($attrs) ? null : $attrs;
        $nodeName = $element->nodeName;

        if ($nodeName === self::NODE_NAME_TH) {
            return new AnzuTapTableHeaderNode(attrs: $attrs);
        }

        return new AnzuTapTableCellNode(attrs: $attrs);
    }
}
