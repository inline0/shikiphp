<?php

declare(strict_types=1);

namespace Shikiphp\Tests\Oracle;

/**
 * Reference HTML from Shiki.js via `bin/.oracle-tools/oracle.mjs`.
 */
final class OracleCapture
{
    public static function isAvailable(): bool
    {
        $node = trim((string) shell_exec('command -v node 2>/dev/null'));
        return $node !== '' && is_dir(self::toolsDir() . '/node_modules');
    }

    public static function html(string $lang, string $theme, string $file): string
    {
        $script = self::toolsDir() . '/oracle.mjs';
        $cmd = sprintf(
            'node %s %s %s %s 2>&1',
            escapeshellarg($script),
            escapeshellarg($lang),
            escapeshellarg($theme),
            escapeshellarg($file),
        );

        $output = (string) shell_exec($cmd);

        return $output;
    }

    private static function toolsDir(): string
    {
        return dirname(__DIR__, 2) . '/bin/.oracle-tools';
    }
}
