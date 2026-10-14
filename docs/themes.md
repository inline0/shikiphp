---
title: "Themes"
description: "The full tm-themes catalog ships with shikiphp: all 65 themes with their ids and light or dark type, and how to list them."
path: "themes"
order: 70
section: "Languages & Themes"
meta_title: "Themes"
meta_description: "The full tm-themes catalog ships with shikiphp: all 65 themes with their ids and light or dark type, and how to list them."
---

# Themes

shikiphp bundles the **full Shiki theme set**: all 65 VS Code themes from
[`tm-themes`](https://github.com/shikijs/textmate-grammars-themes) 1.12.2, the
set Shiki 3.23.0 ships. 44 are dark and 21 are light. Popular ids include
`github-dark`, `github-light`, `nord`, `dracula`, `vitesse-dark`,
`vitesse-light`, `one-dark-pro`, `monokai`, `solarized-dark`, and the
`catppuccin-*` and `material-theme-*` families.

Use a single theme with the [`theme`](/docs/options#theme) option, or two (or
more) with [`themes`](/docs/options#themes) for light/dark CSS-variable output.
See [Getting Started](/docs/getting-started#dual-themes-light-and-dark).

```php
use Shikiphp\Shikiphp;

echo Shikiphp::codeToHtml('const answer = 42', [
    'lang'   => 'js',
    'themes' => ['light' => 'github-light', 'dark' => 'github-dark'],
]);
```

A theme id the bundle does not know throws `Shikiphp\Exceptions\Highlight` with
the message `Unknown theme "…"`.

## What a theme controls

A theme sets the block's foreground and background (written on the `<pre>`)
and the color and font style (italic, bold, underline, strikethrough) of every
token, resolved by scope-selector specificity. For `lang: 'ansi'` its
`terminal.ansi*` colors become the ANSI palette (see [ANSI](/docs/ansi)). To
adjust individual colors without editing a theme, use
[`colorReplacements`](/docs/options#colorreplacements).

## Listing the bundled themes

From PHP:

```php
use Shikiphp\Shikiphp;

$highlighter = Shikiphp::highlighter();

$highlighter->bundledThemes(); // list<string> — ids shipped in the box
$highlighter->loadedThemes();  // list<string> — bundled plus any you registered
```

Or from the command line, one id per line in alphabetical order:

```bash
vendor/bin/shikiphp themes
```

## Catalog

The type is the `light` or `dark` type the bundle records for each theme, useful
when picking a pair for the `themes` option.

| Theme | Id | Type |
| --- | --- | --- |
| Andromeeda | `andromeeda` | dark |
| Aurora X | `aurora-x` | dark |
| Ayu Dark | `ayu-dark` | dark |
| Ayu Light | `ayu-light` | light |
| Ayu Mirage | `ayu-mirage` | dark |
| Catppuccin Frappé | `catppuccin-frappe` | dark |
| Catppuccin Latte | `catppuccin-latte` | light |
| Catppuccin Macchiato | `catppuccin-macchiato` | dark |
| Catppuccin Mocha | `catppuccin-mocha` | dark |
| Dark Plus | `dark-plus` | dark |
| Dracula Theme | `dracula` | dark |
| Dracula Theme Soft | `dracula-soft` | dark |
| Everforest Dark | `everforest-dark` | dark |
| Everforest Light | `everforest-light` | light |
| GitHub Dark | `github-dark` | dark |
| GitHub Dark Default | `github-dark-default` | dark |
| GitHub Dark Dimmed | `github-dark-dimmed` | dark |
| GitHub Dark High Contrast | `github-dark-high-contrast` | dark |
| GitHub Light | `github-light` | light |
| GitHub Light Default | `github-light-default` | light |
| GitHub Light High Contrast | `github-light-high-contrast` | light |
| Gruvbox Dark Hard | `gruvbox-dark-hard` | dark |
| Gruvbox Dark Medium | `gruvbox-dark-medium` | dark |
| Gruvbox Dark Soft | `gruvbox-dark-soft` | dark |
| Gruvbox Light Hard | `gruvbox-light-hard` | light |
| Gruvbox Light Medium | `gruvbox-light-medium` | light |
| Gruvbox Light Soft | `gruvbox-light-soft` | light |
| Horizon | `horizon` | dark |
| Horizon Bright | `horizon-bright` | light |
| Houston | `houston` | dark |
| Kanagawa Dragon | `kanagawa-dragon` | dark |
| Kanagawa Lotus | `kanagawa-lotus` | light |
| Kanagawa Wave | `kanagawa-wave` | dark |
| LaserWave | `laserwave` | dark |
| Light Plus | `light-plus` | light |
| Material Theme | `material-theme` | dark |
| Material Theme Darker | `material-theme-darker` | dark |
| Material Theme Lighter | `material-theme-lighter` | light |
| Material Theme Ocean | `material-theme-ocean` | dark |
| Material Theme Palenight | `material-theme-palenight` | dark |
| Min Dark | `min-dark` | dark |
| Min Light | `min-light` | light |
| Monokai | `monokai` | dark |
| Night Owl | `night-owl` | dark |
| Night Owl Light | `night-owl-light` | light |
| Nord | `nord` | dark |
| One Dark Pro | `one-dark-pro` | dark |
| One Light | `one-light` | light |
| Plastic | `plastic` | dark |
| Poimandres | `poimandres` | dark |
| Red | `red` | dark |
| Rosé Pine | `rose-pine` | dark |
| Rosé Pine Dawn | `rose-pine-dawn` | light |
| Rosé Pine Moon | `rose-pine-moon` | dark |
| Slack Dark | `slack-dark` | dark |
| Slack Ochin | `slack-ochin` | light |
| Snazzy Light | `snazzy-light` | light |
| Solarized Dark | `solarized-dark` | dark |
| Solarized Light | `solarized-light` | light |
| Synthwave '84 | `synthwave-84` | dark |
| Tokyo Night | `tokyo-night` | dark |
| Vesper | `vesper` | dark |
| Vitesse Black | `vitesse-black` | dark |
| Vitesse Dark | `vitesse-dark` | dark |
| Vitesse Light | `vitesse-light` | light |

## Adding your own

Register a custom VS Code theme JSON with `loadTheme`. See
[Custom languages and themes](/docs/custom-languages-themes).
