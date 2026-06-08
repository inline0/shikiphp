# shikiphp

Pure PHP port of [Shiki](https://shiki.style). Tokenizes code with TextMate
grammars and paints it with VS Code themes, producing Shiki-compatible HTML —
no extensions beyond `json`/`mbstring`, no Node, no native Oniguruma binding.

Read **[docs/ARCHITECTURE.md](./docs/ARCHITECTURE.md)** first — it is the
normative contract for every subsystem (value-object shapes, method signatures,
the pipeline). Do not diverge from it without updating it.

## Quick Reference

```bash
# Testing (oracle-driven — Shiki.js is the oracle)
composer verify                          # Final gate: analyse + cs + test + oracle
composer test                            # PHPUnit (unit + integration + oracle suites)
composer test:unit                       # Isolated component tests
composer test:oracle                     # Regression vs Shiki.js across scenarios/
php bin/test-regression --jobs 4         # Oracle matrix, parallel
php bin/oracle <lang> <theme> <file>     # Reference HTML from Shiki.js
php bin/actual <lang> <theme> <file>     # shikiphp HTML
php bin/compare <scenario>               # Normalized diff oracle vs actual

# Code quality
composer cs                              # PHPCS (PSR-12)
composer cs:fix                          # phpcbf
composer analyse                         # PHPStan (level 10, src/ — excludes vendored src/Regex)

# CLI
php bin/shikiphp highlight <file> --lang=<lang> --theme=<theme>
php bin/shikiphp langs
php bin/shikiphp themes
```

## Non-Negotiable Testing Rule

After every meaningful change, run the full matrix from the repo root before
treating the work as done:

```bash
composer verify
```

Highlighting output is diffed against Shiki.js. Token boundaries, scopes, and
rendered HTML must not regress. No partial sign-off.

## What This Is

A library that, given source code + a language + a theme:

1. Loads the language's TextMate grammar and resolves its includes/injections.
2. Tokenizes line by line via a `vscode-textmate` port, driving an `OnigScanner`.
3. The scanner runs grammar regexes by converting each Oniguruma pattern to a JS
   RegExp (`PatternConverter`, an `oniguruma-to-es` port) and matching it on the
   vendored pure-PHP JS regex engine `Shikiphp\Regex`.
4. Resolves each token's scope stack against a VS Code theme.
5. Renders Shiki-compatible `<pre class="shiki">` HTML (single or dual theme).

## What This Is Not

Not a regex-based "good enough" highlighter (Highlight.php, Prism). Not a Node
wrapper or API client. Not a Markdown renderer. shikiphp does one thing: produce
the same themed tokens and HTML as Shiki, in pure PHP.

## Layout

- `src/Regex/` — **vendored** JS regex engine from `phasis`. Do not hand-edit;
  re-sync upstream. See `src/Regex/NOTICE.md`.
- `src/Oniguruma/` — `PatternConverter` (oniguruma-to-es port), `OnigScanner`, value objects.
- `src/Grammar/` — `Registry`, rules, `Tokenizer`, `StateStack`, `ScopeStack`, `Token`.
- `src/Theme/` — `Theme`, theme matching, `StyleAttributes`, `FontStyle`.
- `src/Render/` — `ThemedToken`, `HtmlRenderer`.
- `src/Registry/` — bundled grammars + themes (JSON) and the bundle manifest.
- `src/Highlighter.php`, `src/Shikiphp.php` — orchestration + static facade.
- `bin/.oracle-tools/` — Node side: `shiki` dependency + `oracle.mjs` + bundler.
- `scenarios/` — oracle fixtures (`input.<ext>` + `meta.json`).

## Conventions

PHP 8.2+, `declare(strict_types=1)`, PSR-12, `final` classes, readonly value
objects, constructor promotion. UTF-16 code-unit offsets throughout (matches the
JS engine and Shiki). PHPStan must stay green at level 8 over `src/` (the vendored
`src/Regex/` is excluded from analysis at level 10).

Comments are sparse. Class docblock: 1–3 lines. Method docblock: only to carry
`@param`/`@return`/`@var` shapes PHP types can't express, or a one-line note for
genuinely non-obvious behaviour. No inline comments that narrate what code does —
only the rare *why*. No comments in config files (xml/neon/json/yml).
