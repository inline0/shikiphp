---
title: "Custom Languages & Themes"
description: "Register your own TextMate grammars and VS Code themes with loadGrammar() and loadTheme()."
path: "custom-languages-themes"
order: 80
section: "Languages & Themes"
meta_title: "Custom Languages & Themes"
meta_description: "Register your own TextMate grammars and VS Code themes with loadGrammar() and loadTheme()."
---

# Custom Languages & Themes

The bundled set covers the full Shiki catalog, but you can register your own
TextMate grammar or VS Code theme from decoded JSON. Both live on the
`Highlighter` instance.

## Custom themes

Decode a VS Code theme JSON file and pass it to `loadTheme`. The theme is keyed
by its `name`, and a custom theme overrides a bundled theme of the same name.

```php
use Shikiphp\Shikiphp;

$highlighter = Shikiphp::highlighter();

$theme = json_decode(file_get_contents('my-theme.json'), true);
$highlighter->loadTheme($theme);

echo $highlighter->codeToHtml($code, [
    'lang'  => 'php',
    'theme' => $theme['name'], // the theme's own name
]);
```

## Custom grammars

`loadGrammar` registers a decoded `.tmLanguage.json` so any entry point can use
it by language id:

```php
public function loadGrammar(
    array $rawTmLanguage,
    ?string $langId = null,
    array $aliases = [],
    array $embedded = [],
): void
```

- `$langId` — the id to reference it by. If omitted, the grammar's `name` is
  used, then its `scopeName`.
- `$aliases` — extra ids that resolve to the same grammar.
- `$embedded` — language ids this grammar embeds, so their grammars are resolved
  too.

```php
$highlighter = Shikiphp::highlighter();

$grammar = json_decode(file_get_contents('mylang.tmLanguage.json'), true);
$highlighter->loadGrammar($grammar, 'mylang', aliases: ['ml']);

echo $highlighter->codeToHtml($code, [
    'lang'  => 'mylang',
    'theme' => 'github-dark',
]);
```

Grammar `include` references (`#repo`, `$self`, `$base`, `other.scope`) and
embedded languages resolve against grammars already registered on the
highlighter, so register any dependencies first.

## Using a custom highlighter through the facade

If you want the static `Shikiphp` facade to use your customized highlighter,
hand it over with `Shikiphp::use`:

```php
use Shikiphp\Highlighter;
use Shikiphp\Shikiphp;

$highlighter = Highlighter::createBundled();
$highlighter->loadTheme($myTheme);
$highlighter->loadGrammar($myGrammar, 'mylang');

Shikiphp::use($highlighter);

// Now the facade delegates to your highlighter.
echo Shikiphp::codeToHtml($code, ['lang' => 'mylang', 'theme' => $myTheme['name']]);
```

Call `Shikiphp::reset()` to drop back to the default bundled highlighter.
