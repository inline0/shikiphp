---
title: "Performance"
description: "What to expect from shikiphp's pure-PHP engine, and how to keep highlighting fast in production."
path: "performance"
order: 100
section: "Advanced"
meta_title: "Performance"
meta_description: "What to expect from shikiphp's pure-PHP engine, and how to keep highlighting fast in production."
---

# Performance

shikiphp does real TextMate tokenization in pure PHP, so it is worth being
honest about where it is fast and where it is not.

## What to expect

Expect tens to hundreds of milliseconds per file, not microseconds. The
tokenizer caches compiled rules and scanners, grammars load lazily (a sample
that never touches an embedded language never pays to decode it), and most
grammar patterns run through native `preg_*` on an equivalence-gated PCRE fast
path, with the pure-PHP matcher handling the rest (see
[Architecture](/docs/architecture#the-scanner-and-its-pcre-fast-path)).

These are warm, single-call times from the repository's benchmark
(`bench/bench.php`, theme `github-dark`) on one machine, for corpus files of
roughly 55 to 105 lines:

| Grammar | PHP 8.2 | PHP 8.4 |
| --- | --- | --- |
| JSON, YAML | 30–65 ms | 25–45 ms |
| CSS, HTML | 100–120 ms | 80–100 ms |
| Python, Rust, Go, Bash, Markdown | 190–220 ms | 125–175 ms |
| PHP | about 290 ms | about 170 ms |
| TypeScript, TSX | 490–510 ms | 310–370 ms |

The first call for a language in a process also pays one-time grammar loading
and compilation: in the same runs that ranged from 50–70 ms for JSON to just
over a second for Markdown. Cost scales with grammar complexity and input size,
and the figures move with hardware, so measure on your own: in a clone of the
repository, run `composer install` and then `composer bench`.

## Safety guards

The regex engine includes failure-memoization and ReDoS protection so a
pathological pattern degrades rather than hanging. You can also cap per-line work
with [`tokenizeMaxLineLength`](/docs/options#tokenizemaxlinelength), which emits a
single plain token for any line at or beyond a given length — a guard against
adversarial single-line inputs.

## Cache in production

The reliable way to keep highlighting off your request path is to **not** run it
on every request. Highlighting the same code with the same options always
produces the same HTML, so it caches perfectly:

- Highlight at build time for static sites and documentation.
- Cache the rendered HTML (keyed by code + options) for user-supplied or
  database-stored snippets, and reuse it until the source changes.

Within a process, reuse a single highlighter (the `Shikiphp` facade does this for
you) so grammar and theme caches are shared across calls.
