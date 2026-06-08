<?php

declare(strict_types=1);

namespace Shikiphp;

use Shikiphp\Exceptions\Highlight;
use Shikiphp\Grammar\Grammar;
use Shikiphp\Grammar\Registry;
use Shikiphp\Grammar\StateStack;
use Shikiphp\Grammar\Token;
use Shikiphp\Registry\BundleLoader;
use Shikiphp\Render\HtmlRenderer;
use Shikiphp\Render\RenderOptions;
use Shikiphp\Render\ThemedToken;
use Shikiphp\Theme\FontStyle;
use Shikiphp\Theme\StyleAttributes;
use Shikiphp\Theme\Theme;

/**
 * Orchestrates the pipeline: load a grammar (with its embedded dependencies),
 * tokenize line by line carrying the rule stack across lines, resolve every
 * token's scope stack against the theme(s), and emit themed tokens or
 * Shiki-compatible HTML.
 *
 * @phpstan-type Options array{
 *     lang: string,
 *     theme?: string,
 *     themes?: array<string, string>,
 *     defaultColor?: string|false
 * }
 */
final class Highlighter
{
    private const TOKEN_TYPE_OTHER = 0;
    private const TOKEN_TYPE_COMMENT = 1;
    private const TOKEN_TYPE_STRING = 2;
    private const TOKEN_TYPE_REGEX = 3;

    private readonly Registry $registry;

    /** @var array<string, Grammar> language id → loaded grammar */
    private array $grammars = [];

    /** @var array<string, Theme> theme id → compiled theme */
    private array $themes = [];

    public function __construct(
        private readonly BundleLoader $loader,
    ) {
        $this->registry = new Registry($loader->grammarResolver());
    }

    public static function createBundled(): self
    {
        return new self(BundleLoader::bundled());
    }

    /** @return list<string> */
    public function loadedLanguages(): array
    {
        return $this->loader->languageIds();
    }

    /** @return list<string> */
    public function loadedThemes(): array
    {
        return $this->loader->themeIds();
    }

    /**
     * @param Options $options
     * @return list<list<ThemedToken>>
     */
    public function codeToTokens(string $code, array $options): array
    {
        $grammar = $this->grammar($options['lang']);
        [$themesByKey, $defaultKey] = $this->resolveThemes($options);

        $lines = self::splitLines($code);
        $state = null;

        $out = [];
        foreach ($lines as $line) {
            $result = $grammar->tokenizeLine($line, $state);
            $state = $result->ruleStack;
            $out[] = $this->themeLine($line, $result->tokens, $themesByKey, $defaultKey);
        }

        return $out;
    }

    /**
     * @param Options $options
     */
    public function codeToHtml(string $code, array $options): string
    {
        $lines = $this->codeToTokens($code, $options);
        [$themesByKey, $defaultKey] = $this->resolveThemes($options);

        return (new HtmlRenderer())->render($lines, $this->renderOptions($options, $themesByKey, $defaultKey));
    }

    private function grammar(string $lang): Grammar
    {
        if (isset($this->grammars[$lang])) {
            return $this->grammars[$lang];
        }

        if (!$this->loader->hasLanguage($lang)) {
            throw Highlight::unknownLanguage($lang);
        }

        foreach ($this->loader->dependencyClosure($lang) as $depId) {
            $this->registry->loadGrammarFromRaw($this->loader->rawGrammar($depId));
        }

        return $this->grammars[$lang] = $this->registry->loadGrammar($this->loader->scopeNameFor($lang));
    }

    private function theme(string $themeId): Theme
    {
        return $this->themes[$themeId] ??= Theme::fromRaw($this->loader->rawTheme($themeId));
    }

    /**
     * @param Options $options
     * @return array{array<string, Theme>, string|false}
     */
    private function resolveThemes(array $options): array
    {
        if (isset($options['themes'])) {
            $themes = $options['themes'];
            if ($themes === []) {
                throw Highlight::badThemesOption();
            }

            $byKey = [];
            foreach ($themes as $key => $themeId) {
                $byKey[$key] = $this->theme($themeId);
            }

            $default = $options['defaultColor'] ?? 'light';
            if ($default !== false && !isset($byKey[$default])) {
                $default = array_key_first($byKey);
            }

            return [$byKey, $default];
        }

        $themeId = $options['theme'] ?? throw Highlight::noTheme();
        return [['default' => $this->theme($themeId)], 'default'];
    }

