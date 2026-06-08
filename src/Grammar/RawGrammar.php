<?php

declare(strict_types=1);

namespace Shikiphp\Grammar;

/**
 * Decoded `.tmLanguage.json`: the scope name plus the raw rule arrays
 * (`patterns`, `repository`, `injections`) kept as nested arrays for the
 * RuleFactory to compile on demand.
 */
final readonly class RawGrammar
{
    /**
     * @param list<array<array-key, mixed>> $patterns
     * @param array<string, array<array-key, mixed>> $repository
     * @param array<string, array<array-key, mixed>> $injections
     * @param list<string> $fileTypes
     * @param array<array-key, mixed> $raw the full decoded document, for fields not promoted here
     */
    public function __construct(
        public string $scopeName,
        public array $patterns,
        public array $repository,
        public array $injections,
        public ?string $injectionSelector,
        public array $fileTypes,
        public ?string $name,
        public ?string $foldingStartMarker,
        public ?string $foldingStopMarker,
        public ?string $firstLineMatch,
        public array $raw,
    ) {
    }

    /** @param array<array-key, mixed> $raw */
    public static function fromArray(array $raw): self
    {
        $scopeName = is_string($raw['scopeName'] ?? null) ? $raw['scopeName'] : '';

        $patterns = self::arrayList($raw['patterns'] ?? null);
        $repository = self::arrayMap($raw['repository'] ?? null);
        $injections = self::arrayMap($raw['injections'] ?? null);

        $fileTypes = [];
        if (is_array($raw['fileTypes'] ?? null)) {
            foreach ($raw['fileTypes'] as $type) {
                if (is_string($type)) {
                    $fileTypes[] = $type;
                }
            }
        }

        return new self(
            scopeName: $scopeName,
            patterns: $patterns,
            repository: $repository,
            injections: $injections,
            injectionSelector: is_string($raw['injectionSelector'] ?? null) ? $raw['injectionSelector'] : null,
            fileTypes: $fileTypes,
            name: is_string($raw['name'] ?? null) ? $raw['name'] : null,
            foldingStartMarker: is_string($raw['foldingStartMarker'] ?? null) ? $raw['foldingStartMarker'] : null,
            foldingStopMarker: is_string($raw['foldingStopMarker'] ?? null) ? $raw['foldingStopMarker'] : null,
            firstLineMatch: is_string($raw['firstLineMatch'] ?? null) ? $raw['firstLineMatch'] : null,
            raw: $raw,
        );
    }

    /** @return list<array<array-key, mixed>> */
    private static function arrayList(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        $out = [];
        foreach ($value as $item) {
            if (is_array($item)) {
                $out[] = $item;
            }
        }

        return $out;
    }

    /** @return array<string, array<array-key, mixed>> */
    private static function arrayMap(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        $out = [];
        foreach ($value as $key => $item) {
            if (is_string($key) && is_array($item)) {
                $out[$key] = $item;
            }
        }

        return $out;
    }
}
