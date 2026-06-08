<?php

declare(strict_types=1);

namespace Shikiphp\Theme;

/**
 * A VS Code theme compiled into a scope-name trie for fast specificity-ordered
 * matching with parent-scope selectors, mirroring vscode-textmate's `Theme`.
 */
final class Theme
{
    private const DEFAULT_FOREGROUND_DARK = '#bbbbbb';
    private const DEFAULT_BACKGROUND_DARK = '#1e1e1e';
    private const DEFAULT_FOREGROUND_LIGHT = '#333333';
    private const DEFAULT_BACKGROUND_LIGHT = '#ffffff';

    private readonly ThemeTrieElement $root;
    private readonly StyleAttributes $defaults;

    /** @var array<string, StyleAttributes> */
    private array $cache = [];

    private function __construct(
        private readonly RawTheme $raw,
        ThemeTrieElement $root,
        StyleAttributes $defaults,
    ) {
        $this->root = $root;
        $this->defaults = $defaults;
    }

    /** @param array<string, mixed> $raw */
    public static function fromRaw(array $raw): self
    {
        return self::fromRawTheme(RawTheme::fromArray($raw));
    }

    public static function fromRawTheme(RawTheme $raw): self
    {
        $parsed = self::parseRules($raw);

        $defaultFontStyle = FontStyle::NONE;
        $defaultForeground = null;
        $defaultBackground = null;

        $colorMap = [];
        $rules = [];
        foreach ($parsed as $rule) {
            if ($rule->scope === '' && $rule->parentScopes === null) {
                if ($rule->fontStyle !== FontStyle::NOT_SET) {
                    $defaultFontStyle = $rule->fontStyle;
                }
                $defaultForeground = $rule->foreground ?? $defaultForeground;
                $defaultBackground = $rule->background ?? $defaultBackground;
                continue;
            }
            $rules[] = $rule;
        }

        $isLight = strtolower($raw->type) === 'light';
        $defaultForeground ??= $raw->colors['editor.foreground'] ?? null;
        $defaultBackground ??= $raw->colors['editor.background'] ?? null;
        $defaultForeground ??= $isLight ? self::DEFAULT_FOREGROUND_LIGHT : self::DEFAULT_FOREGROUND_DARK;
        $defaultBackground ??= $isLight ? self::DEFAULT_BACKGROUND_LIGHT : self::DEFAULT_BACKGROUND_DARK;

        $defaults = new StyleAttributes($defaultFontStyle, $defaultForeground, $defaultBackground);

        $rootRule = new ThemeTrieElementRule(0, null, $defaultFontStyle, $defaultForeground, $defaultBackground);
        $root = new ThemeTrieElement($rootRule);

        usort($rules, self::cmpParsedRules(...));

        foreach ($rules as $rule) {
            $root->insert(0, $rule->scope, $rule->parentScopes, $rule->fontStyle, $rule->foreground, $rule->background);
        }

        return new self($raw, $root, $defaults);
    }

    private static function cmpParsedRules(ParsedThemeRule $a, ParsedThemeRule $b): int
    {
        if ($a->scope !== $b->scope) {
            return $a->scope <=> $b->scope;
        }

        $parents = self::cmpParentScopes($a->parentScopes, $b->parentScopes);
        if ($parents !== 0) {
            return $parents;
        }

        return $a->index <=> $b->index;
    }

    /**
     * @param list<string>|null $a
     * @param list<string>|null $b
     */
    private static function cmpParentScopes(?array $a, ?array $b): int
    {
        if ($a === null) {
            return $b === null ? 0 : -1;
        }
        if ($b === null) {
            return 1;
        }

        $count = min(count($a), count($b));
        for ($i = 0; $i < $count; $i++) {
            if ($a[$i] !== $b[$i]) {
                return $a[$i] <=> $b[$i];
            }
        }

        return count($a) <=> count($b);
    }

    /**
     * @param list<string> $scopePath scope segments, innermost LAST
     */
    public function match(array $scopePath): StyleAttributes
    {
        if ($scopePath === []) {
            return $this->defaults;
        }

        $key = implode("\u{1f}", $scopePath);
        if (isset($this->cache[$key])) {
            return $this->cache[$key];
        }

        $scopeName = $scopePath[count($scopePath) - 1];
        $parents = array_slice($scopePath, 0, -1);

        $rules = $this->root->match($scopeName);

        $effectiveFontStyle = FontStyle::NOT_SET;
        $effectiveForeground = null;
        $effectiveBackground = null;

        foreach ($rules as $rule) {
            if (!self::scopesAreMatching($parents, $rule->parentScopes)) {
                continue;
            }

            if ($effectiveFontStyle === FontStyle::NOT_SET && $rule->fontStyle !== FontStyle::NOT_SET) {
                $effectiveFontStyle = $rule->fontStyle;
            }
            $effectiveForeground ??= $rule->foreground;
            $effectiveBackground ??= $rule->background;
        }

        if ($effectiveFontStyle === FontStyle::NOT_SET) {
            $effectiveFontStyle = $this->defaults->fontStyle;
        }
        $effectiveForeground ??= $this->defaults->foreground;
        $effectiveBackground ??= $this->defaults->background;

        return $this->cache[$key] = new StyleAttributes(
            $effectiveFontStyle,
            $effectiveForeground,
            $effectiveBackground,
        );
    }

