---
title: "Getting Started"
description: "Install shikiphp with Composer and render your first themed code block, including dual light/dark themes."
path: "getting-started"
order: 20
section: "Introduction"
meta_title: "Getting Started"
meta_description: "Install shikiphp with Composer and render your first themed code block, including dual light/dark themes."
---

# Getting Started

## Install

```bash
composer require shikiphp/shikiphp
```

shikiphp needs PHP 8.2 or later with `ext-json` and `ext-mbstring` — both of
which are enabled in nearly every PHP build. Nothing else: no Node, no native
Oniguruma binding, no extra extensions.

## Your first block

The static `Shikiphp` facade is the quickest way in. Pass your code, a language
id, and a theme id:

```php
use Shikiphp\Shikiphp;

$html = Shikiphp::codeToHtml(<<<'PHP'
<?php
function greet(string $name): string {
    return "Hello, {$name}!";
}
PHP, [
    'lang'  => 'php',
    'theme' => 'github-dark',
]);

echo $html;
```

`codeToHtml` returns a complete, self-styled `<pre class="shiki …">` block. The
foreground and background colors are written inline on the `<pre>`, so it renders
correctly without any extra stylesheet.

## Dual themes (light and dark)

Instead of a single `theme`, pass a `themes` map. shikiphp emits CSS variables
(like Shiki) so a single rendered block adapts to light and dark mode:

```php
echo Shikiphp::codeToHtml($code, [
    'lang'   => 'ts',
    'themes' => ['light' => 'github-light', 'dark' => 'github-dark'],
]);
```

By default the `light` theme's colors are the inline defaults and `dark` is
exposed through `--shiki-dark` variables. Activate the dark theme with a small
amount of CSS — the same snippet Shiki documents:

```css
html.dark .shiki,
html.dark .shiki span {
  color: var(--shiki-dark) !important;
  background-color: var(--shiki-dark-bg) !important;
  /* and font styles */
  font-style: var(--shiki-dark-font-style) !important;
  font-weight: var(--shiki-dark-font-weight) !important;
  text-decoration: var(--shiki-dark-text-decoration) !important;
}
```

You can choose which theme is the inline default with the
[`defaultColor`](/docs/options#defaultcolor) option, or set `defaultColor: false`
to emit only variables.

## Language ids and aliases

Languages are referenced by their Shiki id or alias — `php`, `ts`, `js`,
`rust`, `python`, `html`, and so on. See [Languages](/docs/languages) for how to
list everything that ships, and [Themes](/docs/themes) for the theme ids.

## Reusing the highlighter

The facade lazily creates and caches a bundled `Highlighter`, so repeated calls
to `Shikiphp::codeToHtml` reuse the same grammar and theme caches. If you need
the richer API (HAST output, custom grammars, token grids), grab the instance
directly:

```php
use Shikiphp\Shikiphp;

$highlighter = Shikiphp::highlighter();
$hast = $highlighter->codeToHast($code, ['lang' => 'rust', 'theme' => 'nord']);
```

See the [API](/docs/api) for the full surface.
