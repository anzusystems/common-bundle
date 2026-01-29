<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Helper;

use Symfony\Component\String\UnicodeString;

final class StringHelper
{
    private const string SHORTHAND_SUFFIX = '...';

    public static function isNotEmpty(string $string): bool
    {
        return false === self::isEmpty($string);
    }

    public static function isSame(string $string1, string $string2): bool
    {
        return $string1 === $string2;
    }

    public static function isNotSame(string $string1, string $string2): bool
    {
        return false === self::isSame($string1, $string2);
    }

    public static function isEmpty(string $string): bool
    {
        return '' === $string;
    }

    public static function cutString(
        string $string,
        int $maxLength,
        bool $trim = true,
    ): string {
        if ($trim) {
            $string = trim($string);
        }

        return mb_substr($string, 0, $maxLength);
    }

    public static function shorthandString(
        string $string,
        int $maxLength,
    ): string {
        if (mb_strlen($string) > $maxLength) {
            return mb_substr($string, 0, $maxLength) . self::SHORTHAND_SUFFIX;
        }

        return $string;
    }

    public static function extractFirstLetter(string $string): string
    {
        return (new UnicodeString(trim(preg_replace('/[^\p{L}]+/u', '', $string))))
            ->slice(0, 1)
            ->ascii()
            ->lower()
            ->toString();
    }
}
