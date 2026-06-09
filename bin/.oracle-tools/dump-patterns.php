<?php

declare(strict_types=1);

require_once __DIR__ . '/../../vendor/autoload.php';

use Shikiphp\Oniguruma\PatternConverter;

$dir = __DIR__ . '/../../src/Registry/grammars';
$patterns = [];

function collect(mixed $node, array &$out): void
{
    if (is_array($node)) {
        foreach (['match', 'begin', 'end', 'while'] as $k) {
            if (isset($node[$k]) && is_string($node[$k])) {
                $out[$node[$k]] = true;
            }
        }
        foreach ($node as $v) {
            collect($v, $out);
        }
    }
}

foreach (glob($dir . '/*.json') ?: [] as $f) {
    $raw = json_decode((string) file_get_contents($f), true);
    collect($raw, $patterns);
}

$onig = array_keys($patterns);
$converter = new PatternConverter();
$converted = 0;
$failed = 0;
$convertedPatterns = [];
foreach ($onig as $p) {
    try {
        $c = $converter->convert($p);
        $converted++;
        $convertedPatterns[] = ['onig' => $p, 'pattern' => $c['pattern'], 'flags' => $c['flags']];
    } catch (\Throwable $e) {
        $failed++;
    }
}

fwrite(STDERR, sprintf("onig patterns: %d  converted: %d  failed: %d\n", count($onig), $converted, $failed));
file_put_contents(__DIR__ . '/converted-patterns.json', json_encode($convertedPatterns, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
