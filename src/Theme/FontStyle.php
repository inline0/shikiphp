<?php

declare(strict_types=1);

namespace Shikiphp\Theme;

/**
 * Font-style bit flags (vscode-textmate's `FontStyle`). NOT_SET means inherit;
 * the rest combine as a bitmask.
 */
final class FontStyle
{
    public const NOT_SET = -1;
    public const NONE = 0;
    public const ITALIC = 1;
    public const BOLD = 2;
    public const UNDERLINE = 4;
    public const STRIKETHROUGH = 8;
}
