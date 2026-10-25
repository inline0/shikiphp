---
title: "Architecture"
description: "How shikiphp tokenizes and renders — the converter, scanner, tokenizer, theme resolver, and the vendored pure-PHP JS regex engine."
path: "architecture"
order: 90
section: "Advanced"
meta_title: "Architecture"
meta_description: "How shikiphp tokenizes and renders — the converter, scanner, tokenizer, theme resolver, and the vendored pure-PHP JS regex engine."
---

# Architecture

shikiphp turns source code into themed, highlighted HTML using TextMate grammars
and VS Code themes, with no PHP extensions beyond `json`/`mbstring`, no Node
runtime, and no native Oniguruma binding. This page summarizes the pipeline; the
full normative contract lives in `ARCHITECTURE.md` in the repository.

## The pipeline

```
source code
  └─ Highlighter::codeToTokens()
       ├─ Grammar\Registry        loads the grammar (+ its includes / embedded langs)
       ├─ Grammar\Tokenizer       line by line, driving an OnigScanner per rule state
       │     └─ Oniguruma\OnigScanner.findNextMatch()
       │           └─ Oniguruma\PatternConverter   Oniguruma pattern → JS regex
       │                 └─ Shikiphp\Regex (Parser + Matcher)   JS RegExp runtime
       ├─ Theme\Theme.match(scopeStack) → StyleAttributes   per token
       └─ Render\ThemedToken[]   (text + style) per line
  └─ Render\HtmlRenderer  → <pre class="shiki">…</pre>
```

Everything operates in **UTF-16 code-unit** offset space — the same space as the
JS regex engine and as Shiki's tokenizer. For ASCII this equals byte offsets.

## The regex runtime

At the bottom is `Shikiphp\Regex`, a complete pure-PHP ECMAScript regex engine
**vendored from [`inline0/phasis`](https://github.com/inline0/phasis)** (a
pure-PHP JavaScript engine). It parses a JS-RegExp source plus flags and runs
forward searches with Oniguruma `onig_search` semantics.

## Oniguruma → JS conversion

TextMate grammars are written in Oniguruma. `Shikiphp\Oniguruma\PatternConverter`
is a PHP port of
[`oniguruma-to-es`](https://github.com/slevithan/oniguruma-to-es): it converts an
Oniguruma pattern into a JS-RegExp-compatible source and flags string the regex
engine accepts. This is the same Oniguruma → RegExp path Shiki's JavaScript
regex engine takes, which is what lets shikiphp match that engine's output
offset-for-offset. The converter
handles the real-world constructs grammars use: POSIX classes, possessive
quantifiers and atomic groups, inline flags, named groups and backreferences,
Unicode properties, and more.

## The scanner and its PCRE fast-path

`Shikiphp\Oniguruma\OnigScanner` mirrors `vscode-oniguruma`'s scanner: it is built
once per rule from a list of patterns and finds the leftmost match at or after a
start position.

The tree-walking matcher is correct but not fast, so the scanner has an
**equivalence-gated PCRE fast-path** with two tiers. A `PcreTranslator` rewrites
the converter's JS source into a native PCRE pattern only where it can prove the
result:

- **Full match.** When the translation behaves identically to the matcher,
  including every capture, the pattern runs on PCRE outright. Named groups,
  backreferences, `\p{}`, `\b`, `\G`, atomic emulation, and captures whose
  JavaScript semantics differ from PCRE (inside a quantified group or a
  lookbehind) are rejected from this tier.
- **Position only.** When only captures could differ, a second translation
  admits those constructs (`\b` and `\B` as exact ASCII lookarounds, atomic
  groups as native `(?>…)`, `\p{…}` when PCRE compiles it). PCRE then only
  finds where the next match is, and the matcher re-runs at that offset to
  produce the real captures. Backreferences and `\G` stay rejected here too.

Everything else runs on the matcher alone. For the bundled grammars at Shiki
4.5.0, about 39% of the 14,234 distinct patterns run fully on PCRE, about 57%
use the position tier, and about 4% stay on the matcher. The classification is
checked by a development tool in the repository
(`bin/.oracle-tools/pcre-equivalence.php`) that compares the fast path with the
matcher — index, end, and, for the first tier, every capture span — over the
scenario inputs and a set of synthetic strings, with zero divergences allowed.

## Grammar and theme

- **Grammar** (`Shikiphp\Grammar`) is a faithful port of `vscode-textmate`: a
  `Registry` resolves grammars by scope name (including `include` references,
  embedded languages, and injections), and a `Tokenizer` maintains the rule and
  scope stacks line by line.
- **Theme** (`Shikiphp\Theme`) ports `vscode-textmate`'s theme matching:
  foreground and font style resolve by scope-selector specificity with
  parent-scope matching.

## Render

`Shikiphp\Render\HtmlRenderer` builds the exact `pre.shiki > code > span.line >
span` HAST tree Shiki builds, then serializes it. Single-theme mode writes plain
colors; dual-theme mode writes `--shiki-light` / `--shiki-dark` CSS variables.

## Validated against Shiki

Shiki itself is the oracle, pinned to `shiki` 4.5.0 with its JavaScript regex
engine. The `scenarios/` directory holds **214** fixtures, each one input in one
language and one theme; together they cover 174 of the 260 bundled grammars,
`lang: 'ansi'`, and all 65 themes. `composer test:oracle` highlights each with
both engines and asserts the HTML is identical once leading and trailing
whitespace is trimmed. Options and transformers are checked by parity tests
against output captured from the same Shiki and `@shikijs/transformers` 4.5.0.
The non-negotiable rule is that highlighting output must not regress against
Shiki.
