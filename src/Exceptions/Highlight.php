<?php

declare(strict_types=1);

namespace Shikiphp\Exceptions;

/**
 * Raised when the highlighter is asked for an unknown language or theme, or when
 * called without a theme option.
 */
final class Highlight extends \RuntimeException
{
    public static function unknownLanguage(string $lang): self
    {
        return new self("Unknown language \"{$lang}\".");
    }

    public static function unknownTheme(string $theme): self
    {
        return new self("Unknown theme \"{$theme}\".");
    }

    public static function noTheme(): self
    {
        return new self('A `theme` or `themes` option is required.');
    }

    public static function badThemesOption(): self
    {
        return new self('The `themes` option must be a non-empty map of colour key to theme name.');
    }
}