    /**
     * @param list<Token> $tokens
     * @param array<string, Theme> $themesByKey
     * @return list<ThemedToken>
     */
    private function themeLine(string $line, array $tokens, array $themesByKey, string|false $defaultKey): array
    {
        $isDual = !isset($themesByKey['default']) || count($themesByKey) > 1;

        $themed = [];
        $types = [];
        foreach ($tokens as $token) {
            $content = self::utf16Substr($line, $token->startIndex, $token->endIndex);
            if ($content === '') {
                continue;
            }

            $themed[] = $isDual
                ? $this->dualToken($content, $token->scopes, $themesByKey, $defaultKey)
                : $this->singleToken($content, $token->scopes, $themesByKey['default']);
            $types[] = self::standardTokenType($token->scopes);
        }

        return self::mergeWhitespace(self::coalesce($themed, $types));
    }

    /**
     * Mirror vscode-textmate's encoded `StandardTokenType` (Other/Comment/
     * String/RegEx), used so adjacent same-coloured tokens only merge when their
     * full metadata matches — a string quote and a following comma share a colour
     * but differ in token type.
     *
     * @param list<string> $scopes
     */
    private static function standardTokenType(array $scopes): int
    {
        $type = self::TOKEN_TYPE_OTHER;
        foreach ($scopes as $scope) {
            if (preg_match('/\b(comment|string|regex|meta\.embedded)\b/', $scope, $m) !== 1) {
                continue;
            }
            $type = match ($m[1]) {
                'comment' => self::TOKEN_TYPE_COMMENT,
                'string' => self::TOKEN_TYPE_STRING,
                'regex' => self::TOKEN_TYPE_REGEX,
                default => self::TOKEN_TYPE_OTHER,
            };
        }

        return $type;
    }

    /**
     * Fold whitespace-only tokens into the following token (adopting its style),
     * mirroring Shiki's default `mergeWhitespaces`. A decorated following token
     * keeps the whitespace as a separate style-less token.
     *
     * @param list<ThemedToken> $tokens
     * @return list<ThemedToken>
     */
    private static function mergeWhitespace(array $tokens): array
    {
        $out = [];
        $carry = '';
        $count = count($tokens);

        foreach ($tokens as $idx => $token) {
            $isLast = $idx === $count - 1;
            $couldMerge = !self::isDecorated($token);

            if ($couldMerge && !$isLast && preg_match('/^\s+$/', $token->content) === 1) {
                $carry .= $token->content;
                continue;
            }

            if ($carry === '') {
                $out[] = $token;
                continue;
            }

            if ($couldMerge) {
                $out[] = new ThemedToken(
                    $carry . $token->content,
                    $token->color,
                    $token->fontStyle,
                    $token->bgColor,
                    $token->htmlStyle,
                );
            } else {
                $out[] = new ThemedToken($carry, null, FontStyle::NONE);
                $out[] = $token;
            }

            $carry = '';
        }

        return $out;
    }

    private static function isDecorated(ThemedToken $token): bool
    {
        return ($token->fontStyle & (FontStyle::UNDERLINE | FontStyle::STRIKETHROUGH)) !== 0;
    }

    /**
     * @param list<ThemedToken> $tokens
     * @param list<int> $types parallel {@see standardTokenType} per token
     * @return list<ThemedToken>
     */
    private static function coalesce(array $tokens, array $types): array
    {
        $out = [];
        $outType = -1;
        foreach ($tokens as $idx => $token) {
            $type = $types[$idx];
            $previous = $out === [] ? null : $out[count($out) - 1];
            if ($previous !== null && $type === $outType && self::sameStyle($previous, $token)) {
                $out[count($out) - 1] = new ThemedToken(
                    $previous->content . $token->content,
                    $previous->color,
                    $previous->fontStyle,
                    $previous->bgColor,
                    $previous->htmlStyle,
                );
                continue;
            }
            $out[] = $token;
            $outType = $type;
        }

        return $out;
    }

    private static function sameStyle(ThemedToken $a, ThemedToken $b): bool
    {
        return $a->color === $b->color
            && $a->fontStyle === $b->fontStyle
            && $a->bgColor === $b->bgColor
            && $a->htmlStyle === $b->htmlStyle;
    }

    /**
     * @param list<string> $scopes
     */
    private function singleToken(string $content, array $scopes, Theme $theme): ThemedToken
    {
        $style = self::resolveStyle($theme, $scopes);
        $color = self::normalizeColor($style->foreground ?? $theme->foreground());

        return new ThemedToken($content, $color, self::normalizeFontStyle($style->fontStyle));
    }

