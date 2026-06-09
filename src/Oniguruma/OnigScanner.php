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
     * @var array<string, array{matcher: Matcher|null, pcre: PcreMatcher|null, prefilter: PcreMatcher|null, sticky: bool, atomicSlots: list<int>}>
     */
    private static array $compileCache = [];

    /** @var list<string> */
    private array $patterns;

    /** @var array<int, Matcher|null> Compiled matchers; null once a pattern is known-bad. */
    private array $compiled = [];

    /** @var array<int, PcreMatcher|null> PCRE fast-path matchers for provably-equivalent patterns; null = use VM. */
    private array $pcre = [];

    /**
     * Position-mode PCRE matchers (see PcreTranslator): extent-equivalent but not
     * capture-faithful, used to locate the match position fast; the VM then
     * confirms anchored at that position and supplies the true ES captures.
     *
     * @var array<int, PcreMatcher|null>
     */
    private array $prefilter = [];

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

            $result = $this->runMatch($i, $matcher, $string->content, $startPosition, $best['index'] ?? null);
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

    /**
     * Run pattern $i from $startPosition: proven-equivalent PCRE directly, else
     * position-mode PCRE to find the match position with an anchored VM confirm
     * there (full VM scan if the confirm disagrees), else the VM. $cap is the
     * best match index so far — a probe at or past it can't win, so the confirm
     * is skipped.
     *
     * @return array{index:int,end:int,captures:list<?array{0:int,1:int,2:string}>}|null
     */
    private function runMatch(int $i, Matcher $matcher, string $content, int $startPosition, ?int $cap): ?array
    {
        try {
            try {
                $pcre = $this->pcre[$i] ?? null;
                if ($pcre !== null) {
                    return $pcre->match($content, $startPosition);
                }

                $prefilter = $this->prefilter[$i] ?? null;
                if ($prefilter !== null && !($this->sticky[$i] ?? false)) {
                    $probe = $prefilter->match($content, $startPosition);
                    if ($probe === null) {
                        return null;
                    }
                    if ($cap !== null && $probe['index'] >= $cap) {
                        return null;
                    }
                    $confirmed = $matcher->match($content, $probe['index'], true);
                    if ($confirmed !== null) {
                        return $confirmed;
                    }
                    return $matcher->match($content, $startPosition);
                }

                return $matcher->match($content, $startPosition);
            } catch (PcreMatchError) {
                // PCRE errored at runtime: fall back to the VM Matcher so the
                // result stays identical to the source of truth.
                return $matcher->match($content, $startPosition);
            }
        } catch (\Throwable) {
            return null;
        }
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
        $this->prefilter[$i] = $entry['prefilter'];
        $this->sticky[$i] = $entry['sticky'];
        $this->atomicSlots[$i] = $entry['atomicSlots'];
        return $entry['matcher'];
    }

    /** @return array{matcher: Matcher|null, pcre: PcreMatcher|null, prefilter: PcreMatcher|null, sticky: bool, atomicSlots: list<int>} */
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
            // others stay on the VM Matcher — with a position-mode prefilter
            // (extent-equivalent only) when available; see runMatch().
            $pcre = null;
            $prefilter = null;
            if (getenv('SHIKIPHP_NO_PCRE') === false) {
                $translated = $this->translator->translate($converted['pattern'], $converted['flags']);
                if ($translated !== null && @preg_match($translated['pcre'], '') !== false) {
                    $pcre = new PcreMatcher($translated['pcre']);
                } else {
                    $position = $this->translator->translate($converted['pattern'], $converted['flags'], true);
                    if ($position !== null && @preg_match($position['pcre'], '') !== false) {
                        $prefilter = new PcreMatcher($position['pcre']);
                    }
                }
            }

            $entry = [
                'matcher' => new Matcher($pattern, $converted['flags']),
                'pcre' => $pcre,
                'prefilter' => $prefilter,
                'sticky' => str_contains($converted['flags'], 'y'),
                'atomicSlots' => $converted['atomicSlots'],
            ];
        } catch (\Throwable) {
            $entry = ['matcher' => null, 'pcre' => null, 'prefilter' => null, 'sticky' => false, 'atomicSlots' => []];
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
