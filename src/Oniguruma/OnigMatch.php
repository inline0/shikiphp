<?php

declare(strict_types=1);

namespace Shikiphp\Oniguruma;

/**
 * A scanner match: the index of the pattern that matched, plus the
 * whole-match span (offset 0) followed by one span per capture group.
 */
final class OnigMatch
{
    /**
     * @param list<OnigCaptureIndex> $captureIndices
     */
    public function __construct(
        public readonly int $index,
        public readonly array $captureIndices,
    ) {
    }
}
