<?php

declare(strict_types=1);

namespace Shikiphp\Render;

/**
 * Rendering options for {@see HtmlRenderer}. In single-theme mode `themeName`,
 * `fg` and `bg` describe the active theme. In dual-theme mode `themes` maps a
 * colour key (e.g. `light`, `dark`) to its theme name and `defaultColor` names
 * the key rendered as a plain colour (the rest become CSS variables); the
 * matching `fg`/`bg` maps supply the per-key `<pre>` colours.
 */
final class RenderOptions
{
    /**
     * @param array<string,string> $themes per-key theme name, dual-theme mode
     * @param array<string,string> $fgByKey per-key foreground, dual-theme mode
     * @param array<string,string> $bgByKey per-key background, dual-theme mode
     */
    public function __construct(
        public readonly ?string $themeName = null,
        public readonly ?string $fg = null,
        public readonly ?string $bg = null,
        public readonly ?string $langId = null,
        public readonly array $themes = [],
        public readonly array $fgByKey = [],
        public readonly array $bgByKey = [],
        public readonly string|false $defaultColor = 'light',
        public readonly string $cssVariablePrefix = '--shiki-',
    ) {
    }

    public function isDualTheme(): bool
    {
        return $this->themes !== [];
    }
}
