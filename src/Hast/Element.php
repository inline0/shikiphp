<?php

declare(strict_types=1);

namespace Shikiphp\Hast;

/**
 * A HAST element node. `properties` mirrors hast: `className` is a
 * `list<string>`, `style` an ordered `array<string,string>` map, and any other
 * key is an arbitrary attribute (string|int|bool|null).
 */
final class Element implements Node
{
    /**
     * @param array<string,mixed> $properties
     * @param list<Node> $children
     */
    public function __construct(
        public string $tag,
        public array $properties = [],
        public array $children = [],
    ) {
    }
}
