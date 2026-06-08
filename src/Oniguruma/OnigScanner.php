<?php

declare(strict_types=1);

namespace Shikiphp\Oniguruma;

use Shikiphp\Regex\Matcher;
use Shikiphp\Regex\Parser;

/**
 * Pure-PHP port of vscode-oniguruma's OnigScanner. Holds a list of
 * Oniguruma patterns, converts and compiles each lazily, and reports the
 * leftmost match at or after a start position (ties → lowest pattern index).
 */
final class OnigScanner
{
    /** @var list<string> */
    private array $patterns;

    /** @var array<int, Matcher|null> Compiled matchers; null once a pattern is known-bad. */
    private array $compiled = [];

    /** @var array<int, true> */
    private array $failed = [];

    /** @var array<int, bool> Whether pattern $i is `\G`-anchored (sticky to startPosition). */
    private array $sticky = [];

    /** @var array<int, list<int>> JS capture slots injected by atomic emulation, to strip from results. */
    private array $atomicSlots = [];

    private PatternConverter $converter;

    /** @param list<string> $patterns Oniguruma source patterns. */
    public function __construct(array $patterns)
    {
        $this->patterns = array_values($patterns);
        $this->converter = new PatternConverter();
    }

    public function findNextMatch(OnigString $string, int $startPosition): ?OnigMatch
    {
        $best = null;
        $bestIndex = -1;

        foreach ($this->patterns as $i => $_) {
            $matcher = $this->matcherFor($i);
            if ($matcher === null) {
                continue;
            }

            try {
                $result = $matcher->match($string->content, $startPosition);
            } catch (\Throwable) {
                continue;
            }
            if ($result === null) {
                continue;
            }
            if (($this->sticky[$i] ?? false) && $result['index'] !== $startPosition) {
                continue;
            }

            if ($best === null || $result['index'] < $best['index']) {
                $best = $result;
                $bestIndex = $i;
                if ($best['index'] === $startPosition) {
                    break;
                }
            }
        }

        if ($best === null) {
            return null;
        }

        return new OnigMatch($bestIndex, $this->buildCaptureIndices($best, $this->atomicSlots[$bestIndex] ?? []));
    }

    private function matcherFor(int $i): ?Matcher
    {
        if (isset($this->failed[$i])) {
            return null;
        }
        if (array_key_exists($i, $this->compiled)) {
            return $this->compiled[$i];
        }

        try {
            $converted = $this->converter->convert($this->patterns[$i]);
            $pattern = (new Parser($converted['pattern'], $converted['flags']))->parse();
            $matcher = new Matcher($pattern, $converted['flags']);
        } catch (\Throwable) {
            $this->failed[$i] = true;
            $this->compiled[$i] = null;
            return null;
        }

        $this->sticky[$i] = str_contains($converted['flags'], 'y');
        $this->atomicSlots[$i] = $converted['atomicSlots'];
        $this->compiled[$i] = $matcher;
        return $matcher;
    }

    /**
     * @param array{index: int, end: int, captures: list<?array{0:int,1:int,2:string}>} $result
     * @param list<int> $atomicSlots JS capture slots injected by atomic emulation, dropped from the result.
     * @return list<OnigCaptureIndex>
     */
    private function buildCaptureIndices(array $result, array $atomicSlots): array
    {
        $skip = array_fill_keys($atomicSlots, true);
        $indices = [new OnigCaptureIndex($result['index'], $result['end'])];
        $captures = $result['captures'];
        for ($n = 1; $n < count($captures); $n++) {
            if (isset($skip[$n])) {
                continue;
            }
            $cap = $captures[$n];
            if ($cap === null) {
                $indices[] = new OnigCaptureIndex($result['index'], $result['index']);
                continue;
            }
            $indices[] = new OnigCaptureIndex($cap[0], $cap[1]);
        }
        return $indices;
    }
}
