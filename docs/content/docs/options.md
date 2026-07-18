---
title: "Options"
description: "Every option codeToHtml, codeToTokens, and codeToHast accept — lang, theme(s), transformers, decorations, structure, and more."
path: "options"
order: 30
section: "Usage"
meta_title: "Options"
meta_description: "Every option codeToHtml, codeToTokens, and codeToHast accept — lang, theme(s), transformers, decorations, structure, and more."
---

# Options

All of the entry points (`codeToHtml`, `codeToTokens`, `codeToTokensResult`,
`codeToHast`) take the same options array as their second argument. Only `lang`
and a theme are required.

## `lang`

**Required.** The language id or alias to tokenize with — `php`, `ts`, `js`,
`rust`, `python`, `html`, and so on. The special value `'ansi'` highlights
terminal escape sequences instead of using a grammar (see [ANSI](/docs/ansi)).

```php
['lang' => 'php']
```

## `theme`

A single theme id. Required unless `themes` is given.

```php
['lang' => 'php', 'theme' => 'github-dark']
```

## `themes`

A map of keys to theme ids for dual (or multi) theme output via CSS variables.
Required unless `theme` is given.

```php
['lang' => 'ts', 'themes' => ['light' => 'github-light', 'dark' => 'github-dark']]
```

## `defaultColor`

Which key in `themes` becomes the inline default color (the others become
`--shiki-<key>` CSS variables). Defaults to `'light'`. Set it to `false` to emit
only variables and no inline default — handy when you control all colors from
CSS.

```php
['themes' => ['light' => '…', 'dark' => '…'], 'defaultColor' => 'dark']
```

## `transformers`

A list of `Shikiphp\Transformer\Transformer` objects applied through the
pipeline. See [Transformers](/docs/transformers).

```php
use Shikiphp\Transformer\Notation\NotationHighlight;

['transformers' => [new NotationHighlight()]]
```

## `decorations`

Apply classes or attributes to arbitrary ranges of the source by line/character
position. Each decoration is `{start, end, properties}`, where positions are
`{line, character}` (zero-based).

```php
['decorations' => [
    [
        'start'      => ['line' => 0, 'character' => 0],
        'end'        => ['line' => 0, 'character' => 5],
        'properties' => ['class' => 'highlight'],
    ],
]]
```

## `colorReplacements`

Remap colors in the output. String entries replace a color globally; nested
entries keyed by theme name only apply to that theme.

```php
['colorReplacements' => [
    '#ffffff'      => '#f8f8f2',          // global
    'github-dark'  => ['#24292e' => '#000'], // only in github-dark
]]
```

## `structure`

`'classic'` (default) emits the full `pre > code > span.line > span` structure.
`'inline'` emits just the token spans with line breaks, with no wrapping
`pre`/`code` — useful for inline snippets.

```php
['structure' => 'inline']
```

## `tabindex`

The `tabindex` attribute on the `<pre>` element. Defaults to `0`. Pass `false`
to omit it.

```php
['tabindex' => false]
```

## `cssVariablePrefix`

The prefix used for dual-theme CSS variables. Defaults to `'--shiki-'`.

```php
['cssVariablePrefix' => '--my-']
```

## `mergeWhitespaces`

Controls whitespace token handling. `true` (default) folds whitespace-only
tokens into the following token; `'never'` splits each token's leading and
trailing whitespace into standalone tokens; `false` leaves tokens untouched.

```php
['mergeWhitespaces' => 'never']
```

## `tokenizeMaxLineLength`

Skip tokenizing (and emit a single plain token for) any line at or beyond this
length, in UTF-16 code units. Defaults to no limit. A guard against pathological
single-line inputs.

```php
['tokenizeMaxLineLength' => 1000]
```

## `meta`

Arbitrary metadata passed through to transformers via the transformer context —
for example the code-fence meta string consumed by
[`MetaHighlight`](/docs/transformers#metahighlight) and
[`MetaWordHighlight`](/docs/transformers#metawordhighlight).

```php
['meta' => ['__raw' => '{1,3-5}']]
```

## `grammarState`

A `Shikiphp\GrammarState` (from `getLastGrammarState`) to resume tokenization
from. The carried `lang` and theme(s) must match. See
[`getLastGrammarState`](/docs/api#shikiphpgetlastgrammarstate).
