<?php

declare(strict_types=1);

namespace Shikiphp\Render;

/**
 * A single rendered token: its text plus the resolved colours and a
 * {@see \Shikiphp\Theme\FontStyle} bitmask. `htmlStyle`, when set, is a
 * pre-built inline style string that overrides the colour/font-style derivation
 * (used by dual-theme mode to carry per-theme CSS variables). `offset` is the
 * token's absolute UTF-16 start offset into the (newline-joined) source, used
 * to split tokens at decoration boundaries.
 */
final class ThemedToken
{
    public function __construct(
        public readonly string $content,
        public readonly ?string $color = null,
        public readonly int $fontStyle = 0,
        public readonly ?string $bgColor = null,
        public readonly ?string $htmlStyle = null,
        public readonly int $offset = 0,
    ) {
    }

    public function withContent(string $content, int $offset): self
    {
        return new self($content, $this->color, $this->fontStyle, $this->bgColor, $this->htmlStyle, $offset);
    }
}
