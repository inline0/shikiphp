<?php

declare(strict_types=1);

namespace Shikiphp\Render;

/**
 * One theme's resolved style for a token, mirroring Shiki's `TokenStyles`:
 * the foreground `color`, a {@see \Shikiphp\Theme\FontStyle} bitmask, and an
 * optional `bgColor`. These are the per-variant entries carried by
 * {@see ThemedTokenWithVariants}.
 */
final class ThemedTokenStyle
{
    public function __construct(
        public readonly ?string $color,
        public readonly int $fontStyle,
        public readonly ?string $bgColor = null,
    ) {
    }
}
