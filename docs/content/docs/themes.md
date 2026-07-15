---
title: "Themes"
description: "The full tm-themes catalog (65 themes) ships with shikiphp, and how to list what's available."
path: "themes"
order: 7
section: "Documentation"
meta_title: "Themes"
meta_description: "The full tm-themes catalog (65 themes) ships with shikiphp, and how to list what's available."
---

# Themes

shikiphp bundles the **full Shiki theme set** — all 65 VS Code themes from
[`tm-themes`](https://github.com/shikijs/textmate-grammars-themes). Popular ids
include `github-dark`, `github-light`, `nord`, `dracula`, `vitesse-dark`,
`vitesse-light`, `one-dark-pro`, `monokai`, `solarized-dark`, and the
`catppuccin-*` and `material-theme-*` families.

Use a single theme with the [`theme`](/docs/options#theme) option, or two (or
more) with [`themes`](/docs/options#themes) for light/dark CSS-variable output.
See [Getting Started](/docs/getting-started#dual-themes-light-and-dark).

## Listing the bundled themes

From PHP:

```php
use Shikiphp\Shikiphp;

$highlighter = Shikiphp::highlighter();

$highlighter->bundledThemes(); // list<string> — ids shipped in the box
$highlighter->loadedThemes();  // list<string> — bundled plus any you registered
```

Or from the command line:

```bash
vendor/bin/shikiphp themes
```

## Adding your own

Register a custom VS Code theme JSON with `loadTheme`. See
[Custom languages and themes](/docs/custom-languages-themes).
