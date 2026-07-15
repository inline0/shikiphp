---
title: "API"
description: "The full shikiphp API surface — the Shikiphp facade, the Highlighter, and the TokensResult and GrammarState value objects."
path: "api"
order: 11
section: "Documentation"
meta_title: "API"
meta_description: "The full shikiphp API surface — the Shikiphp facade, the Highlighter, and the TokensResult and GrammarState value objects."
---

# API

shikiphp exposes two entry points: the static `Shikiphp` facade for the common
cases, and the `Highlighter` instance for everything else (HAST output, custom
grammars and themes, theme variants, and bundle introspection).

## The `Shikiphp` facade

`Shikiphp\Shikiphp` is a thin static facade over a lazily-created, cached bundled
highlighter.

### `Shikiphp::codeToHtml`

```php
public static function codeToHtml(string $code, array $options): string
```

Tokenizes `$code` and renders a complete `<pre class="shiki …">` HTML block. The
options array always carries a `lang`, plus either a `theme` or a `themes` map,
and any of the rendering [options](/docs/options).

```php
echo Shikiphp::codeToHtml($code, ['lang' => 'php', 'theme' => 'github-dark']);
```

### `Shikiphp::codeToTokens`

```php
/** @return list<list<ThemedToken>> */
public static function codeToTokens(string $code, array $options): array
```

Returns the bare 2D token grid (one inner list per line) of
`Shikiphp\Render\ThemedToken` objects instead of HTML.

```php
$lines = Shikiphp::codeToTokens($code, ['lang' => 'js', 'theme' => 'nord']);

foreach ($lines as $line) {
    foreach ($line as $token) {
        // $token->content, $token->color, $token->fontStyle,
        // $token->bgColor, $token->htmlStyle, $token->offset
    }
}
```

### `Shikiphp::codeToTokensBase`

```php
/** @return list<list<ThemedToken>> */
public static function codeToTokensBase(string $code, array $options): array
```

An alias of `codeToTokens` that matches Shiki's `codeToTokensBase` name.

### `Shikiphp::codeToTokensResult`

```php
public static function codeToTokensResult(string $code, array $options): TokensResult
```

