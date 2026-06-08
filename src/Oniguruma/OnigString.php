<?php

declare(strict_types=1);

namespace Shikiphp\Oniguruma;

/**
 * UTF-8 content handed to an {@see OnigScanner}. All scanner offsets are
 * UTF-16 code-unit offsets into this content.
 */
final class OnigString
{
    public function __construct(
        public readonly string $content,
    ) {
    }
}
