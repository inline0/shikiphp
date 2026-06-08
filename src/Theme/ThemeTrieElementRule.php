<?php

declare(strict_types=1);

namespace Shikiphp\Theme;

/**
 * A resolved styling at a given trie depth, optionally gated by a parent-scope
 * selector that must match the scope path's ancestors.
 *
 * @internal
 */
final class ThemeTrieElementRule
{
    /** @param list<string>|null $parentScopes outermost first, innermost last */
    public function __construct(
        public int $scopeDepth,
        public ?array $parentScopes,
        public int $fontStyle,
        public ?string $foreground,
        public ?string $background,
    ) {
    }

    public function clone(): self
    {
        return new self(
            $this->scopeDepth,
            $this->parentScopes,
            $this->fontStyle,
            $this->foreground,
            $this->background,
        );
    }

    public function acceptOverwrite(int $scopeDepth, int $fontStyle, ?string $foreground, ?string $background): void
    {
        if ($this->scopeDepth > $scopeDepth) {
            return;
        }

        $this->scopeDepth = $scopeDepth;

        if ($fontStyle !== FontStyle::NOT_SET) {
            $this->fontStyle = $fontStyle;
        }
        if ($foreground !== null) {
            $this->foreground = $foreground;
        }
        if ($background !== null) {
            $this->background = $background;
        }
    }
}
