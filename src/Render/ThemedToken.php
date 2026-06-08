<?php

declare(strict_types=1);

namespace Shikiphp\Render;

/**
 * A single rendered token: its text plus the resolved colours and a
 * {@see \Shikiphp\Theme\FontStyle} bitmask. `htmlStyle`, when set, is a
 * pre-built inline style string that overrides the colour/font-style derivation
 * (used by dual-theme mode to carry per-theme CSS variables).
 */
final class ThemedToken
{
    public function __construct(
        public readonly string $content,
        public readonly ?string $color = null,
        public readonly int $fontStyle = 0,
        public readonly ?string $bgColor = null,
        public readonly ?string $htmlStyle = null,
    ) {
    }
}
