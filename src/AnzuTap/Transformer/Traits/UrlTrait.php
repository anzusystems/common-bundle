<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\AnzuTap\Transformer\Traits;

use Symfony\Component\String\Slugger\AsciiSlugger;

trait UrlTrait
{
    private const int URL_MAX_LENGHT = 2_048;
    public static function isUrlInvalid(string $href, bool $allowAnchor = true): bool
    {
        if (strlen($href) > self::URL_MAX_LENGHT || empty($href)) {
            return true;
        }
        if ($allowAnchor && preg_match('/^#\w+/', $href)) {
            return false;
        }
        if (false === str_starts_with($href, 'http')) {
            return false;
        }

        return false === filter_var($href, FILTER_VALIDATE_URL);
    }

    public static function getSanitizedAnchor(string $anchor): string
    {
        return (new AsciiSlugger())
            ->slug($anchor)
            ->lower()
            ->ensureStart('pp-')
            ->truncate(64)
            ->trimEnd('-')
            ->toString()
        ;
    }
}
