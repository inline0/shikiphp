<?php

declare(strict_types=1);

namespace Shikiphp\Ansi;

/**
 * A run of text carrying the ANSI state active when it was emitted: optional
 * foreground/background {@see AnsiColor}s and a set of active decorations
 * (bold, dim, italic, underline, reverse, strikethrough, …).
 */
final class AnsiToken
{
    /**
     * @param array<string, true> $decorations decoration name → true
     */
    public function __construct(
        public readonly string $value,
        public readonly ?AnsiColor $foreground,
        public readonly ?AnsiColor $background,
        public readonly array $decorations,
    ) {
    }
}
