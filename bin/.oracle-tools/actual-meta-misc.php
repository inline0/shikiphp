<?php

declare(strict_types=1);

require __DIR__ . '/../../vendor/autoload.php';

use Shikiphp\Highlighter;
use Shikiphp\Transformer\CompactLineOptions;
use Shikiphp\Transformer\MetaHighlight;
use Shikiphp\Transformer\MetaWordHighlight;
use Shikiphp\Transformer\RemoveNotationEscape;
use Shikiphp\Transformer\RenderWhitespace;

[$script, $kind, $lang, $theme, $file, $extra] = array_pad($argv, 6, null);
if ($kind === null || $lang === null || $theme === null || $file === null) {
    fwrite(STDERR, "usage: php actual-meta-misc.php <kind> <lang> <theme> <file> [extra]\n");
    exit(2);
}

$code = rtrim((string) file_get_contents($file), "\n");

$options = ['lang' => $lang, 'theme' => $theme];

switch ($kind) {
    case 'meta-highlight':
        $options['transformers'] = [new MetaHighlight()];
        $options['meta'] = ['__raw' => $extra ?? ''];
        break;
    case 'meta-word-highlight':
        $options['transformers'] = [new MetaWordHighlight()];
        $options['meta'] = ['__raw' => $extra ?? ''];
        break;
    case 'render-whitespace':
        $options['transformers'] = [new RenderWhitespace(position: $extra ?? 'all')];
        break;
    case 'compact-line-options':
        $decoded = json_decode($extra ?? '[]', true);
        $options['transformers'] = [new CompactLineOptions(is_array($decoded) ? $decoded : [])];
        break;
    case 'remove-notation-escape':
        $options['transformers'] = [new RemoveNotationEscape()];
        break;
    default:
        fwrite(STDERR, "unknown kind\n");
        exit(2);
}

$h = Highlighter::createBundled();
try {
    echo $h->codeToHtml($code, $options);
} catch (\Throwable $e) {
    echo 'ERROR: ' . $e->getMessage() . "\n" . $e->getTraceAsString();
}
