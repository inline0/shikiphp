---
title: "Transformers"
description: "The transformer pipeline and hooks, how to write your own, and the built-in notation, meta, and whitespace transformers."
path: "transformers"
order: 40
section: "Usage"
meta_title: "Transformers"
meta_description: "The transformer pipeline and hooks, how to write your own, and the built-in notation, meta, and whitespace transformers."
---

# Transformers

Transformers let you reshape the highlighted output at every stage — from the
raw source string, through the token grid, to the final HAST tree and HTML.
shikiphp ports Shiki's transformer model hook-for-hook, so transformers behave
the same as their JavaScript counterparts.

Pass them through the [`transformers`](/docs/options#transformers) option:

```php
use Shikiphp\Shikiphp;
use Shikiphp\Transformer\Notation\NotationHighlight;
use Shikiphp\Transformer\Notation\NotationDiff;

$code = <<<'JS'
console.log('this line is highlighted') // [!code highlight]
console.log('old line') // [!code --]
console.log('new line') // [!code ++]
JS;

echo Shikiphp::codeToHtml($code, [
    'lang'         => 'js',
    'theme'        => 'vitesse-dark',
    'transformers' => [
        new NotationHighlight(),
        new NotationDiff(),
    ],
]);
```

## The pipeline

Each transformer is an object implementing `Shikiphp\Transformer\Transformer`.
The pipeline runs the hooks in order over the build of a single block:

| Hook | Signature | When |
| --- | --- | --- |
| `preprocess` | `(string $code, array &$options, $context): ?string` | before tokenization; may rewrite the source and mutate options |
| `tokens` | `(array $tokens, $context): ?array` | after tokenization, on the 2D `ThemedToken` grid |
| `root` | `(Element $el, $context): ?Element` | on the HAST root |
| `pre` | `(Element $el, $context): ?Element` | on the `<pre>` element |
| `code` | `(Element $el, $context): ?Element` | on the `<code>` element |
| `line` | `(Element $el, int $line, $context): ?Element` | per line element (1-based) |
| `span` | `(Element $el, int $line, int $col, Element $lineEl, ThemedToken $token, $context): ?Element` | per token span |
| `postprocess` | `(string $html, array $options, $context): ?string` | on the final serialized HTML string |

Every hook is optional. Returning a value replaces the input; returning `null`
keeps it unchanged.

`enforce()` controls ordering across transformers: `'pre'` runs first, `'post'`
runs last, and `null` (the default) runs in between in registration order.

## Writing a transformer

Extend `Shikiphp\Transformer\AbstractTransformer` so you only override the hooks
you need; the rest stay no-ops. The transformer context exposes
`addClassToHast()` for adding classes to elements.

```php
use Shikiphp\Hast\Element;
use Shikiphp\Shikiphp;
use Shikiphp\Transformer\AbstractTransformer;
use Shikiphp\Transformer\TransformerContext;

final class AddLanguageClass extends AbstractTransformer
{
    public function pre(Element $element, TransformerContext $context): ?Element
    {
        if ($context->lang !== null) {
            $context->addClassToHast($element, 'language-' . $context->lang);
        }

        return $element;
    }
}

echo Shikiphp::codeToHtml('const answer = 42', [
    'lang'         => 'js',
    'theme'        => 'nord',
    'transformers' => [new AddLanguageClass()],
]);
// <pre class="shiki nord language-js" …>
```

## Built-in transformers

### Notation transformers

These read special `// [!code …]` comments in the source (the comment syntax of
the language is detected automatically) and translate them into classes,
removing the comment itself. They are direct ports of the `@shikijs/transformers`
notation set.

#### `NotationHighlight`

`Shikiphp\Transformer\Notation\NotationHighlight` — `// [!code highlight]` (or
`// [!code hl]`) adds the `highlighted` class to its line.

```js
console.log('kept plain')
console.log('this line is highlighted') // [!code highlight]
```

#### `NotationDiff`

`Shikiphp\Transformer\Notation\NotationDiff` — `// [!code ++]` marks a line as
added (`diff add`), `// [!code --]` as removed (`diff remove`).

```js
console.log('old line') // [!code --]
console.log('new line') // [!code ++]
```

#### `NotationFocus`

`Shikiphp\Transformer\Notation\NotationFocus` — `// [!code focus]` adds the
`focused` class.

```js
console.log('focus on me') // [!code focus]
```

#### `NotationErrorLevel`

`Shikiphp\Transformer\Notation\NotationErrorLevel` — `// [!code error]`,
`// [!code warning]`, and `// [!code info]` add the `highlighted` class plus
`error` / `warning` / `info`.

```js
throwSomething() // [!code error]
maybeRisky()     // [!code warning]
```

#### `NotationWordHighlight`

`Shikiphp\Transformer\Notation\NotationWordHighlight` — `// [!code word:foo]`
(optionally `// [!code word:foo:2]` to limit to the next N lines) wraps each
occurrence of `foo` in a span carrying `highlighted-word`.

```js
// [!code word:config]
const config = loadConfig()
```

The optional `classActiveCode` constructor argument adds a class to `<code>`
when word notation is present, alongside `classActivePre` for `<pre>`. Standalone
notation applies to the next line; comments that also contain explanation text
keep that text and apply to their own line.

### Meta transformers

These read the code-fence **meta string** supplied through the
[`meta`](/docs/options#meta) option (`meta['__raw']`), the way Shiki reads the
text after a fenced code block's language.

#### `MetaHighlight`

`Shikiphp\Transformer\MetaHighlight` — highlights lines named in a meta range
like `{1,3-5}`, adding the `highlighted` class.

```php
use Shikiphp\Shikiphp;
use Shikiphp\Transformer\MetaHighlight;

$code = "const a = 1\nconst b = 2\nconst c = 3\nconst d = 4\nconst e = 5";

echo Shikiphp::codeToHtml($code, [
    'lang'         => 'js',
    'theme'        => 'nord',
    'meta'         => ['__raw' => '{1,3-5}'],
    'transformers' => [new MetaHighlight()],
]);
```

#### `MetaWordHighlight`

`Shikiphp\Transformer\MetaWordHighlight` — highlights words named in the meta
string with `/word/` syntax, adding `highlighted-word`.

### Whitespace and structure

#### `RenderLineNumber`

`Shikiphp\Transformer\RenderLineNumber` prepends a number span to each line.
Use `new RenderLineNumber(classLineNumber: 'line-number', start: 1)` to set the
class and starting number. Empty lines receive numbers too. Inline rendering
has no line wrappers, so it does not add number spans.


#### `RenderWhitespace`

`Shikiphp\Transformer\RenderWhitespace` — wraps space and tab characters in
spans (classes `space` and `tab` by default) so they can be rendered visibly.
The constructor takes `(classSpace, classTab, position)` where `position` is
`'all'` (default), `'boundary'`, `'trailing'`, or `'leading'`.

#### `CompactLineOptions`

`Shikiphp\Transformer\CompactLineOptions` — applies classes to specific lines
from a list of `{line, classes}` entries.

```php
use Shikiphp\Transformer\CompactLineOptions;

$transformer = new CompactLineOptions([
    ['line' => 1, 'classes' => 'highlighted'],
    ['line' => 3, 'classes' => ['highlighted', 'error']],
]);
```

Line numbers are 1-based. Pass the object in the `transformers` option like any
other transformer.

#### `RemoveNotationEscape`

`Shikiphp\Transformer\RemoveNotationEscape` — unescapes the `[\!code …]` form
used to write a literal notation comment without triggering a notation
transformer.

#### `RemoveLineBreak`

`Shikiphp\Transformer\RemoveLineBreak` — removes the line-break text nodes
between lines (Shiki's `transformerRemoveLineBreak`).

### `StyleToClass`

`Shikiphp\Transformer\StyleToClass` — moves inline styles into generated classes
and collects a stylesheet you fetch with `getCSS()`. Class names hash the style
with Shiki's `cyrb53`, so output is byte-identical to `@shikijs/transformers`
4.5.0, including its scope:

- The `<pre>` style always becomes a class.
- Token spans are converted only when they carry a prebuilt style, which is the
  case with a `themes` map. With a single `theme`, the token spans keep their
  inline `style="color:…"`, as they do in Shiki.

```php
use Shikiphp\Shikiphp;
use Shikiphp\Transformer\StyleToClass;

$code = 'const answer = 42';
$styleToClass = new StyleToClass();

$html = Shikiphp::codeToHtml($code, [
    'lang'         => 'js',
    'themes'       => ['light' => 'github-light', 'dark' => 'github-dark'],
    'transformers' => [$styleToClass],
]);

$css = $styleToClass->getCSS(); // collected stylesheet for the classes above
```

The constructor takes `(classPrefix, classSuffix, classReplacer)`; the default
prefix is `__shiki_`.
