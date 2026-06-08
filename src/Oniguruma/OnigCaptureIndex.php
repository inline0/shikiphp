<?php

declare(strict_types=1);

namespace Shikiphp\Oniguruma;

/**
 * Span of a capture group in code-unit offsets. A non-participating group
 * is reported as an empty span (start === end).
 */
final class OnigCaptureIndex
{
    public function __construct(
        public readonly int $start,
        public readonly int $end,
    ) {
    }

    public function length(): int
    {
        return $this->end - $this->start;
    }
}
