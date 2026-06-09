<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use Shikiphp\Oniguruma\OnigScanner;
use Shikiphp\Oniguruma\OnigString;

/**
 * Micro-benchmark isolating OnigScanner::findNextMatch — the regex hot loop the
 * PCRE fast-path targets. Replays a sample of real (PCRE-safe) grammar patterns
 * against representative input lines. Run with and without SHIKIPHP_NO_PCRE=1.
 */

$converted = json_decode((string) file_get_contents(__DIR__ . '/../bin/.oracle-tools/converted-patterns.json'), true);
if (!is_array($converted)) {
    fwrite(STDERR, "Run bin/.oracle-tools/dump-patterns.php first.\n");
    exit(2);
}

// Reconstruct the original Oniguruma patterns from the dump is unavailable; the
// scanner takes Oniguruma source. Instead, drive the scanner with the original
// grammar patterns sampled directly.
$onig = [];
foreach (glob(__DIR__ . '/../src/Registry/grammars/*.json') ?: [] as $f) {
    $raw = json_decode((string) file_get_contents($f), true);
    array_walk_recursive($raw, static function ($v, $k) use (&$onig): void {
        if (is_string($v) && in_array($k, ['match', 'begin', 'end', 'while'], true)) {
            $onig[$v] = true;
        }
    });
}
// Keep only patterns the fast-path actually handles, so the A/B isolates the
// VM-vs-PCRE cost on the patterns the optimization targets.
$translator = new \Shikiphp\Oniguruma\PcreTranslator();
$converter = new \Shikiphp\Oniguruma\PatternConverter();
$safe = [];
foreach (array_keys($onig) as $p) {
    try {
        $c = $converter->convert($p);
        if ($translator->translate($c['pattern'], $c['flags']) !== null) {
            $safe[] = $p;
        }
    } catch (\Throwable) {
    }
    if (count($safe) >= 400) {
        break;
    }
}
$patterns = $safe;

$lines = [];
foreach (glob(__DIR__ . '/../scenarios/*/input.*') ?: [] as $f) {
    foreach (explode("\n", (string) file_get_contents($f)) as $line) {
        if ($line !== '') {
            $lines[$line . "\n"] = true;
        }
    }
}
$lines = array_slice(array_keys($lines), 0, 120);
$onigLines = array_map(static fn (string $l): OnigString => new OnigString($l), $lines);

// One scanner per pattern (mirrors how rules compile a scanner). Warm compile.
$scanners = [];
foreach ($patterns as $p) {
    $scanners[] = new OnigScanner([$p]);
}
foreach ($scanners as $s) {
    $s->findNextMatch($onigLines[0], 0);
}

$iters = max(1, (int) ($argv[1] ?? 3));
$samples = [];
for ($r = 0; $r < $iters; $r++) {
    $start = hrtime(true);
    foreach ($scanners as $s) {
        foreach ($onigLines as $os) {
            $s->findNextMatch($os, 0);
        }
    }
    $samples[] = (hrtime(true) - $start) / 1e6;
}
sort($samples);

printf(
    "PCRE=%s  scanner median: %.1f ms  (%d patterns x %d lines, iters=%d)\n",
    getenv('SHIKIPHP_NO_PCRE') === false ? 'on ' : 'off',
    $samples[intdiv(count($samples), 2)],
    count($patterns),
    count($lines),
    $iters,
);
