<?php

declare(strict_types=1);

namespace Shikiphp\Theme;

/**
 * A node in the scope-name trie (split on `.`). Holds the styling that applies
 * at this scope plus parent-scope-gated variants, and child segments.
 *
 * @internal
 */
final class ThemeTrieElement
{
    /**
     * @param list<ThemeTrieElementRule> $rulesWithParentScopes
     * @param array<string, ThemeTrieElement> $children
     */
    public function __construct(
        private ThemeTrieElementRule $mainRule,
        private array $rulesWithParentScopes = [],
        private array $children = [],
    ) {
    }

    /**
     * @return list<ThemeTrieElementRule>
     */
    public function match(string $scope): array
    {
        if ($scope !== '') {
            $dot = strpos($scope, '.');
            if ($dot === false) {
                $head = $scope;
                $tail = '';
            } else {
                $head = substr($scope, 0, $dot);
                $tail = substr($scope, $dot + 1);
            }

            if (isset($this->children[$head])) {
                return $this->children[$head]->match($tail);
            }
        }

        $rules = array_merge([$this->mainRule], $this->rulesWithParentScopes);
        return self::sortBySpecificity($rules);
    }

    /** @param list<string>|null $parentScopes */
    public function insert(
        int $scopeDepth,
        string $scope,
        ?array $parentScopes,
        int $fontStyle,
        ?string $foreground,
        ?string $background,
    ): void {
        if ($scope === '') {
            $this->doInsertHere($scopeDepth, $parentScopes, $fontStyle, $foreground, $background);
            return;
        }

        $dot = strpos($scope, '.');
        if ($dot === false) {
            $head = $scope;
            $tail = '';
        } else {
            $head = substr($scope, 0, $dot);
            $tail = substr($scope, $dot + 1);
        }

        if (isset($this->children[$head])) {
            $child = $this->children[$head];
        } else {
            $child = new ThemeTrieElement($this->mainRule->clone());
            $this->children[$head] = $child;
        }

        $child->insert($scopeDepth + 1, $tail, $parentScopes, $fontStyle, $foreground, $background);
    }

    /** @param list<string>|null $parentScopes */
    private function doInsertHere(
        int $scopeDepth,
        ?array $parentScopes,
        int $fontStyle,
        ?string $foreground,
        ?string $background,
    ): void {
        if ($parentScopes === null) {
            $this->mainRule->acceptOverwrite($scopeDepth, $fontStyle, $foreground, $background);
            return;
        }

        foreach ($this->rulesWithParentScopes as $rule) {
            if ($rule->parentScopes === $parentScopes) {
                $rule->acceptOverwrite($scopeDepth, $fontStyle, $foreground, $background);
                return;
            }
        }

        if ($fontStyle === FontStyle::NOT_SET) {
            $fontStyle = $this->mainRule->fontStyle;
        }
        $foreground ??= $this->mainRule->foreground;
        $background ??= $this->mainRule->background;

        $this->rulesWithParentScopes[] = new ThemeTrieElementRule(
            $scopeDepth,
            $parentScopes,
            $fontStyle,
            $foreground,
            $background,
        );
    }

    /**
     * @param list<ThemeTrieElementRule> $rules
     * @return list<ThemeTrieElementRule>
     */
    private static function sortBySpecificity(array $rules): array
    {
        if (count($rules) === 1) {
            return $rules;
        }

        usort($rules, self::cmpBySpecificity(...));
        return $rules;
    }

    private static function cmpBySpecificity(ThemeTrieElementRule $a, ThemeTrieElementRule $b): int
    {
        if ($a->scopeDepth === $b->scopeDepth) {
            $aParents = $a->parentScopes;
            $bParents = $b->parentScopes;

            $aLen = $aParents === null ? 0 : count($aParents);
            $bLen = $bParents === null ? 0 : count($bParents);

            if ($aLen === $bLen && $aParents !== null && $bParents !== null) {
                for ($i = 0; $i < $aLen; $i++) {
                    $aLength = strlen($aParents[$i]);
                    $bLength = strlen($bParents[$i]);
                    if ($aLength !== $bLength) {
                        return $bLength - $aLength;
                    }
                }
            }

            return $bLen - $aLen;
        }

        return $b->scopeDepth - $a->scopeDepth;
    }
}
