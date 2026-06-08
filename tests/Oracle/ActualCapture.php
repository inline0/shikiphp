<?php

declare(strict_types=1);

namespace Shikiphp\Tests\Oracle;

use Shikiphp\Shikiphp;

/**
 * shikiphp's own HTML for a scenario.
 */
final class ActualCapture
{
    public static function html(string $lang, string $theme, string $file): string
    {
        $code = (string) file_get_contents($file);
        return Shikiphp::codeToHtml($code, ['lang' => $lang, 'theme' => $theme]);
    }
}
