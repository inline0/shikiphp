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

    /** @var list<string> bundle-declared language ids (before any runtime registration) */
    private readonly array $bundledLanguageIds;

    /** @var list<string> bundle-declared theme ids (before any runtime registration) */
    private readonly array $bundledThemeIds;

    /** @var array<string, array<string, mixed>> language id → decoded raw grammar registered at runtime */
    private array $customGrammars = [];

    /** @var array<string, array<string, mixed>> theme id → decoded raw theme registered at runtime */
    private array $customThemes = [];

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

        $this->bundledLanguageIds = array_keys($this->languages);
        $this->bundledThemeIds = array_keys($this->themes);
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

    /** @return list<string> ids shipped in the bundle, excluding runtime registrations */
    public function bundledLanguageIds(): array
    {
        return $this->bundledLanguageIds;
    }

    /** @return list<string> ids shipped in the bundle, excluding runtime registrations */
    public function bundledThemeIds(): array
    {
        return $this->bundledThemeIds;
    }

    /**
     * Register a decoded `.tmLanguage` grammar at runtime. The language id is
     * taken from $langId, else the grammar's `name`, else its `scopeName`. Its
     * scope (and `embeddedLangs`/includes) resolve against grammars already
     * registered here, so a custom grammar may reference bundled ones.
     *
     * @param array<string, mixed> $raw
     * @param list<string> $aliases
     * @param list<string> $embedded language ids this grammar embeds
     * @return string the resolved language id
     */
    public function registerGrammar(array $raw, ?string $langId = null, array $aliases = [], array $embedded = []): string
    {
        $scopeName = is_string($raw['scopeName'] ?? null) ? $raw['scopeName'] : null;
        if ($scopeName === null || $scopeName === '') {
            throw Highlight::invalidGrammar('missing scopeName');
        }

        $name = is_string($raw['name'] ?? null) ? $raw['name'] : null;
        $id = $langId ?? $name ?? $scopeName;

        $this->customGrammars[$id] = $raw;
        $this->languages[$id] = [
            'file' => '',
            'scopeName' => $scopeName,
            'aliases' => $aliases,
            'embedded' => $embedded,
        ];
        $this->langIndex[$id] = $id;
        $this->scopeIndex[$scopeName] = $id;
        foreach ($aliases as $alias) {
            $this->langIndex[$alias] = $id;
        }
        $this->rawCache[$scopeName] = $raw;

        return $id;
    }

    /**
     * Register a decoded VS Code theme at runtime, keyed by its `name`. A custom
     * theme overrides a bundled theme of the same name.
     *
     * @param array<string, mixed> $raw
     * @return string the theme id (its `name`)
     */
    public function registerTheme(array $raw): string
    {
        $id = is_string($raw['name'] ?? null) ? $raw['name'] : null;
        if ($id === null || $id === '') {
            throw Highlight::invalidTheme('missing name');
        }

        $this->customThemes[$id] = $raw;
        $this->themes[$id] = ['file' => ''];

        return $id;
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
        if (isset($this->customGrammars[$id])) {
            return $this->customGrammars[$id];
        }
        return $this->loadGrammarFile($this->languages[$id]['file']);
    }

    /** @return array<string, mixed> decoded theme JSON */
    public function rawTheme(string $themeId): array
    {
        if (isset($this->customThemes[$themeId])) {
            return $this->customThemes[$themeId];
        }
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
            if (isset($this->customGrammars[$id])) {
                return $this->customGrammars[$id];
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