    public function name(): string
    {
        return $this->raw->name;
    }

    public function type(): string
    {
        return $this->raw->type;
    }

    public function foreground(): string
    {
        return $this->defaults->foreground ?? self::DEFAULT_FOREGROUND_DARK;
    }

    public function background(): string
    {
        return $this->defaults->background ?? self::DEFAULT_BACKGROUND_DARK;
    }

    /**
     * @param list<string> $scopeParents the candidate's ancestors, innermost LAST
     * @param list<string>|null $selectorParents required ancestors, innermost LAST
     */
    private static function scopesAreMatching(array $scopeParents, ?array $selectorParents): bool
    {
        if ($selectorParents === null || $selectorParents === []) {
            return true;
        }

        $index = count($scopeParents) - 1;
        foreach (array_reverse($selectorParents) as $selector) {
            $found = false;
            while ($index >= 0) {
                if (self::scopeMatches($scopeParents[$index], $selector)) {
                    $found = true;
                    $index--;
                    break;
                }
                $index--;
            }
            if (!$found) {
                return false;
            }
        }

        return true;
    }

    private static function scopeMatches(string $scope, string $selector): bool
    {
        if ($scope === $selector) {
            return true;
        }

        $len = strlen($selector);
        return strlen($scope) > $len
            && substr($scope, 0, $len) === $selector
            && $scope[$len] === '.';
    }

    /**
     * @return list<ParsedThemeRule>
     */
    private static function parseRules(RawTheme $raw): array
    {
        $parsed = [];
        $index = 0;

        foreach ($raw->settings as $entry) {
            $settings = $entry['settings'] ?? null;
            if (!is_array($settings)) {
                continue;
            }

            $fontStyle = self::parseFontStyle($settings['fontStyle'] ?? null);
            $foreground = self::normalizeColor($settings['foreground'] ?? null);
            $background = self::normalizeColor($settings['background'] ?? null);

            $scopes = self::normalizeScopes($entry['scope'] ?? null);

            foreach ($scopes as $scope) {
                $segments = preg_split('/\s+/', trim($scope)) ?: [];
                $segments = array_values(array_filter($segments, static fn (string $s): bool => $s !== ''));

                $target = array_pop($segments);
                $parentScopes = $segments === [] ? null : $segments;

                $parsed[] = new ParsedThemeRule(
                    $target ?? '',
                    $parentScopes,
                    $index,
                    $fontStyle,
                    $foreground,
                    $background,
                );
                $index++;
            }
        }

        return $parsed;
    }

    /**
     * @return list<string>
     */
    private static function normalizeScopes(mixed $scope): array
    {
        if (is_string($scope)) {
            $scope = explode(',', $scope);
        }
        if (!is_array($scope)) {
            return [''];
        }

        $out = [];
        foreach ($scope as $s) {
            if (is_string($s)) {
                $out[] = $s;
            }
        }

        return $out === [] ? [''] : $out;
    }

    private static function normalizeColor(mixed $color): ?string
    {
        if (!is_string($color)) {
            return null;
        }
        $color = trim($color);
        return $color === '' ? null : $color;
    }

    private static function parseFontStyle(mixed $fontStyle): int
    {
        if ($fontStyle === null) {
            return FontStyle::NOT_SET;
        }
        if (!is_string($fontStyle)) {
            return FontStyle::NOT_SET;
        }

        $fontStyle = trim($fontStyle);
        if ($fontStyle === '' || $fontStyle === 'none') {
            return FontStyle::NONE;
        }

        $style = FontStyle::NONE;
        foreach (preg_split('/\s+/', $fontStyle) ?: [] as $token) {
            $style |= match ($token) {
                'italic' => FontStyle::ITALIC,
                'bold' => FontStyle::BOLD,
                'underline' => FontStyle::UNDERLINE,
                'strikethrough' => FontStyle::STRIKETHROUGH,
                default => FontStyle::NONE,
            };
        }

        return $style;
    }
}
