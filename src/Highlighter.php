<?php

declare(strict_types=1);

namespace Shikiphp;

use Shikiphp\Ansi\AnsiTokenizer;
use Shikiphp\Exceptions\Highlight;
use Shikiphp\Grammar\Grammar;
use Shikiphp\Grammar\Registry;
use Shikiphp\Grammar\StateStack;
use Shikiphp\Grammar\Token;
use Shikiphp\Hast\Element;
use Shikiphp\Hast\HastSerializer;
use Shikiphp\Registry\BundleLoader;
use Shikiphp\Render\HtmlRenderer;
use Shikiphp\Render\RenderOptions;
use Shikiphp\Render\ThemedToken;
use Shikiphp\Render\ThemedTokenStyle;
use Shikiphp\Render\ThemedTokenWithVariants;
use Shikiphp\Theme\FontStyle;
use Shikiphp\Theme\StyleAttributes;
use Shikiphp\Theme\Theme;
use Shikiphp\Transformer\DecorationsTransformer;
use Shikiphp\Transformer\PipelineContext;
use Shikiphp\Transformer\TransformerContext;
use Shikiphp\Transformer\TransformerPipeline;

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
 *     defaultColor?: string|false,
 *     transformers?: list<\Shikiphp\Transformer\Transformer>,
 *     colorReplacements?: array<string, string|array<string,string>>,
 *     structure?: 'classic'|'inline',
 *     tabindex?: int|string|false,
 *     cssVariablePrefix?: string,
 *     mergeWhitespaces?: bool|'never',
 *     tokenizeMaxLineLength?: int|null,
 *     decorations?: list<array<string,mixed>>
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

    /** @return list<string> language ids shipped in the bundle */
    public function bundledLanguages(): array
    {
        return $this->loader->bundledLanguageIds();
    }

    /** @return list<string> theme ids shipped in the bundle */
    public function bundledThemes(): array
    {
        return $this->loader->bundledThemeIds();
    }

    /**
     * Register a custom TextMate grammar (decoded `.tmLanguage` JSON) so
     * codeToHtml/codeToTokens can use it by language id. The id is $langId, else
     * the grammar's `name`, else its `scopeName`; includes/embedded langs resolve
     * against already-registered grammars.
     *
     * @param array<string, mixed> $rawTmLanguage
     * @param list<string> $aliases
     * @param list<string> $embedded language ids this grammar embeds
     */
    public function loadGrammar(array $rawTmLanguage, ?string $langId = null, array $aliases = [], array $embedded = []): void
    {
        $id = $this->loader->registerGrammar($rawTmLanguage, $langId, $aliases, $embedded);
        unset($this->grammars[$id]);
    }

    /**
     * Register a custom VS Code theme (decoded JSON) keyed by its `name`. A custom
     * theme overrides a bundled theme of the same name.
     *
     * @param array<string, mixed> $rawTheme
     */
    public function loadTheme(array $rawTheme): void
    {
        $id = $this->loader->registerTheme($rawTheme);
        unset($this->themes[$id]);
    }

    /**
     * @param Options $options
     * @return list<list<ThemedToken>>
     */
    public function codeToTokens(string $code, array $options): array
    {
        [$themesByKey, $defaultKey] = $this->resolveThemes($options);

        return $this->tokenize($code, $options, $themesByKey, $defaultKey);
    }

    /**
     * Tokenize once per theme, then merge into per-token `variants` keyed by
     * theme key, mirroring Shiki's `codeToTokensWithThemes`. Token boundaries
     * are the union across themes (each theme coalesces adjacent same-style
     * tokens independently, then the boundaries are synced).
     *
     * @param array{themes: array<string, string>, lang: string, colorReplacements?: array<string, string|array<string,string>>, tokenizeMaxLineLength?: int|null} $options
     * @return list<list<ThemedTokenWithVariants>>
     */
    public function codeToTokensWithThemes(string $code, array $options): array
    {
        if (($options['themes'] ?? []) === []) {
            throw Highlight::badThemesOption();
        }

        $keys = array_keys($options['themes']);
        $perTheme = [];
        foreach ($options['themes'] as $key => $themeId) {
            $singleOptions = $options;
            unset($singleOptions['themes']);
            $singleOptions['theme'] = $themeId;
            [$themesByKey, $defaultKey] = $this->resolveThemes($singleOptions);
            $perTheme[$key] = $this->tokenize($code, $singleOptions, $themesByKey, $defaultKey);
        }

        $synced = self::syncThemesTokenization($perTheme, $keys);

        $out = [];
        $lineCount = count($synced[$keys[0]]);
        for ($lineIdx = 0; $lineIdx < $lineCount; $lineIdx++) {
            $line = [];
            $tokenCount = count($synced[$keys[0]][$lineIdx]);
            for ($tokenIdx = 0; $tokenIdx < $tokenCount; $tokenIdx++) {
                $base = $synced[$keys[0]][$lineIdx][$tokenIdx];
                $variants = [];
                foreach ($keys as $key) {
                    $variants[$key] = self::tokenStyle($synced[$key][$lineIdx][$tokenIdx]);
                }
                $line[] = new ThemedTokenWithVariants($base->content, $base->offset, $variants);
            }
            $out[] = $line;
        }

        return $out;
    }

    private static function tokenStyle(ThemedToken $token): ThemedTokenStyle
    {
        return new ThemedTokenStyle($token->color, $token->fontStyle, $token->bgColor);
    }

    /**
     * Port of Shiki's `syncThemesTokenization`: split each theme's per-line
     * tokens to the union of all themes' token boundaries so every theme yields
     * the same number of tokens with identical content spans.
     *
     * @param array<string, list<list<ThemedToken>>> $perTheme
     * @param list<string> $keys
     * @return array<string, list<list<ThemedToken>>>
     */
    private static function syncThemesTokenization(array $perTheme, array $keys): array
    {
        $out = [];
        foreach ($keys as $key) {
            $out[$key] = [];
        }

        $lineCount = count($perTheme[$keys[0]]);
        for ($lineIdx = 0; $lineIdx < $lineCount; $lineIdx++) {
            $outLines = [];
            $current = [];
            foreach ($keys as $key) {
                $outLines[$key] = [];
                $current[$key] = $perTheme[$key][$lineIdx];
            }

            while (self::allPresent($current, $keys)) {
                $minLength = PHP_INT_MAX;
                foreach ($keys as $key) {
                    $minLength = min($minLength, self::utf16Length($current[$key][0]->content));
                }

                foreach ($keys as $key) {
                    $token = $current[$key][0];
                    if (self::utf16Length($token->content) === $minLength) {
                        $outLines[$key][] = $token;
                        array_shift($current[$key]);
                    } else {
                        $length = self::utf16Length($token->content);
                        $head = self::utf16Substr($token->content, 0, $minLength);
                        $tail = self::utf16Substr($token->content, $minLength, $length);
                        $outLines[$key][] = $token->withContent($head, $token->offset);
                        $current[$key][0] = $token->withContent($tail, $token->offset + $minLength);
                    }
                }
            }

            foreach ($keys as $key) {
                $out[$key][] = $outLines[$key];
            }
        }

        return $out;
    }

    /**
     * @param array<string, list<ThemedToken>> $current
     * @param list<string> $keys
     */
    private static function allPresent(array $current, array $keys): bool
    {
        foreach ($keys as $key) {
            if ($current[$key] === []) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param Options $options
     */
    public function codeToHtml(string $code, array $options): string
    {
        $context = $this->context($code, $options);
        $pipeline = $context->pipeline;

        $html = HastSerializer::toHtml($this->buildHast($code, $options, $context));

        return $pipeline->postprocess($html, $options, $context->context);
    }

    /**
     * Builds the `pre.shiki > code > (span.line > span)*` HAST tree Shiki
     * builds, ready to serialize or transform.
     *
     * @param Options $options
     */
    public function codeToHast(string $code, array $options): Element
    {
        return $this->buildHast($code, $options, $this->context($code, $options));
    }

    /**
     * @param Options $options
     */
    private function buildHast(string $code, array $options, PipelineContext $ctx): Element
    {
        [$themesByKey, $defaultKey] = $this->resolveThemes($options);

        $source = $ctx->pipeline->preprocess($code, $options, $ctx->context);
        $source = str_replace("\r\n", "\n", $source);
        $ctx->context->source = $source;

        $pipeline = $ctx->pipeline;
        $decorations = $options['decorations'] ?? [];
        if ($decorations !== []) {
            $pipeline = new TransformerPipeline([
                ...$pipeline->transformers,
                new DecorationsTransformer($source, $decorations),
            ]);
        }

        $lines = $this->tokenize($source, $options, $themesByKey, $defaultKey);
        $lines = self::applyMergeWhitespaces($lines, $options['mergeWhitespaces'] ?? true);
        $lines = $pipeline->tokens($lines, $ctx->context);

        return (new HtmlRenderer())->renderToHast(
            $lines,
            $this->renderOptions($options, $themesByKey, $defaultKey),
            $pipeline,
            $ctx->context,
        );
    }

    /**
     * @param Options $options
     */
    private function context(string $code, array $options): PipelineContext
    {
        $pipeline = new TransformerPipeline($options['transformers'] ?? []);
        $themeNames = isset($options['themes'])
            ? $options['themes']
            : ['default' => $options['theme'] ?? ''];

        $context = new TransformerContext(
            options: $options,
            source: $code,
            lang: $this->loader->hasLanguage($options['lang']) ? $options['lang'] : null,
            themes: $themeNames,
            structure: $options['structure'] ?? 'classic',
        );

        return new PipelineContext($pipeline, $context);
    }

    /**
     * @param Options $options
     * @param array<string, Theme> $themesByKey
     * @return list<list<ThemedToken>>
     */
    private function tokenize(string $code, array $options, array $themesByKey, string|false $defaultKey): array
    {
        if (self::isAnsiLang($options['lang'])) {
            return $this->tokenizeAnsi($code, $options, $themesByKey, $defaultKey);
        }

        $grammar = $this->grammar($options['lang']);
        $replacements = self::resolveColorReplacements($options, $themesByKey);
        $maxLineLength = $options['tokenizeMaxLineLength'] ?? null;
        $prefix = $options['cssVariablePrefix'] ?? '--shiki-';

        $lines = self::splitLines($code);
        $state = null;

        $out = [];
        $lineOffset = 0;
        foreach ($lines as $index => $line) {
            $lineLen = self::utf16Length($line);

            if ($maxLineLength !== null && $maxLineLength > 0 && $lineLen >= $maxLineLength) {
                $state = $grammar->initialState();
                $out[] = $line === ''
                    ? []
                    : [new ThemedToken($line, null, FontStyle::NONE, null, null, $lineOffset)];
            } else {
                $result = $grammar->tokenizeLine($line, $state);
                $state = $result->ruleStack;
                $out[] = $this->themeLine($line, $result->tokens, $themesByKey, $defaultKey, $replacements, $lineOffset, $prefix);
            }

            $lineOffset += $lineLen + ($index < count($lines) - 1 ? 1 : 0);
        }

        return $out;
    }

    private static function isAnsiLang(string $lang): bool
    {
        return $lang === 'ansi';
    }

    /**
     * Route Shiki's special `lang: 'ansi'` through the dedicated ANSI tokenizer
     * (no grammar), still resolving the theme(s) for default fg/bg and the ANSI
     * palette. Mirrors @shikijs/core's `tokenizeAnsiWithTheme` and, for dual
     * themes, its `flatTokenVariants` merge.
     *
     * @param Options $options
     * @param array<string, Theme> $themesByKey
     * @return list<list<ThemedToken>>
     */
    private function tokenizeAnsi(string $code, array $options, array $themesByKey, string|false $defaultKey): array
    {
        $replacements = self::resolveColorReplacements($options, $themesByKey);
        $prefix = $options['cssVariablePrefix'] ?? '--shiki-';
        $isDual = !isset($themesByKey['default']) || count($themesByKey) > 1;
        $tokenizer = new AnsiTokenizer();

        if (!$isDual) {
            return $tokenizer->tokenize($code, $themesByKey['default'], $replacements);
        }

        $perTheme = [];
        $keys = [];
        foreach ($themesByKey as $key => $theme) {
            $perTheme[] = $tokenizer->tokenize($code, $theme, $replacements);
            $keys[] = $key;
        }

        $out = [];
        $base = $perTheme[0];
        foreach ($base as $lineIdx => $line) {
            $mergedLine = [];
            foreach ($line as $tokenIdx => $token) {
                $variants = [];
                foreach ($perTheme as $themeIdx => $themeLines) {
                    $variants[$keys[$themeIdx]] = $themeLines[$lineIdx][$tokenIdx];
                }
                $mergedLine[] = self::flatAnsiVariants($token->content, $token->offset, $variants, $keys, $defaultKey, $prefix);
            }
            $out[] = $mergedLine;
        }

        return $out;
    }

    /**
     * Merge per-theme ANSI variants of one token into a single {@see ThemedToken}
     * carrying the default theme's colours plus `--shiki-<key>[-bg|-...]` CSS
     * variables for the rest. Faithful to Shiki's `flatTokenVariants`.
     *
     * @param array<string, ThemedToken> $variants
     * @param list<string> $order
     */
    private static function flatAnsiVariants(
        string $content,
        int $offset,
        array $variants,
        array $order,
        string|false $defaultKey,
        string $prefix,
    ): ThemedToken {
        $styles = [];
        foreach ($order as $key) {
            $styles[$key] = self::tokenStyleObject($variants[$key]);
        }

        $styleKeys = [];
        foreach ($styles as $style) {
            foreach (array_keys($style) as $k) {
                $styleKeys[$k] = true;
            }
        }
        $styleKeys = array_keys($styleKeys);

        $merged = [];
        foreach ($order as $idx => $key) {
            $cur = $styles[$key];
            foreach ($styleKeys as $styleKey) {
                $value = $cur[$styleKey] ?? 'inherit';
                if ($idx === 0 && $defaultKey !== false && in_array($styleKey, ['color', 'background-color'], true)) {
                    $merged[$styleKey] = $value;
                } else {
                    $merged[self::ansiVarKey($prefix, $key, $styleKey)] = $value;
                }
            }
        }

        $parts = [];
        foreach ($merged as $k => $v) {
            $parts[] = $k . ':' . $v;
        }

        return new ThemedToken($content, null, FontStyle::NONE, null, implode(';', $parts), $offset);
    }

    private static function ansiVarKey(string $prefix, string $key, string $styleKey): string
    {
        $suffix = match ($styleKey) {
            'color' => '',
            'background-color' => '-bg',
            default => '-' . $styleKey,
        };

        return $prefix . $key . $suffix;
    }

    /**
     * @return array<string, string>
     */
    private static function tokenStyleObject(ThemedToken $token): array
    {
        $style = [];
        if ($token->color !== null) {
            $style['color'] = $token->color;
        }
        if ($token->bgColor !== null) {
            $style['background-color'] = $token->bgColor;
        }
        if (($token->fontStyle & FontStyle::ITALIC) !== 0) {
            $style['font-style'] = 'italic';
        }
        if (($token->fontStyle & FontStyle::BOLD) !== 0) {
            $style['font-weight'] = 'bold';
        }
        $decorations = [];
        if (($token->fontStyle & FontStyle::UNDERLINE) !== 0) {
            $decorations[] = 'underline';
        }
        if (($token->fontStyle & FontStyle::STRIKETHROUGH) !== 0) {
            $decorations[] = 'line-through';
        }
        if ($decorations !== []) {
            $style['text-decoration'] = implode(' ', $decorations);
        }

        return $style;
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

            if ($default !== false && array_key_first($byKey) !== $default) {
                $byKey = [$default => $byKey[$default], ...array_diff_key($byKey, [$default => null])];
            }

            return [$byKey, $default];
        }

        $themeId = $options['theme'] ?? throw Highlight::noTheme();
        return [['default' => $this->theme($themeId)], 'default'];
    }

    /**
     * @param list<Token> $tokens
     * @param array<string, Theme> $themesByKey
     * @param array<string,string> $replacements lowercased-hex colour remap
     * @param string $prefix dual-theme CSS-variable prefix
     * @return list<ThemedToken>
     */
    private function themeLine(
        string $line,
        array $tokens,
        array $themesByKey,
        string|false $defaultKey,
        array $replacements,
        int $lineOffset,
        string $prefix,
    ): array {
        $isDual = !isset($themesByKey['default']) || count($themesByKey) > 1;

        $themed = [];
        $types = [];
        foreach ($tokens as $token) {
            $content = self::utf16Substr($line, $token->startIndex, $token->endIndex);
            if ($content === '') {
                continue;
            }

            $themed[] = $isDual
                ? $this->dualToken($content, $token->scopes, $themesByKey, $defaultKey, $replacements, $lineOffset + $token->startIndex, $prefix)
                : $this->singleToken($content, $token->scopes, $themesByKey['default'], $replacements, $lineOffset + $token->startIndex);
            $types[] = self::standardTokenType($token->scopes);
        }

        return self::coalesce($themed, $types);
    }

    /**
     * Apply the `mergeWhitespaces` option per line: `true` (default) folds
     * whitespace-only tokens into the next token; `'never'` splits each token's
     * leading/trailing whitespace into standalone tokens; `false` leaves tokens
     * untouched. Mirrors Shiki's `mergeWhitespaceTokens`/`splitWhitespaceTokens`.
     *
     * @param list<list<ThemedToken>> $lines
     * @return list<list<ThemedToken>>
     */
    private static function applyMergeWhitespaces(array $lines, bool|string $mode): array
    {
        if ($mode === true) {
            return array_map(self::mergeWhitespace(...), $lines);
        }
        if ($mode === 'never') {
            return array_map(self::splitWhitespace(...), $lines);
        }

        return $lines;
    }

    /**
     * @param list<ThemedToken> $tokens
     * @return list<ThemedToken>
     */
    private static function splitWhitespace(array $tokens): array
    {
        $out = [];
        foreach ($tokens as $token) {
            if (preg_match('/^\s+$/', $token->content) === 1) {
                $out[] = $token;
                continue;
            }
            if (preg_match('/^(\s*)(.*?)(\s*)$/su', $token->content, $m) !== 1) {
                $out[] = $token;
                continue;
            }
            [$leading, $content, $trailing] = [$m[1], $m[2], $m[3]];
            if ($leading === '' && $trailing === '') {
                $out[] = $token;
                continue;
            }

            $leadLen = self::utf16Length($leading);
            $contentLen = self::utf16Length($content);
            if ($leading !== '') {
                $out[] = new ThemedToken($leading, null, FontStyle::NONE, null, null, $token->offset);
            }
            $out[] = $token->withContent($content, $token->offset + $leadLen);
            if ($trailing !== '') {
                $out[] = new ThemedToken($trailing, null, FontStyle::NONE, null, null, $token->offset + $leadLen + $contentLen);
            }
        }

        return $out;
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
        $carryOffset = 0;
        $count = count($tokens);

        foreach ($tokens as $idx => $token) {
            $isLast = $idx === $count - 1;
            $couldMerge = !self::isDecorated($token);

            if ($couldMerge && !$isLast && preg_match('/^\s+$/', $token->content) === 1) {
                if ($carry === '') {
                    $carryOffset = $token->offset;
                }
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
                    $carryOffset,
                );
            } else {
                $out[] = new ThemedToken($carry, null, FontStyle::NONE, null, null, $carryOffset);
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
                    $previous->offset,
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
     * @param array<string,string> $replacements
     */
    private function singleToken(string $content, array $scopes, Theme $theme, array $replacements, int $offset): ThemedToken
    {
        $style = self::resolveStyle($theme, $scopes);
        $color = self::applyColorReplacements(self::normalizeColor($style->foreground ?? $theme->foreground()), $replacements);

        return new ThemedToken($content, $color, self::normalizeFontStyle($style->fontStyle), null, null, $offset);
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
        $foreground = null;
        $fontStyle = FontStyle::NOT_SET;

        $depth = count($scopes);
        for ($i = 1; $i <= $depth; $i++) {
            $match = $theme->matchRule(array_slice($scopes, 0, $i));

            if ($match->foreground !== null) {
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
     * @param array<string,string> $replacements
     */
    private function dualToken(
        string $content,
        array $scopes,
        array $themesByKey,
        string|false $defaultKey,
        array $replacements,
        int $offset,
        string $prefix = '--shiki-',
    ): ThemedToken {
        $parts = [];
        $color = null;
        $fontStyle = FontStyle::NONE;

        foreach ($themesByKey as $key => $theme) {
            $style = self::resolveStyle($theme, $scopes);
            $themeColor = self::applyColorReplacements(
                self::normalizeColor($style->foreground ?? $theme->foreground()),
                $replacements,
            );

            if ($key === $defaultKey) {
                $parts[] = 'color:' . $themeColor;
                $color = $themeColor;
                $fontStyle = self::normalizeFontStyle($style->fontStyle);
                continue;
            }

            $parts[] = $prefix . $key . ':' . $themeColor;
            foreach (self::fontStyleVarParts($prefix, $key, $style->fontStyle) as $part) {
                $parts[] = $part;
            }
        }

        return new ThemedToken($content, $color, $fontStyle, null, implode(';', $parts), $offset);
    }

    /**
     * @param Options $options
     * @param array<string, Theme> $themesByKey
     */
    private function renderOptions(array $options, array $themesByKey, string|false $defaultKey): RenderOptions
    {
        $langId = $this->loader->hasLanguage($options['lang']) ? $options['lang'] : null;
        $replacements = self::resolveColorReplacements($options, $themesByKey);
        $structure = $options['structure'] ?? 'classic';
        $tabindex = $options['tabindex'] ?? 0;
        $prefix = $options['cssVariablePrefix'] ?? '--shiki-';

        if (!isset($options['themes'])) {
            $theme = $themesByKey['default'];
            return new RenderOptions(
                themeName: $options['theme'] ?? $theme->name(),
                fg: self::applyColorReplacements($theme->foreground(), $replacements),
                bg: self::applyColorReplacements($theme->background(), $replacements),
                langId: $langId,
                cssVariablePrefix: $prefix,
                tabindex: $tabindex,
                structure: $structure,
            );
        }

        $names = [];
        $fgByKey = [];
        $bgByKey = [];
        foreach ($themesByKey as $key => $theme) {
            $names[$key] = $options['themes'][$key];
            $fgByKey[$key] = self::applyColorReplacements($theme->foreground(), $replacements) ?? '';
            $bgByKey[$key] = self::applyColorReplacements($theme->background(), $replacements) ?? '';
        }

        return new RenderOptions(
            langId: $langId,
            themes: $names,
            fgByKey: $fgByKey,
            bgByKey: $bgByKey,
            defaultColor: $defaultKey,
            cssVariablePrefix: $prefix,
            tabindex: $tabindex,
            structure: $structure,
        );
    }

    /**
     * Resolve the active colour-replacement map: per-theme nested entries
     * (`{themeName: {from:to}}`) are merged only for the matching theme name;
     * string entries are global. Mirrors Shiki's `resolveColorReplacements`.
     *
     * @param Options $options
     * @param array<string, Theme> $themesByKey
     * @return array<string,string>
     */
    private static function resolveColorReplacements(array $options, array $themesByKey): array
    {
        $themeNames = [];
        foreach ($themesByKey as $theme) {
            $themeNames[$theme->name()] = true;
        }

        $out = [];
        foreach ($options['colorReplacements'] ?? [] as $key => $value) {
            if (is_string($value)) {
                $out[strtolower($key)] = $value;
            } elseif (is_array($value) && isset($themeNames[$key])) {
                foreach ($value as $from => $to) {
                    if (is_string($to)) {
                        $out[strtolower((string) $from)] = $to;
                    }
                }
            }
        }

        return $out;
    }

    /**
     * @param array<string,string> $replacements
     */
    private static function applyColorReplacements(?string $color, array $replacements): ?string
    {
        if ($color === null) {
            return null;
        }

        return $replacements[strtolower($color)] ?? $color;
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
    private static function fontStyleVarParts(string $prefix, string $key, int $fontStyle): array
    {
        if ($fontStyle <= FontStyle::NONE) {
            return [];
        }

        $parts = [];
        if (($fontStyle & FontStyle::ITALIC) !== 0) {
            $parts[] = $prefix . $key . '-font-style:italic';
        }
        if (($fontStyle & FontStyle::BOLD) !== 0) {
            $parts[] = $prefix . $key . '-font-weight:bold';
        }

        $decorations = [];
        if (($fontStyle & FontStyle::UNDERLINE) !== 0) {
            $decorations[] = 'underline';
        }
        if (($fontStyle & FontStyle::STRIKETHROUGH) !== 0) {
            $decorations[] = 'line-through';
        }
        if ($decorations !== []) {
            $parts[] = $prefix . $key . '-text-decoration:' . implode(' ', $decorations);
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

    private static function utf16Length(string $utf8): int
    {
        return intdiv(strlen(mb_convert_encoding($utf8, 'UTF-16LE', 'UTF-8')), 2);
    }
}
