---
title: "Performance"
description: "What to expect from shikiphp's pure-PHP engine, and how to keep highlighting fast in production."
path: "performance"
order: 9
section: "Documentation"
meta_title: "Performance"
meta_description: "What to expect from shikiphp's pure-PHP engine, and how to keep highlighting fast in production."
---

# Performance

shikiphp does real TextMate tokenization in pure PHP, so it is worth being
honest about where it is fast and where it is not.

## What to expect

Highlighting is fast across the board. The tokenizer caches compiled rules and
scanners, grammars load lazily (a sample that never touches an embedded language
never pays to decode it), and a two-tier, equivalence-gated PCRE fast-path runs
~93% of all grammar patterns through native `preg_*`: proven-identical patterns
run on PCRE outright, and extent-equivalent ones use PCRE to locate the match
position with the spec-faithful matcher confirming there (full fallback on any
disagreement, validated by a 20M-comparison differential harness).

Ballpark figures for a ~90-line file on stock PHP (warm process): simple grammars
like JSON or YAML in the tens of milliseconds; CSS, HTML, Bash around 50–120ms;
PHP, Python, Rust, Go around 200–280ms; and the heaviest grammars in the bundle,
TypeScript and TSX, around 330ms. Cost scales with grammar complexity and input
size; the first call per language additionally pays one-time grammar compilation.

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
