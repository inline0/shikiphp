<?php

declare(strict_types=1);

require __DIR__ . '/../../vendor/autoload.php';

use Shikiphp\Highlighter;
use Shikiphp\Transformer\Notation\NotationDiff;
use Shikiphp\Transformer\Notation\NotationErrorLevel;
use Shikiphp\Transformer\Notation\NotationFocus;
use Shikiphp\Transformer\Notation\NotationHighlight;
use Shikiphp\Transformer\Notation\NotationWordHighlight;

[$script, $kind, $lang, $theme, $file] = array_pad($argv, 5, null);
if ($kind === null || $lang === null || $theme === null || $file === null) {
    fwrite(STDERR, "usage: php actual-notation.php <kind> <lang> <theme> <file>\n");
    exit(2);
}

$code = file_get_contents($file);

$transformer = match ($kind) {
    'highlight' => new NotationHighlight(),
    'diff' => new NotationDiff(),
    'focus' => new NotationFocus(),
    'error' => new NotationErrorLevel(),
    'word' => new NotationWordHighlight(),
    default => exit("unknown kind\n"),
};

$h = Highlighter::createBundled();
try {
    echo $h->codeToHtml($code, ['lang' => $lang, 'theme' => $theme, 'transformers' => [$transformer]]);
} catch (\Throwable $e) {
    echo 'ERROR: ' . $e->getMessage() . "\n" . $e->getTraceAsString();
}
