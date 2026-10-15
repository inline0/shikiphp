# Changelog

All notable changes to shikiphp are documented here. The format is based on
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and this project adheres
to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [0.2.0] - 2026-10-15

### Added

- Shiki 4.5.0 compatibility, with pinned transformers and CLI oracles at 4.5.0.
- `RenderLineNumber` transformer and `NotationWordHighlight::classActiveCode`.
- `includeExplanation: 'tokenType'` for standard TextMate token types.
- `codeToANSI()` on the facade and highlighter with explicit 0/16/256/truecolor
  levels, plus CLI ANSI output and standard input via `highlight -`.
- Seven languages from tm-grammars 1.32.22: AutoHotkey 1/2, Chapel, NSIS, Org,
  RBS, and Smithy; 242 languages, 18 helpers, and 104 aliases in total.
- Reproducible Shiki 4.5 oracle fixtures for HTML transformers, token types and
  all four ANSI color levels, plus live oracle scenarios for all new languages.

### Changed

- Updated tm-themes to 1.12.12 and oniguruma-to-es tooling to 4.3.6.
- CLI language selection follows upstream: case-insensitive ids and plaintext
  fallback for unknown explicit languages. Unknown themes still fail.

### Fixed

- Bound compiled grammar caches in long-lived highlighters while retaining
  incremental states, preventing memory exhaustion across the full language corpus.
  Reloading a grammar also invalidates compiled parents that include it.
- HTML/HAST rendering now resumes the supplied grammar state as token APIs do.
- Shiki light-theme fallback colors resolve before TextMate theme compilation.
- Notation in comments with explanation text affects its own line and retains
  that text; split comment prefixes work with Rose Pine themes.

## [0.1.1] - 2026-10-14

### Fixed

- Plain-text language ids `text`, `txt`, `plain`, and `plaintext` render
  untokenized text with the theme's colors, preserving empty lines and UTF-16
  offsets without a grammar state.
- CLI highlighting falls back to plain text for unknown extensions and
  extensionless files. Invalid explicit languages and themes report a one-line
  error and exit 1 instead of printing a PHP fatal error.
- Composer's description names the required `json` and `mbstring` extensions.

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

[0.1.1]: https://github.com/inline0/shikiphp/compare/v0.1.0...v0.1.1
[0.1.0]: https://github.com/inline0/shikiphp/releases/tag/v0.1.0

[0.2.0]: https://github.com/inline0/shikiphp/compare/v0.1.1...v0.2.0
