---
title: "CLI"
description: "The bin/shikiphp command — highlight a file to HTML, or list bundled languages and themes."
path: "cli"
order: 10
section: "Documentation"
meta_title: "CLI"
meta_description: "The bin/shikiphp command — highlight a file to HTML, or list bundled languages and themes."
---

# CLI

shikiphp ships a small command-line tool at `bin/shikiphp` (installed as
`vendor/bin/shikiphp` in your project). It has three commands.

## `highlight`

Highlight a file to HTML, printed to stdout.

```bash
vendor/bin/shikiphp highlight <file> [--lang=<lang>] [--theme=<theme>]
```

- `--lang` — the language id. If omitted, it is inferred from the file
  extension (falling back to `txt`).
- `--theme` — the theme id. Defaults to `github-dark`.

```bash
vendor/bin/shikiphp highlight src/App.php --lang=php --theme=nord
```

## `langs`

List every bundled language id, one per line.

```bash
vendor/bin/shikiphp langs
```

## `themes`

List every bundled theme id, one per line.

```bash
vendor/bin/shikiphp themes
```

## Help

Run with no command, or `-h` / `--help` / `help`, to print usage.

```bash
vendor/bin/shikiphp --help
```
