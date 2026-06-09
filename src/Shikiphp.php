<?php

declare(strict_types=1);

namespace Shikiphp;

/**
 * Static facade: public API entry point.
 *
 * @phpstan-type CodeToHtmlOptions array{
 *     lang: string,
 *     theme?: string,
 *     themes?: array<string, string>,
 *     defaultColor?: string|false
 * }
 */
final class Shikiphp
{
    private static ?Highlighter $highlighter = null;

    /**
     * @param CodeToHtmlOptions $options
     */
    public static function codeToHtml(string $code, array $options): string
    {
        return self::highlighter()->codeToHtml($code, $options);
    }

    /**
     * @param CodeToHtmlOptions $options
     * @return list<list<\Shikiphp\Render\ThemedToken>>
     */
    public static function codeToTokens(string $code, array $options): array
    {
        return self::highlighter()->codeToTokens($code, $options);
    }

    /**
     * @param CodeToHtmlOptions $options
     * @return list<list<\Shikiphp\Render\ThemedToken>>
     */
    public static function codeToTokensBase(string $code, array $options): array
    {
        return self::highlighter()->codeToTokensBase($code, $options);
    }

    /**
     * @param CodeToHtmlOptions $options
     */
    public static function codeToTokensResult(string $code, array $options): TokensResult
    {
        return self::highlighter()->codeToTokensResult($code, $options);
    }

    /**
     * @param CodeToHtmlOptions $options
     */
    public static function getLastGrammarState(string $code, array $options): GrammarState
    {
        return self::highlighter()->getLastGrammarState($code, $options);
    }

    public static function highlighter(): Highlighter
    {
        return self::$highlighter ??= Highlighter::createBundled();
    }

    public static function use(Highlighter $highlighter): void
    {
        self::$highlighter = $highlighter;
    }

    public static function reset(): void
    {
        self::$highlighter = null;
    }
}
