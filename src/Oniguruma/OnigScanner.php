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
    /**
     * Process-level cache of compiled matchers keyed by Oniguruma source pattern.
     * Conversion + parsing is deterministic in the source, and a Matcher fully
     * resets its per-input state on every match(), so the same compiled state is
     * safely shared across every scanner that lists an identical pattern.
     *
     * @var array<string, array{matcher: Matcher|null, pcre: PcreMatcher|null, sticky: bool, atomicSlots: list<int>}>
     */
    private static array $compileCache = [];

    /** @var list<string> */
    private array $patterns;

    /** @var array<int, Matcher|null> Compiled matchers; null once a pattern is known-bad. */
    private array $compiled = [];

    /** @var array<int, PcreMatcher|null> PCRE fast-path matchers for provably-equivalent patterns; null = use VM. */
    private array $pcre = [];

    /** @var array<int, true> */
    private array $failed = [];

    /** @var array<int, bool> Whether pattern $i is `\G`-anchored (sticky to startPosition). */
    private array $sticky = [];

    /** @var array<int, list<int>> JS capture slots injected by atomic emulation, to strip from results. */
    private array $atomicSlots = [];

    private ?PatternConverter $converter = null;

    private ?PcreTranslator $translator = null;

    /** @param list<string> $patterns Oniguruma source patterns. */
    public function __construct(array $patterns)
    {
        $this->patterns = array_values($patterns);
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
                $pcre = $this->pcre[$i] ?? null;
                try {
                    $result = $pcre !== null
                        ? $pcre->match($string->content, $startPosition)
                        : $matcher->match($string->content, $startPosition);
                } catch (PcreMatchError) {
                    // PCRE errored at runtime: fall back to the VM Matcher so the
                    // result stays identical to the source of truth.
                    $result = $matcher->match($string->content, $startPosition);
                }
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

        $source = $this->patterns[$i];
        $entry = self::$compileCache[$source] ?? self::compile($source);

        $this->compiled[$i] = $entry['matcher'];
        if ($entry['matcher'] === null) {
            $this->failed[$i] = true;
            return null;
        }
        $this->pcre[$i] = $entry['pcre'];
        $this->sticky[$i] = $entry['sticky'];
        $this->atomicSlots[$i] = $entry['atomicSlots'];
        return $entry['matcher'];
    }

    /** @return array{matcher: Matcher|null, pcre: PcreMatcher|null, sticky: bool, atomicSlots: list<int>} */
    private function compile(string $source): array
    {
        $this->converter ??= new PatternConverter();
        $this->translator ??= new PcreTranslator();
        try {
            $converted = $this->converter->convert($source);
            $pattern = (new Parser($converted['pattern'], $converted['flags']))->parse();

            // Equivalence-gated PCRE fast-path: only patterns the translator
            // proves identical to the Matcher (verified by the bundled
            // equivalence harness over the grammar corpus) run via PCRE; all
            // others stay on the VM Matcher.
            $pcre = null;
            if (getenv('SHIKIPHP_NO_PCRE') === false) {
                $translated = $this->translator->translate($converted['pattern'], $converted['flags']);
                if ($translated !== null && @preg_match($translated['pcre'], '') !== false) {
                    $pcre = new PcreMatcher($translated['pcre']);
                }
            }

            $entry = [
                'matcher' => new Matcher($pattern, $converted['flags']),
                'pcre' => $pcre,
                'sticky' => str_contains($converted['flags'], 'y'),
                'atomicSlots' => $converted['atomicSlots'],
            ];
        } catch (\Throwable) {
            $entry = ['matcher' => null, 'pcre' => null, 'sticky' => false, 'atomicSlots' => []];
        }

        return self::$compileCache[$source] = $entry;
    }

    /**
     * @param array{index: int, end: int, captures: list<?array{0:int,1:int}>} $result Whole match + group spans (the Matcher also carries a third matched-text element, ignored here).
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
