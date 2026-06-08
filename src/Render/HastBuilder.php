<?php

declare(strict_types=1);

namespace Shikiphp\Render;

use Shikiphp\Hast\Element;
use Shikiphp\Hast\Text;
use Shikiphp\Theme\FontStyle;
use Shikiphp\Transformer\TransformerContext;
use Shikiphp\Transformer\TransformerPipeline;

/**
 * Builds the `root > pre.shiki > code > (span.line > span)*` HAST tree Shiki
 * produces, with line spans joined by `\n` text nodes, firing the structural
 * transformer hooks (span, line, code, pre, root) in Shiki's order during the
 * build. With no transformers the serialized output is byte-identical to
 * Shiki's default `codeToHtml`.
 */
final class HastBuilder
{
    /**
     * Build the `root` element (Shiki's `codeToHast` always returns a `root`).
     * With transformers the hooks fire in Shiki's order and a transformer-replaced
     * node takes effect; the returned element is the (possibly replaced) `root`.
     *
     * @param list<list<ThemedToken>> $lines
     */
    public function build(
        array $lines,
        RenderOptions $options,
        ?TransformerPipeline $pipeline = null,
        ?TransformerContext $context = null,
    ): Element {
        $transformers = $pipeline === null ? [] : $pipeline->transformers;

        if ($options->structure === 'inline') {
            return $this->buildInline($lines, $options, $transformers, $context);
        }

        $preProps = [
            'className' => $this->preClass($options),
            'style' => $this->preStyle($options),
        ];
        if ($options->tabindex !== false) {
            $preProps['tabindex'] = (string) $options->tabindex;
        }

        $code = new Element('code', [], []);
        $pre = new Element('pre', $preProps, [$code]);
        $root = new Element('root', [], [$pre]);

        if ($context !== null) {
            $context->tokens = $lines;
            $context->root = $root;
            $context->pre = $pre;
            $context->code = $code;
            $context->lines = [];
        }

        $children = [];
        $lineEls = [];
        foreach ($lines as $index => $tokens) {
            if ($index !== 0) {
                $children[] = new Text("\n");
            }

            $lineEl = $this->line($tokens, $index + 1, $transformers, $context);
            $lineEls[] = $lineEl;
            $children[] = $lineEl;
            if ($context !== null) {
                $context->lines = $lineEls;
            }
        }

        $code->children = $children;

        foreach ($transformers as $transformer) {
            $code = $transformer->code($code, $this->context($context)) ?? $code;
        }
        $pre->children = [$code];
        if ($context !== null) {
            $context->code = $code;
        }

        foreach ($transformers as $transformer) {
            $pre = $transformer->pre($pre, $this->context($context)) ?? $pre;
        }
        $root->children = [$pre];
        if ($context !== null) {
            $context->pre = $pre;
        }

        foreach ($transformers as $transformer) {
            $root = $transformer->root($root, $this->context($context)) ?? $root;
        }
        if ($context !== null) {
            $context->root = $root;
        }

        return $root;
    }

    /**
     * Shiki's `structure: 'inline'`: no `pre`/`code`/`line` wrappers — the root's
     * children are the token spans with `<br>` between lines. `span` and `code`
     * (over a synthetic `code`) and `root` hooks fire; `line`/`pre` do not.
     *
     * @param list<list<ThemedToken>> $lines
     * @param list<\Shikiphp\Transformer\Transformer> $transformers
     */
    private function buildInline(array $lines, RenderOptions $options, array $transformers, ?TransformerContext $context): Element
    {
        $root = new Element('root', [], []);
        $lineEls = [];

        if ($context !== null) {
            $context->tokens = $lines;
            $context->root = $root;
            $context->lines = [];
        }

        $children = [];
        foreach ($lines as $index => $tokens) {
            if ($index !== 0) {
                $children[] = new Element('br', [], []);
            }

            $lineEl = new Element('span', ['className' => ['line']], []);
            $col = 0;
            foreach ($tokens as $token) {
                $span = $this->token($token);
                foreach ($transformers as $transformer) {
                    $span = $transformer->span($span, $index + 1, $col, $lineEl, $token, $this->context($context)) ?? $span;
                }
                $children[] = $span;
                $col += self::utf16Length($token->content);
            }
            $lineEls[] = $lineEl;
            if ($context !== null) {
                $context->lines = $lineEls;
            }
        }

        $root->children = $children;

        if ($transformers !== []) {
            $root->children = $this->inlineCodeHook($children, $transformers, $context);
        }

        foreach ($transformers as $transformer) {
            $root = $transformer->root($root, $this->context($context)) ?? $root;
        }
        if ($context !== null) {
            $context->root = $root;
        }

        return $root;
    }

