<?php

declare(strict_types=1);

namespace Shikiphp\Ansi;

/**
 * A parsed ANSI colour: a named palette entry, a 24-bit RGB triple, or an
 * index into the 256-colour table. Mirrors ansi-sequence-parser's colour union.
 */
final class AnsiColor
{
    public const TYPE_NAMED = 'named';
    public const TYPE_RGB = 'rgb';
    public const TYPE_TABLE = 'table';

    /**
     * @param list<int> $rgb
     */
    private function __construct(
        public readonly string $type,
        public readonly ?string $name = null,
        public readonly array $rgb = [],
        public readonly int $index = 0,
    ) {
    }

    public static function named(string $name): self
    {
        return new self(self::TYPE_NAMED, name: $name);
    }

    /**
     * @param list<int> $rgb
     */
    public static function rgb(array $rgb): self
    {
        return new self(self::TYPE_RGB, rgb: $rgb);
    }

    public static function table(int $index): self
    {
        return new self(self::TYPE_TABLE, index: $index);
    }
}
