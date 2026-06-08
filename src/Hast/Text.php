<?php

declare(strict_types=1);

namespace Shikiphp\Hast;

/**
 * A HAST text node.
 */
final class Text implements Node
{
    public function __construct(
        public readonly string $value,
    ) {
    }
}
