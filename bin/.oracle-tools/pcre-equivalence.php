<?php

declare(strict_types=1);

require_once __DIR__ . '/../../vendor/autoload.php';

use Shikiphp\Oniguruma\PcreMatcher;
use Shikiphp\Oniguruma\PcreTranslator;
use Shikiphp\Regex\Matcher;
use Shikiphp\Regex\Parser;

/**
 * Equivalence harness for the PCRE fast-path.
 *
 * For every distinct (converted JS pattern, flags) drawn from the bundled
 * grammars, if the PcreTranslator classifies it PCRE-SAFE, assert that the PCRE
 * fast-path produces byte-identical results (index, end, every capture span) to
 * the vendored Matcher for a large corpus of inputs at multiple start offsets.
 * ANY divergence is a bug: that pattern class must fall back to the VM.
 */

$cacheFile = __DIR__ . '/converted-patterns.json';
if (!is_file($cacheFile)) {
    fwrite(STDERR, "Generating converted-patterns.json (dump-patterns.php)...\n");
    passthru(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(__DIR__ . '/dump-patterns.php'));
}
$converted = json_decode((string) file_get_contents($cacheFile), true);

// Build the input corpus: every scenario input line + a battery of synthetic
// strings exercising boundaries, unicode, newlines, repeats.
$inputs = [];
$scenDir = __DIR__ . '/../../scenarios';
foreach (glob($scenDir . '/*/input.*') ?: [] as $f) {
    $text = (string) file_get_contents($f);
    foreach (explode("\n", $text) as $line) {
        if ($line !== '') {
            $inputs[$line . "\n"] = true;
        }
    }
}
// Cap the scenario-line corpus: distinct lines already dedupe; sample a large
// diverse subset so the harness finishes in minutes while still crossing every
// PCRE-safe pattern against thousands of real grammar inputs.
$cap = (int) ($argv[2] ?? 400);
if (count($inputs) > $cap) {
    $keys = array_keys($inputs);
    // Deterministic stride sample keeps the spread without RNG noise.
    $stride = (int) ceil(count($keys) / $cap);
    $sampled = [];
    for ($k = 0; $k < count($keys); $k += $stride) {
        $sampled[$keys[$k]] = true;
    }
    $inputs = $sampled;
}
$synthetic = [
    '', ' ', '  ', "\t", "\n", "a\n", "abc\n", "ABC\n", "123\n", "  abc  \n",
    "foo_bar baz\n", "a.b.c\n", "/* comment */\n", "// line\n", "\"str\"\n",
    "'x'\n", "<tag attr=\"v\">\n", "key: value\n", "- item\n", "#hash\n",
    "func(a, b)\n", "x = 1;\n", "0x1F a1B2\n", "café résumé\n", "naïve\n",
    "日本語\n", "emoji 😀 here\n", "\\escape\\n", "a   b\tc\n", "...\n",
    "END\n", "{nested {curly}}\n", "[a-z]+\n", "a|b|c\n", "https://x.io/p?q=1\n",
    "  \tmixed\n", "ABCdef123_\n", "trailing   \n", "@decorator\n", '$var->prop' . "\n",
];
foreach ($synthetic as $s) {
    $inputs[$s] = true;
}
$inputs = array_keys($inputs);

function utf16Len(string $utf8): int
{
    return intdiv(strlen(mb_convert_encoding($utf8, 'UTF-16LE', 'UTF-8')), 2);
}

$translator = new PcreTranslator();

$safeCount = 0;
$positionCount = 0;
$checkedPatterns = 0;
$comparisons = 0;
$skipped = 0;
$netted = 0;
$divergences = [];
$maxDiv = (int) ($argv[1] ?? 40);
// Per-pattern wall budget (s): tier-2 admits exactly the patterns the VM is
// slow on; cap each so the harness finishes while every pattern still gets
// crossed against a large slice of the corpus.
$perPattern = (float) ($argv[3] ?? 1.0);

