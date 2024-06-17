<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Model\AnzuTap\Node;

final class AnzuTapParagraphNode extends AnzuTapNode
{
    public const string NODE_NAME = 'paragraph';

    // todo paragraph allowed content types config
    public const array PARAGRAPH_ALLOWED_CONTENT_TYPES = ['text', 'hardBreak', 'embedExternalImageInline', 'embedImageInline', 'button'];

    public function __construct(
        ?array $attrs = null,
    ) {
        parent::__construct(
            type: self::NODE_NAME,
            attrs: $attrs,
        );
    }


    public function addContent(AnzuTapNodeInterface $node): AnzuTapNodeInterface
    {
        if (false === in_array($node->getType(), self::PARAGRAPH_ALLOWED_CONTENT_TYPES, true)) {
            $text = $node->getNodeText();
            if (is_string($text)) {
                $textNode = new AnzuTapTextNode($text);

                return parent::addContent($textNode);
            }

            return $this;
        }

        return parent::addContent($node);
    }
}
