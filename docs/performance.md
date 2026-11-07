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
honest about where it is fast and where it is not. This page shows what the
repository's own benchmarks measure, explains what the library caches by itself,
and shows how to cache the rendered HTML so highlighting stays off your request
path.

## What to expect

Once a language is warm, a file of about 100 lines takes from a few milliseconds
(JSON, YAML) to about 40 ms (TypeScript, TSX). The first call for each language
in a process costs more, because it loads the grammar JSON and compiles its
rules and regexes. The tokenizer caches compiled rules and scanners, grammars
load lazily (a sample that never touches an embedded language never pays to
decode it), and most grammar patterns run through native `preg_*` on an
equivalence-gated PCRE fast path, with the pure-PHP matcher handling the rest
(see [Architecture](/docs/architecture#the-scanner-and-its-pcre-fast-path)).

## Benchmark results

`bench/bench.php` highlights one file per language from `bench/corpus/` with
`Highlighter::codeToHtml()` and the `github-dark` theme. It reports two numbers
per language:

- **First call**: a new `Highlighter` highlights each language once, in the
  order of the table below. This includes loading and compiling the grammar.
- **Warm**: the median of 10 calls on a highlighter that has already
  highlighted every file once.

With `--runs=N` (default 5) the script repeats both passes and reports the
median. Only the first pass in a process is truly cold, because compiled regexes
outlive the highlighter (see [What shikiphp caches](#what-shikiphp-caches)), so
the first-call column comes from a single run:

```bash
php -n -d memory_limit=1G bench/bench.php --runs=1
```

These figures were measured on 2026-11-07 at commit `2232b1c`, on an Apple M1
Pro (10 cores, 16 GB) with Homebrew PHP 8.5.11 NTS. `-n` skips `php.ini`, so
Xdebug was not loaded and OPcache was off, which is the CLI default. The machine
was running other work at the same time, so treat the numbers as indicative.

| Language | Lines | Bytes | First call (ms) | Warm (ms) |
| --- | ---: | ---: | ---: | ---: |
| PHP | 107 | 2,782 | 144.7 | 25.8 |
| TypeScript | 85 | 2,311 | 153.2 | 41.0 |
| TSX | 95 | 2,418 | 73.7 | 41.7 |
| JSON | 56 | 1,442 | 4.7 | 4.4 |
| CSS | 82 | 1,488 | 38.7 | 11.2 |
| HTML | 66 | 1,902 | 48.9 | 14.1 |
| Markdown | 66 | 1,578 | 146.6 | 25.2 |
| Python | 89 | 2,481 | 49.1 | 23.4 |
| Rust | 97 | 2,176 | 20.9 | 17.5 |
| Go | 91 | 1,603 | 36.6 | 26.1 |
| YAML | 71 | 1,380 | 9.6 | 7.3 |
| Bash | 77 | 1,543 | 23.1 | 20.7 |
| **Total** | | | **749.8** | **258.5** |

Peak memory for the run (`memory_get_peak_usage(true)`) was 122.0 MB. The
default five-run benchmark on the same machine measured a warm total of
256.1 ms.

Two things stand out:

- Grammar complexity drives the cost more than file size. The JSON and
  TypeScript files differ in size by a factor of 1.6, but in warm time by a
  factor of about 9.
- A first call is cheaper when an earlier language already compiled the same
  regex patterns. TSX, measured right after TypeScript, took 73.7 ms against
  TypeScript's 153.2 ms.

## How PHP configuration changes the numbers

The same benchmark under different PHP settings, comparing the warm total for
all twelve files:

| Setup | Benchmark flags | Warm total (ms) | Peak memory |
| --- | --- | ---: | ---: |
| `php -n` (baseline above) | `--runs=1` | 258.5 | 122.0 MB |
| OPcache with tracing JIT | `--runs=3` | 166.4 | 104.0 MB |
| Xdebug 3.5.1 loaded, `xdebug.mode=develop` | `--runs=1 --warm=3` | 882.5 | 122.0 MB |

The JIT run used:

```bash
php -n -d memory_limit=1G -d opcache.enable_cli=1 -d opcache.jit=tracing \
  -d opcache.jit_buffer_size=128M bench/bench.php --runs=3
```

The JIT cut warm time by about a third, and a loaded Xdebug made it about 3.4
times slower. Keep Xdebug out of production, and measure with the same
`php.ini` your servers use before you decide whether JIT is worth enabling.

### The PCRE fast path

`bench/pcre-ab.php` highlights ten of the corpus files (all of the above except
YAML and Bash) eight times on a warm highlighter and prints the median total.
Setting `SHIKIPHP_NO_PCRE` turns the fast path off, so every pattern runs on the
pure-PHP matcher:

```bash
php -n -d memory_limit=1G bench/pcre-ab.php
SHIKIPHP_NO_PCRE=1 php -n -d memory_limit=1G bench/pcre-ab.php
```

On the machine above:

```text
PCRE=on  warm median total: 227.80 ms  (iters=8)
PCRE=off  warm median total: 5273.20 ms  (iters=8)
```

The fast path makes warm highlighting about 23 times faster. It only takes
patterns that are proven to match the same way as the matcher, so the output
does not change; the variable exists for comparisons and debugging. It is read
when a pattern is first compiled, so set it before the process starts.

## What shikiphp caches

Every cache lives in memory. Nothing is written to disk and nothing survives the
process. Under PHP-FPM each request therefore starts empty and pays the
first-call cost for every language it highlights, while a long-running process
(a static site build, a queue worker, an application server that keeps the
process alive between requests) pays it once per language.

- **Compiled regexes, per process.** `OnigScanner` keeps a static cache keyed by
  the Oniguruma source of each pattern, holding the converted pattern, the
  compiled matcher and any PCRE translation. Every `Highlighter` in the process
  shares it, and it is never evicted.
- **Compiled grammars, per highlighter.** Each language's root grammar is
  compiled into its own rule graph and kept in a least-recently-used cache of
  16 grammars. A [`GrammarState`](/docs/api#grammarstate) keeps its own grammar
  alive after eviction, so incremental highlighting keeps working.
- **Compiled themes, per highlighter.** Each theme is compiled once by id, and
  each compiled theme remembers the style it resolved for every scope path it
  has seen.
- **Decoded grammar JSON, per bundle loader.** Grammar files are decoded on
  first use and kept for the life of the loader.
- **Rule scanners.** Each rule keeps its compiled scanner, one per `\A`/`\G`
  anchor combination, and begin/end rules keep one per distinct resolved end
  pattern.

Two operations clear caches. `loadGrammar()` discards every compiled grammar on
that highlighter, because includes and injections can make any of them depend on
the new grammar. `loadTheme()` discards only the compiled copy of the theme with
that name. `Shikiphp::reset()` drops the facade's highlighter, but not the
process-wide regex cache.

In practice:

- Reuse one highlighter. The `Shikiphp` facade does this for you (see
  [Reusing the highlighter](/docs/getting-started#reusing-the-highlighter)).
  `Highlighter::createBundled()` builds a new one with empty grammar and theme
  caches and its own bundle loader.
- Register [custom grammars and themes](/docs/custom-languages-themes) once at
  startup, before you highlight anything, so registration never throws away
  compiled grammars.
- Leave memory headroom when one process highlights many languages. The
  compiled-grammar cache is bounded, but the regex cache and the decoded JSON
  are not, and the benchmark above peaked at 122 MB with twelve languages,
  close to PHP's default `memory_limit` of 128M.

```php
use Shikiphp\Shikiphp;

$highlighter = Shikiphp::highlighter();
$highlighter->loadGrammar(json_decode(file_get_contents('my-lang.tmLanguage.json'), true));

foreach ($snippets as $snippet) {
    $html[] = $highlighter->codeToHtml($snippet['code'], [
        'lang'  => $snippet['lang'],
        'theme' => 'github-dark',
    ]);
}
```

## Cache the rendered HTML

The reliable way to keep highlighting off your request path is to **not** run it
on every request. Highlighting the same code with the same options and the same
shikiphp version always produces the same HTML, so it caches perfectly:

- Highlight at build time for static sites and documentation. The
  [CLI](/docs/cli) works for build scripts that are not written in PHP.
- Cache the rendered HTML, keyed by code, options and shikiphp version, for
  user-supplied or database-stored snippets, and reuse it until the source
  changes.

A minimal file cache:

```php
use Composer\InstalledVersions;
use Shikiphp\Shikiphp;

function highlightCached(string $code, array $options, string $cacheDir): string
{
    $key = hash('xxh128', json_encode([
        InstalledVersions::getVersion('shikiphp/shikiphp'),
        $code,
        $options,
    ], JSON_THROW_ON_ERROR));
    $file = $cacheDir . '/' . $key . '.html';

    if (is_file($file)) {
        return (string) file_get_contents($file);
    }

    $html = Shikiphp::codeToHtml($code, $options);
    file_put_contents($file, $html, LOCK_EX);

    return $html;
}

$html = highlightCached('const x = 1', ['lang' => 'js', 'theme' => 'nord'], __DIR__ . '/cache');
```

This key works for options made of strings, numbers and arrays, such as
[`lang`, `theme`, `themes` and `decorations`](/docs/options). Objects do not
encode usefully: [transformers](/docs/transformers) and a `grammarState` encode
as empty objects, so two different transformer lists would share a key. When you
pass them, add a name or version for your transformer set to the key instead.
Including the shikiphp version means a library upgrade, which may change
grammars, themes or markup, starts a fresh cache.

## Safety guards

The regex engine includes failure-memoization and ReDoS protection so a
pathological pattern degrades rather than hanging. You can also cap per-line work
with [`tokenizeMaxLineLength`](/docs/options#tokenizemaxlinelength), which emits a
single plain token for any line at or beyond a given length. Use it as a guard
against adversarial single-line inputs.

## Run the benchmarks yourself

The numbers move with hardware, PHP version and configuration, so measure on the
machine you deploy to. In a clone of the repository:

```bash
composer install
composer bench
php bench/bench.php --runs=10 --warm=20 --json
```

`composer bench` runs `bench/bench.php` with your default `php.ini`, Xdebug
included if it is loaded. `--json` prints the theme, run counts, PHP version,
per-language `lines`, `bytes`, `coldMs` and `warmMs`, and the `coldTotalMs`,
`warmTotalMs` and `peakMemMb` totals.
