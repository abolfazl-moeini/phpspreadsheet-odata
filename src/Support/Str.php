<?php

declare(strict_types=1);

namespace WPDev\PhpSpreadsheetOData\Support;

final class Str
{
    public static function startsWith(string $haystack, string $needle): bool
    {
        if ($needle === '') {
            return true;
        }

        return strncmp($haystack, $needle, strlen($needle)) === 0;
    }

    public static function endsWith(string $haystack, string $needle): bool
    {
        if ($needle === '') {
            return true;
        }

        $length = strlen($needle);

        if ($length > strlen($haystack)) {
            return false;
        }

        return substr($haystack, -$length) === $needle;
    }

    public static function contains(string $haystack, string $needle): bool
    {
        if ($needle === '') {
            return true;
        }

        return strpos($haystack, $needle) !== false;
    }

    /**
     * @param mixed $value
     */
    public static function toString($value): string
    {
        if (is_scalar($value)) {
            return (string) $value;
        }

        if (is_object($value) && method_exists($value, '__toString')) {
            return (string) $value;
        }

        return '';
    }
}
