# Changelog

All notable changes to shikiphp are documented here. The format is based on
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and this project adheres
to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

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
- Public API: `Shikiphp::codeToHtml()`, `codeToTokens()`, and a `Highlighter`
  with `codeToHast()`.
- Transformer pipeline with all Shiki hooks (`preprocess`, `tokens`, `root`,
  `pre`, `code`, `line`, `span`, `postprocess`) and `enforce` ordering.
- `codeToHtml` options: `transformers`, `decorations`, `colorReplacements`,
  `structure` (`classic`/`inline`), `tabindex`, `cssVariablePrefix`,
  `mergeWhitespaces`, `tokenizeMaxLineLength`, `defaultColor`.
- ANSI highlighting (`lang: 'ansi'`): SGR parser, 16/256/truecolor palette with
  theme `terminal.ansi*` overrides and Shiki's default fallback.
- 76 bundled languages and 20 bundled themes (from `tm-grammars`/`tm-themes`).
- CLI (`bin/shikiphp`) and a Shiki.js oracle regression harness (78 scenarios).

[Unreleased]: https://github.com/inline0/shikiphp/commits/main