    /**
     * Resolve a token's style across its whole scope stack, inheriting
     * foreground/fontStyle from ancestor scopes when an inner scope carries no
     * rule of its own — mirroring vscode-textmate's per-scope metadata
     * accumulation rather than matching the innermost scope alone.
     *
     * @param list<string> $scopes
     */
    private static function resolveStyle(Theme $theme, array $scopes): StyleAttributes
    {
        $default = $theme->foreground();

        $foreground = null;
        $fontStyle = FontStyle::NOT_SET;

        $depth = count($scopes);
        for ($i = 1; $i <= $depth; $i++) {
            $match = $theme->match(array_slice($scopes, 0, $i));

            if ($match->foreground !== null && $match->foreground !== $default) {
                $foreground = $match->foreground;
            }
            if ($match->fontStyle !== FontStyle::NOT_SET && $match->fontStyle !== FontStyle::NONE) {
                $fontStyle = $match->fontStyle;
            }
        }

        return new StyleAttributes($fontStyle, $foreground);
    }

    /**
     * @param list<string> $scopes
     * @param array<string, Theme> $themesByKey
     */
    private function dualToken(string $content, array $scopes, array $themesByKey, string|false $defaultKey): ThemedToken
    {
        $parts = [];
        $color = null;
        $fontStyle = FontStyle::NONE;

        foreach ($themesByKey as $key => $theme) {
            $style = self::resolveStyle($theme, $scopes);
            $themeColor = self::normalizeColor($style->foreground ?? $theme->foreground());

            if ($key === $defaultKey) {
                $parts[] = 'color:' . $themeColor;
                $color = $themeColor;
                $fontStyle = self::normalizeFontStyle($style->fontStyle);
                continue;
            }

            $parts[] = '--shiki-' . $key . ':' . $themeColor;
            foreach (self::fontStyleVarParts($key, $style->fontStyle) as $part) {
                $parts[] = $part;
            }
        }

        return new ThemedToken($content, $color, $fontStyle, null, implode(';', $parts));
    }

    /**
     * @param Options $options
     * @param array<string, Theme> $themesByKey
     */
    private function renderOptions(array $options, array $themesByKey, string|false $defaultKey): RenderOptions
    {
        $langId = $this->loader->hasLanguage($options['lang']) ? $options['lang'] : null;

        if (!isset($options['themes'])) {
            $theme = $themesByKey['default'];
            return new RenderOptions(
                themeName: $options['theme'] ?? $theme->name(),
                fg: $theme->foreground(),
                bg: $theme->background(),
                langId: $langId,
            );
        }

        $names = [];
        $fgByKey = [];
        $bgByKey = [];
        foreach ($themesByKey as $key => $theme) {
            $names[$key] = $options['themes'][$key];
            $fgByKey[$key] = $theme->foreground();
            $bgByKey[$key] = $theme->background();
        }

        return new RenderOptions(
            langId: $langId,
            themes: $names,
            fgByKey: $fgByKey,
            bgByKey: $bgByKey,
            defaultColor: $defaultKey,
        );
    }

    private static function normalizeColor(?string $color): ?string
    {
        if ($color === null) {
            return null;
        }
        if (preg_match('/^#[0-9a-fA-F]{3,8}$/', $color) === 1) {
            return strtoupper($color);
        }
        return $color;
    }

    private static function normalizeFontStyle(int $fontStyle): int
    {
        return $fontStyle === FontStyle::NOT_SET ? FontStyle::NONE : $fontStyle;
    }

    /** @return list<string> */
    private static function fontStyleVarParts(string $key, int $fontStyle): array
    {
        if ($fontStyle <= FontStyle::NONE) {
            return [];
        }

        $parts = [];
        if (($fontStyle & FontStyle::ITALIC) !== 0) {
            $parts[] = '--shiki-' . $key . '-font-style:italic';
        }
        if (($fontStyle & FontStyle::BOLD) !== 0) {
            $parts[] = '--shiki-' . $key . '-font-weight:bold';
        }

        $decorations = [];
        if (($fontStyle & FontStyle::UNDERLINE) !== 0) {
            $decorations[] = 'underline';
        }
        if (($fontStyle & FontStyle::STRIKETHROUGH) !== 0) {
            $decorations[] = 'line-through';
        }
        if ($decorations !== []) {
            $parts[] = '--shiki-' . $key . '-text-decoration:' . implode(' ', $decorations);
        }

        return $parts;
    }

    /** @return list<string> */
    private static function splitLines(string $code): array
    {
        $normalized = str_replace("\r\n", "\n", $code);
        return explode("\n", $normalized);
    }

    private static function utf16Substr(string $utf8, int $startCodeUnit, int $endCodeUnit): string
    {
        $utf16 = mb_convert_encoding($utf8, 'UTF-16LE', 'UTF-8');
        $slice = substr($utf16, $startCodeUnit * 2, ($endCodeUnit - $startCodeUnit) * 2);
        return mb_convert_encoding($slice, 'UTF-8', 'UTF-16LE');
    }
}
