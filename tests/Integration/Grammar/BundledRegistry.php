<?php

declare(strict_types=1);

namespace Shikiphp\Tests\Integration\Grammar;

use Shikiphp\Grammar\Registry;

/**
 * Wires a {@see Registry} resolver against the bundled `src/Registry/grammars`
 * plus its `manifest.json`, resolving grammars by scope name on demand.
 */
final class BundledRegistry
{
    public static function create(): Registry
    {
        $base = dirname(__DIR__, 3) . '/src/Registry';
        $grammarsDir = $base . '/grammars';

        $manifest = json_decode((string) file_get_contents($base . '/manifest.json'), true);
        assert(is_array($manifest));

        $fileByScope = [];
        foreach ($manifest['languages'] as $language) {
            $fileByScope[$language['scopeName']] = $grammarsDir . '/' . basename($language['file']);
        }

        $resolver = static function (string $scopeName) use ($fileByScope): ?array {
            $file = $fileByScope[$scopeName] ?? null;
            if ($file === null || !is_file($file)) {
                return null;
            }
            $decoded = json_decode((string) file_get_contents($file), true);
            return is_array($decoded) ? $decoded : null;
        };

        return new Registry($resolver);
    }

    public static function hasBundledGrammars(): bool
    {
        return is_dir(dirname(__DIR__, 3) . '/src/Registry/grammars');
    }
}
