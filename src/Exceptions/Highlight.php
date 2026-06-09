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

    public static function invalidGrammar(string $reason): self
    {
        return new self("Cannot load grammar: {$reason}.");
    }

    public static function invalidTheme(string $reason): self
    {
        return new self("Cannot load theme: {$reason}.");
    }

    public static function badThemesOption(): self
    {
        return new self('The `themes` option must be a non-empty map of colour key to theme name.');
    }

    public static function invalidGrammarState(): self
    {
        return new self('Invalid grammar state.');
    }

    public static function grammarStateLanguageMismatch(string $stateLang, string $lang): self
    {
        return new self("Grammar state language \"{$stateLang}\" does not match highlight language \"{$lang}\".");
    }

    public static function grammarStateThemeMismatch(string $themes, string $theme): self
    {
        return new self("Grammar state themes \"{$themes}\" do not contain highlight theme \"{$theme}\".");
    }

    public static function plainLanguageHasNoGrammarState(): self
    {
        return new self('Plain language does not have grammar state.');
    }

    public static function ansiLanguageHasNoGrammarState(): self
    {
        return new self('ANSI language does not have grammar state.');
    }

    public static function decorationsIntersect(string $a, string $b): self
    {
        return new self("Decorations {$a} and {$b} intersect.");
    }

    public static function invalidDecorationRange(string $start, string $end): self
    {
        return new self("Invalid decoration range: {$start} - {$end}.");
    }

    public static function invalidDecorationOffset(int $offset, int $length): self
    {
        return new self("Invalid decoration offset: {$offset}. Code length: {$length}");
    }

    public static function invalidDecorationPosition(string $position): self
    {
        return new self("Invalid decoration position {$position}.");
    }
}