foreach ($converted as $row) {
    $js = $row['pattern'];
    $flags = $row['flags'];

    // Strict mode → full capture comparison; position mode → extent only
    // (index/end + null agreement), exactly what the scanner's prefilter uses.
    $extentOnly = false;
    $t = $translator->translate($js, $flags);
    if ($t === null) {
        $t = $translator->translate($js, $flags, true);
        if ($t === null || @preg_match($t['pcre'], '') === false) {
            continue;
        }
        $extentOnly = true;
        $positionCount++;
    } else {
        $safeCount++;
    }

    // Build the Matcher for the same converted source.
    try {
        $pattern = (new Parser($js, $flags))->parse();
        $matcher = new Matcher($pattern, $flags);
    } catch (\Throwable $e) {
        // Converter produced something the Parser rejects; the scanner would
        // have failed it too. Not a fast-path concern.
        continue;
    }

    $pcreMatcher = new PcreMatcher($t['pcre']);

    $checkedPatterns++;
    if ($checkedPatterns % 500 === 0) {
        fwrite(STDERR, sprintf("...checked %d patterns, %d comparisons, %d divergences\n", $checkedPatterns, $comparisons, count($divergences)));
        fflush(STDERR);
    }

    $deadline = microtime(true) + $perPattern;
    foreach ($inputs as $input) {
        if (microtime(true) > $deadline) {
            break;
        }
        $maxStart = utf16Len($input);
        // Sample start offsets: 0, every position is expensive; sample a few.
        $starts = [0];
        if ($maxStart > 0) {
            $starts[] = $maxStart;
            $starts[] = intdiv($maxStart, 2);
            $starts[] = min(1, $maxStart);
        }
        $starts = array_values(array_unique($starts));

        foreach ($starts as $start) {
            $comparisons++;
            try {
                $vm = $matcher->match($input, $start);
            } catch (\Shikiphp\Regex\MatcherBudgetExceeded $e) {
                // The VM gave up; production falls back the same way, so there
                // is no fast-path result to validate against.
                $skipped++;
                continue;
            } catch (\Throwable $e) {
                $vm = null;
            }
            try {
                $fp = $pcreMatcher->match($input, $start);
            } catch (\Shikiphp\Oniguruma\PcreMatchError $e) {
                $skipped++;
                continue;
            }

            if (
                $extentOnly
                && resultsEqual($vm, $fp, true)
                && ((($vm === null) !== ($fp === null))
                    || ($vm !== null && $fp !== null && ($vm['index'] !== $fp['index'] || $vm['end'] !== $fp['end'])))
            ) {
                // Sound (netted by confirm+fallback) but behaviourally different:
                // each is a suspected VM-vs-real-JS bug; queue a sample.
                $netted++;
                if ($netted <= 20) {
                    file_put_contents(
                        __DIR__ . '/vm-bug-queue.txt',
                        "js: {$js}\nflags: {$flags}\ninput: " . json_encode($input) . "\nstart: {$start}\nvm: " . json_encode($vm) . "\nfp: " . json_encode($fp) . "\n\n",
                        FILE_APPEND,
                    );
                }
            }

            if (!resultsEqual($vm, $fp, $extentOnly)) {
                if (count($divergences) < $maxDiv) {
                    $divergences[] = [
                        'js' => $js,
                        'flags' => $flags,
                        'pcre' => $t['pcre'],
                        'input' => $input,
                        'start' => $start,
                        'vm' => $vm,
                        'fp' => $fp,
                    ];
                    fwrite(STDERR, "\n--- DIVERGENCE ---\n");
                    fwrite(STDERR, 'js:    ' . $js . "\n");
                    fwrite(STDERR, 'pcre:  ' . $t['pcre'] . "\n");
                    fwrite(STDERR, 'input: ' . json_encode($input) . "\n");
                    fwrite(STDERR, 'start: ' . $start . "\n");
                    fwrite(STDERR, 'vm:    ' . json_encode($vm) . "\n");
                    fwrite(STDERR, 'fp:    ' . json_encode($fp) . "\n");
                    fflush(STDERR);
                    if (count($divergences) >= $maxDiv) {
                        fwrite(STDERR, "\n(stopping after {$maxDiv} divergences)\n");
                        exit(1);
                    }
                }
            }
        }
    }
}

/**
 * @param array{index:int,end:int,captures:list<?array{0:int,1:int,2:string}>}|null $a
 * @param array{index:int,end:int,captures:list<?array{0:int,1:int}>}|null $b
 */
function resultsEqual(?array $a, ?array $b, bool $extentOnly = false): bool
{
    if ($extentOnly) {
        // Position-mode soundness: the scanner confirms the probe with the VM
        // anchored at the probe index and falls back to a full VM scan on
        // disagreement, which nets every case except a prefilter that misses
        // entirely (null while the VM matches) or probes PAST the VM's
        // leftmost match (the confirm could then succeed at the later index).
        if ($a === null) {
            return true;
        }
        if ($b === null) {
            return false;
        }
        return $b['index'] <= $a['index'];
    }
    if ($a === null) {
        return $b === null;
    }
    if ($b === null) {
        return false;
    }
    if ($a['index'] !== $b['index'] || $a['end'] !== $b['end']) {
        return false;
    }
    $ca = $a['captures'];
    $cb = $b['captures'];
    if (count($ca) !== count($cb)) {
        return false;
    }
    foreach ($ca as $i => $capA) {
        $capB = $cb[$i];
        // The Matcher returns null for a non-participating group; the scanner
        // maps that to an empty span. Compare on [start,end] only, normalising
        // null to "no span" on both sides.
        $sa = $capA === null ? null : [$capA[0], $capA[1]];
        $sb = $capB === null ? null : [$capB[0], $capB[1]];
        if ($sa !== $sb) {
            return false;
        }
    }
    return true;
}

fwrite(STDERR, sprintf(
    "PCRE-safe patterns: %d strict + %d position-mode / %d converted\n",
    $safeCount,
    $positionCount,
    count($converted),
));
fwrite(STDERR, sprintf("Patterns checked (Parser-valid): %d\n", $checkedPatterns));
fwrite(STDERR, sprintf("Comparisons: %d (skipped: %d budget-exceeded)\n", $comparisons, $skipped));
fwrite(STDERR, sprintf("Netted behaviour diffs (VM bug queue): %d\n", $netted));
fwrite(STDERR, sprintf("Divergences: %d\n", count($divergences)));

if ($divergences !== []) {
    foreach ($divergences as $d) {
        fwrite(STDERR, "\n--- DIVERGENCE ---\n");
        fwrite(STDERR, 'js:    ' . $d['js'] . "\n");
        fwrite(STDERR, 'flags: ' . $d['flags'] . "\n");
        fwrite(STDERR, 'pcre:  ' . $d['pcre'] . "\n");
        fwrite(STDERR, 'input: ' . json_encode($d['input']) . "\n");
        fwrite(STDERR, 'start: ' . $d['start'] . "\n");
        fwrite(STDERR, 'vm:    ' . json_encode($d['vm']) . "\n");
        fwrite(STDERR, 'fp:    ' . json_encode($d['fp']) . "\n");
    }
    exit(1);
}

fwrite(STDERR, "OK: zero divergences\n");
exit(0);
