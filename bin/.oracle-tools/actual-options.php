<?php

declare(strict_types=1);

require __DIR__ . '/../../vendor/autoload.php';

use Shikiphp\Highlighter;

[$script, $lang, $theme, $file, $optionsJson] = array_pad($argv, 5, null);
if ($lang === null || $theme === null || $file === null) {
    fwrite(STDERR, "usage: php actual-options.php <lang> <theme> <file> <optionsJson>\n");
    exit(2);
}

$code = file_get_contents($file);
$extra = $optionsJson !== null ? json_decode($optionsJson, true) : [];

$options = ['lang' => $lang, ...$extra];
if (!isset($extra['themes'])) {
    $options['theme'] = $theme;
}

$h = Highlighter::createBundled();
try {
    echo $h->codeToHtml($code, $options);
} catch (\Throwable $e) {
    echo 'ERROR: ' . $e->getMessage();
}