    /**
     * Rebuild synthetic `span.line` wrappers from the flat inline children, run
     * the `code` hook over a synthetic `code`, then flatten back to inline form.
     *
     * @param list<\Shikiphp\Hast\Node> $children
     * @param list<\Shikiphp\Transformer\Transformer> $transformers
     * @return list<\Shikiphp\Hast\Node>
     */
    private function inlineCodeHook(array $children, array $transformers, ?TransformerContext $context): array
    {
        $syntheticLines = [];
        $current = new Element('span', ['className' => ['line']], []);
        foreach ($children as $child) {
            if ($child instanceof Element && $child->tag === 'br') {
                $syntheticLines[] = $current;
                $current = new Element('span', ['className' => ['line']], []);
                continue;
            }
            $current->children[] = $child;
        }
        $syntheticLines[] = $current;

        $code = new Element('code', [], $syntheticLines);
        foreach ($transformers as $transformer) {
            $code = $transformer->code($code, $this->context($context)) ?? $code;
        }

        $out = [];
        foreach ($code->children as $i => $line) {
            if ($i > 0) {
                $out[] = new Element('br', [], []);
            }
            if ($line instanceof Element) {
                foreach ($line->children as $node) {
                    $out[] = $node;
                }
            }
        }

        return $out;
    }

    private function context(?TransformerContext $context): TransformerContext
    {
        return $context ?? throw new \LogicException('transformer context required when transformers are present');
    }

    /**
     * @param list<ThemedToken> $tokens
     * @param list<\Shikiphp\Transformer\Transformer> $transformers
     */
    private function line(array $tokens, int $lineNumber, array $transformers, ?TransformerContext $context): Element
    {
        $lineEl = new Element('span', ['className' => ['line']], []);

        $spans = [];
        $col = 0;
        foreach ($tokens as $token) {
            $span = $this->token($token);
            foreach ($transformers as $transformer) {
                $span = $transformer->span($span, $lineNumber, $col, $lineEl, $token, $this->context($context)) ?? $span;
            }
            $spans[] = $span;
            $col += self::utf16Length($token->content);
        }

        $lineEl->children = $spans;

        foreach ($transformers as $transformer) {
            $lineEl = $transformer->line($lineEl, $lineNumber, $this->context($context)) ?? $lineEl;
        }

        return $lineEl;
    }

    private function token(ThemedToken $token): Element
    {
        $style = $token->htmlStyle !== null
            ? self::parseStyle($token->htmlStyle)
            : $this->tokenStyle($token);

        $properties = $style === [] ? [] : ['style' => $style];

        return new Element('span', $properties, [new Text($token->content)]);
    }

    /**
     * @return array<string,string>
     */
    private function tokenStyle(ThemedToken $token): array
    {
        $style = [];
        if ($token->color !== null) {
            $style['color'] = $token->color;
        }
        if ($token->bgColor !== null) {
            $style['background-color'] = $token->bgColor;
        }
        foreach (self::fontStyleParts($token->fontStyle) as $key => $value) {
            $style[$key] = $value;
        }

        return $style;
    }

    /**
     * @return array<string,string>
     */
    private static function fontStyleParts(int $fontStyle): array
    {
        if ($fontStyle <= FontStyle::NONE) {
            return [];
        }

        $parts = [];
        if (($fontStyle & FontStyle::ITALIC) !== 0) {
            $parts['font-style'] = 'italic';
        }
        if (($fontStyle & FontStyle::BOLD) !== 0) {
            $parts['font-weight'] = 'bold';
        }

        $decorations = [];
        if (($fontStyle & FontStyle::UNDERLINE) !== 0) {
            $decorations[] = 'underline';
        }
        if (($fontStyle & FontStyle::STRIKETHROUGH) !== 0) {
            $decorations[] = 'line-through';
        }
        if ($decorations !== []) {
            $parts['text-decoration'] = implode(' ', $decorations);
        }

        return $parts;
    }

    /**
     * @return list<string>
     */
    private function preClass(RenderOptions $options): array
    {
        if (!$options->isDualTheme()) {
            $class = ['shiki'];
            $name = $options->themeName ?? '';
            if ($name !== '') {
                $class[] = $name;
            }
            return $class;
        }

        return ['shiki', 'shiki-themes', ...array_values($options->themes)];
    }

    /**
     * @return array<string,string>
     */
    private function preStyle(RenderOptions $options): array
    {
        if (!$options->isDualTheme()) {
            return [
                'background-color' => $options->bg ?? '',
                'color' => $options->fg ?? '',
            ];
        }

        $prefix = $options->cssVariablePrefix;
        $default = $options->defaultColor;

        $bg = [];
        $fg = [];
        foreach ($options->themes as $key => $_themeName) {
            $background = $options->bgByKey[$key] ?? '';
            $foreground = $options->fgByKey[$key] ?? '';
            if ($key === $default) {
                $bg['background-color'] = $background;
                $fg['color'] = $foreground;
                continue;
            }
            $bg[$prefix . $key . '-bg'] = $background;
            $fg[$prefix . $key] = $foreground;
        }

        return $default === false ? [...$fg, ...$bg] : [...$bg, ...$fg];
    }

    /**
     * @return array<string,string>
     */
    private static function parseStyle(string $style): array
    {
        if ($style === '') {
            return [];
        }

        $out = [];
        foreach (explode(';', $style) as $declaration) {
            $colon = strpos($declaration, ':');
            if ($colon === false) {
                continue;
            }
            $key = substr($declaration, 0, $colon);
            $out[$key] = substr($declaration, $colon + 1);
        }

        return $out;
    }

    private static function utf16Length(string $utf8): int
    {
        $utf16 = mb_convert_encoding($utf8, 'UTF-16LE', 'UTF-8');
        return intdiv(strlen($utf16), 2);
    }
}
