<?php

declare(strict_types=1);

namespace Shikiphp\Theme;

/**
 * Decoded VS Code theme JSON: display name, light/dark type, the editor colour
 * map, and the token-colour rule list (`tokenColors` or legacy `settings`).
 */
final readonly class RawTheme
{
    /**
     * @param array<string, string> $colors
     * @param list<array<string, mixed>> $settings
     */
    public function __construct(
        public string $name,
        public string $type,
        public array $colors,
        public array $settings,
    ) {
    }

    /** @param array<string, mixed> $raw */
    public static function fromArray(array $raw): self
    {
        $rawSettings = $raw['tokenColors'] ?? $raw['settings'] ?? [];
        assert(is_array($rawSettings));

        $rawColors = $raw['colors'] ?? [];
        assert(is_array($rawColors));

        $colors = [];
        foreach ($rawColors as $key => $value) {
            if (is_string($key) && is_string($value)) {
                $colors[$key] = $value;
            }
        }

        $settings = [];
        foreach ($rawSettings as $entry) {
            if (! is_array($entry)) {
                continue;
            }
            $rule = [];
            foreach ($entry as $key => $value) {
                if (is_string($key)) {
                    $rule[$key] = $value;
                }
            }
            $settings[] = $rule;
        }

        return new self(
            name: is_string($raw['name'] ?? null) ? $raw['name'] : '',
            type: is_string($raw['type'] ?? null) ? $raw['type'] : 'dark',
            colors: $colors,
            settings: $settings,
        );
    }
}
