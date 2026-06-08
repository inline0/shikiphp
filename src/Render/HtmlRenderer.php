<?php

declare(strict_types=1);

namespace Shikiphp\Render;

use Shikiphp\Theme\FontStyle;

/**
 * Renders themed token lines into Shiki-compatible `<pre class="shiki">` HTML,
 * in single-theme or dual-theme (CSS-variable) mode.
 */
final class HtmlRenderer
{
    /**
     * @param list<list<ThemedToken>> $lines
     */
    public function render(array $lines, RenderOptions $options): string
    {
        $body = [];
        foreach ($lines as $tokens) {
            $body[] = $this->renderLine($tokens);
        }

        return $this->openPre($options) . '<code>'
            . implode("\n", $body)
            . '</code></pre>';
    }

    /**
     * @param list<ThemedToken> $tokens
     */
    private function renderLine(array $tokens): string
    {
        if ($tokens === []) {
            return '<span class="line"></span>';
        }

        $spans = '';
        foreach ($tokens as $token) {
            $spans .= $this->renderToken($token);
        }

        return '<span class="line">' . $spans . '</span>';
    }

    private function renderToken(ThemedToken $token): string
    {
        $style = $token->htmlStyle ?? $this->tokenStyle($token);
        $attr = $style === '' ? '' : ' style="' . self::escapeAttr($style) . '"';

        return '<span' . $attr . '>' . self::escapeText($token->content) . '</span>';
    }

    private function tokenStyle(ThemedToken $token): string
    {
        $parts = [];
        if ($token->color !== null) {
            $parts[] = 'color:' . $token->color;
        }
        if ($token->bgColor !== null) {
            $parts[] = 'background-color:' . $token->bgColor;
        }
        foreach (self::fontStyleParts($token->fontStyle) as $part) {
            $parts[] = $part;
        }

        return implode(';', $parts);
    }

    /**
     * @return list<string>
     */
    private static function fontStyleParts(int $fontStyle): array
    {
        if ($fontStyle <= FontStyle::NONE) {
            return [];
        }

        $parts = [];
        if (($fontStyle & FontStyle::ITALIC) !== 0) {
            $parts[] = 'font-style:italic';
        }
        if (($fontStyle & FontStyle::BOLD) !== 0) {
            $parts[] = 'font-weight:bold';
        }

        $decorations = [];
        if (($fontStyle & FontStyle::UNDERLINE) !== 0) {
            $decorations[] = 'underline';
        }
        if (($fontStyle & FontStyle::STRIKETHROUGH) !== 0) {
            $decorations[] = 'line-through';
        }
        if ($decorations !== []) {
            $parts[] = 'text-decoration:' . implode(' ', $decorations);
        }

        return $parts;
    }

    private function openPre(RenderOptions $options): string
    {
        return '<pre class="' . self::escapeAttr($this->preClass($options))
            . '" style="' . self::escapeAttr($this->preStyle($options))
            . '" tabindex="0">';
    }

    private function preClass(RenderOptions $options): string
    {
        if (!$options->isDualTheme()) {
            return 'shiki ' . ($options->themeName ?? '');
        }

        return rtrim('shiki shiki-themes ' . implode(' ', $options->themes));
    }

    private function preStyle(RenderOptions $options): string
    {
        if (!$options->isDualTheme()) {
            return 'background-color:' . ($options->bg ?? '')
                . ';color:' . ($options->fg ?? '');
        }

        $prefix = $options->cssVariablePrefix;
        $default = $options->defaultColor;

        $bg = [];
        $fg = [];
        foreach ($options->themes as $key => $_themeName) {
            $background = $options->bgByKey[$key] ?? '';
            $foreground = $options->fgByKey[$key] ?? '';
            if ($key === $default) {
                $bg[] = 'background-color:' . $background;
                $fg[] = 'color:' . $foreground;
                continue;
            }
            $bg[] = $prefix . $key . '-bg:' . $background;
            $fg[] = $prefix . $key . ':' . $foreground;
        }

        return implode(';', [...$bg, ...$fg]);
    }

    private static function escapeText(string $value): string
    {
        return str_replace(
            ['&', '<'],
            ['&#x26;', '&#x3C;'],
            $value,
        );
    }

    private static function escapeAttr(string $value): string
    {
        return str_replace(
            ['&', '<', '"'],
            ['&#x26;', '&#x3C;', '&#x22;'],
            $value,
        );
    }
}
