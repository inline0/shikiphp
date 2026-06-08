<?php

declare(strict_types=1);

namespace Shikiphp\Grammar;

/**
 * A token spanning [startIndex, endIndex) code units of a line, carrying the
 * full scope stack in effect (outermost first).
 */
final class Token
{
    /**
     * @param list<string> $scopes
     */
    public function __construct(
        public readonly int $startIndex,
        public readonly int $endIndex,
        public readonly array $scopes,
    ) {
    }
}
