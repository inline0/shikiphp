<?php

declare(strict_types=1);

namespace Shikiphp\Theme;

/**
 * A single token-colour rule flattened from the raw theme, retaining its source
 * index so later rules can break specificity ties (vscode-textmate semantics).
 *
 * @internal
 */
final readonly class ParsedThemeRule
{
    /** @param list<string>|null $parentScopes outermost first, innermost last */
    public function __construct(
        public string $scope,
        public ?array $parentScopes,
        public int $index,
        public int $fontStyle,
        public ?string $foreground,
        public ?string $background,
    ) {
    }
}
