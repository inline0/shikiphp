<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use Shikiphp\Highlighter;

/**
 * Warm-pass A/B benchmark for the PCRE fast-path. Run twice — once normally and
 * once with SHIKIPHP_NO_PCRE=1 — to compare warm tokenization throughput with
 * the fast-path on vs off. Usage: php bench/pcre-ab.php [iters]
 */

const THEME = 'github-dark';
const CORPUS = [
    'php' => 'sample.php',
    'typescript' => 'sample.ts',
    'tsx' => 'sample.tsx',
    'css' => 'sample.css',
    'html' => 'sample.html',
    'python' => 'sample.py',
    'rust' => 'sample.rs',
    'go' => 'sample.go',
    'json' => 'sample.json',
    'markdown' => 'sample.md',
];

$iters = max(1, (int) ($argv[1] ?? 8));

$corpus = [];
foreach (CORPUS as $lang => $file) {
    $corpus[$lang] = (string) file_get_contents(__DIR__ . '/corpus/' . $file);
}

$h = Highlighter::createBundled();
foreach ($corpus as $lang => $code) {
    $h->codeToHtml($code, ['lang' => $lang, 'theme' => THEME]);
}

$samples = [];
for ($i = 0; $i < $iters; $i++) {
    $start = hrtime(true);
    foreach ($corpus as $lang => $code) {
        $h->codeToHtml($code, ['lang' => $lang, 'theme' => THEME]);
    }
    $samples[] = (hrtime(true) - $start) / 1e6;
}
sort($samples);
$median = $samples[intdiv(count($samples), 2)];

printf(
    "PCRE=%s  warm median total: %.2f ms  (iters=%d)\n",
    getenv('SHIKIPHP_NO_PCRE') === false ? 'on' : 'off',
    $median,
    $iters,
);
