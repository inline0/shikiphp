# shikiphp architecture

shikiphp is a pure-PHP port of [Shiki](https://shiki.style). It turns source
code into themed, highlighted HTML using **TextMate grammars** and **VS Code
themes**, with no PHP extensions beyond `json`/`mbstring`, no Node runtime, and
no native Oniguruma binding.

This document is the **contract** every subsystem is built against. Value-object
shapes and method signatures here are normative — implementers must match them so
the subsystems compose.

## Pipeline

```
source code
  └─ Highlighter::codeToTokens()
       ├─ Grammar\Registry        loads grammar (+ its includes/embedded langs)
       ├─ Grammar\Tokenizer       line-by-line, drives an OnigScanner per rule state
       │     └─ Oniguruma\OnigScanner.findNextMatch()
       │           └─ Oniguruma\PatternConverter   Oniguruma pattern → JS regex
       │                 └─ Shikiphp\Regex (Parser + Matcher)   JS RegExp runtime
       ├─ Theme\Theme.match(scopeStack) → StyleAttributes   per token
       └─ Render\ThemedToken[]   (text + style) per line
  └─ Render\HtmlRenderer  → <pre class="shiki">…</pre>
```

Everything operates in **UTF-16 code-unit** offset space — the same space as the
JS regex engine and as Shiki's tokenizer. For ASCII this equals byte offsets.

## 1. Regex runtime — `Shikiphp\Regex` (vendored, do not edit)

A complete pure-PHP ECMAScript regex engine vendored from `inline0/phasis`. See
`src/Regex/NOTICE.md`. Public API:

```php
use Shikiphp\Regex\Parser;
use Shikiphp\Regex\Matcher;

$pattern = (new Parser(string $source, string $flags))->parse();   // → Ast\Pattern
$matcher = new Matcher(\Shikiphp\Regex\Ast\Pattern $pattern, string $flags);

// Forward search from $startCodeUnit (Oniguruma onig_search semantics):
$result = $matcher->match(string $inputUtf8, int $startCodeUnit);
// → array{index:int, end:int, captures: list<?array{0:int,1:int,2:string}>} | null
//   captures[0] = whole match [start,end,text]; captures[n] = group n or null.
$matcher->matchTest(string $inputUtf8, int $startCodeUnit): bool;
```

Flags: `g i m s u y d` (string subset). Invalid patterns throw
`Shikiphp\Regex\RegexSyntaxError`.

## 2. Oniguruma → JS conversion — `Shikiphp\Oniguruma\PatternConverter`

A PHP port of [`oniguruma-to-es`](https://github.com/slevithan/oniguruma-to-es):
turns a TextMate-grammar Oniguruma pattern into a JS-RegExp-compatible source +
flags string accepted by `Shikiphp\Regex\Parser`.

```php
final class PatternConverter
{
    /** @return array{pattern: string, flags: string} */
    public function convert(string $onigPattern): array;
}
```

Must handle the constructs real grammars use:
- POSIX classes `[[:alpha:]]`, `[[:alnum:]]`, … → `\p{…}` / explicit ranges.
- `\h` `\H` (horizontal ws), `\A` `\z` `\Z`, `\G` (anchor — see notes), `\R`.
- Possessive quantifiers `a++ a*+ a?+ a{n,m}+` and atomic groups `(?>…)` →
  emulate (JS lacks them; wrap as lookahead-capture or accept backtracking
  equivalent as `oniguruma-to-es` does).
- Inline flags `(?i)` `(?i:…)` `(?x)` (extended/whitespace mode), `(?m)`.
- Named groups `(?<name>…)` / `(?'name'…)`, backrefs `\k<name>` `\k'name'`,
  numbered backrefs.
- Unicode properties `\p{…}` `\P{…}`; `.` semantics; nested char classes.
- Conversions that JS cannot represent should degrade gracefully (the scanner
  must not crash on an unconvertible pattern — skip that pattern, log once).

A `\G` anchor (start-of-scan) maps onto the scanner's `startPosition` and the
sticky semantics; document precisely how it's handled in the tokenizer.

## 3. Scanner — `Shikiphp\Oniguruma\OnigScanner`

Mirrors vscode-oniguruma's `OnigScanner`. Built once per rule from a list of
Oniguruma patterns; converts + compiles each lazily.

```php
final class OnigScanner
{
    /** @param list<string> $patterns Oniguruma source patterns. */
    public function __construct(array $patterns);

    /** Leftmost match at/after $startPosition; ties → lowest pattern index. */
    public function findNextMatch(OnigString $string, int $startPosition): ?OnigMatch;
}
```

Returns `OnigMatch{index, captureIndices: list<OnigCaptureIndex>}` (offsets are
UTF-16 code units). A group that did not participate is an empty span
`[start==end]`. Value objects (`OnigString`, `OnigMatch`, `OnigCaptureIndex`)
already exist in `src/Oniguruma/`.

**Equivalence-gated PCRE fast-path.** Native `preg_match` is far faster than the
tree-walking Matcher, but PCRE2 under `/u` diverges from ECMAScript in several
places (Unicode `\d\w\s\b`, `.`, capture-reset on repetition, lookbehind capture
direction, `\p{}` table version, lone surrogates, `\G`). `PcreTranslator` rewrites
the converter's JS source into a PCRE pattern **only** for the subset it can prove
identical to the Matcher — rewriting `\d\w\s`/`.`/`^`/`$` to their spec forms and
rejecting everything else (named groups, backrefs, `\p{}`, `\b`, atomic emulation,
quantified or lookbehind captures, unbounded-length lookbehind, …). Safe patterns
run via `PcreMatcher` (byte↔UTF-16 offset mapping; sticky `\G` → PCRE `A` modifier);
all others stay on the Matcher. `OnigScanner` also rejects any translation PCRE
fails to compile. The classification is proven by `bin/.oracle-tools/`
`pcre-equivalence.php`, which asserts the fast-path result equals the Matcher
result (index, end, every capture span) for every PCRE-safe pattern in the bundled
grammars across a large input corpus — **zero divergences required**.

## 4. Grammar — `Shikiphp\Grammar`

A faithful port of [vscode-textmate](https://github.com/microsoft/vscode-textmate).

- `RawGrammar` — decoded `.tmLanguage.json` (`scopeName`, `patterns`,
  `repository`, `injections`, `injectionSelector`, …).
- `Registry` — owns grammars keyed by scope name; resolves `include`
  (`#repo`, `$self`, `$base`, `other.scope`, `other.scope#repo`), embedded
  languages, and injection grammars. `loadGrammar(scopeName): Grammar`.
- Rule types (compiled from raw patterns), each with an int id:
  `MatchRule` (match + captures), `BeginEndRule` (begin/end, beginCaptures,
  endCaptures, patterns, contentName, applyEndPatternLast, while=false),
  `BeginWhileRule` (begin/while), `IncludeOnlyRule` (patterns only),
  `CaptureRule`. A `RuleFactory` + `RuleRegistry` mint and cache rule ids.
- `Tokenizer` implements `_tokenizeString`: maintains a rule stack
  (`StateStack`) and a `ScopeStack`; at each position assembles the active
  rule's patterns (+ end/while rule + applicable injections) into an
  `OnigScanner`, finds the next match, emits tokens for the gap, applies
  captures (recursively), and pushes/pops rules. Injection priority and
  `applyEndPatternLast`, `\G` anchoring (`anchorPosition`), and back-reference
  substitution into dynamic end patterns must be handled.

```php
final class Grammar
{
    public function tokenizeLine(string $line, ?StateStack $prevState): TokenizeLineResult;
}

final class TokenizeLineResult
{
    /** @param list<Token> $tokens */
    public function __construct(
        public readonly array $tokens,        // Token already exists in src/Grammar/
        public readonly StateStack $ruleStack,
        public readonly bool $stoppedEarly = false,
    ) {}
}
```

`Token{startIndex, endIndex, scopes}` already exists. Tokens span the whole line
with no gaps; the last token reaches the line length (excluding the trailing
`\n`, which the tokenizer appends internally per vscode-textmate).

## 5. Theme — `Shikiphp\Theme`

Port of vscode-textmate's theme matching.

- `RawTheme` — decoded VS Code theme JSON (`name`, `type`, `colors`,
  `tokenColors`/`settings` with `scope` + `settings.{foreground,fontStyle}`,
  optional `semanticTokenColors`).
- `Theme::fromRaw(array $raw): Theme`. `Theme->match(list<string> $scopePath):
  StyleAttributes` resolves foreground/fontStyle by selector specificity with
  parent-scope matching (`scopeA scopeB`). Background/foreground defaults come
  from `colors['editor.background']` / `editor.foreground` (exposed via getters).

`StyleAttributes{fontStyle, foreground, background}` and `FontStyle` already
exist in `src/Theme/`.

## 6. Render — `Shikiphp\Render`

```php
final class ThemedToken
{
    public function __construct(
        public readonly string $content,
        public readonly ?string $color,
        public readonly int $fontStyle,           // FontStyle bitmask
        public readonly ?string $bgColor = null,
        public readonly ?string $htmlStyle = null,
    ) {}
}

final class HtmlRenderer
{
    /** @param list<list<ThemedToken>> $lines */
    public function render(array $lines, RenderOptions $options): string;
}
```

Output must match Shiki's default structure exactly:

```html
<pre class="shiki github-dark" style="background-color:#24292e;color:#e1e4e8" tabindex="0"><code><span class="line"><span style="color:#F97583">echo</span><span style="color:#E1E4E8"> </span>…</span>
<span class="line">…</span></code></pre>
```

- One `<span class="line">` per line; lines joined by `\n`; empty lines render an
  empty `<span class="line"></span>`.
- Per-token `<span style="color:…[;font-style|font-weight|text-decoration]">`,
  HTML-escaping `& < > "`. `fontStyle` bits → inline style (italic →
  `font-style:italic`, bold → `font-weight:bold`, underline/strikethrough →
  `text-decoration`).
- Dual-theme mode (`themes: {light, dark}`) uses CSS variables
  (`--shiki-light`, `--shiki-dark`) like Shiki; single-theme uses plain colors.

## 7. Highlighter & facade — `Shikiphp\Highlighter`, `Shikiphp\Shikiphp`

```php
final class Highlighter
{
    public static function createBundled(): self;          // loads src/Registry manifest

    /** @param array{lang:string,theme?:string,themes?:array<string,string>,defaultColor?:string|false} $o */
    public function codeToHtml(string $code, array $o): string;
    /** @return list<list<ThemedToken>> */
    public function codeToTokens(string $code, array $o): array;

    /** @return list<string> */ public function loadedLanguages(): array;
    /** @return list<string> */ public function loadedThemes(): array;
}
```

`Shikiphp::codeToHtml()` / `::codeToTokens()` delegate to the shared bundled
highlighter (already scaffolded in `src/Shikiphp.php`).

## 8. Bundled assets — `src/Registry`

- `src/Registry/grammars/<name>.json` — TextMate grammars (from `tm-grammars`).
- `src/Registry/themes/<name>.json` — VS Code themes (from `tm-themes`).
- `src/Registry/bundle.php` (or `manifest.json`) — maps language ids + aliases →
  grammar file + scopeName, theme ids → theme file, and grammar embedded-language
  dependencies. The bundler script lives in `bin/.oracle-tools/` (Node) and copies
  a curated set; record provenance/version.

## 9. Oracle harness (inline0 signature)

Shiki.js itself is the oracle (Node available). `bin/.oracle-tools/` holds a
`package.json` depending on `shiki` and an `oracle.mjs` that prints
`codeToHtml(code, {lang, theme})` (default JS engine, to match our pipeline).

- `bin/oracle <lang> <theme> <file>`   → reference HTML from Shiki.js
- `bin/actual <lang> <theme> <file>`   → shikiphp HTML
- `bin/compare <scenario>`             → normalized diff
- `bin/test-regression [--jobs N]`     → all `scenarios/`, pass/fail report

`scenarios/<name>/` holds `input.<ext>` + `meta.json` (`{lang, theme}`).
`composer test:oracle` runs `bin/test-regression`. The "Non-Negotiable Testing
Rule": highlighting output is compared against Shiki and must not regress.
