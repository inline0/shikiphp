<?php

declare(strict_types=1);

namespace Shikiphp\Tests\Oracle;

/**
 * Diffs normalized oracle vs actual HTML, producing a unified-ish line diff.
 */
final class ScenarioComparator
{
    /** @return array{pass: bool, diff: list<string>} */
    public static function compare(string $oracle, string $actual): array
    {
        $expected = HtmlNormalizer::lines($oracle);
        $got = HtmlNormalizer::lines($actual);

        if ($expected === $got) {
            return ['pass' => true, 'diff' => []];
        }

        return ['pass' => false, 'diff' => self::diff($expected, $got)];
    }

    /**
     * @param list<string> $expected
     * @param list<string> $got
     * @return list<string>
     */
    private static function diff(array $expected, array $got): array
    {
        $max = max(count($expected), count($got));
        $diff = [];
        for ($i = 0; $i < $max; $i++) {
            $e = $expected[$i] ?? null;
            $g = $got[$i] ?? null;
            if ($e === $g) {
                continue;
            }
            if ($e !== null) {
                $diff[] = '- ' . $e;
            }
            if ($g !== null) {
                $diff[] = '+ ' . $g;
            }
        }

        return $diff;
    }
}
