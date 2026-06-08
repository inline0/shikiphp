<?php

declare(strict_types=1);

namespace Shikiphp\Theme;

/**
 * Resolved styling for a token's scope stack: a {@see FontStyle} bitmask plus
 * optional CSS colour strings.
 */
final class StyleAttributes
{
    public function __construct(
        public readonly int $fontStyle,
        public readonly ?string $foreground,
        public readonly ?string $background = null,
    ) {
    }
}
