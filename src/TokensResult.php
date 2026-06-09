<?php

declare(strict_types=1);

namespace Shikiphp;

use Shikiphp\Render\ThemedToken;

/**
 * Rich result of {@see Highlighter::codeToTokens()}: the 2D token grid plus the
 * resolved foreground/background, theme name, optional dual-theme root style and
 * the final grammar state. Port of @shikijs/core's `TokensResult`.
 */
final class TokensResult
{
    /**
     * @param list<list<ThemedToken>> $tokens
     */
    public function __construct(
        public readonly array $tokens,
        public readonly ?string $fg = null,
        public readonly ?string $bg = null,
        public readonly ?string $themeName = null,
        public readonly string|false|null $rootStyle = null,
        public readonly ?GrammarState $grammarState = null,
    ) {
    }
}
