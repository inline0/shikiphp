# Project Title

A **medium-sized** Markdown document for _benchmarking_ the highlighter.
It exercises headings, lists, code fences, tables, links, and blockquotes.

## Table of Contents

1. [Installation](#installation)
2. [Usage](#usage)
3. [Configuration](#configuration)
4. [Contributing](#contributing)

> **Note:** This file is a fixture. It is not rendered anywhere real.

## Installation

Install via your package manager of choice:

```bash
npm install example-package
# or
pnpm add example-package
```

## Usage

Import and call the main entry point:

```ts
import { highlight } from "example-package";

const html = highlight("const x = 1;", { lang: "ts", theme: "github-dark" });
console.log(html);
```

You can also use it inline: `highlight(code, opts)` returns a string.

## Configuration

| Option   | Type      | Default        | Description                  |
| -------- | --------- | -------------- | ---------------------------- |
| `lang`   | `string`  | `"text"`       | Language identifier          |
| `theme`  | `string`  | `"github-dark"`| Theme name                   |
| `cache`  | `boolean` | `true`         | Cache compiled grammars      |

### Notes

- Supports [CommonMark](https://commonmark.org) plus GFM extensions.
- Task lists work too:
  - [x] Parse headings
  - [x] Parse fenced code
  - [ ] Parse footnotes
- Nested emphasis like ***bold italic*** is handled.

## Contributing

See `CONTRIBUTING.md`. Run the test suite with:

```sh
make test ARGS="--verbose"
```

---

Made with care. Questions? Email <hi@example.com> or open an issue.
