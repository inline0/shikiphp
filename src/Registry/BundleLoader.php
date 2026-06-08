<?php

declare(strict_types=1);

namespace Shikiphp\Registry;

use Shikiphp\Exceptions\Highlight;

/**
 * Reads `manifest.json` and resolves bundled assets: a language id or alias to
 * its grammar file, scope name and embedded-language dependencies, and a theme
 * id to its theme file. Also mints the scope-name resolver the grammar Registry
 * uses to lazily pull in embedded/included grammars.
 *
 * @phpstan-type LangEntry array{file: string, scopeName: string, aliases: list<string>, embedded: list<string>}
 */
final class BundleLoader
{
    /** @var array<string, LangEntry> */
    private array $languages;

    /** @var array<string, string> alias or id → canonical language id */
    private array $langIndex = [];

    /** @var array<string, string> scope name → language id */
    private array $scopeIndex = [];

    /** @var array<string, array{file: string}> */
    private array $themes;

    /** @var array<string, array<string, mixed>> scope name → decoded raw grammar */
    private array $rawCache = [];

    public function __construct(
        private readonly string $baseDir,
    ) {
        $manifest = self::decodeJson($baseDir . '/manifest.json');

        /** @var array<string, LangEntry> $languages */
        $languages = $manifest['languages'] ?? [];
        $this->languages = $languages;

        /** @var array<string, array{file: string}> $themes */
        $themes = $manifest['themes'] ?? [];
        $this->themes = $themes;

        foreach ($this->languages as $id => $entry) {
            $this->langIndex[$id] = $id;
            $this->scopeIndex[$entry['scopeName']] = $id;
            foreach ($entry['aliases'] as $alias) {
                $this->langIndex[$alias] ??= $id;
            }
        }
    }

    public static function bundled(): self
    {
        return new self(__DIR__);
    }

    /** @return list<string> canonical language ids */
    public function languageIds(): array
    {
        return array_keys($this->languages);
    }

    /** @return list<string> */
    public function themeIds(): array
    {
        return array_keys($this->themes);
    }

    public function hasLanguage(string $idOrAlias): bool
    {
        return isset($this->langIndex[$idOrAlias]);
    }

    public function scopeNameFor(string $idOrAlias): string
    {
        $id = $this->langIndex[$idOrAlias] ?? throw Highlight::unknownLanguage($idOrAlias);
        return $this->languages[$id]['scopeName'];
    }

    /**
     * The transitive set of language ids needed to tokenize the given language:
     * the language itself plus its embedded grammars (recursively).
     *
     * @return list<string>
     */
    public function dependencyClosure(string $idOrAlias): array
    {
        $id = $this->langIndex[$idOrAlias] ?? throw Highlight::unknownLanguage($idOrAlias);

        $seen = [];
        $queue = [$id];
        while ($queue !== []) {
            $current = array_shift($queue);
            if (isset($seen[$current]) || !isset($this->languages[$current])) {
                continue;
            }
            $seen[$current] = true;
            foreach ($this->languages[$current]['embedded'] as $dep) {
                if (!isset($seen[$dep])) {
                    $queue[] = $dep;
                }
            }
        }

        return array_keys($seen);
    }

    /** @return array<string, mixed> decoded grammar JSON */
    public function rawGrammar(string $idOrAlias): array
    {
        $id = $this->langIndex[$idOrAlias] ?? throw Highlight::unknownLanguage($idOrAlias);
        return $this->loadGrammarFile($this->languages[$id]['file']);
    }

    /** @return array<string, mixed> decoded theme JSON */
    public function rawTheme(string $themeId): array
    {
        $entry = $this->themes[$themeId] ?? throw Highlight::unknownTheme($themeId);
        return self::decodeJson($this->baseDir . '/' . $entry['file']);
    }

    /**
     * Resolver for {@see \Shikiphp\Grammar\Registry}: maps a scope name to its raw
     * grammar JSON, or null when no bundled grammar declares that scope.
     *
     * @return (callable(string): ?array<string, mixed>)
     */
    public function grammarResolver(): callable
    {
        return function (string $scopeName): ?array {
            $id = $this->scopeIndex[$scopeName] ?? null;
            if ($id === null) {
                return null;
            }
            return $this->loadGrammarFile($this->languages[$id]['file']);
        };
    }

    /** @return array<string, mixed> */
    private function loadGrammarFile(string $relativeFile): array
    {
        $raw = self::decodeJson($this->baseDir . '/' . $relativeFile);
        $scopeName = is_string($raw['scopeName'] ?? null) ? $raw['scopeName'] : $relativeFile;
        return $this->rawCache[$scopeName] ??= $raw;
    }

    /** @return array<string, mixed> */
    private static function decodeJson(string $path): array
    {
        $contents = @file_get_contents($path);
        assert($contents !== false, "Bundled asset missing: {$path}");

        /** @var array<string, mixed> $decoded */
        $decoded = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
        return $decoded;
    }
}
