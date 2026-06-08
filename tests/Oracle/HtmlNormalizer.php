<?php

declare(strict_types=1);

namespace Shikiphp\Tests\Oracle;

/**
 * Normalizes Shiki HTML for comparison: collapses insignificant whitespace
 * between tags and breaks the markup into one logical line per `<span>`/tag so
 * a line-based diff is legible.
 */
final class HtmlNormalizer
{
    public static function normalize(string $html): string
    {
        $html = trim($html);
        $html = preg_replace('/></', ">\n<", $html) ?? $html;

        return $html;
    }

    /** @return list<string> */
    public static function lines(string $html): array
    {
        return explode("\n", self::normalize($html));
    }
}
