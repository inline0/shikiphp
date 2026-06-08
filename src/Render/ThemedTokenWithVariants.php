<?php

declare(strict_types=1);

namespace Shikiphp\Render;

/**
 * A token carrying its style under each theme, mirroring Shiki's
 * `ThemedTokenWithVariants` ({@see Highlighter::codeToTokensWithThemes}): the
 * `content`, its absolute UTF-16 `offset` into the source, and `variants`, a
 * map of theme key → {@see ThemedTokenStyle}.
 */
final class ThemedTokenWithVariants
{
    /**
     * @param array<string, ThemedTokenStyle> $variants
     */
    public function __construct(
        public readonly string $content,
        public readonly int $offset,
        public readonly array $variants,
    ) {
    }
}
