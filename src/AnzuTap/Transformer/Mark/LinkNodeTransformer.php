<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\AnzuTap\Transformer\Mark;

use AnzuSystems\CommonBundle\AnzuTap\Transformer\Traits\AttributesTrait;
use AnzuSystems\CommonBundle\AnzuTap\Transformer\Traits\UrlTrait;
use DOMElement;

class LinkNodeTransformer extends AbstractMarkNodeTransformer
{
    use AttributesTrait;
    use UrlTrait;

    private const string MARK_LINK = 'link';
    private const string NODE_URL = 'url';
    private const string NODE_EMAIL = 'email';
    private const string NODE_A = 'a';

    private const array LINK_MAP = [
        self::NODE_URL => self::MARK_LINK,
        self::NODE_EMAIL => self::MARK_LINK,
        self::NODE_A => self::MARK_LINK,
    ];

    public static function getSupportedNodeNames(): array
    {
        return [
            self::NODE_URL,
            self::NODE_EMAIL,
            self::NODE_A,
        ];
    }

    public function supports(DOMElement $element): bool
    {
        return false === $this->isButton($element);
    }

    public function transform(DOMElement $element): array|null
    {
        // todo

        if (in_array($element->nodeName, [self::NODE_A, self::NODE_URL], true)) {
            $attrs = $this->getAnchorAttrs($element);
            if (is_array($attrs)) {
                return $this->getMarkNode(
                    nodeName: $element->nodeName,
                    map: self::LINK_MAP,
                    attributes: $attrs
                );
            }

            return null;
        }

        if (self::NODE_EMAIL === $element->nodeName) {
            $href = $element->getAttribute('href');
            if (str_starts_with($href, 'mailto:')) {
                $href = substr($href, 7);
            }

            return $this->getMarkNode(
                nodeName: $element->nodeName,
                map: self::LINK_MAP,
                attributes: [
                    'href' => $href,
                    'variant' => 'email',
                ]
            );
        }

        return null;
    }

    public function getAnchorAttrs(DOMElement $element): ?array
    {
        $attrs = $this->getAttrs(
            ['href', 'clickthrough', 'size', 'target', 'rel'],
            $element, [
            'size' => ['large', 'small'],
            'target' => ['_blank'],
            'rel' => ['nofollow'],
        ]);

        if (self::isUrlInvalid($attrs['href'] ?? '')) {
            return null;
        }

        $attrs['variant'] = str_starts_with($attrs['href'], 'http') ? 'link' : 'anchor';
        if ($attrs['variant'] === 'anchor') {
            $attrs['href'] = '#' . self::getSanitizedAnchor($attrs['href']);
        }

        $attrs['nofollow'] = isset($attrs['rel']);
        $attrs['external'] = isset($attrs['target']);
        if (isset($attrs['clickthrough'])) {
            $attrs['external'] = true;
            unset($attrs['clickthrough']);
        }

        unset($attrs['rel'], $attrs['target']);

        return $attrs;
    }

    public function isButton(DOMElement $element): bool
    {
        return '1' === $element->getAttribute('btn');
    }
}