Returns the rich result that mirrors Shiki's top-level `codeToTokens`: the token
grid plus the resolved colors, theme name, dual-theme root style, and final
grammar state. See [`TokensResult`](#tokensresult) below.

```php
$result = Shikiphp::codeToTokensResult($code, [
    'lang'  => 'php',
    'theme' => 'github-dark',
]);

$result->tokens;       // list<list<ThemedToken>>
$result->fg;           // "#e1e4e8"
$result->bg;           // "#24292e"
$result->themeName;    // "github-dark"
$result->grammarState; // GrammarState
```

### `Shikiphp::getLastGrammarState`

```php
public static function getLastGrammarState(string $code, array $options): GrammarState
```

Tokenizes `$code` and returns the grammar state reached at the end (Shiki's
`getLastGrammarState`). Pass it back through the [`grammarState`](/docs/options)
option to resume tokenizing a later fragment from the same state — useful for
incremental highlighting. Throws if `lang` is `'ansi'` (ANSI has no grammar
state).

```php
$state = Shikiphp::getLastGrammarState("/* unterminated comment", [
    'lang'  => 'js',
    'theme' => 'nord',
]);

$html = Shikiphp::codeToHtml("still inside the comment */", [
    'lang'         => 'js',
    'theme'        => 'nord',
    'grammarState' => $state,
]);
```

### `Shikiphp::highlighter`

```php
public static function highlighter(): Highlighter
```

Returns the shared bundled `Highlighter`, creating it on first use. Use this to
reach the instance methods below.

### `Shikiphp::use` and `Shikiphp::reset`

```php
public static function use(Highlighter $highlighter): void
public static function reset(): void
```

`use` swaps in a custom `Highlighter` (for example, one you have loaded custom
grammars or themes into) so the facade delegates to it. `reset` clears the cached
instance so the next call rebuilds the default bundled highlighter.

## The `Highlighter`

`Shikiphp\Highlighter` carries the full surface. Get the shared bundled instance
with `Shikiphp::highlighter()`, or build one explicitly:

```php
use Shikiphp\Highlighter;

$highlighter = Highlighter::createBundled();
```

It implements `codeToHtml`, `codeToTokens`, `codeToTokensBase`,
`codeToTokensResult`, and `getLastGrammarState` with the same signatures as the
facade, plus the following.

### `Highlighter::codeToHast`

```php
public function codeToHast(string $code, array $options): \Shikiphp\Hast\Element
```

Builds the `pre.shiki > code > (span.line > span)*` HAST tree Shiki builds, ready
to walk, mutate, or serialize yourself. This is what `codeToHtml` serializes
internally.

```php
$hast = Shikiphp::highlighter()->codeToHast($code, [
    'lang'  => 'rust',
    'theme' => 'nord',
]);
```

### `Highlighter::codeToTokensWithThemes`

```php
/** @return list<list<ThemedTokenWithVariants>> */
public function codeToTokensWithThemes(string $code, array $options): array
```

Tokenizes once per theme and merges the result into per-token `variants` keyed by
theme key, mirroring Shiki's `codeToTokensWithThemes`. Requires a `themes` map.

```php
$lines = Shikiphp::highlighter()->codeToTokensWithThemes($code, [
    'lang'   => 'ts',
    'themes' => ['light' => 'github-light', 'dark' => 'github-dark'],
]);

$lines[0][0]->content;            // token text
$lines[0][0]->variants['light'];  // ThemedTokenStyle for the light theme
$lines[0][0]->variants['dark'];   // ThemedTokenStyle for the dark theme
```

### `Highlighter::loadGrammar` and `Highlighter::loadTheme`

```php
public function loadGrammar(
    array $rawTmLanguage,
    ?string $langId = null,
    array $aliases = [],
    array $embedded = [],
): void

public function loadTheme(array $rawTheme): void
```

Register a custom TextMate grammar or VS Code theme from decoded JSON. See
[Custom languages and themes](/docs/custom-languages-themes).

### Bundle introspection

```php
/** @return list<string> */ public function loadedLanguages(): array
/** @return list<string> */ public function loadedThemes(): array
/** @return list<string> */ public function bundledLanguages(): array
/** @return list<string> */ public function bundledThemes(): array
```

`loadedLanguages`/`loadedThemes` report every id currently usable (bundled plus
any custom ones you registered). `bundledLanguages`/`bundledThemes` report only
the ids that ship in the box. See [Languages](/docs/languages) and
[Themes](/docs/themes).

## Value objects

### `ThemedToken`

`Shikiphp\Render\ThemedToken` is one styled run of text:

```php
final class ThemedToken
{
    public readonly string $content;
    public readonly ?string $color;
    public readonly int $fontStyle;      // FontStyle bitmask
    public readonly ?string $bgColor;
    public readonly ?string $htmlStyle;  // pre-built inline style (dual themes / ANSI)
    public readonly int $offset;         // UTF-16 code-unit offset into the source
}
```

`fontStyle` is a bitmask from `Shikiphp\Theme\FontStyle` (`NONE`, `ITALIC`,
`BOLD`, `UNDERLINE`, `STRIKETHROUGH`).

### `TokensResult`

`Shikiphp\TokensResult` is the return of `codeToTokensResult`:

```php
final class TokensResult
{
    public readonly array $tokens;             // list<list<ThemedToken>>
    public readonly ?string $fg;               // resolved foreground
    public readonly ?string $bg;               // resolved background
    public readonly ?string $themeName;        // single id, or "shiki-themes …"
    public readonly string|false|null $rootStyle; // dual-theme root style (or null)
    public readonly ?GrammarState $grammarState;
}
```

### `GrammarState`

`Shikiphp\GrammarState` holds the tokenizer's rule stack (keyed by theme name) so
highlighting can resume from an intermediate point.

```php
final class GrammarState
{
    public readonly string $lang;

    public function theme(): string;          // first theme name
    public function themes(): array;          // list<string>
    public function getScopes(?string $theme = null): array; // scopes in effect, innermost first
    public function withTheme(string $theme): self;
}
```
