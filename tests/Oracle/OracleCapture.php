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
        $stderr = '';
        $exit = -1;
        for ($attempt = 0; $attempt < 3; $attempt++) {
            [$stdout, $stderr, $exit] = self::run($lang, $theme, $file);
            if ($exit === 0 && str_starts_with(ltrim($stdout), '<pre')) {
                return $stdout;
            }
        }

        throw new \RuntimeException(sprintf(
            "Shiki oracle failed for %s/%s (%s): exit=%d\n%s",
            $lang,
            $theme,
            basename($file),
            $exit,
            trim($stderr) !== '' ? $stderr : 'no output',
        ));
    }

    /**
     * Reference `codeToTokensWithThemes` output from Shiki.js, decoded into the
     * same nested array shape shikiphp produces.
     *
     * @param array<string, string> $themes theme key → theme id
     * @return list<list<array{content: string, offset: int, variants: array<string, array{color: ?string, fontStyle: int, bgColor: ?string}>}>>
     */
    public static function tokensWithThemes(string $lang, string $file, array $themes): array
    {
        $themesJson = (string) json_encode($themes);
        $cmd = sprintf(
            'node %s %s %s %s',
            escapeshellarg(self::toolsDir() . '/oracle-tokens-themes.mjs'),
            escapeshellarg($lang),
            escapeshellarg($file),
            escapeshellarg($themesJson),
        );

        $descriptors = [1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
        $process = proc_open($cmd, $descriptors, $pipes);
        if (!is_resource($process)) {
            throw new \RuntimeException('failed to spawn node');
        }

        $stdout = (string) stream_get_contents($pipes[1]);
        $stderr = (string) stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exit = proc_close($process);

        if ($exit !== 0) {
            throw new \RuntimeException('Shiki tokens oracle failed: ' . trim($stderr));
        }

        $decoded = json_decode($stdout, true);
        if (!is_array($decoded)) {
            throw new \RuntimeException('Shiki tokens oracle returned invalid JSON: ' . trim($stderr));
        }

        /** @var list<list<array{content: string, offset: int, variants: array<string, array{color: ?string, fontStyle: int, bgColor: ?string}>}>> $decoded */
        return $decoded;
    }

    /**
     * Run oracle.mjs with stdout and stderr captured separately so a stray node
     * warning never corrupts the reference HTML.
     *
     * @return array{string, string, int}
     */
    private static function run(string $lang, string $theme, string $file): array
    {
        $cmd = sprintf(
            'node %s %s %s %s',
            escapeshellarg(self::toolsDir() . '/oracle.mjs'),
            escapeshellarg($lang),
            escapeshellarg($theme),
            escapeshellarg($file),
        );

        $descriptors = [1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
        $process = proc_open($cmd, $descriptors, $pipes);
        if (!is_resource($process)) {
            return ['', 'failed to spawn node', -1];
        }

        $stdout = (string) stream_get_contents($pipes[1]);
        $stderr = (string) stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exit = proc_close($process);

        return [$stdout, $stderr, $exit];
    }

    private static function toolsDir(): string
    {
        return dirname(__DIR__, 2) . '/bin/.oracle-tools';
    }
}
