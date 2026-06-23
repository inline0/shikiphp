# Changelog

All notable changes to shikiphp are documented here. The format is based on
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and this project adheres
to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [0.1.0] - 2026-06-23

### Added

- Pure-PHP port of Shiki's highlighting pipeline:
  - Oniguruma→JS pattern converter (`oniguruma-to-es` port) over a vendored
    pure-PHP JavaScript regex engine (`Shikiphp\Regex`, from `inline0/phasis`).
  - `vscode-textmate` tokenizer port: match/begin-end/begin-while rules, captures,
    includes, `$self`/`$base`, embedded languages, injections, nested repositories,
    and `\G` scan anchoring.
  - VS Code theme resolution with scope-selector specificity and parent-scope
    selectors.
  - Shiki-compatible HTML renderer (single and dual light/dark themes).
- Public API: `Shikiphp::codeToHtml()`, `codeToTokens()`, `codeToTokensBase()`,
  `codeToTokensResult()` (a `TokensResult` with `fg`/`bg`/`themeName`/`rootStyle`/
  `grammarState`), `codeToTokensWithThemes()` (per-token theme variants),
  `getLastGrammarState()` (resume tokenization across chunks), and a `Highlighter`
  with `codeToHast()`.
- Custom-loading API: `Highlighter::loadGrammar()` / `loadTheme()` for user-supplied
  grammars/themes, plus `bundledLanguages()` / `bundledThemes()` accessors.
- Transformer pipeline with all Shiki hooks (`preprocess`, `tokens`, `root`,
  `pre`, `code`, `line`, `span`, `postprocess`) and `enforce` ordering.
- All `@shikijs/transformers` built-ins: notation comments (highlight, diff,
  focus, error-level, word), meta highlight/word, render-whitespace,
  compact-line-options, remove-notation-escape, remove-line-break, and
  style-to-class (with `getCSS()`).
- `codeToHtml` options: `transformers`, `decorations`, `colorReplacements`,
  `structure` (`classic`/`inline`), `tabindex`, `cssVariablePrefix`,
  `mergeWhitespaces`, `tokenizeMaxLineLength`, `defaultColor`, `meta`.
- ANSI highlighting (`lang: 'ansi'`): SGR parser, 16/256/truecolor palette with
  theme `terminal.ansi*` overrides and Shiki's default fallback.
- The full Shiki bundle: every `tm-grammars` language (200+) and `tm-themes`
  theme (65). The Oniguruma→JS converter handles all 32k+ grammar patterns with
  zero failures, and every language tokenizes without error.
- CLI (`bin/shikiphp`) and a Shiki.js oracle regression harness (214 scenarios
  across ~77 languages, validated token-for-token).

[0.1.0]: https://github.com/inline0/shikiphp/releases/tag/v0.1.0
