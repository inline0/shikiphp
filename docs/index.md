---
title: "Introduction"
description: "shikiphp is a pure-PHP port of Shiki — TextMate grammars and VS Code themes, with output matched against Shiki 4.5.0, entirely in PHP."
path: "."
order: 10
meta_title: "Introduction"
meta_description: "shikiphp is a pure-PHP port of Shiki — TextMate grammars and VS Code themes, with output matched against Shiki 4.5.0, entirely in PHP."
---

# shikiphp

shikiphp is a pure-PHP port of [Shiki](https://shiki.style), the syntax
highlighter that colors code with the same TextMate grammars and themes as VS
Code. It tokenizes code with the same **TextMate grammars** and paints it with
the same **VS Code themes** Shiki uses, producing the same HTML as Shiki 4.5.0
— entirely in PHP.

No Node runtime. No PHP extensions beyond `json` and `mbstring`. No native
Oniguruma binding. No shelling out to a JavaScript process, and no hosted API.

```php
use Shikiphp\Shikiphp;

echo Shikiphp::codeToHtml(<<<'PHP'
<?php
function greet(string $name): string {
    return "Hello, {$name}!";
}
PHP, [
    'lang'  => 'php',
    'theme' => 'github-dark',
]);
```

## Why shikiphp

Server-rendered syntax highlighting in PHP usually means one of three
compromises: shelling out to Node, calling a hosted API, or settling for a
regex-based highlighter that does not actually understand the language. None of
those fit cleanly into a Laravel, WordPress, or static-site build step.

shikiphp removes the compromise by porting Shiki's real pipeline to PHP:

- a TextMate grammar tokenizer (a faithful port of
  [`vscode-textmate`](https://github.com/microsoft/vscode-textmate)), including
  embedded languages, injections, and nested repositories
- VS Code theme resolution with scope-selector specificity and parent-scope
  matching
- Shiki-compatible HTML: dual light/dark themes via CSS variables, `codeToHast`,
  transformers (every hook), decorations, `colorReplacements`,
  `structure: 'inline'`, and ANSI (`lang: 'ansi'`) highlighting
- the **full Shiki bundle** — every `tm-grammars` language (242, plus 18
  helper grammars they embed) and every `tm-themes` theme (65)

## Matched against Shiki 4.5.0

The output is not "close enough": it is checked against a pinned Shiki release.

| Package | Version | Used for |
| --- | --- | --- |
| `shiki` | 4.5.0 | the oracle, run with its JavaScript regex engine |
| `tm-grammars` | 1.32.22 | the bundled grammars |
| `tm-themes` | 1.12.12 | the bundled themes |
| `@shikijs/transformers` | 4.5.0 | the transformer parity tests |

Shiki itself is the oracle. The regression harness holds **221 scenarios**,
each one sample in one language and one theme with default options. Every
scenario is highlighted by Shiki and by shikiphp, and the two HTML strings must
be identical once leading and trailing whitespace is trimmed. Together the
scenarios cover 174 of the 260 bundled grammars, `lang: 'ansi'`, and all 65
themes. The options (`structure`, `tabindex`, `colorReplacements`, dual themes,
`defaultColor` and the rest), `codeToTokensWithThemes`, and the built-in
transformers are covered by separate parity tests whose expected output was
captured from the same Shiki versions.

Two limits follow from that. Shiki's default engine is Oniguruma compiled to
WebAssembly, and the oracle uses Shiki's JavaScript regex engine instead, which
is the engine shikiphp ports. And later Shiki releases (the 4.x line) have not
been checked.

## How it works

shikiphp is built on a vendored pure-PHP **JavaScript regex engine** from
[`inline0/phasis`](https://github.com/inline0/phasis), fed by a PHP port of
[`oniguruma-to-es`](https://github.com/slevithan/oniguruma-to-es). Shiki's
JavaScript regex engine converts the Oniguruma patterns in TextMate grammars to
JavaScript `RegExp`, so a JS regex engine is the natural substrate — it lets
shikiphp match that engine's output offset-for-offset.

```
code → grammar tokenizer → theme resolver → themed tokens → HTML
              │
              └─ OnigScanner → PatternConverter (oniguruma-to-es) → Shikiphp\Regex (JS RegExp engine)
```

See [Architecture](/docs/architecture) for the full pipeline.

## Requirements

- PHP 8.2 or later (CI runs the test suite on PHP 8.4)
- `ext-json` and `ext-mbstring` (both ship with virtually every PHP build)

## Where to next

- [Getting Started](/docs/getting-started) — install and render your first block.
- [API](/docs/api) — the full method surface.
- [Options](/docs/options) — every option `codeToHtml` accepts.
- [Transformers](/docs/transformers) — the hook pipeline and the built-ins.
- [CLI](/docs/cli) — `bin/shikiphp highlight`, `langs`, and `themes`.

## License

MIT, copyright inline0. Bundled TextMate grammars and themes retain their
upstream licenses.
