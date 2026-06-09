#!/usr/bin/env php
<?php

declare(strict_types=1);

use Shikiphp\Highlighter;

require_once __DIR__ . '/../vendor/autoload.php';

/**
 * Benchmark harness: highlights a representative corpus (one medium file per
 * language) with a single theme, measuring cold vs warm highlight time and peak
 * memory. Cold = a fresh Highlighter doing its first call for a language (pays
 * grammar load + compile). Warm = repeated calls on an already-warmed instance.
 *
 * Reports the median over N runs to stay deterministic-ish. Measurement only;
 * no optimization. Usage: php bench/bench.php [--runs=N] [--warm=M] [--json]
 */

const THEME = 'github-dark';

/** @var array<string, string> language id => corpus filename */
const CORPUS = [
    'php' => 'sample.php',
    'typescript' => 'sample.ts',
    'tsx' => 'sample.tsx',
    'json' => 'sample.json',
    'css' => 'sample.css',
    'html' => 'sample.html',
    'markdown' => 'sample.md',
    'python' => 'sample.py',
    'rust' => 'sample.rs',
    'go' => 'sample.go',
    'yaml' => 'sample.yaml',
    'bash' => 'sample.sh',
];

$opts = getopt('', ['runs:', 'warm:', 'json']);
$runs = max(1, (int) ($opts['runs'] ?? 5));
$warmIters = max(1, (int) ($opts['warm'] ?? 10));
$asJson = isset($opts['json']);

$corpus = [];
foreach (CORPUS as $lang => $file) {
    $path = __DIR__ . '/corpus/' . $file;
    if (!is_file($path)) {
        fwrite(STDERR, "Missing corpus file: {$path}\n");
        exit(2);
    }
    $code = (string) file_get_contents($path);
    $corpus[$lang] = [
        'code' => $code,
        'bytes' => strlen($code),
        'lines' => substr_count($code, "\n") + 1,
    ];
}

/**
 * Median of a list of floats.
 *
 * @param list<float> $values
 */
function median(array $values): float
{
    sort($values);
    $n = count($values);
    if ($n === 0) {
        return 0.0;
    }
    $mid = intdiv($n, 2);
    return $n % 2 === 1 ? $values[$mid] : ($values[$mid - 1] + $values[$mid]) / 2.0;
}

/**
 * One cold pass: a brand-new Highlighter; the first codeToHtml per language pays
 * grammar load + compile. Returns per-lang ms (the single first call).
 *
 * @return array<string, float>
 */
function coldPass(array $corpus): array
{
    $h = Highlighter::createBundled();
    $perLang = [];
    foreach ($corpus as $lang => $entry) {
        $start = hrtime(true);
        $h->codeToHtml($entry['code'], ['lang' => $lang, 'theme' => THEME]);
        $perLang[$lang] = (hrtime(true) - $start) / 1e6;
    }
    return $perLang;
}

/**
 * One warm pass on a pre-warmed Highlighter: each language is highlighted
 * $iters times; the reported per-lang ms is the median single-call time.
 *
 * @return array<string, float>
 */
function warmPass(Highlighter $h, array $corpus, int $iters): array
{
    $perLang = [];
    foreach ($corpus as $lang => $entry) {
        $samples = [];
        for ($i = 0; $i < $iters; $i++) {
            $start = hrtime(true);
            $h->codeToHtml($entry['code'], ['lang' => $lang, 'theme' => THEME]);
            $samples[] = (hrtime(true) - $start) / 1e6;
        }
        $perLang[$lang] = median($samples);
    }
    return $perLang;
}

$coldRuns = [];
for ($r = 0; $r < $runs; $r++) {
    $coldRuns[] = coldPass($corpus);
}

$warm = Highlighter::createBundled();
foreach ($corpus as $lang => $entry) {
    $warm->codeToHtml($entry['code'], ['lang' => $lang, 'theme' => THEME]);
}
$warmRuns = [];
for ($r = 0; $r < $runs; $r++) {
    $warmRuns[] = warmPass($warm, $corpus, $warmIters);
}

$coldByLang = [];
$warmByLang = [];
foreach (array_keys(CORPUS) as $lang) {
    $coldByLang[$lang] = median(array_map(static fn (array $run): float => $run[$lang], $coldRuns));
    $warmByLang[$lang] = median(array_map(static fn (array $run): float => $run[$lang], $warmRuns));
}

$coldTotal = array_sum($coldByLang);
$warmTotal = array_sum($warmByLang);
$peakMb = memory_get_peak_usage(true) / (1024 * 1024);

if ($asJson) {
    echo json_encode([
        'theme' => THEME,
        'runs' => $runs,
        'warmIters' => $warmIters,
        'php' => PHP_VERSION,
        'perLang' => array_map(
            static fn (string $lang): array => [
                'lines' => $corpus[$lang]['lines'],
                'bytes' => $corpus[$lang]['bytes'],
                'coldMs' => round($coldByLang[$lang], 3),
                'warmMs' => round($warmByLang[$lang], 3),
            ],
            array_combine(array_keys(CORPUS), array_keys(CORPUS)),
        ),
        'coldTotalMs' => round($coldTotal, 3),
        'warmTotalMs' => round($warmTotal, 3),
        'peakMemMb' => round($peakMb, 1),
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
    exit(0);
}

printf("shikiphp benchmark  (theme=%s, runs=%d, warmIters=%d, php=%s)\n", THEME, $runs, $warmIters, PHP_VERSION);
printf("%-12s %6s %7s %12s %12s\n", 'lang', 'lines', 'bytes', 'cold (ms)', 'warm (ms)');
printf("%s\n", str_repeat('-', 53));
foreach (array_keys(CORPUS) as $lang) {
    printf(
        "%-12s %6d %7d %12.3f %12.3f\n",
        $lang,
        $corpus[$lang]['lines'],
        $corpus[$lang]['bytes'],
        $coldByLang[$lang],
        $warmByLang[$lang],
    );
}
printf("%s\n", str_repeat('-', 53));
printf("%-12s %6s %7s %12.3f %12.3f\n", 'TOTAL', '', '', $coldTotal, $warmTotal);
printf("\npeak memory: %.1f MB\n", $peakMb);
