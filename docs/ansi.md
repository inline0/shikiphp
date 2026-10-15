---
title: "ANSI Output"
description: "Highlight terminal output and ANSI escape sequences with lang 'ansi'."
path: "ansi"
order: 50
section: "Usage"
meta_title: "ANSI Output"
meta_description: "Highlight terminal output and ANSI escape sequences with lang 'ansi'."
---

# ANSI Output

Set `lang` to `'ansi'` to highlight terminal output — text containing ANSI
escape sequences — instead of tokenizing with a grammar. shikiphp parses the
escape codes and colors the text from the theme's ANSI palette. It is a port of
Shiki's `lang: 'ansi'` tokenizer, and four oracle scenarios check its HTML
against Shiki 4.5.0.

```php
use Shikiphp\Shikiphp;

echo Shikiphp::codeToHtml("\e[31merror\e[0m: something broke", [
    'lang'  => 'ansi',
    'theme' => 'github-dark',
]);
```

This is the natural way to render captured CLI output — test runners, build
logs, `git` output — with its original colors preserved. The escape sequences
are removed from the output; only the text remains, wrapped in colored spans.

Note the double-quoted PHP string: `"\e"` is the escape character. When you
render output captured from a process (for example with `shell_exec` or
`proc_open`), the escape characters are already in the string.

## Render source code to ANSI

`codeToANSI` produces terminal escape sequences from source code, matching
Shiki 4.5's CLI renderer:

```php
echo Shikiphp::codeToANSI('const answer = 42', [
    'lang' => 'js',
    'theme' => 'github-dark',
], colorLevel: 3);
```

The same method is available on `Highlighter`. Color levels are `0` (plain
text), `1` (16 colors), `2` (256 colors), and `3` (truecolor, the default).
The level is explicit and does not depend on `NO_COLOR`, `FORCE_COLOR`, or TTY
detection. Each source line ends in a newline, including an empty final line.

## What it understands

The tokenizer reads SGR (Select Graphic Rendition) sequences, `ESC [ … m`, with
any number of `;`-separated codes:

| Codes | Effect |
| --- | --- |
| `0` | reset all colors and attributes |
| `1`, `3`, `4`, `9` | bold, italic, underline, strikethrough |
| `2` | dim: the foreground is drawn at half opacity |
| `7` | reverse: foreground and background swap |
| `21`, `23`, `24`, `27`, `29` | turn the matching attribute off |
| `22` | normal intensity: turns off dim and bold |
| `30`–`37`, `90`–`97` | foreground: the 8 standard and 8 bright colors |
| `40`–`47`, `100`–`107` | background: the 8 standard and 8 bright colors |
| `38;5;n`, `48;5;n` | foreground or background from the 256-color table |
| `38;2;r;g;b`, `48;2;r;g;b` | 24-bit true-color foreground or background |
| `39`, `49` | default foreground or background |

Hidden (`8`) and overline (`53`) are parsed but do not change the output, and
other codes are ignored. Styles carry across line breaks until they are reset,
as in a terminal.

## Colors

The 16 named colors come from the theme's `terminal.ansi*` colors
(`terminal.ansiRed`, `terminal.ansiBrightBlue`, and so on). Where a theme does
not define one, the VS Code default palette is used (`#cd3131` for red,
`#2472C8` for blue, and so on). Text with no color set takes the theme's
foreground, and the block background is the theme's background, as for
grammar-based highlighting. In the 256-color table, entries 0–15 are those same
16 named colors; entries 16–255 (the 6×6×6 color cube and the grayscale ramp)
and true-color values do not depend on the theme.

[`colorReplacements`](/docs/options#colorreplacements) apply to ANSI colors too.

## Dual themes

ANSI honors the `themes` map just like grammar-based highlighting, emitting
CSS-variable variants for the non-default themes:

```php
use Shikiphp\Shikiphp;

$log = "\e[32mPASS\e[0m tests/Unit\n\e[31mFAIL\e[0m tests/Integration";

echo Shikiphp::codeToHtml($log, [
    'lang'   => 'ansi',
    'themes' => ['light' => 'github-light', 'dark' => 'github-dark'],
]);
```

Each named color resolves against each theme's own palette, so a red in the
light theme and a red in the dark theme can differ.

## No grammar state

Because ANSI does not use a TextMate grammar, there is no grammar state to carry.
`getLastGrammarState` throws for `lang: 'ansi'`, and `codeToTokensResult` returns
a `null` `grammarState`.
