<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\AnzuTap\Transformer\Mark;

use DOMElement;

class MarkNodeTransformer extends AbstractMarkNodeTransformer
{
    public const string MARK_BOLD = 'bold';
    private const string MARK_ITALIC = 'italic';
    private const string MARK_UNDERLINE = 'underline';
    private const string MARK_STRIKE = 'strike';
    private const string MARK_SUBSCRIPT = 'subscript';
    private const string MARK_SUPERSCRIPT = 'superscript';

    private const string NODE_BOLD = 'b';
    private const string NODE_STRONG = 'strong';
    private const string NODE_ITALIC = 'i';
    private const string NODE_UNDERLINE = 'u';
    private const string NODE_STRIKE = 's';
    private const string NODE_SUBSCRIPT = 'sub';
    private const string NODE_SUPERSCRIPT = 'sup';
    private const string NODE_ABBR = 'abbr';
    private const string NODE_EMPHASE = 'em';

    private const array MARK_MAP = [
        self::NODE_BOLD => self::MARK_BOLD,
        self::NODE_STRONG => self::MARK_BOLD,
        self::NODE_ABBR => self::MARK_BOLD,
        self::NODE_ITALIC => self::MARK_ITALIC,
        self::NODE_EMPHASE => self::MARK_ITALIC,
        self::NODE_UNDERLINE => self::MARK_UNDERLINE,
        self::NODE_STRIKE => self::MARK_STRIKE,
        self::NODE_SUBSCRIPT => self::MARK_SUBSCRIPT,
        self::NODE_SUPERSCRIPT => self::MARK_SUPERSCRIPT,
    ];

    public static function getSupportedNodeNames(): array
    {
        return [
            self::NODE_BOLD,
            self::NODE_STRONG,
            self::NODE_ITALIC,
            self::NODE_UNDERLINE,
            self::NODE_STRIKE,
            self::NODE_SUBSCRIPT,
            self::NODE_SUPERSCRIPT,
            self::NODE_ABBR,
            self::NODE_EMPHASE,
        ];
    }

    public function transform(DOMElement $element): array|null
    {
        return $this->getMarkNode(
            nodeName: $element->nodeName,
            map: self::MARK_MAP,
        );
    }
}
