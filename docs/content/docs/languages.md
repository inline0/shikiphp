---
title: "Languages"
description: "The full tm-grammars catalog (200+ languages) ships with shikiphp, and how to list what's available."
path: "languages"
order: 6
section: "Documentation"
meta_title: "Languages"
meta_description: "The full tm-grammars catalog (200+ languages) ships with shikiphp, and how to list what's available."
---

# Languages

shikiphp bundles the **full Shiki language set** — every grammar from
[`tm-grammars`](https://github.com/shikijs/textmate-grammars-themes) (200+
languages). These are the same TextMate grammars VS Code and Shiki tokenize
with, so language coverage, aliases, and scope names match.

You reference a language by its Shiki id or one of its aliases — for example
`ts` or `typescript`, `js` or `javascript`, `php`, `rust`, `python`, `html`,
`yaml`, `json`, and so on.

The special id `'ansi'` is not a grammar; it highlights terminal output. See
[ANSI](/docs/ansi).

## Listing the bundled languages

From PHP, ask the highlighter:

```php
use Shikiphp\Shikiphp;

$highlighter = Shikiphp::highlighter();

$highlighter->bundledLanguages(); // list<string> — ids shipped in the box
$highlighter->loadedLanguages();  // list<string> — bundled plus any you registered
```

Or from the command line:

```bash
vendor/bin/shikiphp langs
```

## Adding your own

Need a language that is not bundled, or a private grammar? Register it with
`loadGrammar`. See [Custom languages and themes](/docs/custom-languages-themes).
