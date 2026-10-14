---
title: "CLI"
description: "The bin/shikiphp command — highlight a file to HTML, or list bundled languages and themes."
path: "cli"
order: 110
section: "Reference"
meta_title: "CLI"
meta_description: "The bin/shikiphp command — highlight a file to HTML, or list bundled languages and themes."
---

# CLI

shikiphp ships a small command-line tool at `bin/shikiphp` (installed as
`vendor/bin/shikiphp` in your project). It has three commands: `highlight`
renders one file to HTML, and `langs` and `themes` list what the bundle
contains.

## `highlight`

Highlight a file to HTML, printed to stdout.

```bash
vendor/bin/shikiphp highlight <file> [--lang=<lang>] [--theme=<theme>]
```

- `<file>` — the path to read. It must be an existing file; standard input is
  not read.
- `--lang` — the language id or alias. If omitted, a recognized file extension
  selects its language; unknown extensions and extensionless files use plain text.
- `--theme` — the theme id. Defaults to `github-dark`.

```bash
vendor/bin/shikiphp highlight src/App.php --lang=php --theme=nord
```

The output is the same single-theme `<pre class="shiki …">` block that
[`codeToHtml`](/docs/api#shikiphpcodetohtml) returns, followed by a newline, so
it can be redirected straight into a file:

```bash
vendor/bin/shikiphp highlight src/App.php --theme=github-light > app.html
```

The command takes no other options. For dual themes, transformers, or any other
[option](/docs/options), call the PHP API instead.

### Options and arguments

Options must use the `--name=value` form. Any other argument that starts with
`--` is ignored, and the last argument that does not start with `--` is taken as
the file. `--lang php file.rs` therefore highlights `file.rs` as Rust: `--lang`
is ignored, `php` is replaced by `file.rs`, and the language comes from the
`.rs` extension.

### Language inference

Without `--lang`, a recognized extension becomes the language id. That works
whenever the extension is itself a bundled id or alias — `php`, `js`, `ts`,
`tsx`, `py`, `rs`, `go`, `md`, `yml`, `json`, `sh`, and many more (see
[Languages](/docs/languages)). Other files render as plain text:

- files without an extension, such as `Makefile` or `Dockerfile`, which fall
  back to `txt`;
- extensions that are not a language id. The `.txt` extension also renders plain text.

Pass `--lang` to select a grammar for extensionless files:

```bash
vendor/bin/shikiphp highlight Dockerfile --lang=docker
vendor/bin/shikiphp highlight Makefile --lang=make
```

## `langs`

Print every id the bundle accepts as a language, one per line.

```bash
vendor/bin/shikiphp langs
```

The list has 350 lines: the 253 bundled grammar ids in bundle order (not
alphabetical), followed by their 97 aliases (`js`, `cjs`, `mjs`, and so on).
The grammar ids include 18 helper grammars that other grammars embed, such as
`vue-directives` and `es-tag-css`; see [Languages](/docs/languages) for the
catalog with names and aliases. Pipe through `sort` for an alphabetical list.

## `themes`

Print every bundled theme id, one per line, in alphabetical order (65 themes).

```bash
vendor/bin/shikiphp themes
```

See [Themes](/docs/themes) for the catalog with names and light/dark types.

## Help and exit status

Run with no command, or `-h` / `--help` / `help`, to print usage.

```bash
vendor/bin/shikiphp --help
```

| Situation | Output | Exit status |
| --- | --- | --- |
| Success, or help | stdout | `0` |
| Unknown command | `Unknown command: <name>` on stderr | `1` |
| `highlight` without a file, or the file does not exist | the usage line on stderr | `1` |
| Unknown explicit language or theme | a one-line error on stderr | `1` |
