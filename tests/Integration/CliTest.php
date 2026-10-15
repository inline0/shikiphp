<?php

declare(strict_types=1);

namespace Shikiphp\Tests\Integration;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class CliTest extends TestCase
{
    #[Test]
    public function unknown_extensions_and_extensionless_files_render_plain_text(): void
    {
        $directory = sys_get_temp_dir() . '/shikiphp-cli-' . bin2hex(random_bytes(6));
        mkdir($directory);
        try {
            foreach (['NOTES', 'notes.txt', 'notes.unknown', 'Makefile', '.config'] as $name) {
                $file = $directory . '/' . $name;
                file_put_contents($file, 'hello <b>');
                [$status, $stdout, $stderr] = self::runCli(['highlight', $file]);
                self::assertSame(0, $status, $stderr);
                self::assertSame('', $stderr);
                self::assertStringContainsString('<span>hello &#x3C;b></span>', $stdout);
                unlink($file);
            }
        } finally {
            rmdir($directory);
        }
    }

    #[Test]
    public function invalid_themes_produce_one_line_errors(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'shikiphp-cli-');
        self::assertIsString($file);
        file_put_contents($file, 'hello');
        try {
            foreach (['--theme=nope'] as $option) {
                [$status, $stdout, $stderr] = self::runCli(['highlight', $file, $option]);
                self::assertSame(1, $status);
                self::assertSame('', $stdout);
                self::assertMatchesRegularExpression('/^Unknown (language|theme) "nope"\.\n$/', $stderr);
            }
        } finally {
            unlink($file);
        }
    }

    #[Test]
    public function unknown_explicit_language_falls_back_and_known_ids_are_case_insensitive(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'shikiphp-cli-');
        self::assertIsString($file);
        file_put_contents($file, 'const x = 1');
        try {
            [$status, $stdout, $stderr] = self::runCli(['highlight', $file, '--lang=nope']);
            self::assertSame(0, $status, $stderr);
            self::assertStringContainsString('<span>const x = 1</span>', $stdout);
            [$status, $stdout, $stderr] = self::runCli(['highlight', $file, '--lang=JS']);
            self::assertSame(0, $status, $stderr);
            self::assertStringContainsString('<span style="color:#F97583">const</span>', $stdout);
            [$status, $stdout, $stderr] = self::runCli(['highlight', $file, '--lang=js', '--format=ansi', '--color-level=0']);
            self::assertSame(0, $status, $stderr);
            self::assertSame("const x = 1\n", $stdout);
        } finally {
            unlink($file);
        }
    }

    /** @param list<string> $arguments
     * @return array{int, string, string}
     */
    private static function runCli(array $arguments): array
    {
        $process = proc_open(
            [PHP_BINARY, dirname(__DIR__, 2) . '/bin/shikiphp', ...$arguments],
            [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
        );
        self::assertIsResource($process);
        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);

        return [proc_close($process), $stdout, $stderr];
    }
}
