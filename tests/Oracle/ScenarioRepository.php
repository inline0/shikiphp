<?php

declare(strict_types=1);

namespace Shikiphp\Tests\Oracle;

/**
 * Discovers oracle scenarios under `scenarios/`. Each scenario is a directory
 * holding an `input.<ext>` and a `meta.json` (`{lang, theme}`).
 *
 * @phpstan-type Scenario array{name: string, path: string, lang: string, theme: string, input: string}
 */
final class ScenarioRepository
{
    private readonly string $baseDir;

    public function __construct(?string $baseDir = null)
    {
        $this->baseDir = $baseDir ?? dirname(__DIR__, 2) . '/scenarios';
    }

    /** @return list<Scenario> */
    public function all(): array
    {
        $scenarios = [];
        foreach (glob($this->baseDir . '/*', GLOB_ONLYDIR) ?: [] as $dir) {
            $meta = $dir . '/meta.json';
            if (!is_file($meta)) {
                continue;
            }

            /** @var array{lang?: string, theme?: string} $config */
            $config = json_decode((string) file_get_contents($meta), true, 512, JSON_THROW_ON_ERROR);
            $input = self::findInput($dir);
            if ($input === null || !isset($config['lang'], $config['theme'])) {
                continue;
            }

            $scenarios[] = [
                'name' => basename($dir),
                'path' => $dir,
                'lang' => $config['lang'],
                'theme' => $config['theme'],
                'input' => $input,
            ];
        }

        usort($scenarios, static fn (array $a, array $b): int => $a['name'] <=> $b['name']);

        return $scenarios;
    }

    private static function findInput(string $dir): ?string
    {
        foreach (glob($dir . '/input.*') ?: [] as $candidate) {
            return $candidate;
        }

        return null;
    }
}
